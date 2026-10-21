import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, Plus, Search, UsersRound } from 'lucide-react';
import { useState } from 'react';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
type StudentLookup = {
    data: Student[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
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
    studentLookup: StudentLookup;
    lookupFilters: { search: string };
    teachers: Teacher[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manajemen Kelas', href: '/kurikulum/management-class' },
    { title: 'Kelola', href: '/kurikulum/management-class/manage' },
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
    studentLookup,
    lookupFilters,
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
    const [lookupSearch, setLookupSearch] = useState(lookupFilters.search);
    const [lookupOpen, setLookupOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [registeredRombelNoticeOpen, setRegisteredRombelNoticeOpen] =
        useState(false);

    const selectedStudentRows = selectedStudentIds
        .map((id) => selectedStudentDetails[id])
        .filter(Boolean);

    const selectedRombelAlreadyRegistered = registeredRombelIds.includes(
        Number(rombelId),
    );
    const lookupStudents = (page = 1, perPage = studentLookup.per_page) => {
        router.get(
            '/kurikulum/management-class/manage',
            {
                academic_year_id: selectedYear?.id,
                rombel_id: rombelId ? Number(rombelId) : undefined,
                lookup_search: lookupSearch.trim() || undefined,
                lookup_page: page,
                lookup_per_page: perPage,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openLookup = () => {
        setLookupSelectedIds(selectedStudentIds);
        setLookupOpen(true);
    };

    const toggleStudent = (student: Student, checked: boolean) => {
        if (checked) {
            setSelectedStudentDetails((current) => ({
                ...current,
                [student.id]: student,
            }));
        }

        setLookupSelectedIds((current) =>
            checked
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

    const selectedStudentColumns: DataTableColumn<Student>[] = [
        {
            id: 'nis',
            header: 'NIS',
            cell: (student) => student.nis ?? '-',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'name',
            header: 'Nama Siswa',
            cell: (student) => student.full_name,
            cellClassName: 'font-medium',
        },
    ];
    const lookupColumns: DataTableColumn<Student>[] = [
        {
            id: 'nis',
            header: 'NIS',
            cell: (student) => student.nis ?? '-',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'name',
            header: 'Nama Siswa',
            cell: (student) => student.full_name,
            cellClassName: 'font-medium',
        },
    ];

    const submit = () => {
        if (!selectedYear || !rombelId) {
            return;
        }

        setProcessing(true);
        router.post(
            '/kurikulum/management-class/classes/update',
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
            <SecondaryPageShell
                title="Kelola Manajemen Kelas"
                description="Atur tahun ajaran, wali kelas, dan siswa dalam satu kelas"
                backHref="/kurikulum/management-class"
                backLabel="Kembali ke daftar manajemen kelas"
                footerActions={
                    <>
                        <Button asChild variant="outline">
                            <Link href="/kurikulum/management-class">
                                Batal
                            </Link>
                        </Button>
                        <Button
                            onClick={submit}
                            disabled={
                                processing ||
                                !selectedYear ||
                                !rombelId ||
                                !teacherId
                            }
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </>
                }
            >
                <div className="grid gap-4 rounded-xl border p-4 md:p-6">
                    <section className="grid gap-4 border-b pb-6 lg:grid-cols-3 lg:items-start">
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
                                        '/kurikulum/management-class/manage',
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
                            <div className="flex items-center gap-1">
                                <SearchableCombobox
                                    className="min-w-0 flex-1"
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
                                            '/kurikulum/management-class/manage',
                                            {
                                                academic_year_id:
                                                    selectedYear?.id,
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
                                {selectedRombelAlreadyRegistered && (
                                    <Tooltip
                                        open={registeredRombelNoticeOpen}
                                        onOpenChange={
                                            setRegisteredRombelNoticeOpen
                                        }
                                    >
                                        <TooltipTrigger asChild>
                                            <button
                                                type="button"
                                                className="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-amber-500 outline-none hover:bg-amber-500/10 focus-visible:ring-2 focus-visible:ring-ring"
                                                aria-label="Informasi: kelas sudah terdaftar"
                                                onPointerDown={(event) =>
                                                    event.preventDefault()
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();
                                                    setRegisteredRombelNoticeOpen(
                                                        (open) => !open,
                                                    );
                                                }}
                                            >
                                                <AlertCircle
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent
                                            side="top"
                                            className="max-w-[min(24rem,calc(100vw-2rem))] text-wrap"
                                        >
                                            Kelas ini sudah terdaftar pada Tahun
                                            Ajaran yang dipilih. Data siswa dan
                                            wali kelas yang tampil adalah data
                                            kelas tersebut. Perubahan akan
                                            memperbarui data kelas ini.
                                        </TooltipContent>
                                    </Tooltip>
                                )}
                            </div>
                            <InputError message={errors.rombel_id} />
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
                                <Plus />
                                Tambah Siswa
                            </Button>
                        </div>
                        <InputError message={errors.student_ids} />
                        <DataTableShell
                            data={selectedStudentRows}
                            columns={selectedStudentColumns}
                            getRowKey={(student) => student.id}
                            emptyState={
                                <div className="flex flex-col items-center gap-2">
                                    <UsersRound className="h-8 w-8 text-muted-foreground/50" />
                                    Belum ada siswa ditambahkan.
                                </div>
                            }
                            rowActions={{
                                variant: 'custom',
                                render: (student) => (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() =>
                                            removeStudent(student.id)
                                        }
                                        aria-label={`Hapus ${student.full_name} dari kelas`}
                                    >
                                        Hapus
                                    </Button>
                                ),
                            }}
                        />
                    </section>
                </div>
            </SecondaryPageShell>

            <Dialog open={lookupOpen} onOpenChange={setLookupOpen}>
                <ModalShell size="medium">
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
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            lookupStudents();
                                        }
                                    }}
                                    placeholder="Cari NIS atau nama siswa..."
                                    className="pl-8"
                                    aria-label="Cari siswa dalam lookup"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => lookupStudents()}
                            >
                                Cari
                            </Button>
                        </div>
                        <DataTableShell
                            data={studentLookup.data}
                            columns={lookupColumns}
                            getRowKey={(student) => student.id}
                            emptyState="Siswa tidak ditemukan."
                            className="max-h-[55vh] overflow-auto"
                            selection={{
                                selectedKeys: lookupSelectedIds,
                                onSelectionChange: setLookupSelectedIds,
                                onRowSelectionChange: toggleStudent,
                                onSelectAll: (checked, rows) => {
                                    rows.forEach((student) => {
                                        if (checked) {
                                            setSelectedStudentDetails(
                                                (current) => ({
                                                    ...current,
                                                    [student.id]: student,
                                                }),
                                            );
                                        }
                                    });
                                    setLookupSelectedIds((current) =>
                                        checked
                                            ? Array.from(
                                                  new Set([
                                                      ...current,
                                                      ...rows.map(
                                                          (student) =>
                                                              student.id,
                                                      ),
                                                  ]),
                                              )
                                            : current.filter(
                                                  (id) =>
                                                      !rows.some(
                                                          (student) =>
                                                              student.id === id,
                                                      ),
                                              ),
                                    );
                                },
                                selectedCount: lookupSelectedIds.length,
                                selectAllLabel:
                                    'Pilih semua siswa di halaman ini',
                                getRowLabel: (student) =>
                                    `Pilih ${student.full_name}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} siswa dipilih.`,
                                clearSelectionLabel: 'Hapus semua pilihan',
                            }}
                            pagination={{
                                resource: studentLookup,
                                noun: 'siswa',
                                onPageChange: lookupStudents,
                                onPerPageChange: (perPage) =>
                                    lookupStudents(1, perPage),
                            }}
                        />
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
                </ModalShell>
            </Dialog>
        </AppLayout>
    );
}
