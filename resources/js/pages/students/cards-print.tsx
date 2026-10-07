import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type StudentCard = {
    student: {
        id: number;
        nis: string | null;
        full_name: string;
        birth_place: string | null;
        birth_date: string | null;
        address: string | null;
        photo_url: string | null;
    };
    placement: {
        academic_year: string;
        rombel: string | null;
    } | null;
    qrCode: string | null;
};

type Props = {
    students: StudentCard[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kesiswaan', href: '/students/cards' },
    { title: 'Kartu Pelajar', href: '/students/cards' },
    { title: 'Cetak Semua Kartu', href: '/students/cards/print' },
];

function PrintableCard({ card }: { card: StudentCard }) {
    const birthDetails = [card.student.birth_place, card.student.birth_date]
        .filter(Boolean)
        .join(', ');

    return (
        <article
            className="relative flex aspect-[3/2] h-[5.4cm] w-[8.56cm] break-inside-avoid flex-col overflow-hidden rounded-lg border border-slate-300 bg-white p-3 text-slate-900 shadow-sm print:rounded-none print:shadow-none"
            aria-label={`Kartu Pelajar ${card.student.full_name}`}
        >
            <header className="flex items-center gap-2 border-b border-slate-200 pb-2">
                <img
                    src="/images/logo-sekolah.png"
                    alt="Logo SMP Negeri 17 Denpasar"
                    className="size-8 object-contain"
                />
                <div className="min-w-0">
                    <p className="text-[7px] font-bold tracking-wide uppercase">
                        Kartu Pelajar
                    </p>
                    <p className="truncate text-[8px] font-semibold">
                        SMP Negeri 17 Denpasar
                    </p>
                </div>
            </header>

            <div className="flex min-h-0 flex-1 gap-2 pt-2">
                <div className="min-w-0 flex-1 space-y-1 text-[8px] leading-tight">
                    <div>
                        <p className="text-[6px] text-slate-500 uppercase">
                            Nama Lengkap
                        </p>
                        <p className="font-bold">{card.student.full_name}</p>
                    </div>
                    <div>
                        <p className="text-[6px] text-slate-500 uppercase">
                            NIS
                        </p>
                        <p>{card.student.nis || '-'}</p>
                    </div>
                    <div>
                        <p className="text-[6px] text-slate-500 uppercase">
                            Tempat, Tanggal Lahir
                        </p>
                        <p>{birthDetails || '-'}</p>
                    </div>
                    <div>
                        <p className="text-[6px] text-slate-500 uppercase">
                            Alamat
                        </p>
                        <p className="line-clamp-2">
                            {card.student.address || '-'}
                        </p>
                    </div>
                    {card.placement !== null && (
                        <div>
                            <p className="text-[6px] text-slate-500 uppercase">
                                Kelas
                            </p>
                            <p>
                                {card.placement.rombel || '-'} (
                                {card.placement.academic_year})
                            </p>
                        </div>
                    )}
                </div>
                <div className="flex w-16 shrink-0 flex-col items-center justify-end gap-2">
                    {card.student.photo_url !== null ? (
                        <img
                            src={card.student.photo_url}
                            alt={`Foto ${card.student.full_name}`}
                            className="size-16 rounded-sm object-cover"
                        />
                    ) : (
                        <div className="flex size-16 items-center justify-center rounded-sm bg-slate-100 text-[12px] font-semibold text-slate-500">
                            {card.student.full_name
                                .split(' ')
                                .map((part) => part[0])
                                .join('')
                                .slice(0, 2)
                                .toUpperCase()}
                        </div>
                    )}
                    {card.qrCode !== null && (
                        <img
                            src={card.qrCode}
                            alt="QR Code akun siswa"
                            className="size-16 object-contain"
                        />
                    )}
                </div>
            </div>
        </article>
    );
}

export default function StudentCardsPrint({ students }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Cetak Semua Kartu Pelajar" />
            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4 print:p-0">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center print:hidden">
                    <Heading
                        variant="small"
                        title="Cetak Semua Kartu Pelajar"
                        description={`${students.length} kartu siap dicetak`}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/students/cards">
                                <ArrowLeft />
                                Kembali
                            </Link>
                        </Button>
                        <Button onClick={() => window.print()}>
                            <Printer />
                            Cetak
                        </Button>
                    </div>
                </div>

                {students.length === 0 ? (
                    <div className="rounded-xl border p-8 text-center text-muted-foreground print:hidden">
                        Belum ada data siswa untuk dicetak.
                    </div>
                ) : (
                    <div className="flex flex-wrap gap-4 print:grid print:grid-cols-2 print:gap-2">
                        {students.map((card) => (
                            <PrintableCard key={card.student.id} card={card} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
