import { Head, useForm } from '@inertiajs/react';
import { CalendarDays, Clock3, Save } from 'lucide-react';
import { toast } from 'sonner';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import InputError from '@/components/input-error';
import StudentAttendance from '@/components/teachers/student-attendance';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDate } from '@/lib/date-time';
import { index, store, update } from '@/routes/teacher/agenda';
import type { AttendanceRow } from '@/types/student-attendance';
import type { AgendaSnapshot } from '@/types/teacher-agenda';

type Agenda = {
    id: number | null;
    date: string;
    block_key: string;
    context_token: string;
    snapshot: AgendaSnapshot;
    material: string;
    attendance: AttendanceRow[];
    canManageAttendance: boolean;
};

function AgendaEditor({ agenda }: { agenda: Agenda }) {
    const form = useForm({
        agenda_date: agenda.date,
        block_key: agenda.block_key,
        context_token: agenda.context_token,
        material: agenda.material,
    });
    const errors: Record<string, string | undefined> = form.errors;
    const snapshot = agenda.snapshot;
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onHttpException: () => {
                toast.error(
                    'Agenda tidak dapat disimpan. Muat ulang bila jadwal telah berubah.',
                );
            },
            onNetworkError: () => {
                toast.error('Koneksi bermasalah. Silakan coba lagi.');
            },
        };

        if (agenda.id === null) {
            form.post(store.url(), options);
        } else {
            form.put(update.url(agenda.id), options);
        }
    };

    return (
        <form onSubmit={submit}>
            <SecondaryPageShell
                title={
                    agenda.id === null
                        ? `Selamat mengajar di ${snapshot.rombel_code}`
                        : `Agenda ${snapshot.rombel_code}`
                }
                description={`${snapshot.teacher_name} · ${snapshot.subject_name}`}
                backHref={index.url({ query: { date: agenda.date } })}
                footerActions={
                    <Button
                        type="submit"
                        disabled={
                            form.processing || agenda.attendance.length === 0
                        }
                    >
                        <Save />
                        {form.processing
                            ? 'Menyimpan…'
                            : agenda.id === null
                              ? 'Simpan Agenda'
                              : 'Simpan Perubahan'}
                    </Button>
                }
            >
                <section className="@container overflow-hidden rounded-xl border bg-card shadow-sm">
                    <div className="grid @min-[28rem]:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
                        <div className="flex items-start gap-3 border-b bg-primary/5 p-4 @min-[28rem]:border-r @min-[28rem]:border-b-0 @min-[28rem]:p-5">
                            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/15">
                                <CalendarDays
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <div className="grid min-w-0 flex-1 gap-2">
                                <p className="text-sm font-medium text-muted-foreground">
                                    Tanggal pertemuan
                                </p>
                                <p className="text-lg font-semibold tracking-tight">
                                    {formatWitaDate(
                                        `${agenda.date}T12:00:00+08:00`,
                                    )}
                                </p>
                                <InputError message={errors.agenda_date} />
                            </div>
                        </div>
                        <div className="flex items-start gap-3 p-4 @min-[28rem]:p-5">
                            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-muted text-foreground ring-1 ring-border">
                                <Clock3 className="size-5" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-medium text-muted-foreground">
                                    Jam mengajar
                                </p>
                                <p className="mt-1 text-lg font-semibold tracking-tight">
                                    {snapshot.start_time} – {snapshot.end_time}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Jam ke-{snapshot.lesson_start}
                                    {snapshot.lesson_end !==
                                        snapshot.lesson_start &&
                                        `–${snapshot.lesson_end}`}
                                </p>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {snapshot.academic_year} ·{' '}
                                    {snapshot.academic_period}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-start gap-3 border-t bg-muted/30 px-4 py-3 text-xs leading-relaxed text-muted-foreground sm:px-5">
                        <span
                            className="mt-1 size-1.5 shrink-0 rounded-full bg-primary/60"
                            aria-hidden="true"
                        />
                        <p>
                            {agenda.id === null
                                ? 'Kelas, mata pelajaran, dan jam diambil dari jadwal untuk tanggal yang dipilih.'
                                : 'Konteks pertemuan dan daftar siswa mengikuti data ketika agenda dibuat. Materi dan kehadiran harian bersama disimpan terpisah.'}
                        </p>
                    </div>
                </section>
                <div className="grid gap-2">
                    <Label htmlFor="agenda-material">
                        Materi yang diajarkan *
                    </Label>
                    <Textarea
                        id="agenda-material"
                        value={form.data.material}
                        onChange={(event) =>
                            form.setData('material', event.target.value)
                        }
                        rows={4}
                        maxLength={10000}
                        required
                        disabled={form.processing}
                        aria-invalid={Boolean(errors.material)}
                        aria-describedby={
                            errors.material
                                ? 'agenda-material-error'
                                : undefined
                        }
                        placeholder="Tuliskan materi atau kegiatan pembelajaran dalam blok pertemuan ini…"
                    />
                    <InputError
                        id="agenda-material-error"
                        message={errors.material}
                    />
                </div>
                <section className="grid gap-3">
                    <StudentAttendance
                        rows={agenda.attendance}
                        source="agenda"
                        date={agenda.date}
                        blockKey={agenda.block_key}
                        agendaId={agenda.id}
                        canManage={agenda.canManageAttendance}
                        emptyMessage="Belum ada siswa aktif pada kelas ini. Lengkapi penempatan siswa sebelum membuat agenda."
                    />
                </section>
                {['block_key', 'context_token', 'attendance'].map((field) => (
                    <InputError
                        key={field}
                        message={errors[field]}
                        role="alert"
                    />
                ))}
            </SecondaryPageShell>
        </form>
    );
}

export default function AgendaForm({ agenda }: { agenda: Agenda }) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Agenda Guru', href: index.url() },
                { title: agenda.snapshot.rombel_code, href: '#' },
            ]}
        >
            <Head title={`Agenda ${agenda.snapshot.rombel_code}`} />
            <AgendaEditor
                key={`${agenda.id ?? agenda.block_key}-${agenda.context_token}`}
                agenda={agenda}
            />
        </AppLayout>
    );
}
