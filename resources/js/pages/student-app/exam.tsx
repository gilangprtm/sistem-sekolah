import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
const FIRST_INTERRUPTION_WARNING =
    'Kamu telah keluar aplikasi, kamu tidak akan bisa mengikuti ujian jika tetap keluar dari aplikasi';
const SECOND_INTERRUPTION_WARNING =
    'Jika kamu keluar aplikasi sekali lagi, ujian akan langsung terkunci.';

type Props = {
    attemptId: number | null;
    linkId: number;
    category: string;
    subject: string | null;
    subjectCode: string | null;
    formUrl: string | null;
    started: boolean;
    interruptionCount: number;
    reopen: boolean;
};

type WakeLockSentinel = {
    release: () => Promise<void>;
    addEventListener: (type: 'release', listener: () => void) => void;
};

type WakeLockNavigator = Navigator & {
    wakeLock?: { request: (type: 'screen') => Promise<WakeLockSentinel> };
};

export default function StudentExam({
    attemptId: initialAttemptId,
    linkId,
    category,
    subject,
    subjectCode,
    formUrl,
    started,
    interruptionCount: initialCount,
    reopen,
}: Props) {
    const [attemptId] = useState<number | null>(initialAttemptId);
    const [hasStarted] = useState(started);
    const [error, setError] = useState('');
    const [completionError, setCompletionError] = useState('');
    const [wakeLockMessage, setWakeLockMessage] = useState('');
    const [warning, setWarning] = useState('');
    const [isStarting, setIsStarting] = useState(false);
    const [pendingOpen, setPendingOpen] = useState(false);
    const [isCompletionOpen, setIsCompletionOpen] = useState(false);
    const [isCompleting, setIsCompleting] = useState(false);
    const startRequested = useRef(false);
    const hiddenAt = useRef<number | null>(null);
    const wakeLock = useRef<WakeLockSentinel | null>(null);
    const pendingVisible = useRef(false);

    const acquireWakeLock = useCallback(async () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        const api = navigator as WakeLockNavigator;

        if (!api.wakeLock) {
            setWakeLockMessage(
                'Perangkat tidak mendukung fitur menjaga layar tetap aktif. Tetap lanjutkan ujian dengan menjaga halaman terbuka.',
            );

            return;
        }

        try {
            wakeLock.current = await api.wakeLock.request('screen');
            setWakeLockMessage(
                'Layar dijaga tetap aktif selama halaman ujian terlihat.',
            );
            wakeLock.current.addEventListener('release', () => {
                wakeLock.current = null;
            });
        } catch {
            setWakeLockMessage(
                'Fitur menjaga layar tetap aktif tidak tersedia saat ini. Pastikan halaman tetap terbuka.',
            );
        }
    }, []);

    const record = useCallback(
        async (eventType: 'exam_page_hidden' | 'exam_page_visible') => {
            if (!attemptId) {
                return;
            }

            setWarning('');

            try {
                const token = document.querySelector<HTMLMetaElement>(
                    'meta[name="csrf-token"]',
                )?.content;
                const response = await fetch(
                    `/student/uas/attempts/${attemptId}/events`,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                        },
                        body: JSON.stringify({ event_type: eventType }),
                    },
                );

                if (!response.ok && response.status !== 423) {
                    setError(
                        `Server menolak pencatatan gangguan (HTTP ${response.status}). Status ujian akan diverifikasi ulang.`,
                    );

                    return;
                }

                const result = (await response.json()) as {
                    status?: string;
                    interruptionCount?: number;
                    warning?: boolean;
                    locked?: boolean;
                };

                if (result.warning && result.interruptionCount !== undefined) {
                    setWarning(
                        result.interruptionCount === 2
                            ? FIRST_INTERRUPTION_WARNING
                            : result.interruptionCount === 3
                              ? SECOND_INTERRUPTION_WARNING
                              : '',
                    );
                }

                if (result.locked || response.status === 423) {
                    window.location.reload();
                }
            } catch {
                setError(
                    'Tidak dapat menghubungi server untuk mencatat gangguan halaman. Periksa koneksi Anda lalu coba kembali.',
                );
            }
        },
        [attemptId],
    );

    useEffect(() => {
        if (!hasStarted) {
            return;
        }

        const wakeLockTimer = window.setTimeout(() => {
            void acquireWakeLock();
        }, 0);
        const onVisibilityChange = () => {
            if (document.visibilityState === 'hidden') {
                hiddenAt.current = Date.now();
                pendingVisible.current = false;
                void record('exam_page_hidden');
                void wakeLock.current?.release().catch(() => undefined);
                wakeLock.current = null;

                return;
            }

            if (hiddenAt.current !== null && !pendingVisible.current) {
                hiddenAt.current = null;
                pendingVisible.current = true;
                void record('exam_page_visible').finally(() => {
                    pendingVisible.current = false;
                });
            }

            void acquireWakeLock();
        };
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pageshow', onVisibilityChange);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
            window.removeEventListener('pageshow', onVisibilityChange);
            window.clearTimeout(wakeLockTimer);
            void wakeLock.current?.release().catch(() => undefined);
        };
    }, [acquireWakeLock, hasStarted, record]);

    const startAttempt = useCallback(async () => {
        if (isStarting) {
            return;
        }

        setIsStarting(true);
        setError('');

        try {
            const token = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            const response = await fetch(
                `/student/uas/${linkId}/attempt${reopen ? '?reopen=1' : ''}`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({ expected_count: initialCount }),
                },
            );

            if (!response.ok && response.status !== 423) {
                setError(
                    `Server menolak permintaan memulai ujian (HTTP ${response.status}). Silakan coba lagi atau hubungi administrator jika masalah berlanjut.`,
                );

                return;
            }

            const result = (await response.json()) as {
                interruptionCount?: number;
                warning?: boolean;
                locked?: boolean;
            };

            if (result.locked || response.status === 423) {
                window.location.reload();

                return;
            }

            if (result.warning) {
                setWarning(
                    result.interruptionCount === 2
                        ? FIRST_INTERRUPTION_WARNING
                        : result.interruptionCount === 3
                          ? SECOND_INTERRUPTION_WARNING
                          : '',
                );
                setPendingOpen(true);

                return;
            }

            window.location.replace(`/student/uas/${linkId}/attempt`);
        } catch {
            setError(
                'Tidak dapat menghubungi server untuk memulai ujian. Periksa koneksi Anda lalu coba kembali.',
            );
        } finally {
            setIsStarting(false);
        }
    }, [initialCount, isStarting, linkId, reopen]);

    const completeAttempt = useCallback(async () => {
        if (!attemptId || isCompleting) {
            return;
        }

        setIsCompleting(true);
        setCompletionError('');

        try {
            const token = document.querySelector<HTMLMetaElement>(
                'meta[name="csrf-token"]',
            )?.content;
            const response = await fetch(
                `/student/uas/attempts/${attemptId}/complete`,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                },
            );

            if (!response.ok) {
                setCompletionError(
                    `Server menolak penyelesaian ujian (HTTP ${response.status}). Silakan coba lagi.`,
                );

                return;
            }

            window.location.replace('/student/uas');
        } catch {
            setCompletionError(
                'Tidak dapat menghubungi server untuk menyelesaikan ujian. Periksa koneksi Anda lalu coba lagi.',
            );
        } finally {
            setIsCompleting(false);
        }
    }, [attemptId, isCompleting]);

    const acknowledgeWarning = useCallback(() => {
        setWarning('');

        if (pendingOpen) {
            setPendingOpen(false);
            window.location.replace(`/student/uas/${linkId}/attempt`);
        }
    }, [linkId, pendingOpen]);

    useEffect(() => {
        if (hasStarted || startRequested.current) {
            return;
        }

        startRequested.current = true;
        void startAttempt();
    }, [hasStarted, startAttempt]);

    return (
        <>
            <Head title={`${category} ${subjectCode ?? ''}`.trim()} />
            <SecondaryPageShell
                title={`${category} ${subjectCode ?? ''}`.trim()}
                fallbackHref="/student/uas"
            >
                <main
                    className={
                        formUrl
                            ? 'h-[calc(100dvh-3.5rem)] w-full'
                            : 'mx-auto flex min-h-[calc(100dvh-3.5rem)] w-full max-w-5xl flex-col gap-4 p-4 sm:p-6'
                    }
                    aria-labelledby="exam-title"
                >
                    {!hasStarted ? (
                        <section
                            className="grid gap-2 p-2 text-sm"
                            aria-live="polite"
                        >
                            {error ? (
                                <>
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {error}
                                    </p>
                                    <Button
                                        type="button"
                                        onClick={() => void startAttempt()}
                                        disabled={isStarting}
                                    >
                                        Coba lagi
                                    </Button>
                                </>
                            ) : (
                                <p
                                    role="status"
                                    className="text-sm text-muted-foreground"
                                >
                                    {isStarting
                                        ? 'Membuka formulir ujian...'
                                        : 'Menyiapkan formulir ujian...'}
                                </p>
                            )}
                        </section>
                    ) : formUrl ? (
                        <div className="relative size-full">
                            <iframe
                                title={`Google Form ${subject ?? 'ujian'}`}
                                src={`${formUrl}${formUrl.includes('?') ? '&' : '?'}embedded=true`}
                                className="block size-full border-0 bg-white"
                                referrerPolicy="no-referrer"
                                allow="fullscreen"
                            />
                            <div className="absolute right-2 bottom-2">
                                <Button
                                    type="button"
                                    onClick={() => setIsCompletionOpen(true)}
                                >
                                    Selesai
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <section
                            className="grid gap-2 p-4 text-sm"
                            aria-live="polite"
                        >
                            <p className="text-muted-foreground">
                                {wakeLockMessage}
                            </p>
                            {warning && (
                                <p
                                    role="status"
                                    className="font-medium text-amber-700"
                                >
                                    {warning}
                                </p>
                            )}
                            {error && (
                                <p role="alert" className="text-destructive">
                                    {error}
                                </p>
                            )}
                            {hasStarted && (
                                <Button
                                    type="button"
                                    onClick={() => setIsCompletionOpen(true)}
                                >
                                    Selesai
                                </Button>
                            )}
                        </section>
                    )}
                    <Dialog
                        open={isCompletionOpen}
                        onOpenChange={(open) => {
                            if (!isCompleting) {
                                setIsCompletionOpen(open);
                            }
                        }}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Selesaikan ujian?</DialogTitle>
                            </DialogHeader>
                            <DialogDescription>
                                Setelah diselesaikan, ujian ini akan dikunci dan
                                tidak dapat dibuka kembali tanpa bantuan
                                administrator.
                            </DialogDescription>
                            {completionError && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {completionError}
                                </p>
                            )}
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsCompletionOpen(false)}
                                    disabled={isCompleting}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() => void completeAttempt()}
                                    disabled={isCompleting}
                                >
                                    {isCompleting
                                        ? 'Menyelesaikan...'
                                        : 'Selesai'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <Dialog
                        open={warning !== ''}
                        onOpenChange={(open) => {
                            if (!open) {
                                acknowledgeWarning();
                            }
                        }}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Peringatan ujian</DialogTitle>
                            </DialogHeader>
                            <div className="space-y-3 text-sm">
                                <DialogDescription>{warning}</DialogDescription>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    onClick={acknowledgeWarning}
                                >
                                    Mengerti
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </main>
            </SecondaryPageShell>
        </>
    );
}
