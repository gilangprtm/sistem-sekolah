import {
    DndContext,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, CircleAlert } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTableShell from '@/components/data-table/data-table-shell';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Period = {
    id: number;
    code: string;
    name: string;
    academic_year: { year: string };
};

type Rombel = {
    id: number;
    code: string;
    name: string;
    grade_level: string;
    parallel_code: string;
};

type ScheduleRow = {
    type: 'lesson' | 'break' | 'custom';
    number?: number;
    start: string;
    end: string;
    label?: string;
};

type ScheduleEntry = {
    schedule_entry_id: number;
    schedule_plan_id: number;
    day: string;
    lesson_number: number;
    rombel_id: number;
    assignment_code: string;
    subject_color: string;
    teacher_name: string;
};

type ScheduleDay = {
    key: string;
    label: string;
    rows: ScheduleRow[];
};

type Props = {
    periods: Period[];
    selectedPeriod: Period | null;
    grade: string;
    selectedRombels: Rombel[];
    selectedRombelIds: number[];
    days: ScheduleDay[];
    entries: ScheduleEntry[];
    scheduleMeta: {
        lessonDurationMinutes: number;
        breakDurationMinutes: number;
        startTime: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jadwal Pelajaran', href: '/kurikulum/schedule' },
];

function formatTime(start: string, end: string) {
    return `${start} - ${end}`;
}

function DraggableScheduleEntry({ entry }: { entry: ScheduleEntry }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } =
        useDraggable({ id: `entry:${entry.schedule_entry_id}` });

    return (
        <button
            ref={setNodeRef}
            type="button"
            className="w-full rounded p-1 text-center font-medium text-foreground"
            style={{
                backgroundColor: entry.subject_color,
                transform: transform
                    ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
                    : undefined,
                opacity: isDragging ? 0.5 : 1,
            }}
            {...listeners}
            {...attributes}
            aria-label={`Pindahkan ${entry.assignment_code}`}
        >
            {entry.assignment_code}
        </button>
    );
}

function DroppableScheduleCell({
    id,
    children,
}: {
    id: string;
    children: ReactNode;
}) {
    const { isOver, setNodeRef } = useDroppable({ id });

    return (
        <TableCell
            ref={setNodeRef}
            className={`border-r text-center align-middle text-muted-foreground ${isOver ? 'bg-primary/20' : ''}`}
        >
            {children}
        </TableCell>
    );
}

export default function JadwalPelajaranIndex({
    periods,
    selectedPeriod,
    grade,
    selectedRombels,
    selectedRombelIds,
    days,
    scheduleMeta,
    entries,
}: Props) {
    const [moveError, setMoveError] = useState<string | null>(null);
    const sensors = useSensors(useSensor(PointerSensor));

    const resolveMoveError = (errors: Record<string, string | string[]>) => {
        const message = errors.move ?? Object.values(errors)[0];
        const friendlyMessage = Array.isArray(message) ? message[0] : message;

        return friendlyMessage || 'Posisi jadwal belum dapat diperbarui.';
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over) {
            return;
        }

        const entry = entries.find(
            (item) => `entry:${item.schedule_entry_id}` === active.id,
        );
        const [day, lessonNumber] = over.id.toString().split(':');
        const lesson = Number(lessonNumber);

        if (!entry || !day || !Number.isInteger(lesson)) {
            return;
        }

        setMoveError(null);
        router.post(
            '/kurikulum/schedule/move',
            {
                schedule_plan_id: entry.schedule_plan_id,
                schedule_entry_id: entry.schedule_entry_id,
                selected_rombel_ids: selectedRombelIds,
                day,
                lesson_number: lesson,
            },
            {
                preserveScroll: true,
                onError: (errors) => setMoveError(resolveMoveError(errors)),
            },
        );
    };

    const selectGrade = (value: string) => {
        router.get(
            '/kurikulum/schedule',
            {
                ...(selectedPeriod
                    ? { academic_period_id: selectedPeriod.id }
                    : {}),
                grade: value,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const selectPeriod = (value: string) => {
        router.get(
            '/kurikulum/schedule',
            {
                academic_period_id: Number(value),
                grade,
                ...(selectedRombelIds.length
                    ? { rombel_ids: selectedRombelIds }
                    : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const viewerQuery = new URLSearchParams();

    if (selectedPeriod) {
        viewerQuery.set('academic_period_id', selectedPeriod.id.toString());
    }

    viewerQuery.set('grade', grade);
    selectedRombelIds.forEach((id) =>
        viewerQuery.append('rombel_ids[]', id.toString()),
    );
    const generateUrl = `/kurikulum/schedule/generate?${viewerQuery.toString()}`;
    const totalColumns = selectedRombels.length + 3;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jadwal Pelajaran" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <CalendarDays
                                className="size-5 text-primary"
                                aria-hidden="true"
                            />
                            <h1 className="text-xl font-semibold tracking-tight">
                                Jadwal Pelajaran
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Seret jadwal pada tabel untuk memindahkan slot.
                            Perubahan disimpan otomatis.
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <a href={generateUrl}>Siapkan Jadwal</a>
                    </Button>
                </div>

                <div className="grid gap-2 rounded-xl border p-4 sm:max-w-xl">
                    <label className="text-sm font-medium">Jenjang</label>
                    <Tabs value={grade} onValueChange={selectGrade}>
                        <TabsList className="w-full" aria-label="Pilih jenjang">
                            {['VII', 'VIII', 'IX'].map((item) => (
                                <TabsTrigger key={item} value={item}>
                                    {item}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                </div>

                <div className="grid gap-2 rounded-xl border p-4 sm:max-w-xl">
                    <label
                        htmlFor="schedule-period"
                        className="text-sm font-medium"
                    >
                        Tahun Ajaran & Semester
                    </label>
                    <SearchableCombobox
                        value={selectedPeriod?.id.toString() ?? ''}
                        options={periods.map((period) => ({
                            value: period.id.toString(),
                            label: `${period.academic_year.year} / ${period.name}`,
                        }))}
                        onChange={selectPeriod}
                        placeholder="Tidak ada semester aktif"
                        searchPlaceholder="Cari semester..."
                        emptyMessage="Semester aktif tidak ditemukan."
                    />
                </div>

                <DataTableShell>
                    <div className="border-b px-4 py-3 text-sm text-muted-foreground">
                        {selectedPeriod
                            ? `${selectedPeriod.academic_year.year} / ${selectedPeriod.name}`
                            : 'Tidak ada periode akademik aktif'}{' '}
                        · Jenjang {grade} · {selectedRombels.length} rombel
                        dipilih
                    </div>
                    <div className="overflow-x-auto">
                        <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
                            <Table className="min-w-[960px] border-separate border-spacing-0">
                                <caption className="sr-only">
                                    Jadwal mingguan untuk rombel yang dipilih
                                </caption>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="sticky left-0 z-20 w-28 border-r bg-muted">
                                            Hari
                                        </TableHead>
                                        <TableHead className="w-16 border-r bg-muted text-center">
                                            Jam Pelajaran
                                        </TableHead>
                                        <TableHead className="w-28 border-r bg-muted">
                                            Waktu
                                        </TableHead>
                                        {selectedRombels.map((rombel) => (
                                            <TableHead
                                                key={rombel.id}
                                                className="min-w-32 border-r bg-muted text-center"
                                            >
                                                {rombel.code}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {selectedRombels.length === 0 ? (
                                        <DataTableEmptyState
                                            colSpan={totalColumns}
                                        >
                                            Belum ada rombel yang dipilih. Pilih
                                            rombel melalui halaman Siapkan
                                            Jadwal untuk melihat jadwalnya.
                                        </DataTableEmptyState>
                                    ) : (
                                        days.flatMap((day, dayIndex) => [
                                            ...(dayIndex > 0
                                                ? [
                                                      <TableRow
                                                          key={`${day.key}-divider`}
                                                          aria-hidden="true"
                                                      >
                                                          <TableCell
                                                              colSpan={
                                                                  totalColumns
                                                              }
                                                              className="h-3 border-y-2 border-border bg-background p-0"
                                                          />
                                                      </TableRow>,
                                                  ]
                                                : []),
                                            ...day.rows.map((row, index) => (
                                                <TableRow
                                                    key={`${day.key}-${row.type}-${row.number ?? row.start}`}
                                                    className={
                                                        row.type === 'break'
                                                            ? 'bg-muted/50'
                                                            : row.type ===
                                                                'custom'
                                                              ? 'bg-primary/10'
                                                              : undefined
                                                    }
                                                >
                                                    {index === 0 && (
                                                        <TableCell
                                                            rowSpan={
                                                                day.rows.length
                                                            }
                                                            className="sticky left-0 z-10 border-r bg-background text-center align-middle font-semibold"
                                                        >
                                                            {day.label}
                                                        </TableCell>
                                                    )}
                                                    <TableCell className="border-r text-center text-muted-foreground">
                                                        {row.number ?? ''}
                                                    </TableCell>
                                                    <TableCell className="border-r font-medium whitespace-nowrap">
                                                        {formatTime(
                                                            row.start,
                                                            row.end,
                                                        )}
                                                    </TableCell>
                                                    {row.type === 'break' ||
                                                    row.type === 'custom' ? (
                                                        <TableCell
                                                            colSpan={
                                                                selectedRombels.length
                                                            }
                                                            className={
                                                                row.type ===
                                                                'custom'
                                                                    ? 'border-r text-center font-medium text-foreground'
                                                                    : 'border-r text-center text-muted-foreground italic'
                                                            }
                                                        >
                                                            {row.type ===
                                                            'custom'
                                                                ? row.label
                                                                : 'Istirahat'}
                                                        </TableCell>
                                                    ) : (
                                                        selectedRombels.map(
                                                            (rombel) => {
                                                                const entry =
                                                                    entries.find(
                                                                        (
                                                                            item,
                                                                        ) =>
                                                                            item.day ===
                                                                                day.key &&
                                                                            item.lesson_number ===
                                                                                row.number &&
                                                                            item.rombel_id ===
                                                                                rombel.id,
                                                                    );

                                                                return (
                                                                    <DroppableScheduleCell
                                                                        key={`${day.key}-${row.start}-${rombel.id}`}
                                                                        id={`${day.key}:${row.number}:${rombel.id}`}
                                                                    >
                                                                        {entry ? (
                                                                            <DraggableScheduleEntry
                                                                                entry={
                                                                                    entry
                                                                                }
                                                                            />
                                                                        ) : (
                                                                            <span className="sr-only">
                                                                                Belum
                                                                                ada
                                                                                jadwal
                                                                            </span>
                                                                        )}
                                                                    </DroppableScheduleCell>
                                                                );
                                                            },
                                                        )
                                                    )}
                                                </TableRow>
                                            )),
                                        ])
                                    )}
                                </TableBody>
                            </Table>
                        </DndContext>
                    </div>
                </DataTableShell>
                <p className="text-xs text-muted-foreground">
                    Durasi pelajaran: {scheduleMeta.lessonDurationMinutes}{' '}
                    menit. Perubahan posisi jadwal disimpan otomatis.
                </p>
            </div>
            <Dialog
                open={moveError !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMoveError(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                                <CircleAlert
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <DialogTitle>Jadwal belum dipindahkan</DialogTitle>
                        </div>
                        <DialogDescription>{moveError}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={() => setMoveError(null)}
                        >
                            Mengerti
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
