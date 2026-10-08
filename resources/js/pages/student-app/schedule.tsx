import { Head } from '@inertiajs/react';
import { CalendarDays, Clock3, GraduationCap } from 'lucide-react';

type Day = {
    key: string;
    label: string;
    entries: ScheduleEntry[];
};

type ScheduleEntry = {
    id: number;
    lessonNumber: number;
    startTime: string;
    endTime: string;
    subjectCode: string;
    subjectName: string;
    teacherName: string;
};

type Props = {
    status: 'ready' | 'student_unlinked' | 'no_active_year' | 'no_active_period' | 'no_active_class' | 'no_schedule';
    message: string | null;
    academicYear: { year: string } | null;
    academicPeriod: { code: string; name: string } | null;
    rombel: { code: string; name: string; grade_level: string } | null;
    days: Day[];
};

function EmptySchedule({ message }: { message: string | null }) {
    return (
        <div
            className="rounded-2xl border border-dashed bg-card p-8 text-center"
            role="status"
        >
            <CalendarDays
                className="mx-auto size-10 text-muted-foreground/60"
                aria-hidden="true"
            />
            <p className="mt-3 text-sm font-medium">
                {message ?? 'Jadwal belum tersedia.'}
            </p>
            <p className="mt-1 text-xs text-muted-foreground">
                Jadwal akan tampil setelah data akademik dan kelas diterbitkan.
            </p>
        </div>
    );
}

export default function StudentSchedule({
    status,
    message,
    academicYear,
    academicPeriod,
    rombel,
    days,
}: Props) {
    const hasEntries = days.some((day) => day.entries.length > 0);

    return (
        <>
            <Head title="Jadwal Siswa" />
            <section
                className="space-y-5"
                aria-labelledby="student-schedule-heading"
            >
                <div>
                    <p className="text-sm text-muted-foreground">Agenda belajar</p>
                    <h1
                        id="student-schedule-heading"
                        className="text-2xl font-semibold tracking-tight"
                    >
                        Jadwal
                    </h1>
                </div>

                {status === 'ready' && rombel && academicPeriod && academicYear && (
                    <div className="rounded-2xl border bg-card p-4">
                        <div className="flex items-start gap-3">
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-700">
                                <GraduationCap className="size-5" aria-hidden="true" />
                            </span>
                            <div className="min-w-0">
                                <p className="font-semibold">{rombel.name}</p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {academicYear.year} · {academicPeriod.name}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {status !== 'ready' || !hasEntries ? (
                    <EmptySchedule message={message} />
                ) : (
                    <div className="space-y-4">
                        {days.map((day) => (
                            <section
                                key={day.key}
                                aria-labelledby={`schedule-${day.key}`}
                                className="space-y-2"
                            >
                                <h2
                                    id={`schedule-${day.key}`}
                                    className="text-sm font-semibold"
                                >
                                    {day.label}
                                </h2>
                                {day.entries.length > 0 ? (
                                    <div className="space-y-2">
                                        {day.entries.map((entry) => (
                                            <article
                                                key={entry.id}
                                                className="rounded-2xl border bg-card p-4 shadow-xs"
                                            >
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="text-xs font-medium text-violet-700">
                                                            {entry.subjectCode}
                                                        </p>
                                                        <h3 className="mt-1 font-semibold">
                                                            {entry.subjectName}
                                                        </h3>
                                                        <p className="mt-1 text-sm text-muted-foreground">
                                                            {entry.teacherName}
                                                        </p>
                                                    </div>
                                                    <div className="flex shrink-0 items-center gap-1 text-xs text-muted-foreground">
                                                        <Clock3 className="size-3.5" aria-hidden="true" />
                                                        <span>{entry.startTime}–{entry.endTime}</span>
                                                    </div>
                                                </div>
                                            </article>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="rounded-xl border border-dashed p-3 text-xs text-muted-foreground">
                                        Tidak ada pelajaran.
                                    </p>
                                )}
                            </section>
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
