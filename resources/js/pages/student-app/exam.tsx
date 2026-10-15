import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';

type Props = {
    attemptId: number | null;
    linkId: number;
    category: string;
    subject: string | null;
    subjectCode: string | null;
    formUrl: string | null;
    started: boolean;
    interruptionCount: number;
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
}: Props) {
    const [attemptId] = useState<number | null>(initialAttemptId);
    const [hasStarted] = useState(started);
    const [count, setCount] = useState(initialCount);
    const [error, setError] = useState('');
    const [wakeLockMessage, setWakeLockMessage] = useState('');
    const [warning, setWarning] = useState(false);
    const [isStarting, setIsStarting] = useState(false);
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

                if (result.interruptionCount !== undefined) {
                    setCount(result.interruptionCount);
                }

                if (result.warning) {
                    setWarning(true);
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
            const response = await fetch(`/student/uas/${linkId}/attempt`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
            });

            if (!response.ok) {
                setError(
                    `Server menolak permintaan memulai ujian (HTTP ${response.status}). Silakan coba lagi atau hubungi administrator jika masalah berlanjut.`,
                );

                return;
            }

            window.location.reload();
        } catch {
            setError(
                'Tidak dapat menghubungi server untuk memulai ujian. Periksa koneksi Anda lalu coba kembali.',
            );
        } finally {
            setIsStarting(false);
        }
    }, [isStarting, linkId]);

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
                        <iframe
                            title={`Google Form ${subject ?? 'ujian'}`}
                            src={`${formUrl}${formUrl.includes('?') ? '&' : '?'}embedded=true`}
                            className="block size-full border-0 bg-white"
                            referrerPolicy="no-referrer"
                            allow="fullscreen"
                        />
                    ) : (
                        <section
                            className="grid gap-2 p-4 text-sm"
                            aria-live="polite"
                        >
                            <p>Gangguan halaman tercatat: {count} dari 4</p>
                            <p className="text-muted-foreground">
                                {wakeLockMessage}
                            </p>
                            {warning && (
                                <p
                                    role="status"
                                    className="font-medium text-amber-700"
                                >
                                    Perhatian: gangguan halaman kembali
                                    tercatat. Gangguan keempat akan mengunci
                                    percobaan.
                                </p>
                            )}
                            {error && (
                                <p role="alert" className="text-destructive">
                                    {error}
                                </p>
                            )}
                        </section>
                    )}
                </main>
            </SecondaryPageShell>
        </>
    );
}
