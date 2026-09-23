import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
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
    category: { id: number; name: string } | null;
    inventoryType: { id: number; name: string } | null;
    qty: number;
    total: number;
    units: Unit[];
};

type Props = {
    item: Item;
    categories: { id: number; name: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
];

export default function InventoryShow({ item, categories }: Props) {
    const [keterangan, setKeterangan] = useState(item.keterangan ?? '');
    const [categoryId, setCategoryId] = useState(
        item.category?.id?.toString() ?? '',
    );
    const [categoryErrors, setCategoryErrors] = useState<
        Record<string, string>
    >({});
    const [keteranganErrors, setKeteranganErrors] = useState<
        Record<string, string>
    >({});
    const [addQty, setAddQty] = useState('1');
    const [addQtyErrors, setAddQtyErrors] = useState<Record<string, string>>(
        {},
    );
    const sortedUnits = useMemo(
        () =>
            [...item.units].sort((a, b) =>
                a.register.localeCompare(b.register, undefined, {
                    numeric: true,
                    sensitivity: 'base',
                }),
            ),
        [item.units],
    );

    const saveKeterangan = () => {
        router.patch(
            `/inventory/${item.id}`,
            { keterangan },
            {
                preserveScroll: true,
                onError: (errs) => setKeteranganErrors(errs),
            },
        );
    };

    const saveCategory = () => {
        router.patch(
            `/inventory/${item.id}`,
            { category_id: categoryId || null },
            {
                preserveScroll: true,
                onError: (errs) => setCategoryErrors(errs),
            },
        );
    };

    const addUnits = () => {
        router.post(
            `/inventory/${item.id}/units`,
            { qty: addQty },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAddQty('1');
                    setAddQtyErrors({});
                },
                onError: (errs) => setAddQtyErrors(errs),
            },
        );
    };

    const updateCondition = (unit: Unit, condition: string) => {
        router.patch(
            `/inventory/${item.id}/units/${unit.id}`,
            { condition },
            {
                preserveScroll: true,
            },
        );
    };

    const remove = () => {
        if (
            !confirm(
                `Hapus inventaris "${item.nama_jenis_barang}" (${item.kode_barang})? Semua unit akan ikut terhapus.`,
            )
        ) {
            return;
        }

        router.delete(`/inventory/${item.id}`);
    };

    const formatRupiah = (value: number) =>
        new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);

    return (
        <AppLayout
            breadcrumbs={[
                ...breadcrumbs,
                { title: item.kode_barang, href: `/inventory/${item.id}` },
            ]}
        >
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
                        <h3 className="mb-3 text-sm font-semibold">
                            Data Barang (immutable)
                        </h3>
                        <dl className="grid gap-2 text-sm">
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Kode Barang
                                </dt>
                                <dd className="font-mono">
                                    {item.kode_barang}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Nama/Jenis
                                </dt>
                                <dd>{item.nama_jenis_barang}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Merk/Type
                                </dt>
                                <dd>{item.merk_type ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    No Identitas
                                </dt>
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
                                <dt className="text-muted-foreground">
                                    Ukuran
                                </dt>
                                <dd>{item.ukuran_konstruksi ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Satuan
                                </dt>
                                <dd>{item.satuan ?? '-'}</dd>
                            </div>
                            <div className="grid gap-2 border-t pt-2">
                                <Label htmlFor="category_id">Kategori</Label>
                                <SearchableCombobox
                                    value={categoryId}
                                    options={[
                                        { value: '', label: 'Tanpa kategori' },
                                        ...categories.map((category) => ({
                                            value: category.id.toString(),
                                            label: category.name,
                                        })),
                                    ]}
                                    onChange={setCategoryId}
                                    placeholder="Tanpa kategori"
                                    searchPlaceholder="Cari kategori..."
                                    emptyMessage="Kategori tidak ditemukan."
                                />
                                <InputError
                                    message={categoryErrors.category_id}
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={saveCategory}
                                >
                                    Simpan Kategori
                                </Button>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Jenis</dt>
                                <dd>{item.inventoryType?.name ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Harga/unit
                                </dt>
                                <dd>{formatRupiah(Number(item.harga))}</dd>
                            </div>
                            <div className="flex justify-between gap-2 border-t pt-2">
                                <dt className="font-medium">Qty</dt>
                                <dd className="font-medium">{item.qty}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="font-medium">Total</dt>
                                <dd className="font-medium">
                                    {formatRupiah(item.total)}
                                </dd>
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
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={saveKeterangan}
                            >
                                <Pencil />
                                Simpan Keterangan
                            </Button>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4 lg:col-span-2">
                        <div className="mb-3 flex items-center justify-between">
                            <h3 className="text-sm font-semibold">
                                Unit / Register
                            </h3>
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

                        <div className="w-full overflow-x-auto">
                            <Table className="w-full table-fixed">
                                <colgroup>
                                    <col className="w-[20%]" />
                                    <col className="w-[30%]" />
                                    <col className="w-[50%]" />
                                </colgroup>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Register</TableHead>
                                        <TableHead>Kode</TableHead>
                                        <TableHead className="text-right">
                                            Kondisi
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sortedUnits.map((unit) => (
                                        <TableRow key={unit.id}>
                                            <TableCell className="font-mono">
                                                {unit.register}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs text-muted-foreground">
                                                {item.kode_barang}.
                                                {unit.register}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <NativeSelect
                                                    value={unit.condition}
                                                    onChange={(e) =>
                                                        updateCondition(
                                                            unit,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="ml-auto w-40"
                                                >
                                                    <option value="B">
                                                        B — Baik
                                                    </option>
                                                    <option value="KB">
                                                        KB — Kurang Baik
                                                    </option>
                                                    <option value="RB">
                                                        RB — Rusak Berat
                                                    </option>
                                                </NativeSelect>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {item.units.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="text-center text-muted-foreground"
                                            >
                                                Tidak ada unit.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
