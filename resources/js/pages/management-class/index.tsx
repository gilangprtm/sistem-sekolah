import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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
type ClassRow = {
    id: number;
    rombel_id: number;
    rombel: Rombel;
    student_placements_count: number;
    homeroom_assignments: { teacher?: { full_name: string } }[];
};
type Props = {
    years: Year[];
    selectedYear: Year | null;
    classes: {
        data: ClassRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { academic_year_id?: number; search?: string; per_page?: number };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manajemen Kelas', href: '/kurikulum/management-class' },
];

export default function ManagementClass({
    years,
    selectedYear,
    classes,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [yearId, setYearId] = useState(
        filters.academic_year_id?.toString() ??
            selectedYear?.id.toString() ??
            '',
    );
    const [selected, setSelected] = useState<number[]>([]);

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (yearId) {
            params.academic_year_id = yearId;
        }

        if (search) {
            params.search = search;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) =>
        router.get('/kurikulum/management-class', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const applyFilters = () =>
        navigate({ ...filterParams(), page: 1, per_page: classes.per_page });

    const resetFilters = () => {
        setSearch('');
        setYearId('');
        navigate({ page: 1, per_page: classes.per_page });
    };

    const goToPage = (page: number) =>
        navigate({ ...filterParams(), page, per_page: classes.per_page });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manajemen Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Manajemen Kelas"
                        description="Pantau kelas, jumlah siswa, dan wali kelas berdasarkan Tahun Ajaran."
                    />
                    <Button asChild>
                        <Link
                            href={`/kurikulum/management-class/manage${selectedYear ? `?academic_year_id=${selectedYear.id}` : ''}`}
                        >
                            Kelola Manajemen Kelas
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="flex flex-col items-stretch gap-3 md:flex-row md:items-end">
                        <div className="grid w-full flex-1 gap-2">
                            <Label htmlFor="management-class-search">
                                Search
                            </Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="management-class-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Kode, nama, atau jenjang..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid w-full gap-2 md:w-80">
                            <Label>Tahun Ajaran</Label>
                            <SearchableCombobox
                                value={yearId}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...years.map((year) => ({
                                        value: year.id.toString(),
                                        label: year.year,
                                    })),
                                ]}
                                onChange={setYearId}
                                placeholder="Semua"
                                searchPlaceholder="Cari Tahun Ajaran..."
                                emptyMessage="Tahun Ajaran tidak ditemukan."
                            />
                        </div>
                        <div className="flex w-full gap-2 md:w-auto">
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
                            {selected.length} dari {classes.total} baris
                            dipilih.
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
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                classes.data.length > 0 &&
                                                selected.length ===
                                                    classes.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? classes.data.map(
                                                              (item) => item.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua kelas"
                                        />
                                    </TableHead>
                                    <TableHead>Rombel</TableHead>
                                    <TableHead>Tingkat</TableHead>
                                    <TableHead>Jumlah Siswa</TableHead>
                                    <TableHead>Wali Kelas</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {classes.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
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
                                                aria-label={`Pilih kelas ${item.rombel.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {item.rombel.code}
                                        </TableCell>
                                        <TableCell>
                                            {item.rombel.grade_level}
                                        </TableCell>
                                        <TableCell>
                                            {item.student_placements_count}
                                        </TableCell>
                                        <TableCell>
                                            {item.homeroom_assignments[0]
                                                ?.teacher?.full_name ??
                                                'Belum ditetapkan'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${item.rombel.name}`}
                                            >
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={`/kurikulum/management-class/manage?academic_year_id=${selectedYear?.id ?? ''}&rombel_id=${item.rombel_id}`}
                                                    >
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {classes.data.length === 0 && (
                                    <DataTableEmptyState colSpan={6}>
                                        Belum ada data kelas.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={classes}
                        noun="kelas"
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
            </div>
        </AppLayout>
    );
}
