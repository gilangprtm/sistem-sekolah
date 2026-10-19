import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatRupiah } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Unit = {
    id: number;
    register: string;
    condition: string;
    room: { id: number; name: string } | null;
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
    image_url: string | null;
    category: { id: number; name: string } | null;
    inventoryType: { id: number; name: string } | null;
    qty: number;
    total: number;
    units: Unit[];
};

type Props = {
    item: Item;
    categories: { id: number; name: string }[];
    rooms: { id: number; name: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventaris/inventory' },
];

export default function InventoryShow({ item, categories, rooms }: Props) {
    const [keterangan, setKeterangan] = useState(item.keterangan ?? '');
    const [categoryId, setCategoryId] = useState(
        item.category?.id?.toString() ?? '',
    );
    const [image, setImage] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [imageErrors, setImageErrors] = useState<Record<string, string>>({});
    const [imageProcessing, setImageProcessing] = useState(false);
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
    useEffect(() => {
        if (!imagePreview) {
            return;
        }

        return () => URL.revokeObjectURL(imagePreview);
    }, [imagePreview]);

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

    const saveImage = () => {
        if (!image) {
            return;
        }

        const payload = new FormData();
        payload.append('image', image);
        payload.append('_method', 'PATCH');
        setImageProcessing(true);

        router.post(`/inventaris/inventory/${item.id}`, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImage(null);
                setImagePreview(null);
                setImageErrors({});
            },
            onError: (errors) => setImageErrors(errors),
            onFinish: () => setImageProcessing(false),
        });
    };

    const saveKeterangan = () => {
        router.patch(
            `/inventaris/inventory/${item.id}`,
            { keterangan },
            {
                preserveScroll: true,
                onError: (errs) => setKeteranganErrors(errs),
            },
        );
    };

    const saveCategory = () => {
        router.patch(
            `/inventaris/inventory/${item.id}`,
            { inventory_category_id: categoryId || null },
            {
                preserveScroll: true,
                onError: (errs) => setCategoryErrors(errs),
            },
        );
    };

    const addUnits = () => {
        router.post(
            `/inventaris/inventory/${item.id}/units`,
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
            `/inventaris/inventory/${item.id}/units/${unit.id}`,
            { condition },
            {
                preserveScroll: true,
            },
        );
    };

    const updateRoom = (unit: Unit, inventoryRoomId: string) => {
        router.patch(
            `/inventaris/inventory/${item.id}/units/${unit.id}/room`,
            { inventory_room_id: inventoryRoomId || null },
            { preserveScroll: true },
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

        router.delete(`/inventaris/inventory/${item.id}`);
    };

    return (
        <AppLayout
            breadcrumbs={[
                ...breadcrumbs,
                {
                    title: item.kode_barang,
                    href: `/inventaris/inventory/${item.id}`,
                },
            ]}
        >
            <Head title={`${item.kode_barang} — ${item.nama_jenis_barang}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="outline" size="icon">
                            <Link href="/inventaris/inventory">
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

                <div className="grid items-start gap-4 lg:grid-cols-3">
                    <section className="grid gap-3 rounded-xl border p-4">
                        <h3 className="text-sm font-semibold">
                            Gambar Inventaris
                        </h3>
                        <div className="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg border bg-muted/40">
                            {imagePreview || item.image_url ? (
                                <img
                                    src={imagePreview ?? item.image_url ?? ''}
                                    alt={`Gambar ${item.nama_jenis_barang}`}
                                    className="size-full object-contain"
                                />
                            ) : (
                                <div className="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                                    <ImagePlus
                                        className="size-8"
                                        aria-hidden="true"
                                    />
                                    <span>Belum ada gambar</span>
                                </div>
                            )}
                        </div>
                        <Input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            aria-label="Pilih gambar inventaris"
                            aria-describedby="inventory-image-help inventory-image-error"
                            aria-invalid={Boolean(imageErrors.image)}
                            onChange={(event) => {
                                const selected =
                                    event.target.files?.[0] ?? null;
                                setImage(selected);
                                setImagePreview(
                                    selected
                                        ? URL.createObjectURL(selected)
                                        : null,
                                );
                            }}
                        />
                        <p
                            id="inventory-image-help"
                            className="text-xs text-muted-foreground"
                        >
                            JPG, PNG, atau WebP. Maksimal 1 MB.
                        </p>
                        <InputError
                            id="inventory-image-error"
                            role="alert"
                            message={imageErrors.image}
                        />
                        {image && (
                            <Button
                                onClick={saveImage}
                                className="min-h-11"
                                aria-label="Simpan gambar inventaris"
                                disabled={imageProcessing}
                            >
                                <Pencil aria-hidden="true" />
                                {imageProcessing
                                    ? 'Menyimpan gambar...'
                                    : 'Simpan gambar'}
                            </Button>
                        )}
                    </section>
                    <section className="rounded-xl border p-4">
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
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">Jenis</dt>
                                <dd>{item.inventoryType?.name ?? '-'}</dd>
                            </div>
                            <div className="flex justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Harga/unit
                                </dt>
                                <dd>{formatRupiah(item.harga)}</dd>
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
                    </section>
                    <section className="grid content-start gap-3 rounded-xl border p-4">
                        <h3 className="text-sm font-semibold">
                            Kategori & Keterangan
                        </h3>
                        <div className="grid gap-2">
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
                                message={categoryErrors.inventory_category_id}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={saveCategory}
                            >
                                Simpan Kategori
                            </Button>
                        </div>
                        <div className="grid gap-2 border-t pt-3">
                            <Label htmlFor="keterangan">Keterangan</Label>
                            <Textarea
                                id="keterangan"
                                value={keterangan}
                                onChange={(event) =>
                                    setKeterangan(event.target.value)
                                }
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
                    </section>

                    <div className="rounded-xl border p-4 lg:col-span-3">
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
                                    <col className="w-[15%]" />
                                    <col className="w-[25%]" />
                                    <col className="w-[30%]" />
                                    <col className="w-[30%]" />
                                </colgroup>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Register</TableHead>
                                        <TableHead>Kode</TableHead>
                                        <TableHead>Ruangan Saat Ini</TableHead>
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
                                            <TableCell>
                                                <SearchableCombobox
                                                    value={
                                                        unit.room?.id.toString() ??
                                                        ''
                                                    }
                                                    options={[
                                                        {
                                                            value: '',
                                                            label: 'Tanpa ruangan',
                                                        },
                                                        ...rooms.map(
                                                            (room) => ({
                                                                value: room.id.toString(),
                                                                label: room.name,
                                                            }),
                                                        ),
                                                    ]}
                                                    onChange={(roomId) =>
                                                        updateRoom(unit, roomId)
                                                    }
                                                    placeholder="Tanpa ruangan"
                                                    searchPlaceholder="Cari ruangan..."
                                                    emptyMessage="Ruangan tidak ditemukan."
                                                />
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <SearchableCombobox
                                                    value={unit.condition}
                                                    options={[
                                                        {
                                                            value: 'B',
                                                            label: 'B — Baik',
                                                        },
                                                        {
                                                            value: 'KB',
                                                            label: 'KB — Kurang Baik',
                                                        },
                                                        {
                                                            value: 'RB',
                                                            label: 'RB — Rusak Berat',
                                                        },
                                                    ]}
                                                    onChange={(condition) =>
                                                        updateCondition(
                                                            unit,
                                                            condition,
                                                        )
                                                    }
                                                    placeholder="Pilih kondisi"
                                                    searchPlaceholder="Cari kondisi..."
                                                    emptyMessage="Kondisi tidak ditemukan."
                                                    className="ml-auto w-40"
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {item.units.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
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
