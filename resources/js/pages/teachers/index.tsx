import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronsLeft,
    ChevronsRight,
    GraduationCap,
    MoreHorizontal,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
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

function getPageNumbers(currentPage: number, pageCount: number) {
    if (pageCount <= 3) {
        return Array.from({ length: pageCount }, (_, index) => index + 1);
    }

    if (currentPage <= 2) {
        return [1, 2, 3];
    }

    if (currentPage >= pageCount - 1) {
        return [pageCount - 2, pageCount - 1, pageCount];
    }

    return [currentPage - 1, currentPage, currentPage + 1];
}

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
    const pageNumbers = getPageNumbers(
        teachers.current_page,
        teachers.last_page,
    );

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

    const remove = (teacher: Teacher) => {
        const type = teacher.staff_type === 'guru' ? 'Guru' : 'Staff';

        if (!confirm(`Hapus data ${type} "${teacher.full_name}"?`)) {
            return;
        }

        router.delete(`/teachers/${teacher.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Guru & Staff" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Master Guru & Staff"
                        description="Kelola profil Guru dan Staff serta akun yang terhubung"
                    />
                    <Button asChild>
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

                <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                    <div className="flex flex-col gap-3 border-b px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {teachers.total} baris
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
                    </div>
                    <div className="overflow-x-auto">
                        <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                teachers.data.length > 0 &&
                                                selected.length ===
                                                    teachers.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? teachers.data.map(
                                                              (teacher) =>
                                                                  teacher.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua Guru dan Staff"
                                        />
                                    </TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead>NIP</TableHead>
                                    <TableHead>NUPTK</TableHead>
                                    <TableHead>Akun</TableHead>
                                    <TableHead>Gender</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {teachers.data.map((teacher) => (
                                    <TableRow key={teacher.id}>
                                        <TableCell className="w-12">
                                            <Checkbox
                                                checked={selected.includes(
                                                    teacher.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  teacher.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      teacher.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih ${teacher.full_name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {teacher.full_name}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.staff_type === 'guru'
                                                ? 'Guru'
                                                : 'Staff'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {teacher.nip || '-'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {teacher.nuptk || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.user?.email ||
                                                'Belum terhubung'}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.gender === 'L'
                                                ? 'Laki-laki'
                                                : teacher.gender === 'P'
                                                  ? 'Perempuan'
                                                  : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.status === 'active'
                                                ? 'Aktif'
                                                : 'Tidak aktif'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        className="text-muted-foreground"
                                                        aria-label={`Aksi ${teacher.full_name}`}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={`/teachers/${teacher.id}/edit`}
                                                        >
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            remove(teacher)
                                                        }
                                                    >
                                                        Hapus
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {teachers.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={9}
                                            className="text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center gap-2 py-8">
                                                <GraduationCap className="h-8 w-8 text-muted-foreground/50" />
                                                Belum ada data Guru & Staff.
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <div className="flex flex-col gap-3 border-t px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {teachers.total} baris
                            dipilih.
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-6">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Baris per halaman
                                </span>
                                <Select
                                    value={`${teachers.per_page}`}
                                    onValueChange={(value) =>
                                        navigate({
                                            ...filterParams(),
                                            page: 1,
                                            per_page: value,
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-8 w-18">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[10, 20, 30, 40, 50].map((size) => (
                                            <SelectItem
                                                key={size}
                                                value={`${size}`}
                                            >
                                                {size}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="text-sm font-medium text-muted-foreground">
                                Halaman {teachers.current_page} dari{' '}
                                {teachers.last_page}
                            </div>
                            {teachers.last_page > 1 && (
                                <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                                    <PaginationContent className="gap-1">
                                        <PaginationItem className="hidden lg:block">
                                            <PaginationLink
                                                href="#"
                                                aria-label="Halaman pertama"
                                                aria-disabled={
                                                    teachers.current_page === 1
                                                }
                                                className={
                                                    teachers.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        teachers.current_page >
                                                        1
                                                    ) {
                                                        goToPage(1);
                                                    }
                                                }}
                                            >
                                                <ChevronsLeft />
                                            </PaginationLink>
                                        </PaginationItem>
                                        <PaginationItem>
                                            <PaginationPrevious
                                                href="#"
                                                text="Prev"
                                                aria-disabled={
                                                    teachers.current_page === 1
                                                }
                                                className={
                                                    teachers.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        teachers.current_page >
                                                        1
                                                    ) {
                                                        goToPage(
                                                            teachers.current_page -
                                                                1,
                                                        );
                                                    }
                                                }}
                                            />
                                        </PaginationItem>
                                        {pageNumbers[0] > 1 && (
                                            <PaginationItem>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        )}
                                        {pageNumbers.map((page) => (
                                            <PaginationItem key={page}>
                                                <PaginationLink
                                                    href="#"
                                                    isActive={
                                                        page ===
                                                        teachers.current_page
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        goToPage(page);
                                                    }}
                                                >
                                                    {page}
                                                </PaginationLink>
                                            </PaginationItem>
                                        ))}
                                        {pageNumbers[pageNumbers.length - 1] <
                                            teachers.last_page && (
                                            <PaginationItem>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        )}
                                        <PaginationItem>
                                            <PaginationNext
                                                href="#"
                                                text="Next"
                                                aria-disabled={
                                                    teachers.current_page ===
                                                    teachers.last_page
                                                }
                                                className={
                                                    teachers.current_page ===
                                                    teachers.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        teachers.current_page <
                                                        teachers.last_page
                                                    ) {
                                                        goToPage(
                                                            teachers.current_page +
                                                                1,
                                                        );
                                                    }
                                                }}
                                            />
                                        </PaginationItem>
                                        <PaginationItem className="hidden lg:block">
                                            <PaginationLink
                                                href="#"
                                                aria-label="Halaman terakhir"
                                                aria-disabled={
                                                    teachers.current_page ===
                                                    teachers.last_page
                                                }
                                                className={
                                                    teachers.current_page ===
                                                    teachers.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        teachers.current_page <
                                                        teachers.last_page
                                                    ) {
                                                        goToPage(
                                                            teachers.last_page,
                                                        );
                                                    }
                                                }}
                                            >
                                                <ChevronsRight />
                                            </PaginationLink>
                                        </PaginationItem>
                                    </PaginationContent>
                                </Pagination>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
