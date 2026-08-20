import { Head, Link, router } from '@inertiajs/react';
import {
    PackageSearch,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
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
    nama_jenis_barang: string;
    merk_type: string | null;
    tahun_pembelian: number | null;
    satuan: string | null;
    harga: string;
    keterangan: string | null;
    qty: number;
    total: number;
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
    };
    filterOptions: {
        tahun: number[];
        asal: string[];
        satuan: string[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
];

export default function InventoryIndex({ items, filters, filterOptions }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [tahun, setTahun] = useState(filters.tahun ?? '');
    const [kondisi, setKondisi] = useState(filters.kondisi ?? '');
    const [asal, setAsal] = useState(filters.asal ?? '');
    const [satuan, setSatuan] = useState(filters.satuan ?? '');

    const applyFilters = (overrides: Record<string, string> = {}) => {
        const params: Record<string, string> = {};
        const searchVal = overrides.search ?? search;
        const tahunVal = overrides.tahun ?? tahun;
        const kondisiVal = overrides.kondisi ?? kondisi;
        const asalVal = overrides.asal ?? asal;
        const satuanVal = overrides.satuan ?? satuan;

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

        router.get('/inventory', params, { preserveState: true, replace: true });
    };

    const resetFilters = () => {
        setSearch('');
        setTahun('');
        setKondisi('');
        setAsal('');
        setSatuan('');
        router.get('/inventory', {}, { preserveState: true, replace: true });
    };

    const formatRupiah = (value: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

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
                    <Button asChild>
                        <Link href="/inventory/create">
                            <Plus />
                            Tambah Inventaris
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-2">
                            <Label>Search</Label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    placeholder="Kode / nama / merk / register..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>Tahun</Label>
                            <NativeSelect value={tahun} onChange={(e) => setTahun(e.target.value)}>
                                <option value="">Semua</option>
                                {filterOptions.tahun.map((t) => (
                                    <option key={t} value={t}>{t}</option>
                                ))}
                            </NativeSelect>
                        </div>
                        <div className="grid gap-2">
                            <Label>Kondisi</Label>
                            <NativeSelect value={kondisi} onChange={(e) => setKondisi(e.target.value)}>
                                <option value="">Semua</option>
                                <option value="B">Baik</option>
                                <option value="KB">Kurang Baik</option>
                                <option value="RB">Rusak Berat</option>
                            </NativeSelect>
                        </div>
                        <div className="grid gap-2">
                            <Label>Asal</Label>
                            <NativeSelect value={asal} onChange={(e) => setAsal(e.target.value)}>
                                <option value="">Semua</option>
                                {filterOptions.asal.map((a) => (
                                    <option key={a} value={a}>{a}</option>
                                ))}
                            </NativeSelect>
                        </div>
                        <div className="grid gap-2">
                            <Label>Satuan</Label>
                            <NativeSelect value={satuan} onChange={(e) => setSatuan(e.target.value)}>
                                <option value="">Semua</option>
                                {filterOptions.satuan.map((s) => (
                                    <option key={s} value={s}>{s}</option>
                                ))}
                            </NativeSelect>
                        </div>
                        <div className="flex gap-2">
                            <Button onClick={() => applyFilters()}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>Reset</Button>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kode Barang</TableHead>
                                <TableHead>Nama/Jenis</TableHead>
                                <TableHead>Merk/Type</TableHead>
                                <TableHead>Tahun</TableHead>
                                <TableHead className="text-right">Qty</TableHead>
                                <TableHead className="text-right">Harga</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-mono text-xs">{item.kode_barang}</TableCell>
                                    <TableCell className="font-medium">{item.nama_jenis_barang}</TableCell>
                                    <TableCell>{item.merk_type ?? '-'}</TableCell>
                                    <TableCell>{item.tahun_pembelian ?? '-'}</TableCell>
                                    <TableCell className="text-right">{item.qty}</TableCell>
                                    <TableCell className="text-right">{formatRupiah(Number(item.harga))}</TableCell>
                                    <TableCell className="text-right font-medium">{formatRupiah(item.total)}</TableCell>
                                    <TableCell className="text-right">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={`/inventory/${item.id}`}>Detail</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {items.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={8} className="text-center text-muted-foreground">
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

                {items.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>
                            Halaman {items.current_page} dari {items.last_page} ({items.total} item)
                        </span>
                        <div className="flex gap-2">
                            {Array.from({ length: items.last_page }, (_, i) => i + 1).map((page) => (
                                <Button
                                    key={page}
                                    variant={page === items.current_page ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => router.get('/inventory', { ...filters, page }, { preserveState: true })}
                                >
                                    {page}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
