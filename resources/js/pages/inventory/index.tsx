import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronsLeft,
    ChevronsRight,
    Download,
    MoreHorizontal,
    PackageSearch,
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
    DropdownMenuItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
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

type Item = {
    id: number;
    kode_barang: string;
    register_range: string;
    nama_jenis_barang: string;
    merk_type: string | null;
    tahun_pembelian: number | null;
    satuan: string | null;
    harga: string;
    keterangan: string | null;
    qty: number;
    total: number;
    category: { id: number; name: string } | null;
    inventoryType: { id: number; name: string } | null;
};

type Props = {
    items: {
        data: Item[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
        tahun?: string;
        kondisi?: string;
        asal?: string;
        satuan?: string;
        category?: string;
        inventory_type?: string;
    };
    filterOptions: {
        tahun: number[];
        asal: string[];
        satuan: string[];
        categories: { id: number; name: string }[];
        inventoryTypes: { id: number; name: string }[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
];

type FilterOption = { value: string; label: string };

type FilterComboboxProps = {
    label: string;
    value: string;
    options: FilterOption[];
    onChange: (value: string) => void;
    className?: string;
};

function FilterCombobox({
    label,
    value,
    options,
    onChange,
    className,
}: FilterComboboxProps) {
    return (
        <div className={className ?? 'grid gap-2'}>
            <Label>{label}</Label>
            <SearchableCombobox
                value={value}
                options={options}
                onChange={onChange}
                placeholder="Semua"
                searchPlaceholder={`Cari ${label.toLowerCase()}...`}
                emptyMessage={`${label} tidak ditemukan.`}
            />
        </div>
    );
}

function getPageNumbers(currentPage: number, pageCount: number) {
    if (pageCount <= 3) {
        return Array.from({ length: pageCount }, (_, i) => i + 1);
    }

    if (currentPage <= 2) {
        return [1, 2, 3];
    }

    if (currentPage >= pageCount - 1) {
        return [pageCount - 2, pageCount - 1, pageCount];
    }

    return [currentPage - 1, currentPage, currentPage + 1];
}

export default function InventoryIndex({
    items,
    filters,
    filterOptions,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [tahun, setTahun] = useState(filters.tahun ?? '');
    const [kondisi, setKondisi] = useState(filters.kondisi ?? '');
    const [asal, setAsal] = useState(filters.asal ?? '');
    const [satuan, setSatuan] = useState(filters.satuan ?? '');
    const [category, setCategory] = useState(filters.category ?? '');
    const [inventoryType, setInventoryType] = useState(
        filters.inventory_type ?? '',
    );

    const categories = filterOptions.categories;

    const applyFilters = (overrides: Record<string, string> = {}) => {
        const params: Record<string, string> = {};
        const searchVal = overrides.search ?? search;
        const tahunVal = overrides.tahun ?? tahun;
        const kondisiVal = overrides.kondisi ?? kondisi;
        const asalVal = overrides.asal ?? asal;
        const satuanVal = overrides.satuan ?? satuan;
        const categoryVal = overrides.category ?? category;
        const inventoryTypeVal = overrides.inventory_type ?? inventoryType;

        if (searchVal) {
            params.search = searchVal;
        }

        if (tahunVal) {
            params.tahun = tahunVal;
        }

        if (kondisiVal) {
            params.kondisi = kondisiVal;
        }

        if (asalVal) {
            params.asal = asalVal;
        }

        if (satuanVal) {
            params.satuan = satuanVal;
        }

        if (categoryVal) {
            params.category = categoryVal;
        }

        if (inventoryTypeVal) {
            params.inventory_type = inventoryTypeVal;
        }

        router.get('/inventory', params, {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setTahun('');
        setKondisi('');
        setAsal('');
        setSatuan('');
        setCategory('');
        setInventoryType('');
        router.get('/inventory', {}, { preserveState: true, replace: true });
    };

    const formatRupiah = (value: number) =>
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Daftar Inventaris"
                        description="Kelola aset & inventaris sekolah"
                    />
                    <div className="flex items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <Download />
                                    Download
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <a
                                        href={`/inventory/export/excel?${new URLSearchParams(
                                            Object.entries({
                                                ...filters,
                                                per_page: undefined,
                                            }).filter(
                                                ([, value]) =>
                                                    value !== undefined &&
                                                    value !== '',
                                            ) as [string, string][],
                                        ).toString()}`}
                                    >
                                        Excel (.xls)
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuItem disabled>
                                    PDF (segera hadir)
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Button asChild>
                            <Link href="/inventory/create">
                                <Plus />
                                Tambah Inventaris
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-12">
                            <Label>Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) =>
                                        e.key === 'Enter' && applyFilters()
                                    }
                                    placeholder="Kode / nama / merk / register..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <FilterCombobox
                            className="grid gap-2 md:col-span-2"
                            label="Tahun"
                            value={tahun}
                            options={[
                                { value: '', label: 'Semua' },
                                ...filterOptions.tahun.map((year) => ({
                                    value: year.toString(),
                                    label: year.toString(),
                                })),
                            ]}
                            onChange={setTahun}
                        />
                        <FilterCombobox
                            className="grid gap-2 md:col-span-2"
                            label="Kondisi"
                            value={kondisi}
                            options={[
                                { value: '', label: 'Semua' },
                                { value: 'B', label: 'Baik' },
                                { value: 'KB', label: 'Kurang Baik' },
                                { value: 'RB', label: 'Rusak Berat' },
                            ]}
                            onChange={setKondisi}
                        />
                        <FilterCombobox
                            className="grid gap-2 md:col-span-2"
                            label="Asal"
                            value={asal}
                            options={[
                                { value: '', label: 'Semua' },
                                ...filterOptions.asal.map((origin) => ({
                                    value: origin,
                                    label: origin,
                                })),
                            ]}
                            onChange={setAsal}
                        />
                        <FilterCombobox
                            className="grid gap-2 md:col-span-2"
                            label="Satuan"
                            value={satuan}
                            options={[
                                { value: '', label: 'Semua' },
                                ...filterOptions.satuan.map((unit) => ({
                                    value: unit,
                                    label: unit,
                                })),
                            ]}
                            onChange={setSatuan}
                        />
                        <FilterCombobox
                            className="grid gap-2 md:col-span-2"
                            label="Kategori"
                            value={category}
                            options={[
                                { value: '', label: 'Semua' },
                                ...categories.map((item) => ({
                                    value: item.id.toString(),
                                    label: item.name,
                                })),
                            ]}
                            onChange={setCategory}
                        />
                        <FilterCombobox
                            className="grid gap-2 md:col-span-2"
                            label="Jenis Inventaris"
                            value={inventoryType}
                            options={[
                                { value: '', label: 'Semua' },
                                ...filterOptions.inventoryTypes.map((item) => ({
                                    value: item.id.toString(),
                                    label: item.name,
                                })),
                            ]}
                            onChange={setInventoryType}
                        />
                        <div className="flex gap-2">
                            <Button onClick={() => applyFilters()}>
                                Filter
                            </Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                    <div className="flex flex-col gap-3 border-b px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {items.total} baris dipilih.
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
                                                items.data.length > 0 &&
                                                selected.length ===
                                                    items.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? items.data.map(
                                                              (item) => item.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua inventaris"
                                        />
                                    </TableHead>
                                    <TableHead>Kode Barang</TableHead>
                                    <TableHead>Register</TableHead>
                                    <TableHead>Nama/Jenis</TableHead>
                                    <TableHead>Merk/Type</TableHead>
                                    <TableHead>Tahun</TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Jenis</TableHead>
                                    <TableHead className="text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Harga
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.data.map((item) => (
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
                                                aria-label={`Pilih ${item.kode_barang}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {item.kode_barang}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {item.register_range}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {item.nama_jenis_barang}
                                        </TableCell>
                                        <TableCell>
                                            {item.merk_type ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.tahun_pembelian ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {item.category?.name ??
                                                'Tanpa kategori'}
                                        </TableCell>
                                        <TableCell>
                                            {item.inventoryType?.name ?? '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {item.qty}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatRupiah(Number(item.harga))}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {formatRupiah(item.total)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        className="text-muted-foreground"
                                                        aria-label={`Aksi ${item.kode_barang}`}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={`/inventory/${item.id}`}
                                                        >
                                                            Lihat detail
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {items.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={12}
                                            className="text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center gap-2 py-8">
                                                <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                                                Tidak ada data inventaris.
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <div className="flex flex-col gap-3 border-t px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {items.total} baris dipilih.
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-6">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Baris per halaman
                                </span>
                                <Select
                                    value="10"
                                    onValueChange={(value) =>
                                        router.get(
                                            '/inventory',
                                            {
                                                ...filters,
                                                per_page: value,
                                                page: 1,
                                            },
                                            { preserveState: true },
                                        )
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
                                Halaman {items.current_page} dari{' '}
                                {items.last_page}
                            </div>
                            {items.last_page > 1 && (
                                <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                                    <PaginationContent className="gap-1">
                                        <PaginationItem className="hidden lg:block">
                                            <PaginationLink
                                                href="#"
                                                aria-label="Halaman pertama"
                                                className={
                                                    items.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : ''
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        items.current_page > 1
                                                    ) {
                                                        router.get(
                                                            '/inventory',
                                                            {
                                                                ...filters,
                                                                page: 1,
                                                            },
                                                            {
                                                                preserveState: true,
                                                            },
                                                        );
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
                                                    items.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : ''
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        items.current_page > 1
                                                    ) {
                                                        router.get(
                                                            '/inventory',
                                                            {
                                                                ...filters,
                                                                page:
                                                                    items.current_page -
                                                                    1,
                                                            },
                                                            {
                                                                preserveState: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            />
                                        </PaginationItem>
                                        {getPageNumbers(
                                            items.current_page,
                                            items.last_page,
                                        ).map((page) => (
                                            <PaginationItem key={page}>
                                                <PaginationLink
                                                    href="#"
                                                    isActive={
                                                        page ===
                                                        items.current_page
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        router.get(
                                                            '/inventory',
                                                            {
                                                                ...filters,
                                                                page,
                                                            },
                                                            {
                                                                preserveState: true,
                                                            },
                                                        );
                                                    }}
                                                >
                                                    {page}
                                                </PaginationLink>
                                            </PaginationItem>
                                        ))}
                                        <PaginationItem>
                                            <PaginationNext
                                                href="#"
                                                className={
                                                    items.current_page ===
                                                    items.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : ''
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        items.current_page <
                                                        items.last_page
                                                    ) {
                                                        router.get(
                                                            '/inventory',
                                                            {
                                                                ...filters,
                                                                page:
                                                                    items.current_page +
                                                                    1,
                                                            },
                                                            {
                                                                preserveState: true,
                                                            },
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
                                                    items.current_page ===
                                                    items.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : ''
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        items.current_page <
                                                        items.last_page
                                                    ) {
                                                        router.get(
                                                            '/inventory',
                                                            {
                                                                ...filters,
                                                                page: items.last_page,
                                                            },
                                                            {
                                                                preserveState: true,
                                                            },
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
