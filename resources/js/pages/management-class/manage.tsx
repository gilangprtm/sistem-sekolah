import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Search,
    Trash2,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Year = { id: number; year: string; status: string };
type Rombel = {
    id: number;
    code: string;
    name: string;
    grade_level: string;
    parallel_code: string;
};
type Student = { id: number; full_name: string; nis?: string | null };
type Teacher = { id: number; full_name: string };
type ClassRow = {
    id: number;
    rombel_id: number;
    rombel: { id: number; code: string; name: string; grade_level: string };
    homeroom_assignments?: { teacher?: Teacher }[];
    homeroomAssignments?: { teacher?: Teacher }[];
};
type Props = {
    years: Year[];
    selectedYear: Year | null;
    selectedRombelId: number | null;
    selectedClass: ClassRow | null;
    selectedTeacher: Teacher | null;
    availableRombels: Rombel[];
    registeredRombelIds: number[];
    selectedStudents: Student[];
    students: Student[];
    teachers: Teacher[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manajemen Kelas', href: '/management-class' },
    { title: 'Kelola', href: '/management-class/manage' },
];

export default function ManagementClassManage({
    years,
    selectedYear,
    selectedRombelId,
    selectedClass,
    selectedTeacher,
    availableRombels,
    registeredRombelIds,
    selectedStudents,
    students,
    teachers,
}: Props) {
    const currentTeacher = selectedTeacher;
    const [rombelId, setRombelId] = useState(
        selectedRombelId?.toString() ??
            selectedClass?.rombel_id.toString() ??
            '',
    );
    const [teacherId, setTeacherId] = useState(
        currentTeacher?.id.toString() ?? '',
    );
    const [selectedStudentIds, setSelectedStudentIds] = useState<number[]>(
        selectedStudents.map((student) => student.id),
    );
    const [selectedStudentDetails, setSelectedStudentDetails] = useState<
        Record<number, Student>
    >(() =>
        Object.fromEntries(
            selectedStudents.map((student) => [student.id, student]),
        ),
    );
    const [lookupSelectedIds, setLookupSelectedIds] = useState<number[]>([]);
    const [lookupSearch, setLookupSearch] = useState('');
    const [lookupOpen, setLookupOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const selectedStudentRows = useMemo(
        () =>
            selectedStudentIds
                .map((id) => selectedStudentDetails[id])
                .filter(Boolean),
        [selectedStudentDetails, selectedStudentIds],
    );
    useEffect(() => {
        // Inertia can preserve this page instance while replacing server props.
        // Sync the editable form with the newly selected class context.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setRombelId(
            selectedRombelId?.toString() ??
                selectedClass?.rombel_id.toString() ??
                '',
        );
        setTeacherId(currentTeacher?.id.toString() ?? '');
        setSelectedStudentIds(selectedStudents.map((student) => student.id));
        setSelectedStudentDetails(
            Object.fromEntries(
                selectedStudents.map((student) => [student.id, student]),
            ),
        );
        setErrors({});
    }, [
        currentTeacher?.id,
        selectedClass?.rombel_id,
        selectedRombelId,
        selectedStudents,
    ]);

    const selectedRombelAlreadyRegistered = registeredRombelIds.includes(
        Number(rombelId),
    );
    const filteredStudents = useMemo(() => {
        const query = lookupSearch.trim().toLowerCase();

        if (!query) {
            return students;
        }

        return students.filter((student) =>
            [student.full_name, student.nis ?? ''].some((value) =>
                value.toLowerCase().includes(query),
            ),
        );
    }, [lookupSearch, students]);

    const openLookup = () => {
        setLookupSelectedIds(selectedStudentIds);
        setLookupOpen(true);
    };

    const toggleStudent = (
        student: Student,
        checked: boolean | 'indeterminate',
    ) => {
        if (checked === true) {
            setSelectedStudentDetails((current) => ({
                ...current,
                [student.id]: student,
            }));
        }

        setLookupSelectedIds((current) =>
            checked === true
                ? current.includes(student.id)
                    ? current
                    : [...current, student.id]
                : current.filter((id) => id !== student.id),
        );
    };

    const confirmStudents = () => {
        setSelectedStudentIds(lookupSelectedIds);
        setLookupOpen(false);
    };

    const removeStudent = (studentId: number) => {
        setSelectedStudentIds((current) =>
            current.filter((id) => id !== studentId),
        );
        setSelectedStudentDetails((current) => {
            const next = { ...current };
            delete next[studentId];

            return next;
        });
    };

    const submit = () => {
        if (!selectedYear || !rombelId) {
            return;
        }

        setProcessing(true);
        router.post(
            '/management-class/classes/update',
            {
                academic_year_id: selectedYear.id,
                rombel_id: Number(rombelId),
                teacher_id: Number(teacherId),
                student_ids: selectedStudentIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => setProcessing(false),
                onError: (validationErrors) => {
                    setErrors(validationErrors);
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kelola Manajemen Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-start gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link
                            href="/management-class"
                            aria-label="Kembali ke daftar manajemen kelas"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Kelola Manajemen Kelas"
                        description="Atur tahun ajaran, wali kelas, dan siswa dalam satu kelas"
                    />
                </div>

                <div className="grid gap-4 rounded-xl border p-4 md:p-6">
                    <section className="grid gap-4 border-b pb-6">
                        <div className="grid gap-2">
                            <Label>Tahun Ajaran</Label>
                            <SearchableCombobox
                                value={selectedYear?.id.toString() ?? ''}
                                options={years.map((year) => ({
                                    value: year.id.toString(),
                                    label: year.year,
                                }))}
                                onChange={(value) =>
                                    router.get(
                                        '/management-class/manage',
                                        {
                                            academic_year_id: Number(value),
                                            rombel_id: rombelId
                                                ? Number(rombelId)
                                                : undefined,
                                        },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                        },
                                    )
                                }
                                placeholder="Pilih Tahun Ajaran"
                                searchPlaceholder="Cari Tahun Ajaran..."
                                emptyMessage="Tahun Ajaran tidak ditemukan."
                            />
                            <InputError message={errors.academic_year_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Kelas</Label>
                            <SearchableCombobox
                                value={rombelId}
                                options={availableRombels.map((rombel) => ({
                                    value: rombel.id.toString(),
                                    label: registeredRombelIds.includes(
                                        rombel.id,
                                    )
                                        ? `${rombel.code} · ${rombel.name} — Sudah terdaftar`
                                        : `${rombel.code} · ${rombel.name}`,
                                }))}
                                onChange={(value) => {
                                    setRombelId(value);
                                    router.get(
                                        '/management-class/manage',
                                        {
                                            academic_year_id: selectedYear?.id,
                                            rombel_id: Number(value),
                                        },
                                        {
                                            preserveState: false,
                                            preserveScroll: true,
                                        },
                                    );
                                }}
                                placeholder="Pilih kelas dari master Rombel"
                                searchPlaceholder="Cari kode atau nama Rombel..."
                                emptyMessage="Rombel tidak ditemukan."
                            />
                            <InputError message={errors.rombel_id} />
                            {selectedRombelAlreadyRegistered && (
                                <Alert className="border-amber-500/40 text-amber-700 dark:text-amber-300">
                                    <AlertCircle className="size-4" />
                                    <AlertDescription>
                                        Kelas ini sudah terdaftar pada Tahun
                                        Ajaran yang dipilih. Data siswa dan wali
                                        kelas yang tampil adalah data kelas
                                        tersebut. Perubahan akan memperbarui
                                        data kelas ini.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label>Wali Kelas</Label>
                            <SearchableCombobox
                                value={teacherId}
                                options={teachers.map((teacher) => ({
                                    value: teacher.id.toString(),
                                    label: teacher.full_name,
                                }))}
                                onChange={setTeacherId}
                                placeholder="Pilih wali kelas"
                                searchPlaceholder="Cari nama wali kelas..."
                                emptyMessage="Guru tidak ditemukan."
                            />
                            <InputError message={errors.teacher_id} />
                        </div>
                    </section>

                    <section className="grid gap-3">
                        <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Daftar Siswa
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {selectedStudentRows.length} siswa
                                    ditambahkan.
                                </p>
                            </div>
                            <Button
                                type="button"
                                onClick={openLookup}
                                disabled={!rombelId}
                            >
                                Tambah Siswa
                            </Button>
                        </div>
                        <InputError message={errors.student_ids} />
                        <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                            <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama Siswa</TableHead>
                                        <TableHead className="text-right">
                                            Aksi
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {selectedStudentRows.map((student) => (
                                        <TableRow key={student.id}>
                                            <TableCell className="font-mono text-xs">
                                                {student.nis ?? '-'}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {student.full_name}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        removeStudent(
                                                            student.id,
                                                        )
                                                    }
                                                    aria-label={`Hapus ${student.full_name} dari kelas`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {selectedStudentRows.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                <div className="flex flex-col items-center gap-2">
                                                    <UsersRound className="h-8 w-8 text-muted-foreground/50" />
                                                    Belum ada siswa ditambahkan.
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </section>

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button asChild variant="outline">
                            <Link href="/management-class">Batal</Link>
                        </Button>
                        <Button
                            onClick={submit}
                            disabled={
                                processing || !selectedClass || !teacherId
                            }
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </div>
            </div>

            <Dialog open={lookupOpen} onOpenChange={setLookupOpen}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Tambah Siswa</DialogTitle>
                        <DialogDescription>
                            Cari dan pilih siswa, lalu klik OK untuk
                            mengonfirmasi pilihan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={lookupSearch}
                                    onChange={(event) =>
                                        setLookupSearch(event.target.value)
                                    }
                                    placeholder="Cari NIS atau nama siswa..."
                                    className="pl-8"
                                    aria-label="Cari siswa dalam lookup"
                                />
                            </div>
                        </div>
                        <div className="max-h-[55vh] overflow-auto rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            Pilih
                                        </TableHead>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama Siswa</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredStudents.map((student) => (
                                        <TableRow key={student.id}>
                                            <TableCell>
                                                <Checkbox
                                                    checked={lookupSelectedIds.includes(
                                                        student.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleStudent(
                                                            student,
                                                            checked,
                                                        )
                                                    }
                                                    aria-label={`Pilih ${student.full_name}`}
                                                />
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {student.nis ?? '-'}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {student.full_name}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {filteredStudents.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                Siswa tidak ditemukan.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setLookupOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="button" onClick={confirmStudents}>
                            OK ({lookupSelectedIds.length})
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
