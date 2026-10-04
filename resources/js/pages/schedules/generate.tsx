import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronsUpDown, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type Subject = {
    id: number;
    code: string;
    name: string;
    jp_per_class: number;
};
type TeacherSubject = {
    id: number;
    teacher_id: number;
    teacher_name: string;
    subject_id: number;
    subject_code: string;
    subject_name: string;
    code: string;
};
type TeacherAssignment = {
    id: string;
    teacher_subject_id: string;
    rombel_ids: string[];
};
type DraftTeachingAssignment = {
    teacher_subject_id: number;
    rombel_id: number;
};
type ManualSlot = {
    day: string;
    lesson_number: number;
};
type ManualPlacement = {
    teacher_subject_id: number;
    rombel_id: number;
    slots: ManualSlot[];
};
type CustomSlot = {
    id?: number | string;
    day: string;
    lesson_number: number;
    start_time?: string;
    end_time?: string;
    label: string;
};

const scheduleDays = [
    { value: 'monday', label: 'Senin' },
    { value: 'tuesday', label: 'Selasa' },
    { value: 'wednesday', label: 'Rabu' },
    { value: 'thursday', label: 'Kamis' },
    { value: 'friday', label: 'Jumat' },
] as const;

function createCustomSlotId(): string {
    return `custom-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}
type DraftPlan = {
    id: number;
    revision: number;
    source_plan_id: number | null;
    teaching_assignments: DraftTeachingAssignment[];
    custom_slots: CustomSlot[];
    manual_placements?: ManualPlacement[];
    entries?: ManualSlot[];
} | null;

type RombelMultiSelectProps = {
    value: string[];
    options: { value: string; label: string }[];
    onChange: (value: string[]) => void;
};

function RombelMultiSelect({
    value,
    options,
    onChange,
}: RombelMultiSelectProps) {
    const [open, setOpen] = useState(false);
    const selectedLabels = options
        .filter((option) => value.includes(option.value))
        .map((option) => option.label);
    const selectedSummary =
        selectedLabels.length > 1
            ? `${selectedLabels.length}+ kelas dipilih`
            : (selectedLabels[0] ?? 'Pilih kelas');

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between font-normal"
                >
                    <span className="truncate text-left">
                        {selectedSummary}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[var(--radix-popover-trigger-width)] min-w-0 p-0"
            >
                <Command>
                    <CommandInput placeholder="Cari kelas..." />
                    <CommandList>
                        <CommandEmpty>Kelas tidak ditemukan.</CommandEmpty>
                        {options.map((option) => {
                            const checked = value.includes(option.value);

                            return (
                                <CommandItem
                                    key={option.value}
                                    value={option.label}
                                    onSelect={() =>
                                        onChange(
                                            checked
                                                ? value.filter(
                                                      (item) =>
                                                          item !== option.value,
                                                  )
                                                : [...value, option.value],
                                        )
                                    }
                                >
                                    <Checkbox
                                        checked={checked}
                                        tabIndex={-1}
                                        className="pointer-events-none"
                                        aria-hidden="true"
                                    />
                                    <span>{option.label}</span>
                                </CommandItem>
                            );
                        })}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

type ScheduleSlot = {
    number: number;
    start: string;
    end: string;
};
type Props = {
    periods: Period[];
    selectedPeriod: Period | null;
    grade: string;
    search: string;
    availableRombels: Rombel[];
    selectedRombels: Rombel[];
    selectedRombelIds: number[];
    subjects: Subject[];
    teacherSubjects: TeacherSubject[];
    scheduleSlotOptions: Record<string, ScheduleSlot[]>;
    draftPlan: DraftPlan;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jadwal Pelajaran', href: '/schedule' },
    { title: 'Rancang Jadwal', href: '/schedule/generate' },
];
const grades = ['VII', 'VIII', 'IX'];
function createAssignmentId(): string {
    try {
        if (typeof globalThis.crypto?.randomUUID === 'function') {
            return globalThis.crypto.randomUUID();
        }
    } catch {
        // Fall through when the browser exposes but cannot use randomUUID.
    }

    return `draft-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export default function ScheduleGenerate({
    periods,
    selectedPeriod,
    grade,
    search: initialSearch,
    availableRombels,
    selectedRombels: initialSelectedRombels,
    selectedRombelIds: initialSelectedRombelIds,
    subjects,
    teacherSubjects,
    draftPlan,
    scheduleSlotOptions,
}: Props) {
    const [search, setSearch] = useState(initialSearch);
    const [selectedIds, setSelectedIds] = useState(initialSelectedRombelIds);
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [manualPlacements] = useState<Record<string, ManualPlacement>>(() =>
        Object.fromEntries(
            (draftPlan?.manual_placements ?? []).map((placement) => [
                `${placement.teacher_subject_id}:${placement.rombel_id}`,
                placement,
            ]),
        ),
    );
    const [customSlots, setCustomSlots] = useState<CustomSlot[]>(
        () => draftPlan?.custom_slots ?? [],
    );
    const [customSlotOpen, setCustomSlotOpen] = useState(false);
    const [customSlotDraft, setCustomSlotDraft] = useState<CustomSlot>({
        id: createCustomSlotId(),
        day: 'friday',
        lesson_number: 1,
        label: '',
    });
    const [assignments, setAssignments] = useState<
        Record<string, TeacherAssignment[]>
    >(() => {
        if (!draftPlan?.teaching_assignments.length) {
            return {};
        }

        return draftPlan.teaching_assignments.reduce<
            Record<string, TeacherAssignment[]>
        >((grouped, assignment) => {
            const subject = teacherSubjects.find(
                (teacherSubject) =>
                    teacherSubject.id === assignment.teacher_subject_id,
            );

            if (!subject) {
                return grouped;
            }

            const current = grouped[subject.subject_id] ?? [];
            const existing = current.find(
                (item) =>
                    item.teacher_subject_id ===
                    assignment.teacher_subject_id.toString(),
            );

            if (existing) {
                existing.rombel_ids = [
                    ...new Set([
                        ...existing.rombel_ids,
                        assignment.rombel_id.toString(),
                    ]),
                ];

                return grouped;
            }

            grouped[subject.subject_id] = [
                ...current,
                {
                    id: `draft-${assignment.teacher_subject_id}`,
                    teacher_subject_id:
                        assignment.teacher_subject_id.toString(),
                    rombel_ids: [assignment.rombel_id.toString()],
                },
            ];

            return grouped;
        }, {});
    });
    const saveAssignments = () => {
        setProcessing(true);
        setSaveError(null);
        router.post(
            '/schedule',
            {
                academic_period_id: selectedPeriod?.id,
                grade,
                selected_rombel_ids: selectedIds,
                source_plan_id: draftPlan?.id ?? null,
                manual_placements: Object.values(manualPlacements),
                custom_slots: customSlots.map(
                    ({ day, lesson_number, label }) => ({
                        day,
                        lesson_number,
                        label,
                    }),
                ),
                assignments: Object.entries(assignments).flatMap(
                    ([subjectId, subjectAssignments]) =>
                        subjectAssignments
                            .filter(
                                (assignment) =>
                                    assignment.teacher_subject_id &&
                                    assignment.rombel_ids.length,
                            )
                            .map((assignment) => ({
                                subject_id: Number(subjectId),
                                teacher_subject_id: Number(
                                    assignment.teacher_subject_id,
                                ),
                                rombel_ids: assignment.rombel_ids.map(Number),
                            })),
                ),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    setOpen(false);
                },
                onError: (errors) => {
                    setProcessing(false);
                    setSaveError(
                        Object.values(errors)[0] ??
                            'Jadwal belum dapat disimpan.',
                    );
                },
            },
        );
    };

    const selectedRombels = useMemo(() => {
        const rombelOptions = new Map(
            [...initialSelectedRombels, ...availableRombels].map((rombel) => [
                rombel.id,
                rombel,
            ]),
        );

        return selectedIds
            .map((id) => rombelOptions.get(id))
            .filter((rombel): rombel is Rombel => Boolean(rombel));
    }, [availableRombels, initialSelectedRombels, selectedIds]);

    const navigate = (nextGrade = grade, nextIds = selectedIds) => {
        router.get(
            '/schedule/generate',
            {
                ...(selectedPeriod
                    ? { academic_period_id: selectedPeriod.id }
                    : {}),
                grade: nextGrade,
                ...(search ? { search } : {}),
                ...(nextIds.length ? { rombel_ids: nextIds } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const selectPeriod = (value: string) => {
        router.get(
            '/schedule/generate',
            {
                academic_period_id: Number(value),
                grade,
                ...(search ? { search } : {}),
                ...(selectedIds.length ? { rombel_ids: selectedIds } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const toggleRombel = (id: number, checked: boolean | 'indeterminate') => {
        setSelectedIds((current) =>
            checked === true
                ? [...new Set([...current, id])]
                : current.filter((selectedId) => selectedId !== id),
        );
    };
    const teacherOptions = (subjectId: number) =>
        teacherSubjects
            .filter((item) => item.subject_id === subjectId)
            .map((item) => ({
                value: item.id.toString(),
                label: item.teacher_name,
            }));
    const addTeacherAssignment = (subjectId: number) => {
        setAssignments((current) => ({
            ...current,
            [subjectId]: [
                ...(current[subjectId] ?? []),
                {
                    id: createAssignmentId(),
                    teacher_subject_id: '',
                    rombel_ids: [],
                },
            ],
        }));
    };
    const updateTeacherAssignment = (
        subjectId: number,
        assignmentId: string,
        patch: Partial<TeacherAssignment>,
    ) => {
        setAssignments((current) => ({
            ...current,
            [subjectId]: (current[subjectId] ?? []).map((assignment) =>
                assignment.id === assignmentId
                    ? { ...assignment, ...patch }
                    : assignment,
            ),
        }));
    };
    const removeTeacherAssignment = (
        subjectId: number,
        assignmentId: string,
    ) => {
        setAssignments((current) => ({
            ...current,
            [subjectId]: (current[subjectId] ?? []).filter(
                (assignment) => assignment.id !== assignmentId,
            ),
        }));
    };
    const addCustomSlot = () => {
        const label = customSlotDraft.label.trim();
        const slotKey = `${customSlotDraft.day}:${customSlotDraft.lesson_number}`;

        if (
            !label ||
            customSlots.some(
                (slot) => `${slot.day}:${slot.lesson_number}` === slotKey,
            )
        ) {
            return;
        }

        setCustomSlots((current) => [
            ...current,
            {
                ...customSlotDraft,
                id: createCustomSlotId(),
                label,
            },
        ]);
        setCustomSlotDraft({
            id: createCustomSlotId(),
            day: 'friday',
            lesson_number: 1,
            label: '',
        });
        setCustomSlotOpen(false);
    };

    const removeCustomSlot = (slotId: number | string | undefined) => {
        setCustomSlots((current) =>
            current.filter((slot) => slot.id !== slotId),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rancang Jadwal" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-start gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link
                            href="/schedule"
                            aria-label="Kembali ke jadwal pelajaran"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Rancang Jadwal"
                        description="Tentukan Guru dan Rombel untuk setiap Mata Pelajaran. Pengaturan dapat disimpan sebagai jadwal dan jadwal tersimpan dapat diubah kembali."
                    />
                </div>
                <div className="grid gap-3 rounded-xl border p-4 md:grid-cols-12 md:items-end">
                    <div className="grid gap-2 md:col-span-4">
                        <Label>Tahun Ajaran & Semester</Label>
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
                    <div className="grid gap-2 md:col-span-5">
                        <Label htmlFor="schedule-rombel-search">
                            Cari rombel
                        </Label>
                        <Input
                            id="schedule-rombel-search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) =>
                                event.key === 'Enter' && navigate()
                            }
                            placeholder="Kode atau nama rombel..."
                        />
                    </div>
                    <div className="flex gap-2 md:col-span-3">
                        <Button onClick={() => navigate()}>Terapkan</Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                setSelectedIds([]);
                                setAssignments({});
                                router.get(
                                    '/schedule/generate',
                                    { grade: 'VII' },
                                    { replace: true },
                                );
                            }}
                        >
                            Atur Ulang
                        </Button>
                    </div>
                    <div className="grid gap-2 md:col-span-12">
                        <Label>Jenjang</Label>
                        <Tabs
                            value={grade}
                            onValueChange={(value) => {
                                setSelectedIds([]);
                                setAssignments({});
                                navigate(value, []);
                            }}
                        >
                            <TabsList aria-label="Pilih jenjang">
                                {grades.map((item) => (
                                    <TabsTrigger key={item} value={item}>
                                        {item}
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>
                    </div>
                </div>
                <DataTableShell>
                    <DataTableToolbar>
                        <div className="text-sm text-muted-foreground">
                            {selectedIds.length} Rombel dipilih untuk rancangan
                            jadwal.
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                onClick={() => setOpen(true)}
                                disabled={
                                    !selectedIds.length || !subjects.length
                                }
                            >
                                Rancang Pembagian
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={saveAssignments}
                                disabled={
                                    processing ||
                                    selectedIds.length === 0 ||
                                    !subjects.length
                                }
                            >
                                {processing
                                    ? 'Membuat Jadwal...'
                                    : 'Buat Jadwal'}
                            </Button>
                            {draftPlan && (
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    Hapus Jadwal
                                </Button>
                            )}
                        </div>
                    </DataTableToolbar>
                    {saveError && (
                        <p
                            role="alert"
                            className="px-4 pb-3 text-sm text-destructive"
                        >
                            {saveError}
                        </p>
                    )}
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">
                                        Pilih
                                    </TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama Rombel</TableHead>
                                    <TableHead>Jenjang</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {availableRombels.map((rombel) => (
                                    <TableRow key={rombel.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    rombel.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleRombel(
                                                        rombel.id,
                                                        checked,
                                                    )
                                                }
                                                aria-label={`Pilih ${rombel.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {rombel.code}
                                        </TableCell>
                                        <TableCell>{rombel.name}</TableCell>
                                        <TableCell>
                                            {rombel.grade_level}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {availableRombels.length === 0 && (
                                    <DataTableEmptyState colSpan={4}>
                                        Tidak ada rombel aktif pada semester ini
                                        yang sesuai dengan filter.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </DataTableShell>
                <p className="text-xs text-muted-foreground">
                    {selectedRombels.length} Rombel aktif tersedia untuk
                    rancangan. Pengaturan dapat disimpan sebagai jadwal dan
                    jadwal tersimpan dapat diubah kembali.
                </p>
            </div>
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus jadwal jenjang?</DialogTitle>
                        <DialogDescription>
                            Jadwal {grade} pada periode ini akan dihapus permanen.
                            Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() =>
                                router.delete('/schedule', {
                                    data: {
                                        academic_period_id: selectedPeriod?.id,
                                        grade,
                                    },
                                    onSuccess: () => setDeleteOpen(false),
                                })
                            }
                        >
                            Hapus Permanen
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Rancang Pembagian Jadwal</DialogTitle>
                        <DialogDescription>
                            Atur Guru dan kelas untuk setiap Mata Pelajaran. JP
                            akan mengikuti pengaturan pada Master Mata
                            Pelajaran.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[65vh] space-y-3 overflow-y-auto pr-1">
                        <section className="rounded-lg border border-primary/30 bg-primary/5 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="font-medium">
                                        Kegiatan Khusus
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Blokir slot tertentu dari alokasi Guru
                                        dan tampilkan kegiatannya di semua
                                        Rombel.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setCustomSlotOpen((current) => !current)
                                    }
                                >
                                    <Plus data-icon="inline-start" />
                                    Tambah Kegiatan
                                </Button>
                            </div>
                            {customSlotOpen && (
                                <div className="mt-3 grid gap-3 rounded-md bg-muted/30 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)_minmax(0,3fr)_auto] sm:items-end">
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="custom-schedule-day">
                                            Hari
                                        </Label>
                                        <Select
                                            value={customSlotDraft.day}
                                            onValueChange={(value) =>
                                                setCustomSlotDraft(
                                                    (current) => ({
                                                        ...current,
                                                        day: value,
                                                        lesson_number:
                                                            scheduleSlotOptions[
                                                                value
                                                            ]?.[0]?.number ?? 1,
                                                    }),
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="custom-schedule-day"
                                                className="w-full min-w-0"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {scheduleDays.map((day) => (
                                                    <SelectItem
                                                        key={day.value}
                                                        value={day.value}
                                                    >
                                                        {day.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="custom-schedule-lesson">
                                            Jam Pelajaran
                                        </Label>
                                        <Select
                                            value={customSlotDraft.lesson_number.toString()}
                                            onValueChange={(value) =>
                                                setCustomSlotDraft(
                                                    (current) => ({
                                                        ...current,
                                                        lesson_number:
                                                            Number(value),
                                                    }),
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="custom-schedule-lesson"
                                                className="w-full min-w-0"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(
                                                    scheduleSlotOptions[
                                                        customSlotDraft.day
                                                    ] ?? []
                                                ).map((slot) => (
                                                    <SelectItem
                                                        key={slot.number}
                                                        value={slot.number.toString()}
                                                    >
                                                        Jam {slot.number} (
                                                        {slot.start} -{' '}
                                                        {slot.end})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="custom-schedule-label">
                                            Nama Kegiatan
                                        </Label>
                                        <Input
                                            id="custom-schedule-label"
                                            value={customSlotDraft.label}
                                            onChange={(event) =>
                                                setCustomSlotDraft(
                                                    (current) => ({
                                                        ...current,
                                                        label: event.target
                                                            .value,
                                                    }),
                                                )
                                            }
                                            placeholder="Contoh: Kebersihan Sekolah"
                                            maxLength={120}
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        onClick={addCustomSlot}
                                    >
                                        Tambahkan
                                    </Button>
                                </div>
                            )}
                            {customSlots.length > 0 && (
                                <div className="mt-3 space-y-2">
                                    {customSlots.map((slot) => (
                                        <div
                                            key={slot.id}
                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-background px-3 py-2 text-sm"
                                        >
                                            <span>
                                                <strong>
                                                    {
                                                        scheduleDays.find(
                                                            (day) =>
                                                                day.value ===
                                                                slot.day,
                                                        )?.label
                                                    }
                                                </strong>{' '}
                                                · Jam {slot.lesson_number} ·{' '}
                                                {slot.label}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    removeCustomSlot(slot.id)
                                                }
                                            >
                                                Hapus
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                        {subjects.map((subject) => {
                            const subjectAssignments =
                                assignments[subject.id] ?? [];

                            return (
                                <section
                                    key={subject.id}
                                    className="rounded-lg border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <h3 className="font-medium">
                                                {subject.code} · {subject.name}
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                {subjectAssignments.length
                                                    ? `${subjectAssignments.length} Guru ditambahkan`
                                                    : 'Belum ada Guru yang ditambahkan'}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            onClick={() =>
                                                addTeacherAssignment(subject.id)
                                            }
                                        >
                                            <Plus data-icon="inline-start" />
                                            Tambah Guru
                                        </Button>
                                    </div>
                                    <div className="mt-3 space-y-3">
                                        {subjectAssignments.map(
                                            (assignment) => (
                                                <div
                                                    key={assignment.id}
                                                    className="grid gap-3 rounded-md bg-muted/30 p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end"
                                                >
                                                    <div className="grid gap-2">
                                                        <Label>
                                                            Guru Mata Pelajaran
                                                        </Label>
                                                        <SearchableCombobox
                                                            value={
                                                                assignment.teacher_subject_id
                                                            }
                                                            options={teacherOptions(
                                                                subject.id,
                                                            )}
                                                            onChange={(value) =>
                                                                updateTeacherAssignment(
                                                                    subject.id,
                                                                    assignment.id,
                                                                    {
                                                                        teacher_subject_id:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                            placeholder="Cari dan pilih Guru"
                                                            searchPlaceholder="Cari Guru..."
                                                            emptyMessage="Guru Mata Pelajaran tidak ditemukan."
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label>Kelas</Label>
                                                        <RombelMultiSelect
                                                            value={
                                                                assignment.rombel_ids
                                                            }
                                                            options={selectedRombels.map(
                                                                (rombel) => ({
                                                                    value: rombel.id.toString(),
                                                                    label: `${rombel.code} · ${rombel.name}`,
                                                                }),
                                                            )}
                                                            onChange={(value) =>
                                                                updateTeacherAssignment(
                                                                    subject.id,
                                                                    assignment.id,
                                                                    {
                                                                        rombel_ids:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            removeTeacherAssignment(
                                                                subject.id,
                                                                assignment.id,
                                                            )
                                                        }
                                                        aria-label={`Hapus Guru dari ${subject.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                        {!subjectAssignments.length && (
                                            <p className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                                Belum ada Guru untuk Mata
                                                Pelajaran ini.
                                            </p>
                                        )}
                                    </div>
                                </section>
                            );
                        })}
                        {!subjects.length && (
                            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                Belum ada Mata Pelajaran aktif.
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={saveAssignments}
                        >
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
