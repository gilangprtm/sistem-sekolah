import { Head, Link } from '@inertiajs/react';
import { ExternalLink, LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatWitaDateTime } from '@/lib/date-time';

type UasLink = {
    id: number;
    subject: { code: string; name: string } | null;
    opens_at: string | null;
    available: boolean;
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
                            className="min-h-11 rounded-xl px-4 text-sm font-semibold data-[active=true]:bg-background data-[active=true]:text-violet-700 data-[active=true]:shadow-sm"
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
                            className="min-h-11 rounded-xl px-4 text-sm font-semibold data-[active=true]:bg-background data-[active=true]:text-violet-700 data-[active=true]:shadow-sm"
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
                            <DialogTitle>Konfirmasi Mulai Ujian</DialogTitle>
                            <DialogDescription>
                                {selectedExam?.subject?.name ??
                                    'Mata Pelajaran'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-3 text-sm">
                            <p>
                                Setelah Anda setuju, percobaan ujian langsung
                                dimulai dan formulir akan dibuka.
                            </p>
                            <p>
                                Aktivitas halaman ujian dicatat untuk keperluan
                                audit. Jika halaman ujian disembunyikan lalu
                                dibuka kembali sebanyak empat kali, percobaan
                                akan terkunci dan Anda perlu menghubungi
                                administrator.
                            </p>
                            <p className="text-muted-foreground">
                                Catatan aktivitas tidak memastikan penyebab
                                perpindahan halaman.
                            </p>
                        </div>
                        <div className="flex justify-end gap-2">
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
                                        Setuju dan mulai
                                    </Link>
                                </Button>
                            )}
                        </div>
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
