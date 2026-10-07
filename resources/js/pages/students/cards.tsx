import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronsLeft,
    ChevronsRight,
    CreditCard,
    Printer,
    Search,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
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

type Student = {
    id: number;
    nis: string | null;
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
        tahun_angkatan?: string;
    };
    filterOptions: {
        genders: FilterOption[];
        statuses: FilterOption[];
        angkatans: number[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kesiswaan', href: '/students/cards' },
    { title: 'Kartu Pelajar', href: '/students/cards' },
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

export default function StudentCards({
    students,
    filters,
    filterOptions,
}: Props) {
    const { auth } = usePage<{
        auth?: { permissions?: string[]; roles?: string[] };
    }>().props;
    const canPrint =
        auth?.roles?.includes('Super Admin') ||
        auth?.permissions?.includes('student.card.print');
    const [search, setSearch] = useState(filters.search ?? '');
    const [gender, setGender] = useState(filters.gender ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [tahunAngkatan, setTahunAngkatan] = useState(
        filters.tahun_angkatan ?? '',
    );
    const pageNumbers = getPageNumbers(
        students.current_page,
        students.last_page,
    );

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

        if (tahunAngkatan) {
            params.tahun_angkatan = tahunAngkatan;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) => {
        router.get('/students/cards', params, {
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
        setTahunAngkatan('');
        navigate({ page: 1, per_page: students.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: students.per_page });
    };
    const printQuery = new URLSearchParams(filterParams()).toString();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kartu Pelajar" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        variant="small"
                        title="Kartu Pelajar"
                        description="Pilih siswa untuk membuka dan mencetak kartu pelajar"
                    />
                    {canPrint && (
                        <Button asChild>
                            <Link
                                href={`/students/cards/print${printQuery ? `?${printQuery}` : ''}`}
                            >
                                <Printer />
                                Cetak Semua Kartu
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-5">
                            <Label htmlFor="student-card-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="student-card-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Nama / NIS / email akun..."
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
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Angkatan</Label>
                            <SearchableCombobox
                                value={tahunAngkatan}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.angkatans.map((year) => ({
                                        value: `${year}`,
                                        label: `${year}`,
                                    })),
                                ]}
                                onChange={setTahunAngkatan}
                                placeholder="Semua"
                                searchPlaceholder="Cari angkatan..."
                                emptyMessage="Angkatan tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2 md:col-span-1">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                    <div className="border-b px-4 py-4 text-sm text-muted-foreground">
                        Menampilkan {students.data.length} dari {students.total}{' '}
                        siswa.
                    </div>
                    <div className="overflow-x-auto">
                        <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead>Nama</TableHead>
                                    <TableHead>NIS</TableHead>
                                    <TableHead>Angkatan</TableHead>
                                    <TableHead>Akun</TableHead>
                                    <TableHead>Gender</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {students.data.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="font-medium">
                                            {student.full_name}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {student.nis || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {student.tahun_angkatan || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {student.user?.email ||
                                                'Belum terhubung'}
                                        </TableCell>
                                        <TableCell>
                                            {student.gender === 'L'
                                                ? 'Laki-laki'
                                                : student.gender === 'P'
                                                  ? 'Perempuan'
                                                  : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {student.status === 'active'
                                                ? 'Aktif'
                                                : 'Tidak aktif'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`/students/cards/${student.id}`}
                                                >
                                                    <CreditCard />
                                                    Kartu Pelajar
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {students.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center gap-2 py-8">
                                                <UserRound className="h-8 w-8 text-muted-foreground/50" />
                                                Belum ada data siswa untuk
                                                dicetak.
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <div className="flex flex-col gap-3 border-t px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm font-medium text-muted-foreground">
                            Halaman {students.current_page} dari{' '}
                            {students.last_page}
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-6">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Baris per halaman
                                </span>
                                <Select
                                    value={`${students.per_page}`}
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
                            {students.last_page > 1 && (
                                <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                                    <PaginationContent className="gap-1">
                                        <PaginationItem className="hidden lg:block">
                                            <PaginationLink
                                                href="#"
                                                aria-label="Halaman pertama"
                                                className={
                                                    students.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        students.current_page >
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
                                                className={
                                                    students.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        students.current_page >
                                                        1
                                                    ) {
                                                        goToPage(
                                                            students.current_page -
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
                                                        students.current_page
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
                                            students.last_page && (
                                            <PaginationItem>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        )}
                                        <PaginationItem>
                                            <PaginationNext
                                                href="#"
                                                text="Next"
                                                className={
                                                    students.current_page ===
                                                    students.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        students.current_page <
                                                        students.last_page
                                                    ) {
                                                        goToPage(
                                                            students.current_page +
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
                                                className={
                                                    students.current_page ===
                                                    students.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        students.current_page <
                                                        students.last_page
                                                    ) {
                                                        goToPage(
                                                            students.last_page,
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
