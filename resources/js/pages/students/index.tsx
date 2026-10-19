import { Head, Link, router } from '@inertiajs/react';
import { Download, Plus, Search, UserRound } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
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
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
    tahun_angkatan: number | null;
    full_name: string;
    gender: string | null;
    status: string;
    user?: { name: string; email: string } | null;
};

type FilterOption = { value: string; label: string };

type Props = {
    students: {
        data: Student[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        gender?: string;
        status?: string;
        per_page?: number;
    };
    filterOptions: { genders: FilterOption[]; statuses: FilterOption[] };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Siswa', href: '/students' }];

export default function StudentsIndex({
    students,
    filters,
    filterOptions,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [gender, setGender] = useState(filters.gender ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [importErrors, setImportErrors] = useState<string[]>([]);
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [importProcessing, setImportProcessing] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (gender) {
            params.gender = gender;
        }

        if (status) {
            params.status = status;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) => {
        router.get('/students', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        navigate({ ...filterParams(), page: 1, per_page: students.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setGender('');
        setStatus('');
        navigate({ page: 1, per_page: students.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: students.per_page });
    };

    const handleImport = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setImportFile(file);

        setImportErrors([]);
    };

    const submitImport = () => {
        if (!importFile) {
            setImportErrors(['File Excel wajib dipilih.']);

            return;
        }

        setImportErrors([]);

        setImportProcessing(true);
        router.post(
            '/students/import',
            { file: importFile },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setImportDialogOpen(false);
                    setImportFile(null);
                },
                onError: (errors) => {
                    setImportErrors(Object.values(errors));
                },
                onFinish: () => setImportProcessing(false),
            },
        );
    };

    const remove = (student: Student) => {
        if (!confirm(`Hapus data Siswa "${student.full_name}"?`)) {
            return;
        }

        router.delete(`/students/${student.id}`, { preserveScroll: true });
    };

    const columns: DataTableColumn<Student>[] = [
        {
            id: 'name',
            header: 'Nama',
            cell: (student) => student.full_name,
            cellClassName: 'font-medium',
        },
        {
            id: 'identity',
            header: 'NIS/NISN',
            cell: (student) =>
                `${student.nis?.trim() || '-'} / ${student.nisn?.trim() || '-'}`,
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'cohort',
            header: 'Tahun Angkatan',
            cell: (student) => student.tahun_angkatan || '-',
        },
        {
            id: 'account',
            header: 'Akun',
            cell: (student) => student.user?.email || 'Belum terhubung',
        },
        {
            id: 'gender',
            header: 'Gender',
            cell: (student) =>
                student.gender === 'L'
                    ? 'Laki-laki'
                    : student.gender === 'P'
                      ? 'Perempuan'
                      : '-',
        },
        {
            id: 'status',
            header: 'Status',
            cell: (student) =>
                student.status === 'active' ? 'Aktif' : 'Tidak aktif',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Siswa" />
            <div className="flex max-w-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-4 pb-24 sm:pb-6">
                <div className="flex flex-col items-stretch justify-between gap-4 sm:flex-row sm:items-center">
                    <div className="min-w-0 break-words">
                        <Heading
                            variant="small"
                            title="Master Siswa"
                            description="Kelola data profil Siswa dan akun yang terhubung"
                        />
                    </div>
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setImportDialogOpen(true)}
                            className="w-full sm:w-auto"
                        >
                            Import Excel
                        </Button>
                        <Button asChild className="w-full sm:w-auto">
                            <Link href="/students/create">
                                <Plus />
                                Tambah Siswa
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-5">
                            <Label htmlFor="student-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="student-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Nama / NIS / NISN / email akun..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Gender</Label>
                            <SearchableCombobox
                                value={gender}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.genders,
                                ]}
                                onChange={setGender}
                                placeholder="Semua"
                                searchPlaceholder="Cari gender..."
                                emptyMessage="Gender tidak ditemukan."
                            />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Status</Label>
                            <SearchableCombobox
                                value={status}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.statuses,
                                ]}
                                onChange={setStatus}
                                placeholder="Semua"
                                searchPlaceholder="Cari status..."
                                emptyMessage="Status tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2 md:col-span-3">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <DataTableShell
                    data={students.data}
                    columns={columns}
                    getRowKey={(student) => student.id}
                    emptyState={
                        <div className="flex flex-col items-center gap-2 py-8">
                            <UserRound className="h-8 w-8 text-muted-foreground/50" />
                            Belum ada data Siswa.
                        </div>
                    }
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectedCount: selected.length,
                        selectAllLabel: 'Pilih semua siswa',
                        getRowLabel: (student) => `Pilih ${student.full_name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                        onSelectAll: (checked, rows) =>
                            setSelected(
                                checked
                                    ? rows.map((student) => student.id)
                                    : [],
                            ),
                    }}
                    rowActions={{
                        label: (student) => `Aksi ${student.full_name}`,
                        render: (student) => (
                            <>
                                <DropdownMenuItem asChild>
                                    <Link href={`/students/${student.id}/edit`}>
                                        Edit
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => remove(student)}
                                >
                                    Hapus
                                </DropdownMenuItem>
                            </>
                        ),
                    }}
                    pagination={{
                        resource: students,
                        noun: 'siswa',
                        onPageChange: goToPage,
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            }),
                    }}
                    tableClassName="min-w-[760px]"
                />
                <Dialog
                    open={importDialogOpen}
                    onOpenChange={(open) => {
                        if (!importProcessing) {
                            setImportDialogOpen(open);
                        }
                    }}
                >
                    <DialogContent
                        showCloseButton={!importProcessing}
                        onEscapeKeyDown={(event) => {
                            if (importProcessing) {
                                event.preventDefault();
                            }
                        }}
                        onPointerDownOutside={(event) => {
                            if (importProcessing) {
                                event.preventDefault();
                            }
                        }}
                    >
                        <DialogHeader>
                            <DialogTitle>Import Data Siswa</DialogTitle>
                            <DialogDescription>
                                Gunakan template Excel. Data baru dimulai dari
                                baris 4.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-3">
                            <Button
                                asChild
                                variant="outline"
                                disabled={importProcessing}
                            >
                                <a href="/students/import/template" download>
                                    <Download />
                                    Download Template Excel
                                </a>
                            </Button>
                            <input
                                ref={fileInput}
                                type="file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                className="block w-full rounded-md border p-2 text-sm"
                                onChange={handleImport}
                                disabled={importProcessing}
                            />
                            {importFile && (
                                <p className="text-sm text-muted-foreground">
                                    File dipilih: {importFile.name}
                                </p>
                            )}
                            {importErrors.length > 0 && (
                                <AlertError
                                    title="Import Excel gagal"
                                    errors={importErrors}
                                />
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setImportDialogOpen(false)}
                                disabled={importProcessing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="button"
                                onClick={submitImport}
                                disabled={importProcessing || !importFile}
                            >
                                {importProcessing && (
                                    <Spinner className="mr-2" />
                                )}
                                {importProcessing
                                    ? 'Mengimpor...'
                                    : 'Import Excel'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
