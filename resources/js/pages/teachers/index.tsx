import { Head, Link, router } from '@inertiajs/react';
import { GraduationCap, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Teacher = {
    id: number;
    staff_type: string;
    nip: string | null;
    nuptk: string | null;
    full_name: string;
    gender: string | null;
    status: string;
    user?: { email: string } | null;
};

type FilterOption = { value: string; label: string };

type Props = {
    teachers: {
        data: Teacher[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        staff_type?: string;
        gender?: string;
        status?: string;
        per_page?: number;
    };
    filterOptions: {
        staffTypes: FilterOption[];
        genders: FilterOption[];
        statuses: FilterOption[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Guru & Staff', href: '/teachers' },
];

export default function TeachersIndex({
    teachers,
    filters,
    filterOptions,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [staffType, setStaffType] = useState(filters.staff_type ?? '');
    const [gender, setGender] = useState(filters.gender ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (staffType) {
            params.staff_type = staffType;
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
        router.get('/teachers', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        navigate({ ...filterParams(), page: 1, per_page: teachers.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setStaffType('');
        setGender('');
        setStatus('');
        navigate({ page: 1, per_page: teachers.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: teachers.per_page });
    };

    const confirm = useConfirmation();

    const remove = async (teacher: Teacher) => {
        const type = teacher.staff_type === 'guru' ? 'Guru' : 'Staff';

        if (
            !(await confirm({
                title: `Hapus ${type}?`,
                description: `Hapus data ${type} "${teacher.full_name}"?`,
            }))
        ) {
            return;
        }

        router.delete(`/teachers/${teacher.id}`, { preserveScroll: true });
    };

    const columns: DataTableColumn<Teacher>[] = [
        {
            id: 'name',
            header: 'Nama',
            cell: (teacher) => teacher.full_name,
            cellClassName: 'font-medium',
        },
        {
            id: 'type',
            header: 'Jenis',
            cell: (teacher) =>
                teacher.staff_type === 'guru' ? 'Guru' : 'Staff',
        },
        {
            id: 'nip',
            header: 'NIP',
            cell: (teacher) => teacher.nip || '-',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'nuptk',
            header: 'NUPTK',
            cell: (teacher) => teacher.nuptk || '-',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'account',
            header: 'Akun',
            cell: (teacher) => teacher.user?.email || 'Belum terhubung',
        },
        {
            id: 'gender',
            header: 'Gender',
            cell: (teacher) =>
                teacher.gender === 'L'
                    ? 'Laki-laki'
                    : teacher.gender === 'P'
                      ? 'Perempuan'
                      : '-',
        },
        {
            id: 'status',
            header: 'Status',
            cell: (teacher) =>
                teacher.status === 'active' ? 'Aktif' : 'Tidak aktif',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Guru & Staff" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Master Guru & Staff"
                        description="Kelola profil Guru dan Staff serta akun yang terhubung"
                    />
                    <Button asChild className="w-full md:w-auto md:shrink-0">
                        <Link href="/teachers/create">
                            <Plus />
                            Tambah Guru & Staff
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="teacher-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="teacher-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Nama / NIP / NUPTK / email..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Jenis</Label>
                            <SearchableCombobox
                                value={staffType}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.staffTypes,
                                ]}
                                onChange={setStaffType}
                                placeholder="Semua"
                                searchPlaceholder="Cari jenis..."
                                emptyMessage="Jenis tidak ditemukan."
                            />
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
                    data={teachers.data}
                    columns={columns}
                    getRowKey={(teacher) => teacher.id}
                    emptyState={
                        <div className="flex flex-col items-center gap-2 py-8">
                            <GraduationCap className="h-8 w-8 text-muted-foreground/50" />
                            Belum ada data Guru & Staff.
                        </div>
                    }
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectedCount: selected.length,
                        selectAllLabel: 'Pilih semua Guru dan Staff',
                        getRowLabel: (teacher) => `Pilih ${teacher.full_name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                        onSelectAll: (checked, rows) =>
                            setSelected(
                                checked
                                    ? rows.map((teacher) => teacher.id)
                                    : [],
                            ),
                    }}
                    rowActions={{
                        label: (teacher) => `Aksi ${teacher.full_name}`,
                        render: (teacher) => (
                            <>
                                <DropdownMenuItem asChild>
                                    <Link href={`/teachers/${teacher.id}/edit`}>
                                        Edit
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => remove(teacher)}
                                >
                                    Hapus
                                </DropdownMenuItem>
                            </>
                        ),
                    }}
                    pagination={{
                        resource: teachers,
                        noun: 'guru/staff',
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
            </div>
        </AppLayout>
    );
}
