import { Head } from '@inertiajs/react';
import { CalendarDays, GraduationCap } from 'lucide-react';
import { useState } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type ScheduleRow = {
    type: 'lesson' | 'break' | 'custom';
    number?: number;
    start: string;
    end: string;
    label?: string;
};

type Day = {
    key: string;
    label: string;
    rows: ScheduleRow[];
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
    status:
        | 'ready'
        | 'student_unlinked'
        | 'no_active_year'
        | 'no_active_period'
        | 'no_active_class'
        | 'no_schedule';
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
    const dayGroups = days.map((day) => ({
        ...day,
        entries: Array.from(
            new Map(
                day.entries.map((entry) => [
                    `${entry.lessonNumber}-${entry.subjectCode}-${entry.subjectName}-${entry.teacherName}`,
                    entry,
                ]),
            ).values(),
        ).sort((first, second) => first.lessonNumber - second.lessonNumber),
    }));
    const [selectedDayKey, setSelectedDayKey] = useState('monday');
    const selectedDay =
        dayGroups.find((day) => day.key === selectedDayKey) ?? dayGroups[0];

    return (
        <>
            <Head title="Jadwal Siswa" />
            <section
                className="space-y-5"
                aria-labelledby="student-schedule-heading"
            >
                <div>
                    <p className="text-sm text-muted-foreground">
                        Agenda belajar
                    </p>
                    <h1
                        id="student-schedule-heading"
                        className="text-2xl font-semibold tracking-tight"
                    >
                        Jadwal
                    </h1>
                </div>

                {status === 'ready' &&
                    rombel &&
                    academicPeriod &&
                    academicYear && (
                        <div className="rounded-2xl border bg-card p-4">
                            <div className="flex items-start gap-3">
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-700">
                                    <GraduationCap
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {rombel.name}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {academicYear.year} ·{' '}
                                        {academicPeriod.name}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                {status !== 'ready' || !hasEntries ? (
                    <EmptySchedule message={message} />
                ) : (
                    <div className="space-y-3">
                        <div
                            className="grid h-12 min-h-12 w-full grid-cols-5 items-stretch gap-1 overflow-hidden rounded-lg border border-border bg-muted p-1"
                            role="tablist"
                            aria-label="Pilih hari jadwal"
                        >
                            {dayGroups.map((day) => {
                                const isSelected =
                                    day.key ===
                                    (selectedDay?.key ?? selectedDayKey);

                                return (
                                    <button
                                        key={day.key}
                                        type="button"
                                        role="tab"
                                        aria-selected={isSelected}
                                        aria-controls={`schedule-panel-${day.key}`}
                                        onClick={() =>
                                            setSelectedDayKey(day.key)
                                        }
                                        className={`h-full min-h-10 w-full min-w-0 rounded-md px-1 py-2 text-center text-sm leading-tight transition-colors focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${isSelected ? 'bg-background font-medium text-foreground shadow-sm' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}
                                    >
                                        {day.label}
                                    </button>
                                );
                            })}
                        </div>

                        {selectedDay && (
                            <div
                                id={`schedule-panel-${selectedDay.key}`}
                                role="tabpanel"
                                aria-label={`Jadwal ${selectedDay.label}`}
                                className="overflow-x-auto rounded-2xl border bg-card"
                            >
                                <Table className="min-w-[520px] border-separate border-spacing-0">
                                    <caption className="sr-only">
                                        Jadwal {selectedDay.label} untuk{' '}
                                        {rombel?.code ?? 'kelas siswa'}
                                    </caption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead
                                                scope="col"
                                                className="sticky left-0 z-20 w-28 border-r bg-muted text-center"
                                            >
                                                Jam Pelajaran
                                            </TableHead>
                                            <TableHead
                                                scope="col"
                                                className="w-32 border-r bg-muted"
                                            >
                                                Waktu
                                            </TableHead>
                                            <TableHead
                                                scope="col"
                                                className="min-w-56 border-r bg-muted text-center"
                                            >
                                                {selectedDay.label}
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {selectedDay.rows.map((row) => {
                                            const entries =
                                                row.type === 'lesson' &&
                                                row.number !== undefined
                                                    ? selectedDay.entries.filter(
                                                          (item) =>
                                                              item.lessonNumber ===
                                                              row.number,
                                                      )
                                                    : [];

                                            return (
                                                <TableRow
                                                    key={`${selectedDay.key}-${row.type}-${row.number ?? row.start}`}
                                                    className={
                                                        row.type === 'break'
                                                            ? 'bg-muted/50'
                                                            : row.type ===
                                                                'custom'
                                                              ? 'bg-violet-50'
                                                              : undefined
                                                    }
                                                >
                                                    <TableCell className="sticky left-0 z-10 border-r bg-background text-center font-medium">
                                                        {row.number ?? ''}
                                                    </TableCell>
                                                    <TableCell className="w-32 min-w-32 border-r font-medium whitespace-nowrap">
                                                        {row.start}–{row.end}
                                                    </TableCell>
                                                    <TableCell className="border-r align-top">
                                                        {row.type ===
                                                        'break' ? (
                                                            <span className="text-sm font-medium">
                                                                Istirahat
                                                            </span>
                                                        ) : row.type ===
                                                          'custom' ? (
                                                            <span className="text-sm font-medium">
                                                                {row.label}
                                                            </span>
                                                        ) : entries.length >
                                                          0 ? (
                                                            <div className="space-y-2">
                                                                {entries.map(
                                                                    (entry) => (
                                                                        <div
                                                                            key={
                                                                                entry.id
                                                                            }
                                                                            className="space-y-1"
                                                                        >
                                                                            <p className="font-semibold">
                                                                                {
                                                                                    entry.subjectName
                                                                                }
                                                                            </p>
                                                                            {entry.subjectCode !==
                                                                                entry.subjectName && (
                                                                                <p className="text-xs text-violet-700">
                                                                                    {
                                                                                        entry.subjectCode
                                                                                    }
                                                                                </p>
                                                                            )}
                                                                            <p className="text-xs text-muted-foreground">
                                                                                {
                                                                                    entry.teacherName
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span
                                                                className="text-xs text-muted-foreground"
                                                                aria-label="Kosong"
                                                            >
                                                                —
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                )}
            </section>
        </>
    );
}
