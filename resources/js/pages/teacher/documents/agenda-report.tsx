import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatWitaDate } from '@/lib/date-time';
import type { AgendaSnapshot } from '@/types/teacher-agenda';

type Props = {
    from: string;
    to: string;
    teacherName: string;
    agendas: {
        id: number;
        date: string;
        snapshot: AgendaSnapshot;
        material: string;
        counts: Record<'H' | 'I' | 'S' | 'D' | 'A', number>;
        unrecorded: number;
    }[];
};
const dateLabel = (date: string) => formatWitaDate(`${date}T12:00:00+08:00`);

export default function AgendaReport({
    from,
    to,
    teacherName,
    agendas,
}: Props) {
    return (
        <main className="min-h-screen bg-white p-6 text-black print:p-0">
            <Head title={`Agenda ${teacherName}`} />
            <div className="mb-6 flex items-center justify-between gap-3 print:hidden">
                <p className="text-sm">
                    Gunakan tujuan “Simpan sebagai PDF” pada dialog cetak untuk
                    mengunduh laporan.
                </p>
                <Button onClick={() => window.print()}>
                    <Printer />
                    Cetak / Simpan PDF
                </Button>
            </div>
            <header className="mb-6 border-b border-black pb-4">
                <h1 className="text-xl font-bold">Laporan Agenda Guru</h1>
                <p>{teacherName}</p>
                <p>
                    {dateLabel(from)} – {dateLabel(to)}
                </p>
            </header>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            {[
                                'Tanggal',
                                'Kelas / Mata Pelajaran',
                                'Jam',
                                'Materi',
                                'Kehadiran',
                            ].map((heading) => (
                                <th
                                    key={heading}
                                    className="border border-gray-500 p-2 text-left"
                                >
                                    {heading}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {agendas.map((agenda) => (
                            <tr key={agenda.id} className="break-inside-avoid">
                                <td className="border border-gray-500 p-2 align-top">
                                    {dateLabel(agenda.date)}
                                </td>
                                <td className="border border-gray-500 p-2 align-top">
                                    {agenda.snapshot.rombel_code}
                                    <br />
                                    {agenda.snapshot.subject_name}
                                    <p className="text-xs">
                                        {agenda.snapshot.academic_year} ·{' '}
                                        {agenda.snapshot.academic_period}
                                    </p>
                                </td>
                                <td className="border border-gray-500 p-2 align-top">
                                    {agenda.snapshot.start_time} –{' '}
                                    {agenda.snapshot.end_time}
                                    <br />
                                    Jam ke-{agenda.snapshot.lesson_start}–
                                    {agenda.snapshot.lesson_end}
                                </td>
                                <td className="max-w-sm border border-gray-500 p-2 align-top break-words whitespace-pre-wrap">
                                    {agenda.material}
                                </td>
                                <td className="border border-gray-500 p-2 align-top">
                                    <span>
                                        {Object.entries(agenda.counts)
                                            .map(
                                                ([status, count]) =>
                                                    `${count}${status}`,
                                            )
                                            .join(', ')}
                                    </span>
                                    {agenda.unrecorded > 0 && (
                                        <p className="mt-1 font-semibold">
                                            {agenda.unrecorded} belum dicatat
                                        </p>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {agendas.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="border border-gray-500 p-6 text-center"
                                >
                                    Belum ada Agenda pada rentang tanggal ini.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <p className="mt-4 text-xs">
                H Hadir · I Izin · S Sakit · D Dispensasi · A Alpa. Rekap
                memakai roster tersimpan dan absensi harian terkini; siswa tanpa
                catatan tidak dihitung sebagai Alpa.
            </p>
            <style>
                {
                    '@page { size: A4 landscape; margin: 12mm; } @media print { thead { display: table-header-group; } }'
                }
            </style>
        </main>
    );
}
