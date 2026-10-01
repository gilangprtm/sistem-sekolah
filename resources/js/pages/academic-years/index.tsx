import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
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
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import AppLayout from '@/layouts/app-layout';
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

type PeriodForm = {
    yearId: number;
    code: string;
    name: string;
    status: string;
    id?: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tahun Ajaran & Semester', href: '/academic-years' },
];

export default function AcademicYearsIndex({
    years,
    filters,
    filterOptions,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [year, setYear] = useState('');
    const [newYearStatus, setNewYearStatus] = useState('inactive');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [period, setPeriod] = useState<PeriodForm | null>(null);
    const [editingYear, setEditingYear] = useState<Year | null>(null);
    const [yearDialogOpen, setYearDialogOpen] = useState(false);
    const [semesterDialogOpen, setSemesterDialogOpen] = useState(false);

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
        router.get('/academic-years', params, {
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

    const submitYear = () =>
        router.post(
            '/academic-years',
            { year, status: newYearStatus },
            {
                onError: setErrors,
                onSuccess: () => {
                    setYear('');
                    setErrors({});
                    setYearDialogOpen(false);
                },
            },
        );

    const submitYearUpdate = () => {
        if (!editingYear) {
            return;
        }

        router.patch(
            `/academic-years/${editingYear.id}`,
            {
                year: editingYear.year,
                status: editingYear.status,
            },
            {
                onError: setErrors,
                onSuccess: () => {
                    setEditingYear(null);
                    setErrors({});
                    setYearDialogOpen(false);
                },
            },
        );
    };

    const submitPeriod = () => {
        if (!period) {
            return;
        }

        const payload = {
            code: period.code,
            name: period.name,
            status: period.status,
        };

        if (period.id) {
            router.patch(`/academic-periods/${period.id}`, payload, {
                onError: setErrors,
                onSuccess: () => {
                    setPeriod(null);
                    setErrors({});
                    setSemesterDialogOpen(false);
                },
            });

            return;
        }

        router.post(`/academic-years/${period.yearId}/periods`, payload, {
            onError: setErrors,
            onSuccess: () => {
                setPeriod(null);
                setErrors({});
                setSemesterDialogOpen(false);
            },
        });
    };

    const openYearCreate = () => {
        setErrors({});
        setEditingYear(null);
        setYear('');
        setNewYearStatus('inactive');
        setYearDialogOpen(true);
    };

    const openYearEdit = (item: Year) => {
        setErrors({});
        setEditingYear(item);
        setYearDialogOpen(true);
    };

    const openNewPeriod = (yearId: number, code: 'ganjil' | 'genap') => {
        setErrors({});
        setPeriod({
            yearId,
            code,
            name: code === 'ganjil' ? 'Semester Ganjil' : 'Semester Genap',
            status: 'inactive',
        });
        setSemesterDialogOpen(true);
    };

    const openPeriodEdit = (item: Year, semester: Period) => {
        setErrors({});
        setPeriod({
            yearId: item.id,
            id: semester.id,
            code: semester.code,
            name: semester.name,
            status: semester.status,
        });
        setSemesterDialogOpen(true);
    };

    const closeYearDialog = (open: boolean) => {
        setYearDialogOpen(open);

        if (!open) {
            setEditingYear(null);
            setErrors({});
        }
    };

    const closeSemesterDialog = (open: boolean) => {
        setSemesterDialogOpen(open);

        if (!open) {
            setPeriod(null);
            setErrors({});
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tahun Ajaran & Semester" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Tahun Ajaran & Semester"
                        description="Kelola periode akademik dan status aktifnya."
                    />
                    <Button onClick={openYearCreate}>
                        Tambah Tahun Ajaran
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-5">
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
                        <div className="grid gap-2 md:col-span-3">
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
                        <div className="flex gap-2 md:col-span-4">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <DataTableShell>
                    <DataTableToolbar>
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {years.total} baris dipilih.
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSelected([])}
                            disabled={!selected.length}
                        >
                            Hapus pilihan
                        </Button>
                    </DataTableToolbar>
                    <div className="overflow-x-auto">
                        <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                years.data.length > 0 &&
                                                selected.length ===
                                                    years.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? years.data.map(
                                                              (item) => item.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua Tahun Ajaran"
                                        />
                                    </TableHead>
                                    <TableHead>Tahun Ajaran</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Mulai</TableHead>
                                    <TableHead>Selesai</TableHead>
                                    <TableHead>Semester</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {years.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="w-12">
                                            <Checkbox
                                                checked={selected.includes(
                                                    item.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  item.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      item.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih Tahun Ajaran ${item.year}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {item.year}
                                        </TableCell>
                                        <TableCell>
                                            {item.status === 'active'
                                                ? 'Aktif'
                                                : 'Tidak aktif'}
                                        </TableCell>
                                        <TableCell>
                                            {item.start_date ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.end_date ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="grid min-w-72 gap-2">
                                                {item.periods.map(
                                                    (semester) => (
                                                        <div
                                                            key={semester.id}
                                                            className="flex flex-wrap items-center gap-2"
                                                        >
                                                            <span className="font-medium">
                                                                {semester.name}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {semester.status ===
                                                                'active'
                                                                    ? 'Aktif'
                                                                    : 'Tidak aktif'}{' '}
                                                                ·{' '}
                                                                {semester.start_date ??
                                                                    '-'}{' '}
                                                                —{' '}
                                                                {semester.end_date ??
                                                                    '-'}
                                                            </span>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    openPeriodEdit(
                                                                        item,
                                                                        semester,
                                                                    )
                                                                }
                                                            >
                                                                Ubah
                                                            </Button>
                                                        </div>
                                                    ),
                                                )}
                                                <div className="flex flex-wrap gap-2">
                                                    {!item.periods.some(
                                                        (semester) =>
                                                            semester.code ===
                                                            'ganjil',
                                                    ) && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openNewPeriod(
                                                                    item.id,
                                                                    'ganjil',
                                                                )
                                                            }
                                                        >
                                                            Tambah Ganjil
                                                        </Button>
                                                    )}
                                                    {!item.periods.some(
                                                        (semester) =>
                                                            semester.code ===
                                                            'genap',
                                                    ) && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openNewPeriod(
                                                                    item.id,
                                                                    'genap',
                                                                )
                                                            }
                                                        >
                                                            Tambah Genap
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi Tahun Ajaran ${item.year}`}
                                            >
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        openYearEdit(item)
                                                    }
                                                >
                                                    Ubah Tahun Ajaran
                                                </DropdownMenuItem>
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {years.data.length === 0 && (
                                    <DataTableEmptyState colSpan={7}>
                                        Tidak ada data Tahun Ajaran.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={years}
                        noun="Tahun Ajaran"
                        onPageChange={goToPage}
                        onPerPageChange={(perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            })
                        }
                    />
                </DataTableShell>

                <Dialog open={yearDialogOpen} onOpenChange={closeYearDialog}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {editingYear
                                    ? 'Ubah Tahun Ajaran'
                                    : 'Tambah Tahun Ajaran'}
                            </DialogTitle>
                            <DialogDescription>
                                Atur periode Tahun Ajaran dan status awalnya.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
                            <div className="grid gap-2">
                                <Label htmlFor="academic-year-form">
                                    Tahun Ajaran
                                </Label>
                                <Input
                                    id="academic-year-form"
                                    placeholder="2025/2026"
                                    value={editingYear?.year ?? year}
                                    onChange={(event) => {
                                        if (editingYear) {
                                            setEditingYear({
                                                ...editingYear,
                                                year: event.target.value,
                                            });
                                        } else {
                                            setYear(event.target.value);
                                        }
                                    }}
                                />
                                <InputError message={errors.year} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Status</Label>
                                <Select
                                    value={editingYear?.status ?? newYearStatus}
                                    onValueChange={(value) => {
                                        if (editingYear) {
                                            setEditingYear({
                                                ...editingYear,
                                                status: value,
                                            });
                                        } else {
                                            setNewYearStatus(value);
                                        }
                                    }}
                                >
                                    <SelectTrigger>
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
                                onClick={() => closeYearDialog(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                onClick={
                                    editingYear ? submitYearUpdate : submitYear
                                }
                            >
                                Simpan Tahun Ajaran
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={semesterDialogOpen}
                    onOpenChange={closeSemesterDialog}
                >
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {period?.id
                                    ? 'Ubah Semester'
                                    : 'Tambah Semester'}
                            </DialogTitle>
                            <DialogDescription>
                                Atur semester dan statusnya pada Tahun Ajaran
                                terkait.
                            </DialogDescription>
                        </DialogHeader>
                        {period && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Semester</Label>
                                    <Select
                                        value={period.code}
                                        onValueChange={(
                                            code: 'ganjil' | 'genap',
                                        ) =>
                                            setPeriod({
                                                ...period,
                                                code,
                                                name:
                                                    code === 'ganjil'
                                                        ? 'Semester Ganjil'
                                                        : 'Semester Genap',
                                            })
                                        }
                                    >
                                        <SelectTrigger>
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
                                    <InputError message={errors.code} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Status</Label>
                                    <Select
                                        value={period.status}
                                        onValueChange={(value) =>
                                            setPeriod({
                                                ...period,
                                                status: value,
                                            })
                                        }
                                    >
                                        <SelectTrigger>
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
                        )}
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => closeSemesterDialog(false)}
                            >
                                Batal
                            </Button>
                            <Button onClick={submitPeriod}>
                                Simpan Semester
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
