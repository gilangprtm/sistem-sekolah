import { Head, Link, router } from '@inertiajs/react';
import { Download, PackageSearch, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import {
    DropdownMenu,
    DropdownMenuItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatRupiah } from '@/lib/utils';
import type { Auth, BreadcrumbItem } from '@/types';

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

type Register = {
    id: number;
    register: string;
    display_code: string;
    condition: string;
    item: {
        id: number;
        kode_barang: string;
        nama_jenis_barang: string;
        merk_type: string | null;
        tahun_pembelian: number | null;
        harga: string;
    };
};

type Props = {
    auth: Auth;
    view: 'assets' | 'registers';
    items: {
        data: Item[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    registers: {
        data: Register[];
        current_page: number;
        last_page: number;
        per_page: number;
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
    { title: 'Inventaris', href: '/inventaris/inventory' },
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

export default function InventoryIndex({
    auth,
    view,
    items,
    registers,
    filters,
    filterOptions,
}: Props) {
    const confirm = useConfirmation();
    const canDelete = auth.permissions?.includes('inventory.delete') ?? false;
    const [selected, setSelected] = useState<number[]>([]);
    const [selectedRegisters, setSelectedRegisters] = useState<number[]>([]);
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
    const itemColumns: DataTableColumn<Item>[] = [
        {
            id: 'code',
            header: 'Kode Barang',
            cell: (item) => item.kode_barang,
            headerClassName: 'whitespace-nowrap',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'register',
            header: 'Register',
            cell: (item) => item.register_range,
            headerClassName: 'whitespace-nowrap',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'name',
            header: 'Nama/Jenis',
            cell: (item) => item.nama_jenis_barang,
            headerClassName: 'whitespace-nowrap',
            cellClassName: 'font-medium',
        },
        {
            id: 'brand',
            header: 'Merk/Type',
            cell: (item) => item.merk_type ?? '-',
            headerClassName: 'whitespace-nowrap',
        },
        {
            id: 'year',
            header: 'Tahun',
            cell: (item) => item.tahun_pembelian ?? '-',
        },
        {
            id: 'category',
            header: 'Kategori',
            cell: (item) => item.category?.name ?? 'Tanpa kategori',
        },
        {
            id: 'type',
            header: 'Jenis',
            cell: (item) => item.inventoryType?.name ?? '-',
        },
        {
            id: 'quantity',
            header: 'Qty',
            cell: (item) => item.qty,
            headerClassName: 'text-right',
            cellClassName: 'text-right',
        },
        {
            id: 'price',
            header: 'Harga',
            cell: (item) => formatRupiah(Number(item.harga)),
            headerClassName: 'text-right',
            cellClassName: 'text-right',
        },
        {
            id: 'total',
            header: 'Total',
            cell: (item) => formatRupiah(item.total),
            headerClassName: 'text-right',
            cellClassName: 'text-right font-medium',
        },
    ];
    const registerColumns: DataTableColumn<Register>[] = [
        {
            id: 'full-code',
            header: 'Kode Lengkap',
            cell: (register) => register.display_code,
            headerClassName: 'whitespace-nowrap',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'item-code',
            header: 'Kode Aset',
            cell: (register) => register.item.kode_barang,
            headerClassName: 'whitespace-nowrap',
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'register',
            header: 'Register',
            cell: (register) => register.register,
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'name',
            header: 'Nama Aset',
            cell: (register) => register.item.nama_jenis_barang,
            headerClassName: 'whitespace-nowrap',
            cellClassName: 'font-medium',
        },
        {
            id: 'brand',
            header: 'Merk/Type',
            cell: (register) => register.item.merk_type ?? '-',
            headerClassName: 'whitespace-nowrap',
        },
        {
            id: 'year',
            header: 'Tahun',
            cell: (register) => register.item.tahun_pembelian ?? '-',
        },
        {
            id: 'price',
            header: 'Harga per Unit',
            cell: (register) => formatRupiah(Number(register.item.harga)),
            headerClassName: 'text-right whitespace-nowrap',
            cellClassName: 'text-right',
        },
        {
            id: 'condition',
            header: 'Kondisi',
            cell: (register) => register.condition,
        },
    ];

    const remove = async (item: Item) => {
        if (
            !canDelete ||
            !(await confirm({
                title: 'Hapus inventaris?',
                description: `Hapus inventaris "${item.nama_jenis_barang}" (${item.kode_barang})? Semua unit akan ikut terhapus.`,
            }))
        ) {
            return;
        }

        router.delete(`/inventaris/inventory/${item.id}`, {
            preserveScroll: true,
            onSuccess: () =>
                setSelected((current) =>
                    current.filter((selectedId) => selectedId !== item.id),
                ),
        });
    };

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (tahun) {
            params.tahun = tahun;
        }

        if (kondisi) {
            params.kondisi = kondisi;
        }

        if (asal) {
            params.asal = asal;
        }

        if (satuan) {
            params.satuan = satuan;
        }

        if (category) {
            params.category = category;
        }

        if (inventoryType) {
            params.inventory_type = inventoryType;
        }

        return params;
    };

    const applyFilters = () => {
        setSelected([]);
        setSelectedRegisters([]);
        router.get(
            '/inventaris/inventory',
            {
                ...filterParams(),
                view,
                page: view === 'assets' ? 1 : items.current_page,
                per_page: items.per_page,
                register_page:
                    view === 'registers' ? 1 : registers.current_page,
                register_per_page: registers.per_page,
            },
            { preserveState: true, replace: true },
        );
    };

    const resetFilters = () => {
        setSearch('');
        setTahun('');
        setKondisi('');
        setAsal('');
        setSatuan('');
        setCategory('');
        setInventoryType('');
        setSelected([]);
        setSelectedRegisters([]);
        router.get(
            '/inventaris/inventory',
            {
                view,
                page: view === 'assets' ? 1 : items.current_page,
                per_page: items.per_page,
                register_page:
                    view === 'registers' ? 1 : registers.current_page,
                register_per_page: registers.per_page,
            },
            { preserveState: true, replace: true },
        );
    };

    const goToPage = (page: number) => {
        setSelected([]);
        router.get(
            '/inventaris/inventory',
            {
                ...filters,
                view: 'assets',
                page,
                per_page: items.per_page,
            },
            { preserveState: true },
        );
    };

    const changeView = (nextView: string) => {
        setSelected([]);
        setSelectedRegisters([]);
        router.get(
            '/inventaris/inventory',
            {
                ...filterParams(),
                view: nextView,
                page: items.current_page,
                per_page: items.per_page,
                register_page: registers.current_page,
                register_per_page: registers.per_page,
            },
            { preserveState: true, replace: true },
        );
    };

    const goToRegisterPage = (page: number) => {
        setSelectedRegisters([]);
        router.get(
            '/inventaris/inventory',
            {
                ...filters,
                view: 'registers',
                page: items.current_page,
                per_page: items.per_page,
                register_page: page,
                register_per_page: registers.per_page,
            },
            { preserveState: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Daftar Inventaris"
                        description="Kelola aset & inventaris sekolah"
                    />
                    <div className="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="w-full md:w-auto"
                                >
                                    <Download />
                                    Download
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <a
                                        href={`/inventaris/inventory/export/excel?${new URLSearchParams(
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
                        <Button asChild className="w-full md:w-auto">
                            <Link href="/inventaris/inventory/create">
                                <Plus />
                                Tambah Inventaris
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
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
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
                <Tabs value={view} onValueChange={changeView}>
                    <TabsList
                        variant="line"
                        className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"
                    >
                        <TabsTrigger value="assets">Aset</TabsTrigger>
                        <TabsTrigger value="registers">Register</TabsTrigger>
                    </TabsList>
                    <TabsContent value="assets">
                        <DataTableShell
                            data={items.data}
                            columns={itemColumns}
                            getRowKey={(item) => item.id}
                            emptyState={
                                <>
                                    <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                                    Tidak ada data inventaris.
                                </>
                            }
                            selection={{
                                selectedKeys: selected,
                                onSelectionChange: setSelected,
                                selectAllLabel: 'Pilih semua inventaris',
                                getRowLabel: (item) =>
                                    `Pilih ${item.kode_barang}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} baris dipilih.`,
                            }}
                            rowActions={{
                                label: (item) => `Aksi ${item.kode_barang}`,
                                render: (item) => (
                                    <>
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={`/inventaris/inventory/${item.id}`}
                                            >
                                                Lihat detail
                                            </Link>
                                        </DropdownMenuItem>
                                        {canDelete && (
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onClick={() => remove(item)}
                                            >
                                                Hapus
                                            </DropdownMenuItem>
                                        )}
                                    </>
                                ),
                            }}
                            pagination={{
                                resource: items,
                                onPageChange: goToPage,
                                onPerPageChange: (perPage) => {
                                    setSelected([]);
                                    router.get(
                                        '/inventaris/inventory',
                                        {
                                            ...filters,
                                            view: 'assets',
                                            per_page: perPage,
                                            page: 1,
                                            register_page:
                                                registers.current_page,
                                            register_per_page:
                                                registers.per_page,
                                        },
                                        { preserveState: true },
                                    );
                                },
                                noun: 'inventaris',
                            }}
                            tableClassName="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4"
                        />
                    </TabsContent>
                    <TabsContent value="registers">
                        <DataTableShell
                            data={registers.data}
                            columns={registerColumns}
                            getRowKey={(register) => register.id}
                            emptyState={
                                <>
                                    <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                                    Tidak ada data register.
                                </>
                            }
                            selection={{
                                selectedKeys: selectedRegisters,
                                onSelectionChange: setSelectedRegisters,
                                selectAllLabel: 'Pilih semua register',
                                getRowLabel: (register) =>
                                    `Pilih register ${register.display_code}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} register dipilih.`,
                            }}
                            pagination={{
                                resource: registers,
                                onPageChange: goToRegisterPage,
                                onPerPageChange: (perPage) => {
                                    setSelectedRegisters([]);
                                    router.get(
                                        '/inventaris/inventory',
                                        {
                                            ...filters,
                                            view: 'registers',
                                            page: items.current_page,
                                            per_page: items.per_page,
                                            register_per_page: perPage,
                                            register_page: 1,
                                        },
                                        { preserveState: true },
                                    );
                                },
                                noun: 'register',
                            }}
                            tableClassName="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4"
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
