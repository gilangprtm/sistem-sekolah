import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, LockKeyhole } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
import { formatWitaDateTime } from '@/lib/date-time';

type UasLink = {
    id: number;
    subject: { code: string; name: string } | null;
    opens_at: string | null;
    available: boolean;
    attempt: { interruption_count: number; status: string } | null;
};
type Props = {
    academicYear: { year: string } | null;
    academicPeriod: { name: string } | null;
    links: UasLink[];
    utsLinks: UasLink[];
    status: string;
};

export default function StudentUas({
    academicYear,
    academicPeriod,
    links,
    utsLinks,
    status,
}: Props) {
    const [activeTab, setActiveTab] = useState('uas');
    const [selectedLockedItem, setSelectedLockedItem] =
        useState<UasLink | null>(null);
    const [selectedExam, setSelectedExam] = useState<UasLink | null>(null);
    const isReady = status === 'ready';
    const refreshInFlight = useRef(false);

    useEffect(() => {
        const refreshAttempts = () => {
            if (
                document.visibilityState !== 'visible' ||
                refreshInFlight.current
            ) {
                return;
            }

            refreshInFlight.current = true;
            router.reload({
                only: [
                    'academicYear',
                    'academicPeriod',
                    'links',
                    'status',
                    'utsLinks',
                ],
                onFinish: () => {
                    refreshInFlight.current = false;
                },
            });
        };
        const stopListening = router.on('navigate', ({ detail }) => {
            if (detail.page.component === 'student-app/uas') {
                refreshAttempts();
            }
        });

        window.addEventListener('pageshow', refreshAttempts);
        window.addEventListener('focus', refreshAttempts);

        return () => {
            stopListening();
            window.removeEventListener('pageshow', refreshAttempts);
            window.removeEventListener('focus', refreshAttempts);
        };
    }, []);

    return (
        <>
            <Head title="Ujian" />
            <SecondaryPageShell title="Ujian" fallbackHref="/student">
                <section
                    className="space-y-4 px-4 py-5"
                    aria-label="Daftar ujian"
                >
                    {academicYear && academicPeriod && (
                        <p className="text-sm text-muted-foreground">
                            {academicYear.year} · {academicPeriod.name}
                        </p>
                    )}

                    <div
                        className="grid w-full grid-cols-2 rounded-2xl bg-muted p-1"
                        role="tablist"
                        aria-label="Pilih jenis ujian"
                    >
                        <button
                            type="button"
                            role="tab"
                            id="uts-tab"
                            aria-controls="uts-panel"
                            aria-selected={activeTab === 'uts'}
                            className="min-h-11 rounded-xl px-4 text-sm font-semibold data-[active=true]:bg-background data-[active=true]:text-primary data-[active=true]:shadow-sm"
                            data-active={activeTab === 'uts'}
                            onClick={() => setActiveTab('uts')}
                        >
                            UTS
                        </button>
                        <button
                            type="button"
                            role="tab"
                            id="uas-tab"
                            aria-controls="uas-panel"
                            aria-selected={activeTab === 'uas'}
                            className="min-h-11 rounded-xl px-4 text-sm font-semibold data-[active=true]:bg-background data-[active=true]:text-primary data-[active=true]:shadow-sm"
                            data-active={activeTab === 'uas'}
                            onClick={() => setActiveTab('uas')}
                        >
                            UAS
                        </button>
                    </div>

                    {(['uts', 'uas'] as const).map((category) => {
                        const categoryLinks =
                            category === 'uts' ? utsLinks : links;
                        const categoryLabel = category.toUpperCase();
                        const active = activeTab === category;

                        if (!active) {
                            return null;
                        }

                        return (
                            <div
                                key={category}
                                id={`${category}-panel`}
                                className="mt-4"
                                role="tabpanel"
                                aria-labelledby={`${category}-tab`}
                            >
                                {!isReady ? (
                                    <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                                        {status === 'student_unlinked'
                                            ? 'Akun ini belum terhubung dengan data siswa.'
                                            : 'Tahun ajaran dan semester aktif belum tersedia.'}
                                    </p>
                                ) : categoryLinks.length === 0 ? (
                                    <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                                        {category === 'uts'
                                            ? 'Data UTS belum tersedia'
                                            : 'Belum ada link UAS untuk periode aktif.'}
                                    </p>
                                ) : (
                                    <ul className="space-y-3">
                                        {categoryLinks.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex items-center justify-between gap-3 rounded-xl border bg-card p-4"
                                            >
                                                <div className="min-w-0">
                                                    <p className="font-semibold">
                                                        {item.subject?.name ??
                                                            'Mata Pelajaran'}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {item.subject?.code}
                                                    </p>
                                                </div>
                                                {item.available ? (
                                                    item.attempt &&
                                                    item.attempt
                                                        .interruption_count >
                                                        0 ? (
                                                        <Button
                                                            asChild
                                                            className="min-h-11 shrink-0 gap-2"
                                                        >
                                                            <Link
                                                                href={`/student/uas/${item.id}/attempt?reopen=1`}
                                                            >
                                                                Buka Form
                                                                <ExternalLink className="size-4" />
                                                            </Link>
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            type="button"
                                                            className="min-h-11 shrink-0 gap-2"
                                                            onClick={() =>
                                                                setSelectedExam(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            Buka Form
                                                            <ExternalLink className="size-4" />
                                                        </Button>
                                                    )
                                                ) : (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-11 shrink-0"
                                                        aria-label={`Lihat jadwal buka ${categoryLabel} ${item.subject?.name ?? 'mata pelajaran'}`}
                                                        title="Lihat jadwal buka"
                                                        onClick={() =>
                                                            setSelectedLockedItem(
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        <LockKeyhole
                                                            className="size-5"
                                                            aria-hidden="true"
                                                        />
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        );
                    })}
                </section>

                <Dialog
                    open={selectedExam !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedExam(null);
                        }
                    }}
                >
                    <DialogContent className="w-[calc(100%-2rem)] rounded-2xl sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Mulai ujian</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-3 text-sm">
                            <DialogDescription>
                                {selectedExam?.subject?.name ??
                                    'Mata Pelajaran'}
                            </DialogDescription>
                            <p>
                                Ujian{' '}
                                {selectedExam?.subject?.name ??
                                    'Mata Pelajaran'}{' '}
                                akan dimulai saat Anda memilih Mulai.
                            </p>
                            <p>
                                Aktivitas halaman ujian dicatat untuk audit.
                                Jika halaman ujian disembunyikan lalu dibuka
                                kembali empat kali, percobaan terkunci. Hubungi
                                administrator untuk meminta bantuan.
                            </p>
                            <p className="text-muted-foreground">
                                Catatan aktivitas tidak memastikan penyebab
                                perpindahan halaman.
                            </p>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSelectedExam(null)}
                            >
                                Batal
                            </Button>
                            {selectedExam && (
                                <Button asChild>
                                    <Link
                                        href={`/student/uas/${selectedExam.id}/attempt`}
                                    >
                                        Mulai
                                    </Link>
                                </Button>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={selectedLockedItem !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedLockedItem(null);
                        }
                    }}
                >
                    <DialogContent className="w-[calc(100%-2rem)] rounded-2xl sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Jadwal Ujian</DialogTitle>
                            <DialogDescription>
                                {selectedLockedItem?.subject?.name ??
                                    'Mata Pelajaran'}
                            </DialogDescription>
                        </DialogHeader>
                        <p className="rounded-xl bg-muted p-4 text-sm">
                            Ujian dapat dibuka mulai{' '}
                            <span className="font-semibold">
                                {selectedLockedItem?.opens_at
                                    ? formatWitaDateTime(
                                          selectedLockedItem.opens_at,
                                      )
                                    : '-'}
                            </span>{' '}
                            WITA.
                        </p>
                    </DialogContent>
                </Dialog>
            </SecondaryPageShell>
        </>
    );
}
