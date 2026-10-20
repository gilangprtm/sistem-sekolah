import {
    DndContext,
    MouseSensor,
    TouchSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import { Armchair, Grip } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { update as updateSeating } from '@/routes/teacher/homeroom/seating';
import type { BreadcrumbItem } from '@/types';

type AcademicYear = {
    id: number;
    year: string;
};

type Homeroom = {
    id: number;
    code: string;
    name: string;
    grade_level: string;
    parallel_code: string;
};

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
    tahun_angkatan: number | null;
    full_name: string | null;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    phone: string | null;
    mother_name: string | null;
    father_name: string | null;
    mother_occupation: string | null;
    father_occupation: string | null;
    parent_phone: string | null;
};

type PaginatedStudents = {
    data: Student[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    academicYear: AcademicYear | null;
    homeroom: Homeroom | null;
    students: PaginatedStudents | null;
    filters: { per_page?: number };
    hasTeacherProfile: boolean;
    canManageSeating: boolean;
    seating: {
        version: string;
        seats: Array<{ seat_number: number; student_id: number }>;
        students: Array<{
            id: number;
            full_name: string | null;
            photo_url: string | null;
        }>;
    } | null;
};

type SeatStudent = NonNullable<Props['seating']>['students'][number];

function ClassroomSeat({
    number,
    student,
    disabled,
    onSelect,
}: {
    number: number;
    student?: SeatStudent;
    disabled: boolean;
    onSelect: () => void;
}) {
    const { setNodeRef: setDropRef, isOver } = useDroppable({
        id: number,
        disabled,
    });
    const {
        setNodeRef: setDragRef,
        attributes,
        listeners,
        transform,
        isDragging,
    } = useDraggable({
        id: number,
        disabled: disabled || !student,
    });

    return (
        <div
            ref={setDropRef}
            className={`relative min-w-0 rounded-xl border bg-card p-2 ${isOver ? 'border-primary bg-primary/10 ring-2 ring-primary' : ''}`}
        >
            <button
                ref={setDragRef}
                type="button"
                disabled={disabled}
                onClick={onSelect}
                {...attributes}
                {...listeners}
                style={{
                    transform: transform
                        ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
                        : undefined,
                    opacity: isDragging ? 0.4 : 1,
                }}
                className="relative z-10 grid h-44 w-full touch-manipulation grid-rows-[minmax(0,1fr)_2rem_1rem] items-center justify-items-center gap-1 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
                aria-label={`Meja ${number}: ${student?.full_name ?? 'Kosong'}. Pilih atau ganti siswa.`}
            >
                {student ? (
                    <Avatar
                        className={
                            student.photo_url
                                ? 'aspect-[2/3] h-auto w-full max-w-20 overflow-hidden rounded-md after:rounded-md'
                                : 'size-12'
                        }
                    >
                        <AvatarImage
                            src={student.photo_url ?? undefined}
                            alt={student.full_name ?? 'Siswa'}
                            draggable={false}
                            className="aspect-auto rounded-md object-top"
                        />
                        <AvatarFallback
                            className={
                                student.photo_url ? 'rounded-md' : undefined
                            }
                        >
                            {(student.full_name ?? 'S')
                                .split(' ')
                                .map((word) => word[0])
                                .slice(0, 2)
                                .join('')}
                        </AvatarFallback>
                    </Avatar>
                ) : (
                    <Armchair
                        className="size-8 text-muted-foreground"
                        aria-hidden="true"
                    />
                )}
                <span className="line-clamp-2 w-full text-center text-xs font-medium">
                    {student?.full_name ?? `Meja ${number}`}
                </span>
                {student && (
                    <Grip
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                )}
            </button>
            <span
                className="absolute top-1 left-1 z-20 rounded bg-background/80 px-1 text-[10px] text-muted-foreground"
                aria-hidden="true"
            >
                {number}
            </span>
        </div>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Wali Kelas', href: '/guru/wali-kelas' },
];

function whatsappUrl(phone: string | null): string | null {
    const digits = phone?.replace(/\D/g, '') ?? '';

    if (digits === '') {
        return null;
    }

    const internationalNumber = digits.startsWith('0')
        ? `62${digits.slice(1)}`
        : digits.startsWith('8')
          ? `62${digits}`
          : digits;

    return `https://wa.me/${internationalNumber}`;
}

function StudentActionItems({
    student,
    onView,
}: {
    student: Student;
    onView: () => void;
}) {
    const studentWhatsappUrl = whatsappUrl(student.phone);
    const parentWhatsappUrl = whatsappUrl(student.parent_phone);

    return (
        <>
            <DropdownMenuItem onClick={onView}>Lihat</DropdownMenuItem>
            {studentWhatsappUrl && (
                <DropdownMenuItem asChild>
                    <a
                        href={studentWhatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Hub Siswa
                    </a>
                </DropdownMenuItem>
            )}
            {parentWhatsappUrl && (
                <DropdownMenuItem asChild>
                    <a
                        href={parentWhatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Hub Ortu
                    </a>
                </DropdownMenuItem>
            )}
        </>
    );
}

const INDONESIAN_MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

function formatStudentDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;
    const monthName = INDONESIAN_MONTHS[Number(month) - 1];
    const dayNumber = Number(day);

    if (!monthName || dayNumber < 1 || dayNumber > 31) {
        return value;
    }

    return `${dayNumber} ${monthName} ${year}`;
}

function displayDetailValue(value: string | number | null): string {
    if (value === null || `${value}`.trim() === '') {
        return '—';
    }

    return `${value}`;
}

function StudentDetail({ student }: { student: Student }) {
    const sections = [
        {
            title: 'Data pribadi',
            description: 'Identitas dan informasi dasar siswa.',
            fields: [
                { label: 'NIS', value: student.nis },
                { label: 'NISN', value: student.nisn },
                {
                    label: 'Jenis kelamin',
                    value:
                        student.gender === 'L'
                            ? 'Laki-laki'
                            : student.gender === 'P'
                              ? 'Perempuan'
                              : null,
                },
                { label: 'Tahun angkatan', value: student.tahun_angkatan },
                { label: 'Tempat lahir', value: student.birth_place },
                {
                    label: 'Tanggal lahir',
                    value: formatStudentDate(student.birth_date),
                },
                {
                    label: 'Nomor telepon siswa',
                    value: student.phone,
                    fullWidth: true,
                },
                {
                    label: 'Alamat',
                    value: student.address,
                    fullWidth: true,
                },
            ],
        },
        {
            title: 'Informasi orang tua',
            description: 'Data orang tua dan kontak yang dapat dihubungi.',
            fields: [
                { label: 'Nama ayah', value: student.father_name },
                {
                    label: 'Pekerjaan ayah',
                    value: student.father_occupation,
                },
                { label: 'Nama ibu', value: student.mother_name },
                {
                    label: 'Pekerjaan ibu',
                    value: student.mother_occupation,
                },
                {
                    label: 'Nomor telepon orang tua',
                    value: student.parent_phone,
                    fullWidth: true,
                },
            ],
        },
    ];

    return (
        <div className="space-y-6">
            {sections.map((section) => (
                <section key={section.title} className="space-y-3">
                    <div className="space-y-1">
                        <h3 className="font-heading text-base font-medium">
                            {section.title}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {section.description}
                        </p>
                    </div>
                    <dl className="grid gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-2">
                        {section.fields.map((field) => (
                            <div
                                key={field.label}
                                className={
                                    field.fullWidth
                                        ? 'min-w-0 bg-background p-4 sm:col-span-2'
                                        : 'min-w-0 bg-background p-4'
                                }
                            >
                                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {field.label}
                                </dt>
                                <dd className="mt-1.5 font-medium break-words whitespace-pre-line">
                                    {displayDetailValue(field.value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ))}
        </div>
    );
}

export default function HomeroomPage({
    academicYear,
    homeroom,
    students,
    filters,
    hasTeacherProfile,
    canManageSeating,
    seating,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [viewingStudent, setViewingStudent] = useState<Student | null>(null);
    const [editingSeat, setEditingSeat] = useState<number | null>(null);
    const [seatError, setSeatError] = useState<string | null>(null);
    const [seatSaving, setSeatSaving] = useState(false);
    const seatSensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, {
            activationConstraint: { delay: 250, tolerance: 8 },
        }),
    );
    const activeAssignments: Record<number, number> = Object.fromEntries(
        (seating?.seats ?? []).map(({ seat_number, student_id }) => [
            seat_number,
            student_id,
        ]),
    );
    const seatedIds = new Set(Object.values(activeAssignments));
    const unseatedStudents = (seating?.students ?? []).filter(
        ({ id }) => !seatedIds.has(id),
    );
    const seatOptions = unseatedStudents.map((student) => ({
        value: String(student.id),
        label: student.full_name ?? `Siswa ${student.id}`,
    }));

    const saveSeatAssignments = (assignments: Record<number, number>) => {
        if (!seating || !canManageSeating || seatSaving) {
            return;
        }

        setSeatSaving(true);
        setSeatError(null);
        router.put(
            updateSeating.url(),
            {
                version: seating.version,
                seats: Array.from({ length: 40 }, (_, index) => ({
                    seat_number: index + 1,
                    student_id: assignments[index + 1] ?? null,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditingSeat(null),
                onError: (errors) => {
                    setSeatError(
                        Object.values(errors).flat()[0] ??
                            'Posisi duduk belum dapat disimpan.',
                    );
                },
                onFinish: () => setSeatSaving(false),
            },
        );
    };

    const moveStudent = (sourceSeat: number, targetSeat: number) => {
        if (!sourceSeat || !targetSeat || sourceSeat === targetSeat) {
            return;
        }

        const next = { ...activeAssignments };
        const sourceStudent = next[sourceSeat];
        const targetStudent = next[targetSeat];

        if (!sourceStudent) {
            return;
        }

        if (targetStudent) {
            next[sourceSeat] = targetStudent;
        } else {
            delete next[sourceSeat];
        }

        next[targetSeat] = sourceStudent;
        saveSeatAssignments(next);
    };
    const handleSeatDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over) {
            return;
        }

        moveStudent(Number(active.id), Number(over.id));
    };
    const emptyMessage = !hasTeacherProfile
        ? 'Akun Anda belum terhubung dengan data guru.'
        : !academicYear
          ? 'Belum ada tahun ajaran aktif.'
          : !homeroom
            ? 'Anda belum ditetapkan sebagai wali kelas pada tahun ajaran aktif.'
            : 'Belum ada siswa aktif di kelas ini.';

    const navigate = (params: Record<string, number>) =>
        router.get('/guru/wali-kelas', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    const columns = [
        {
            id: 'number',
            header: 'No.',
            headerClassName: 'w-16',
            cell: (_student: Student, index: number) =>
                (students!.current_page - 1) * students!.per_page + index + 1,
        },
        {
            id: 'name',
            header: 'Nama siswa',
            cell: (student: Student) => student.full_name ?? '—',
            cellClassName: 'font-medium',
        },
        {
            id: 'nis',
            header: 'NIS',
            cell: (student: Student) => student.nis ?? '—',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Wali Kelas" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Wali Kelas"
                    description="Lihat kelas yang Anda ampu dan daftar siswanya."
                />

                {homeroom && academicYear ? (
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Tahun Ajaran {academicYear.year}
                            </CardDescription>
                            <CardTitle className="text-xl">
                                {homeroom.name}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <span>
                                <span className="text-muted-foreground">
                                    Kode kelas
                                </span>{' '}
                                <strong>{homeroom.code}</strong>
                            </span>
                            <span>
                                <span className="text-muted-foreground">
                                    Jumlah siswa
                                </span>{' '}
                                <strong>{students?.total ?? 0}</strong>
                            </span>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="py-8 text-center text-muted-foreground">
                            {emptyMessage}
                        </CardContent>
                    </Card>
                )}

                {homeroom && academicYear && students && (
                    <Tabs defaultValue="data-siswa" className="gap-4">
                        <TabsList
                            variant="line"
                            aria-label="Informasi Wali Kelas"
                            className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"
                        >
                            <TabsTrigger value="data-siswa">
                                Data Siswa
                            </TabsTrigger>
                            <TabsTrigger value="tempat-duduk">
                                Tempat Duduk
                            </TabsTrigger>
                            <TabsTrigger value="absen-hari-ini">
                                Absen Hari Ini
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="data-siswa">
                            <section aria-labelledby="student-list-heading">
                                <div className="mb-3">
                                    <h2
                                        id="student-list-heading"
                                        className="font-heading text-base font-medium"
                                    >
                                        Daftar Siswa
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Siswa aktif yang terdaftar di{' '}
                                        {homeroom.name}.
                                    </p>
                                </div>

                                <DataTableShell
                                    data={students.data}
                                    columns={columns}
                                    getRowKey={(student) => student.id}
                                    emptyState={emptyMessage}
                                    selection={{
                                        selectedKeys: selected,
                                        onSelectionChange: setSelected,
                                        selectAllLabel: 'Pilih semua siswa',
                                        getRowLabel: (student) =>
                                            `Pilih ${student.full_name ?? 'siswa'}`,
                                        selectedCountLabel: (count, total) =>
                                            `${count} dari ${total} baris dipilih.`,
                                    }}
                                    rowActions={{
                                        label: (student) =>
                                            `Aksi ${student.full_name ?? 'siswa'}`,
                                        render: (student) => (
                                            <StudentActionItems
                                                student={student}
                                                onView={() =>
                                                    setViewingStudent(student)
                                                }
                                            />
                                        ),
                                    }}
                                    pagination={{
                                        resource: students,
                                        onPageChange: (page) => {
                                            setSelected([]);
                                            navigate({
                                                ...filters,
                                                page,
                                                per_page: students.per_page,
                                            });
                                        },
                                        onPerPageChange: (perPage) => {
                                            setSelected([]);
                                            navigate({
                                                ...filters,
                                                page: 1,
                                                per_page: perPage,
                                            });
                                        },
                                        noun: 'siswa',
                                    }}
                                />
                            </section>
                        </TabsContent>

                        <TabsContent value="tempat-duduk">
                            <section
                                className="space-y-4"
                                aria-labelledby="seating-heading"
                            >
                                <div>
                                    <h2
                                        id="seating-heading"
                                        className="font-semibold"
                                    >
                                        Denah Tempat Duduk
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        4 pasang di depan · 5 pasang ke belakang
                                        per kelompok · 40 meja siswa
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Sentuh meja untuk memilih, mengganti,
                                        atau mengosongkan siswa. Geser foto
                                        untuk memindahkan atau menukar tempat;
                                        pada layar sentuh, tahan foto sebentar
                                        lalu geser. Perubahan tersimpan
                                        otomatis.
                                    </p>
                                </div>
                                <p className="text-sm" aria-live="polite">
                                    {seatSaving
                                        ? 'Menyimpan denah…'
                                        : `${seatedIds.size} siswa sudah duduk · ${unseatedStudents.length} belum mendapat tempat`}
                                </p>
                                {seatError && (
                                    <div
                                        role="alert"
                                        className="rounded-lg border border-destructive p-3 text-sm text-destructive"
                                    >
                                        {seatError}{' '}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.reload({
                                                    only: ['seating'],
                                                })
                                            }
                                        >
                                            Muat ulang denah
                                        </Button>
                                    </div>
                                )}
                                {!canManageSeating && (
                                    <p className="text-sm text-muted-foreground">
                                        Anda hanya dapat melihat denah tempat
                                        duduk.
                                    </p>
                                )}
                                <div
                                    className="overflow-x-auto rounded-xl border bg-muted/20 p-4"
                                    tabIndex={0}
                                    role="region"
                                    aria-label="Denah 40 meja siswa; geser mendatar pada layar kecil"
                                >
                                    <div className="min-w-[860px] space-y-6">
                                        <div className="grid grid-cols-4 gap-10">
                                            <div className="col-start-4 rounded-lg border bg-muted px-6 py-4 text-center text-sm font-semibold">
                                                Meja Guru · Depan kelas
                                            </div>
                                        </div>
                                        <DndContext
                                            sensors={seatSensors}
                                            onDragEnd={handleSeatDragEnd}
                                        >
                                            <div className="grid grid-cols-4 gap-10">
                                                {Array.from(
                                                    { length: 4 },
                                                    (_, group) => (
                                                        <div
                                                            key={group}
                                                            className="grid grid-cols-2 gap-x-2 gap-y-5"
                                                            aria-label={`Kelompok meja ${group + 1}`}
                                                        >
                                                            {Array.from(
                                                                { length: 5 },
                                                                (_, depth) =>
                                                                    [0, 1].map(
                                                                        (
                                                                            side,
                                                                        ) => {
                                                                            const number =
                                                                                depth *
                                                                                    8 +
                                                                                group *
                                                                                    2 +
                                                                                side +
                                                                                1;

                                                                            return (
                                                                                <ClassroomSeat
                                                                                    key={
                                                                                        number
                                                                                    }
                                                                                    number={
                                                                                        number
                                                                                    }
                                                                                    student={seating?.students.find(
                                                                                        ({
                                                                                            id,
                                                                                        }) =>
                                                                                            id ===
                                                                                            activeAssignments[
                                                                                                number
                                                                                            ],
                                                                                    )}
                                                                                    disabled={
                                                                                        !canManageSeating ||
                                                                                        seatSaving
                                                                                    }
                                                                                    onSelect={() =>
                                                                                        setEditingSeat(
                                                                                            number,
                                                                                        )
                                                                                    }
                                                                                />
                                                                            );
                                                                        },
                                                                    ),
                                                            )}
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </DndContext>
                                    </div>
                                </div>
                            </section>
                        </TabsContent>

                        <TabsContent value="absen-hari-ini">
                            <p className="py-6 text-sm text-muted-foreground">
                                Informasi absen hari ini belum tersedia.
                            </p>
                        </TabsContent>
                    </Tabs>
                )}

                <Dialog
                    open={editingSeat !== null}
                    onOpenChange={(open) => {
                        if (!open && !seatSaving) {
                            setEditingSeat(null);
                            setSeatError(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Meja {editingSeat}</DialogTitle>
                            <DialogDescription>
                                Pilih siswa yang belum mendapat tempat duduk.
                                Untuk menukar dengan siswa yang sudah duduk,
                                gunakan pilihan pindah/tukar atau geser foto.
                            </DialogDescription>
                        </DialogHeader>
                        {editingSeat !== null && (
                            <>
                                <p className="text-sm">
                                    Penghuni:{' '}
                                    {seating?.students.find(
                                        ({ id }) =>
                                            id ===
                                            activeAssignments[editingSeat],
                                    )?.full_name ?? 'Kosong'}
                                </p>
                                <fieldset
                                    disabled={seatSaving}
                                    className="space-y-4"
                                >
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">
                                            Pilih siswa
                                        </p>
                                        <SearchableCombobox
                                            value=""
                                            options={seatOptions}
                                            placeholder="Cari dan pilih siswa…"
                                            searchPlaceholder="Cari nama siswa…"
                                            emptyMessage="Tidak ada siswa yang belum mendapat tempat."
                                            onChange={(value) =>
                                                saveSeatAssignments({
                                                    ...activeAssignments,
                                                    [editingSeat]:
                                                        Number(value),
                                                })
                                            }
                                        />
                                    </div>
                                    {activeAssignments[editingSeat] && (
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Pindah / tukar meja
                                            </p>
                                            <SearchableCombobox
                                                value=""
                                                placeholder="Pilih meja tujuan…"
                                                searchPlaceholder="Cari meja atau nama siswa…"
                                                options={Array.from(
                                                    { length: 40 },
                                                    (_, index) => index + 1,
                                                )
                                                    .filter(
                                                        (number) =>
                                                            number !==
                                                            editingSeat,
                                                    )
                                                    .map((number) => ({
                                                        value: String(number),
                                                        label: `Meja ${number} · ${seating?.students.find(({ id }) => id === activeAssignments[number])?.full_name ?? 'Kosong'}`,
                                                    }))}
                                                onChange={(value) =>
                                                    moveStudent(
                                                        editingSeat,
                                                        Number(value),
                                                    )
                                                }
                                            />
                                        </div>
                                    )}
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={
                                            !activeAssignments[editingSeat] ||
                                            seatSaving
                                        }
                                        onClick={() => {
                                            const next = {
                                                ...activeAssignments,
                                            };
                                            delete next[editingSeat];
                                            saveSeatAssignments(next);
                                        }}
                                    >
                                        Clear · Kosongkan meja
                                    </Button>
                                </fieldset>
                                {seatSaving && (
                                    <p
                                        role="status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Menyimpan…
                                    </p>
                                )}
                                {seatError && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {seatError}
                                    </p>
                                )}
                            </>
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={viewingStudent !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setViewingStudent(null);
                        }
                    }}
                >
                    <DialogContent className="max-h-[calc(100dvh-2rem)] grid-rows-[auto_minmax(0,1fr)] gap-0 overflow-hidden p-0 sm:max-w-3xl [&_[data-slot=dialog-close]]:top-4 [&_[data-slot=dialog-close]]:right-4 [&_[data-slot=dialog-close]]:min-h-11 [&_[data-slot=dialog-close]]:min-w-11">
                        <DialogHeader className="border-b px-5 py-5 pr-16 sm:px-7 sm:py-6 sm:pr-20">
                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                Profil siswa
                            </p>
                            <DialogTitle className="text-xl sm:text-2xl">
                                {viewingStudent?.full_name || 'Detail Siswa'}
                            </DialogTitle>
                            <DialogDescription>
                                {viewingStudent?.nis
                                    ? `NIS ${viewingStudent.nis} · `
                                    : ''}
                                Informasi siswa dan orang tua.
                            </DialogDescription>
                        </DialogHeader>
                        {viewingStudent && (
                            <div className="min-h-0 overflow-y-auto px-5 py-5 sm:px-7 sm:py-6">
                                <StudentDetail student={viewingStudent} />
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
