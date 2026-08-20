import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Unit = {
    id: number;
    register: string;
    condition: string;
};

type Item = {
    id: number;
    kode_barang: string;
    nama_jenis_barang: string;
    merk_type: string | null;
    no_identitas: string | null;
    bahan: string | null;
    asal_perolehan: string | null;
    tahun_pembelian: number | null;
    ukuran_konstruksi: string | null;
    satuan: string | null;
    harga: string;
    keterangan: string | null;
    qty: number;
    total: number;
    units: Unit[];
};

type Props = {
    item: Item;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
];

const conditionLabel: Record<string, string> = {
    B: 'Baik',
    KB: 'Kurang Baik',
    RB: 'Rusak Berat',
};

export default function InventoryShow({ item }: Props) {
    const [keterangan, setKeterangan] = useState(item.keterangan ?? '');
    const [keteranganErrors, setKeteranganErrors] = useState<Record<string, string>>({});
    const [addQty, setAddQty] = useState('1');
    const [addQtyErrors, setAddQtyErrors] = useState<Record<string, string>>({});

    const saveKeterangan = () => {
        router.patch(`/inventory/${item.id}`, { keterangan }, {
            preserveScroll: true,
            onError: (errs) => setKeteranganErrors(errs),
        });
    };

    const addUnits = () => {
        router.post(`/inventory/${item.id}/units`, { qty: addQty }, {
            preserveScroll: true,
            onSuccess: () => {
                setAddQty('1');
                setAddQtyErrors({});
            },
            onError: (errs) => setAddQtyErrors(errs),
        });
    };

    const updateCondition = (unit: Unit, condition: string) => {
        router.patch(`/inventory/${item.id}/units/${unit.id}`, { condition }, {
            preserveScroll: true,
        });
    };

    const remove = () => {
        if (!confirm(`Hapus inventaris "${item.nama_jenis_barang}" (${item.kode_barang})? Semua unit akan ikut terhapus.`)) {
            return;
        }

        router.delete(`/inventory/${item.id}`);
    };

    const formatRupiah = (value: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

    return (
        <AppLayout breadcrumbs={[...breadcrumbs, { title: item.kode_barang, href: `/inventory/${item.id}` }]}>
            <Head title={`${item.kode_barang} — ${item.nama_jenis_barang}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="outline" size="icon">
                            <Link href="/inventory">
                                <ArrowLeft />
                            </Link>
                        </Button>
                        <Heading
                            variant="small"
                            title={`${item.kode_barang} — ${item.nama_jenis_barang}`}
                            description="Detail kelompok barang & unit"
                        />
                    </div>
                    <Button variant="destructive" onClick={remove}>
                        <Trash2 />
                        Hapus
                    </Button>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-xl border p-4 lg:col-span-1">
                        <h3 className="mb-3 text-sm font-semibold">Data Barang (immutable)</h3>
                        <dl className="grid gap-2 text-sm">
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Kode Barang</dt>
                                <dd className="font-mono">{item.kode_barang}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Nama/Jenis</dt>
                                <dd>{item.nama_jenis_barang}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Merk/Type</dt>
                                <dd>{item.merk_type ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">No Identitas</dt>
                                <dd>{item.no_identitas ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Bahan</dt>
                                <dd>{item.bahan ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Asal</dt>
                                <dd>{item.asal_perolehan ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Tahun</dt>
                                <dd>{item.tahun_pembelian ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Ukuran</dt>
                                <dd>{item.ukuran_konstruksi ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Satuan</dt>
                                <dd>{item.satuan ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Harga/unit</dt>
                                <dd>{formatRupiah(Number(item.harga))}</dd>
                            </div>
                            <div className="flex justify-between gap-2 border-t pt-2">
                                <dt className="font-medium">Qty</dt>
                                <dd className="font-medium">{item.qty}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="font-medium">Total</dt>
                                <dd className="font-medium">{formatRupiah(item.total)}</dd>
                            </div>
                        </dl>

                        <div className="mt-4 grid gap-2 border-t pt-4">
                            <Label htmlFor="keterangan">Keterangan</Label>
                            <Textarea
                                id="keterangan"
                                value={keterangan}
                                onChange={(e) => setKeterangan(e.target.value)}
                            />
                            <InputError message={keteranganErrors.keterangan} />
                            <Button variant="outline" size="sm" onClick={saveKeterangan}>
                                <Pencil />
                                Simpan Keterangan
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4 lg:col-span-2">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-semibold">Unit / Register</h3>
                            <div className="flex items-center gap-2">
                                <Input
                                    type="number"
                                    min={1}
                                    value={addQty}
                                    onChange={(e) => setAddQty(e.target.value)}
                                    className="w-20"
                                />
                                <Button size="sm" onClick={addUnits}>
                                    <Plus />
                                    Tambah Unit
                                </Button>
                            </div>
                        </div>
                        <InputError message={addQtyErrors.qty} />

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Register</TableHead>
                                    <TableHead>Kondisi</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {item.units.map((unit) => (
                                    <TableRow key={unit.id}>
                                        <TableCell className="font-mono">{unit.register}</TableCell>
                                        <TableCell>
                                            <NativeSelect
                                                value={unit.condition}
                                                onChange={(e) => updateCondition(unit, e.target.value)}
                                                className="w-40"
                                            >
                                                <option value="B">B — Baik</option>
                                                <option value="KB">KB — Kurang Baik</option>
                                                <option value="RB">RB — Rusak Berat</option>
                                            </NativeSelect>
                                        </TableCell>
                                        <TableCell className="text-right text-sm text-muted-foreground">
                                            {conditionLabel[unit.condition]}
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {item.units.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={3} className="text-center text-muted-foreground">
                                            Tidak ada unit.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
