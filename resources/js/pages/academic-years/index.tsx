import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { destroy } from '@/routes/academic-years';
import type { BreadcrumbItem } from '@/types';

type Period = {
    id: number;
    code: string;
    name: string;
    status: string;
    start_date: string | null;
    end_date: string | null;
};

type Year = {
    id: number;
    year: string;
    status: string;
    start_date: string | null;
    end_date: string | null;
    periods: Period[];
};

type Props = {
    canDelete: boolean;
    years: {
        data: Year[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
        per_page?: number;
    };
    filterOptions: {
        statuses: { value: string; label: string }[];
    };
};

type AcademicForm =
    | { kind: 'year'; id?: number; year: string; status: string }
    | {
          kind: 'period';
          yearId: number;
          id?: number;
          code: string;
          name: string;
          status: string;
      };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tahun Ajaran & Semester', href: '/kurikulum/academic-years' },
];

export default function AcademicYearsIndex({
    canDelete,
    years,
    filters,
    filterOptions,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [form, setForm] = useState<AcademicForm | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const confirm = useConfirmation();

    const removeYear = async (item: Year) => {
        if (
            await confirm({
                title: 'Hapus Tahun Ajaran?',
                description: `Hapus Tahun Ajaran ${item.year} beserta semesternya? Tindakan ini tidak dapat dibatalkan. Tahun Ajaran aktif atau yang sudah digunakan tidak dapat dihapus.`,
            })
        ) {
            setDeleteError(null);
            setDeletingId(item.id);
            router.delete(destroy.url(item.id), {
                preserveScroll: true,
                onError: (errors) => setDeleteError(errors.year),
                onSuccess: () =>
                    setSelected((previous) =>
                        previous.filter((id) => id !== item.id),
                    ),
                onFinish: () => setDeletingId(null),
            });
        }
    };

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (status) {
            params.status = status;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) => {
        router.get('/kurikulum/academic-years', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        setSelected([]);
        navigate({ ...filterParams(), page: 1, per_page: years.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setStatus('');
        setSelected([]);
        navigate({ page: 1, per_page: years.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: years.per_page });
    };

    const closeDialog = (open: boolean) => {
        if (!open) {
            setForm(null);
            setErrors({});
        }
    };

    const submitForm = () => {
        if (!form) {
            return;
        }

        const options = {
            onError: setErrors,
            onSuccess: () => closeDialog(false),
        };

        if (form.kind === 'year') {
            const payload = { year: form.year, status: form.status };

            if (form.id) {
                router.patch(
                    `/kurikulum/academic-years/${form.id}`,
                    payload,
                    options,
                );
            } else {
                router.post('/kurikulum/academic-years', payload, options);
            }

            return;
        }

        const payload = {
            code: form.code,
            name: form.name,
            status: form.status,
        };

        if (form.id) {
            router.patch(
                `/kurikulum/academic-periods/${form.id}`,
                payload,
                options,
            );
        } else {
            router.post(
                `/kurikulum/academic-years/${form.yearId}/periods`,
                payload,
                options,
            );
        }
    };

    const openYearCreate = () => {
        setErrors({});
        setForm({ kind: 'year', year: '', status: 'inactive' });
    };

    const openYearEdit = (item: Year) => {
        setErrors({});
        setForm({
            kind: 'year',
            id: item.id,
            year: item.year,
            status: item.status,
        });
    };

    const openNewPeriod = (yearId: number, code: 'ganjil' | 'genap') => {
        setErrors({});
        setForm({
            kind: 'period',
            yearId,
            code,
            name: code === 'ganjil' ? 'Semester Ganjil' : 'Semester Genap',
            status: 'inactive',
        });
    };

    const openPeriodEdit = (item: Year, semester: Period) => {
        setErrors({});
        setForm({
            kind: 'period',
            yearId: item.id,
            id: semester.id,
            code: semester.code,
            name: semester.name,
            status: semester.status,
        });
    };

    const columns: DataTableColumn<Year>[] = [
        {
            id: 'year',
            header: 'Tahun Ajaran',
            cell: (item) => item.year,
            cellClassName: 'font-medium',
        },
        {
            id: 'status',
            header: 'Status',
            cell: (item) =>
                item.status === 'active' ? 'Aktif' : 'Tidak aktif',
        },
        {
            id: 'start-date',
            header: 'Mulai',
            cell: (item) => item.start_date ?? '-',
        },
        {
            id: 'end-date',
            header: 'Selesai',
            cell: (item) => item.end_date ?? '-',
        },
        {
            id: 'periods',
            header: 'Semester',
            cell: (item) => (
                <div className="grid min-w-72 gap-2">
                    {item.periods.map((semester) => (
                        <div
                            key={semester.id}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <span className="font-medium">{semester.name}</span>
                            <span className="text-xs text-muted-foreground">
                                {semester.status === 'active'
                                    ? 'Aktif'
                                    : 'Tidak aktif'}{' '}
                                · {semester.start_date ?? '-'} —{' '}
                                {semester.end_date ?? '-'}
                            </span>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => openPeriodEdit(item, semester)}
                            >
                                Ubah
                            </Button>
                        </div>
                    ))}
                    <div className="flex flex-wrap gap-2">
                        {!item.periods.some(
                            (semester) => semester.code === 'ganjil',
                        ) && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => openNewPeriod(item.id, 'ganjil')}
                            >
                                <Plus />
                                Tambah Ganjil
                            </Button>
                        )}
                        {!item.periods.some(
                            (semester) => semester.code === 'genap',
                        ) && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => openNewPeriod(item.id, 'genap')}
                            >
                                <Plus />
                                Tambah Genap
                            </Button>
                        )}
                    </div>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tahun Ajaran & Semester" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Tahun Ajaran & Semester"
                        description="Kelola periode akademik dan status aktifnya."
                    />
                    <Button
                        onClick={openYearCreate}
                        className="w-full md:w-auto md:shrink-0"
                    >
                        <Plus />
                        Tambah Tahun Ajaran
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-[2_1_18rem] gap-2">
                            <Label htmlFor="academic-year-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="academic-year-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Tahun ajaran / semester..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid min-w-0 flex-[1_1_12rem] gap-2">
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
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                {deleteError && (
                    <AlertError
                        title="Tahun Ajaran tidak dapat dihapus"
                        errors={[deleteError]}
                    />
                )}

                <DataTableShell
                    data={years.data}
                    columns={columns}
                    getRowKey={(item) => item.id}
                    emptyState="Tidak ada data Tahun Ajaran."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectedCount: selected.length,
                        selectAllLabel: 'Pilih semua Tahun Ajaran',
                        getRowLabel: (item) =>
                            `Pilih Tahun Ajaran ${item.year}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (item) => `Aksi Tahun Ajaran ${item.year}`,
                        render: (item) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() => openYearEdit(item)}
                                >
                                    Edit
                                </DropdownMenuItem>
                                {canDelete && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        disabled={deletingId !== null}
                                        onClick={() => void removeYear(item)}
                                    >
                                        Hapus
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: years,
                        noun: 'Tahun Ajaran',
                        onPageChange: goToPage,
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            }),
                    }}
                />

                <Dialog open={form !== null} onOpenChange={closeDialog}>
                    <ModalShell size="small" className="@container sm:max-w-sm">
                        {form && (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        {form.kind === 'year'
                                            ? form.id
                                                ? 'Edit Tahun Ajaran'
                                                : 'Tambah Tahun Ajaran'
                                            : form.id
                                              ? 'Ubah Semester'
                                              : 'Tambah Semester'}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {form.kind === 'year'
                                            ? 'Atur periode Tahun Ajaran dan status awalnya.'
                                            : 'Atur semester dan statusnya pada Tahun Ajaran terkait.'}
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid items-start gap-3 @min-[20rem]:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                                    <div className="grid gap-2">
                                        {form.kind === 'year' ? (
                                            <>
                                                <Label htmlFor="academic-year-form">
                                                    Tahun Ajaran
                                                </Label>
                                                <Input
                                                    id="academic-year-form"
                                                    placeholder="2025/2026"
                                                    value={form.year}
                                                    onChange={(event) =>
                                                        setForm({
                                                            ...form,
                                                            year: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                                <InputError
                                                    message={errors.year}
                                                />
                                            </>
                                        ) : (
                                            <>
                                                <Label htmlFor="academic-semester-form">
                                                    Semester
                                                </Label>
                                                <Select
                                                    value={form.code}
                                                    onValueChange={(
                                                        code:
                                                            'ganjil' | 'genap',
                                                    ) =>
                                                        setForm({
                                                            ...form,
                                                            code,
                                                            name:
                                                                code ===
                                                                'ganjil'
                                                                    ? 'Semester Ganjil'
                                                                    : 'Semester Genap',
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="academic-semester-form"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="ganjil">
                                                            Semester Ganjil
                                                        </SelectItem>
                                                        <SelectItem value="genap">
                                                            Semester Genap
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.code}
                                                />
                                            </>
                                        )}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="academic-status-form">
                                            Status
                                        </Label>
                                        <Select
                                            value={form.status}
                                            onValueChange={(status) =>
                                                setForm({ ...form, status })
                                            }
                                        >
                                            <SelectTrigger
                                                id="academic-status-form"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">
                                                    Aktif
                                                </SelectItem>
                                                <SelectItem value="inactive">
                                                    Tidak Aktif
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        onClick={() => closeDialog(false)}
                                    >
                                        Batal
                                    </Button>
                                    <Button onClick={submitForm}>
                                        {form.kind === 'year'
                                            ? 'Simpan Tahun Ajaran'
                                            : 'Simpan Semester'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </ModalShell>
                </Dialog>
            </div>
        </AppLayout>
    );
}
