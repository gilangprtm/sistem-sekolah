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
import { CircleAlert, Plus } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTableShell from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import ModalShell from '@/components/ui/modal-shell';
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
import { activity, place } from '@/routes/schedule';
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

type PlacementOption = {
    rombel_id: number;
    subject_id: number;
    subject_name: string;
    teacher_subject_id: number;
    label: string;
    weekly_jp: number;
    remaining_jp: number;
};

type Props = {
    periods: Period[];
    selectedPeriod: Period | null;
    grade: string;
    selectedRombels: Rombel[];
    selectedRombelIds: number[];
    days: ScheduleDay[];
    entries: ScheduleEntry[];
    editablePlanId: number | null;
    canManageSchedule: boolean;
    placementOptions: PlacementOption[];
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

function DraggableScheduleEntry({
    entry,
    disabled,
}: {
    entry: ScheduleEntry;
    disabled: boolean;
}) {
    const { attributes, listeners, setNodeRef, transform, isDragging } =
        useDraggable({ id: `entry:${entry.schedule_entry_id}`, disabled });

    return (
        <button
            ref={setNodeRef}
            type="button"
            disabled={disabled}
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
    canPlace,
    onPlace,
    placementLabel,
}: {
    id: string;
    children: ReactNode;
    canPlace: boolean;
    onPlace: () => void;
    placementLabel: string;
}) {
    const { isOver, setNodeRef } = useDroppable({ id });

    return (
        <TableCell
            ref={setNodeRef}
            className={`border-r p-1 text-center align-middle text-muted-foreground ${isOver ? 'bg-primary/20' : ''}`}
        >
            {children ||
                (canPlace ? (
                    <button
                        type="button"
                        onClick={onPlace}
                        aria-label={placementLabel}
                        className="flex min-h-8 w-full items-center justify-center rounded text-muted-foreground/40 transition-colors hover:bg-primary/10 hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <Plus className="size-4" aria-hidden="true" />
                    </button>
                ) : null)}
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
    editablePlanId,
    canManageSchedule,
    placementOptions,
}: Props) {
    const [activitySlot, setActivitySlot] = useState<{
        day: string;
        lessonNumber: number;
    } | null>(null);
    const [activityName, setActivityName] = useState('');
    const [activityError, setActivityError] = useState<string | null>(null);
    const [isSavingActivity, setIsSavingActivity] = useState(false);
    const [moveError, setMoveError] = useState<string | null>(null);
    const [placementError, setPlacementError] = useState<string | null>(null);
    const [placementSlot, setPlacementSlot] = useState<{
        day: string;
        lessonNumber: number;
        rombelId: number;
    } | null>(null);
    const [selectedSubjectId, setSelectedSubjectId] = useState('');
    const [selectedTeacherSubjectId, setSelectedTeacherSubjectId] =
        useState('');
    const [isPlacing, setIsPlacing] = useState(false);
    const [isMoving, setIsMoving] = useState(false);
    const sensors = useSensors(useSensor(PointerSensor));

    const currentPlacementOptions = placementOptions.filter(
        (option) => option.rombel_id === placementSlot?.rombelId,
    );
    const subjectOptions = Array.from(
        new Map(
            currentPlacementOptions.map((option) => [
                option.subject_id,
                {
                    value: option.subject_id.toString(),
                    label: option.subject_name,
                },
            ]),
        ).values(),
    );
    const teacherPlacementOptions = currentPlacementOptions.filter(
        (option) => option.subject_id.toString() === selectedSubjectId,
    );
    const selectedPlacement = teacherPlacementOptions.find(
        (option) =>
            option.teacher_subject_id.toString() === selectedTeacherSubjectId,
    );

    const resolveMoveError = (errors: Record<string, string | string[]>) => {
        const message = errors.move ?? Object.values(errors)[0];
        const friendlyMessage = Array.isArray(message) ? message[0] : message;

        return friendlyMessage || 'Posisi jadwal belum dapat diperbarui.';
    };

    const openPlacement = (
        day: string,
        lessonNumber: number,
        rombelId: number,
    ) => {
        setPlacementSlot({ day, lessonNumber, rombelId });
        setSelectedSubjectId('');
        setSelectedTeacherSubjectId('');
        setPlacementError(null);
    };

    const submitPlacement = () => {
        if (!placementSlot || !selectedPlacement || editablePlanId === null) {
            return;
        }

        if (isPlacing) {
            return;
        }

        setPlacementError(null);
        setIsPlacing(true);
        router.post(
            place.url(),
            {
                schedule_plan_id: editablePlanId,
                rombel_id: placementSlot.rombelId,
                teacher_subject_id: selectedPlacement.teacher_subject_id,
                day: placementSlot.day,
                lesson_number: placementSlot.lessonNumber,
                selected_rombel_ids: selectedRombelIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => setPlacementSlot(null),
                onError: (errors) =>
                    setPlacementError(resolveMoveError(errors)),
                onFinish: () => setIsPlacing(false),
            },
        );
    };

    const openActivity = (day: string, row: ScheduleRow) => {
        if (row.number === undefined) {
            return;
        }

        setActivitySlot({ day, lessonNumber: row.number });
        setActivityName(row.type === 'custom' ? (row.label ?? '') : '');
        setActivityError(null);
    };

    const submitActivity = () => {
        if (
            !activitySlot ||
            editablePlanId === null ||
            !activityName.trim() ||
            isSavingActivity
        ) {
            return;
        }

        setActivityError(null);
        setIsSavingActivity(true);
        router.post(
            activity.url(),
            {
                schedule_plan_id: editablePlanId,
                day: activitySlot.day,
                lesson_number: activitySlot.lessonNumber,
                label: activityName.trim(),
                selected_rombel_ids: selectedRombelIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => setActivitySlot(null),
                onError: (errors) =>
                    setActivityError(
                        Object.values(errors)[0] ||
                            'Kegiatan belum dapat disimpan.',
                    ),
                onFinish: () => setIsSavingActivity(false),
            },
        );
    };

    const handleDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || !canManageSchedule || isMoving) {
            return;
        }

        const entry = entries.find(
            (item) => `entry:${item.schedule_entry_id}` === active.id,
        );
        const [day, lessonNumber, targetRombelId] = over.id
            .toString()
            .split(':');
        const lesson = Number(lessonNumber);
        const rombelId = Number(targetRombelId);

        if (
            !entry ||
            !day ||
            !Number.isInteger(lesson) ||
            !Number.isInteger(rombelId)
        ) {
            return;
        }

        if (rombelId !== entry.rombel_id) {
            setMoveError(
                'Jadwal hanya dapat dipindahkan atau ditukar dalam rombel yang sama.',
            );

            return;
        }

        if (entry.day === day && entry.lesson_number === lesson) {
            return;
        }

        setMoveError(null);
        setIsMoving(true);
        router.post(
            '/kurikulum/schedule/move',
            {
                schedule_plan_id: entry.schedule_plan_id,
                schedule_entry_id: entry.schedule_entry_id,
                target_rombel_id: rombelId,
                selected_rombel_ids: selectedRombelIds,
                day,
                lesson_number: lesson,
            },
            {
                preserveScroll: true,
                onError: (errors) => setMoveError(resolveMoveError(errors)),
                onFinish: () => setIsMoving(false),
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
    const generateUrl = `/kurikulum/schedule/generate?${viewerQuery.toString()}`;
    const totalColumns = selectedRombels.length + 3;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jadwal Pelajaran" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Jadwal Pelajaran"
                        description="Klik angka jam untuk menambahkan kegiatan seluruh kelas. Pilih slot kosong untuk mengisi JP mingguan. Seret kotak untuk memindahkan atau menukar posisi dalam kelas yang sama. Perubahan disimpan otomatis."
                    />
                    <Button
                        variant="outline"
                        asChild
                        className="w-full md:w-auto md:shrink-0"
                    >
                        <a href={generateUrl}>Siapkan Jadwal</a>
                    </Button>
                </div>

                <div className="grid gap-2 sm:max-w-xl">
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

                <div className="grid gap-2 sm:max-w-xl">
                    <label className="text-sm font-medium">Jenjang</label>
                    <Tabs value={grade} onValueChange={selectGrade}>
                        <TabsList
                            variant="line"
                            className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"
                            aria-label="Pilih jenjang"
                        >
                            {['VII', 'VIII', 'IX'].map((item) => (
                                <TabsTrigger key={item} value={item}>
                                    {item}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
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
                                                        {row.number !==
                                                            undefined &&
                                                        canManageSchedule &&
                                                        editablePlanId !==
                                                            null ? (
                                                            <button
                                                                type="button"
                                                                className="inline-flex min-h-9 min-w-9 items-center justify-center rounded font-medium text-primary hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                                disabled={
                                                                    isSavingActivity ||
                                                                    isMoving ||
                                                                    isPlacing
                                                                }
                                                                aria-label={`Jadwal Kegiatan ${day.label}, jam ke-${row.number}`}
                                                                onClick={() =>
                                                                    openActivity(
                                                                        day.key,
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                {row.number}
                                                            </button>
                                                        ) : (
                                                            (row.number ?? '')
                                                        )}
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
                                                                        canPlace={
                                                                            canManageSchedule &&
                                                                            editablePlanId !==
                                                                                null &&
                                                                            !entry
                                                                        }
                                                                        placementLabel={`Tambahkan jadwal ${rombel.code}, ${day.label}, jam ke-${row.number}`}
                                                                        onPlace={() =>
                                                                            openPlacement(
                                                                                day.key,
                                                                                row.number ??
                                                                                    0,
                                                                                rombel.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        {entry ? (
                                                                            <DraggableScheduleEntry
                                                                                disabled={
                                                                                    !canManageSchedule ||
                                                                                    isMoving
                                                                                }
                                                                                entry={
                                                                                    entry
                                                                                }
                                                                            />
                                                                        ) : null}
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
                    Slot kosong dapat dipilih untuk menjadwalkan sisa JP
                    mingguan. Durasi pelajaran:{' '}
                    {scheduleMeta.lessonDurationMinutes} menit. Perubahan posisi
                    jadwal disimpan otomatis.
                </p>
            </div>
            <Dialog
                open={activitySlot !== null}
                onOpenChange={(open) => {
                    if (!open && !isSavingActivity) {
                        setActivitySlot(null);
                    }
                }}
            >
                <ModalShell size="medium">
                    <DialogHeader>
                        <DialogTitle>Jadwal Kegiatan</DialogTitle>
                        <DialogDescription>
                            {
                                days.find(
                                    (day) => day.key === activitySlot?.day,
                                )?.label
                            }{' '}
                            · Jam ke-{activitySlot?.lessonNumber}. Berlaku untuk
                            seluruh kelas jenjang {grade} pada periode ini,
                            termasuk kelas yang tidak sedang ditampilkan. Baris
                            harus kosong dari mata pelajaran.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submitActivity();
                        }}
                    >
                        <div className="grid gap-2">
                            <label
                                htmlFor="schedule-activity-name"
                                className="text-sm font-medium"
                            >
                                Nama Kegiatan
                            </label>
                            <Input
                                id="schedule-activity-name"
                                value={activityName}
                                onChange={(event) =>
                                    setActivityName(event.target.value)
                                }
                                placeholder="Contoh: Literasi"
                                required
                                maxLength={120}
                                disabled={isSavingActivity}
                                aria-invalid={!!activityError}
                                aria-describedby={
                                    activityError
                                        ? 'schedule-activity-error'
                                        : undefined
                                }
                            />
                        </div>
                        {activityError && (
                            <p
                                id="schedule-activity-error"
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {activityError}
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={isSavingActivity}
                                onClick={() => setActivitySlot(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    isSavingActivity || !activityName.trim()
                                }
                            >
                                {isSavingActivity ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </ModalShell>
            </Dialog>
            <Dialog
                open={placementSlot !== null}
                onOpenChange={(open) => {
                    if (!open && !isPlacing) {
                        setPlacementSlot(null);
                        setPlacementError(null);
                    }
                }}
            >
                <ModalShell size="medium">
                    <DialogHeader>
                        <DialogTitle>Isi slot jadwal kosong</DialogTitle>
                        <DialogDescription>
                            {
                                selectedRombels.find(
                                    (rombel) =>
                                        rombel.id === placementSlot?.rombelId,
                                )?.code
                            }{' '}
                            ·{' '}
                            {
                                days.find(
                                    (day) => day.key === placementSlot?.day,
                                )?.label
                            }{' '}
                            · Jam ke-{placementSlot?.lessonNumber}.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <label
                            htmlFor="schedule-placement-subject"
                            className="text-sm font-medium"
                        >
                            Mata pelajaran
                        </label>
                        <SearchableCombobox
                            id="schedule-placement-subject"
                            value={selectedSubjectId}
                            options={subjectOptions}
                            onChange={(value) => {
                                setSelectedSubjectId(value);
                                setSelectedTeacherSubjectId('');
                                setPlacementError(null);
                            }}
                            disabled={isPlacing}
                            placeholder="Pilih mata pelajaran"
                            searchPlaceholder="Cari mata pelajaran..."
                            emptyMessage="Tidak ada mata pelajaran dengan sisa JP untuk kelas ini."
                        />
                    </div>
                    <div className="grid gap-2">
                        <label
                            htmlFor="schedule-placement-teacher"
                            className="text-sm font-medium"
                        >
                            Guru mata pelajaran
                        </label>
                        <SearchableCombobox
                            id="schedule-placement-teacher"
                            value={selectedTeacherSubjectId}
                            options={teacherPlacementOptions.map((option) => ({
                                value: option.teacher_subject_id.toString(),
                                label: option.label,
                            }))}
                            onChange={(value) => {
                                setSelectedTeacherSubjectId(value);
                                setPlacementError(null);
                            }}
                            disabled={isPlacing || selectedSubjectId === ''}
                            placeholder="Pilih guru mata pelajaran"
                            searchPlaceholder="Cari kode atau nama guru..."
                            emptyMessage="Tidak ada guru dengan sisa JP untuk mata pelajaran ini."
                        />
                    </div>
                    {placementError && (
                        <p role="alert" className="text-sm text-destructive">
                            {placementError}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPlacementSlot(null)}
                            disabled={isPlacing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            onClick={submitPlacement}
                            disabled={!selectedPlacement || isPlacing}
                        >
                            {isPlacing ? 'Menjadwalkan...' : 'Jadwalkan'}
                        </Button>
                    </DialogFooter>
                </ModalShell>
            </Dialog>
            <Dialog
                open={moveError !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setMoveError(null);
                    }
                }}
            >
                <ModalShell>
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
                </ModalShell>
            </Dialog>
        </AppLayout>
    );
}
