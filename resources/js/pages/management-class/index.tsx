import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

    const columns: DataTableColumn<ClassRow>[] = [
        {
            id: 'rombel',
            header: 'Rombel',
            cell: (item) => item.rombel.code,
            cellClassName: 'font-medium',
        },
        {
            id: 'grade',
            header: 'Tingkat',
            cell: (item) => item.rombel.grade_level,
        },
        {
            id: 'students',
            header: 'Jumlah Siswa',
            cell: (item) => item.student_placements_count,
        },
        {
            id: 'homeroom',
            header: 'Wali Kelas',
            cell: (item) =>
                item.homeroom_assignments[0]?.teacher?.full_name ??
                'Belum ditetapkan',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manajemen Kelas" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Manajemen Kelas"
                        description="Pantau kelas, jumlah siswa, dan wali kelas berdasarkan Tahun Ajaran."
                    />
                    <Button asChild className="w-full md:w-auto md:shrink-0">
                        <Link
                            href={`/kurikulum/management-class/manage${selectedYear ? `?academic_year_id=${selectedYear.id}` : ''}`}
                        >
                            Kelola Manajemen Kelas
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-[2_1_18rem] gap-2">
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
                        <div className="grid min-w-0 flex-[1_1_14rem] gap-2">
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
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <DataTableShell
                    data={classes.data}
                    columns={columns}
                    getRowKey={(item) => item.id}
                    emptyState="Belum ada data kelas."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua kelas',
                        getRowLabel: (item) =>
                            `Pilih kelas ${item.rombel.name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (item) => `Aksi ${item.rombel.name}`,
                        render: (item) => (
                            <DropdownMenuItem asChild>
                                <Link
                                    href={`/kurikulum/management-class/manage?academic_year_id=${selectedYear?.id ?? ''}&rombel_id=${item.rombel_id}`}
                                >
                                    Edit
                                </Link>
                            </DropdownMenuItem>
                        ),
                    }}
                    pagination={{
                        resource: classes,
                        noun: 'kelas',
                        onPageChange: goToPage,
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            }),
                    }}
                />
            </div>
        </AppLayout>
    );
}
