import { Head, router } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
    { title: 'Tambah', href: '/inventory/create' },
];

type Category = { id: number; name: string };

const initialForm = {
    kode_barang: '',
    nama_jenis_barang: '',
    merk_type: '',
    no_identitas: '',
    bahan: '',
    asal_perolehan: '',
    tahun_pembelian: '',
    ukuran_konstruksi: '',
    satuan: '',
    harga: '',
    keterangan: '',
    category_id: '',
    qty: '1',
};

export default function InventoryCreate({
    categories,
}: {
    categories: Category[];
}) {
    const [form, setForm] = useState(initialForm);
    const [categoryName, setCategoryName] = useState('');
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const set = (field: string, value: string) =>
        setForm((prev) => ({ ...prev, [field]: value }));

    const formatHarga = (value: string): string => {
        const normalized = value.replace(/[^0-9,]/g, '');
        const [whole, fraction] = normalized.split(',');
        const grouped = whole
            .replace(/^0+(?=\d)/, '')
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return fraction === undefined
            ? grouped
            : `${grouped},${fraction.slice(0, 2)}`;
    };

    const cleanHarga = (value: string): string => {
        const normalized = value.replace(/\./g, '').replace(',', '.');

        return normalized || '0';
    };

    const submit = () => {
        setProcessing(true);
        router.post(
            '/inventory',
            {
                ...form,
                harga: cleanHarga(form.harga),
                category_name: categoryName || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => setProcessing(false),
                onError: (errs) => {
                    setErrors(errs);
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Tambah Inventaris"
                    description="Input data kelompok barang baru (register dibuat otomatis)"
                />

                <div className="grid gap-6 rounded-xl border p-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="kode_barang">Kode Barang *</Label>
                            <Input
                                id="kode_barang"
                                value={form.kode_barang}
                                onChange={(e) =>
                                    set('kode_barang', e.target.value)
                                }
                                placeholder="A.01.01"
                            />
                            <InputError message={errors.kode_barang} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="nama_jenis_barang">
                                Nama/Jenis Barang *
                            </Label>
                            <Input
                                id="nama_jenis_barang"
                                value={form.nama_jenis_barang}
                                onChange={(e) =>
                                    set('nama_jenis_barang', e.target.value)
                                }
                            />
                            <InputError message={errors.nama_jenis_barang} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="merk_type">Merk/Type</Label>
                            <Input
                                id="merk_type"
                                value={form.merk_type}
                                onChange={(e) =>
                                    set('merk_type', e.target.value)
                                }
                            />
                            <InputError message={errors.merk_type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="no_identitas">
                                No Sertifikat/No Pabrik/No Chasis/No Mesin
                            </Label>
                            <Input
                                id="no_identitas"
                                value={form.no_identitas}
                                onChange={(e) =>
                                    set('no_identitas', e.target.value)
                                }
                            />
                            <InputError message={errors.no_identitas} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="bahan">Bahan</Label>
                            <Input
                                id="bahan"
                                value={form.bahan}
                                onChange={(e) => set('bahan', e.target.value)}
                            />
                            <InputError message={errors.bahan} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="asal_perolehan">
                                Asal/Cara Perolehan
                            </Label>
                            <Input
                                id="asal_perolehan"
                                value={form.asal_perolehan}
                                onChange={(e) =>
                                    set('asal_perolehan', e.target.value)
                                }
                            />
                            <InputError message={errors.asal_perolehan} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tahun_pembelian">
                                Tahun Pembelian
                            </Label>
                            <Input
                                id="tahun_pembelian"
                                type="number"
                                value={form.tahun_pembelian}
                                onChange={(e) =>
                                    set('tahun_pembelian', e.target.value)
                                }
                            />
                            <InputError message={errors.tahun_pembelian} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ukuran_konstruksi">
                                Ukuran/Konstruksi (P,S,D)
                            </Label>
                            <Input
                                id="ukuran_konstruksi"
                                value={form.ukuran_konstruksi}
                                onChange={(e) =>
                                    set('ukuran_konstruksi', e.target.value)
                                }
                            />
                            <InputError message={errors.ukuran_konstruksi} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="satuan">Satuan</Label>
                            <Input
                                id="satuan"
                                value={form.satuan}
                                onChange={(e) => set('satuan', e.target.value)}
                            />
                            <InputError message={errors.satuan} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="harga">Harga (per unit) *</Label>
                            <Input
                                id="harga"
                                type="text"
                                inputMode="decimal"
                                autoComplete="off"
                                placeholder="5.000.000"
                                value={form.harga}
                                onChange={(e) =>
                                    set('harga', formatHarga(e.target.value))
                                }
                            />
                            <InputError message={errors.harga} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="qty">Qty (jumlah unit) *</Label>
                            <Input
                                id="qty"
                                type="number"
                                min={1}
                                value={form.qty}
                                onChange={(e) => set('qty', e.target.value)}
                            />
                            <InputError message={errors.qty} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category-combobox">Kategori</Label>
                        <Popover
                            open={categoryOpen}
                            onOpenChange={setCategoryOpen}
                        >
                            <PopoverTrigger asChild>
                                <Button
                                    id="category-combobox"
                                    type="button"
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={categoryOpen}
                                    className="justify-between font-normal"
                                >
                                    {categoryName ||
                                        'Pilih atau ketik kategori'}
                                    <ChevronsUpDown className="opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="p-0">
                                <Command>
                                    <CommandInput
                                        placeholder="Cari atau ketik kategori..."
                                        value={categoryName}
                                        onValueChange={setCategoryName}
                                    />
                                    <CommandList>
                                        <CommandEmpty>
                                            {categoryName.trim()
                                                ? `Buat kategori “${categoryName.trim()}”`
                                                : 'Kategori tidak ditemukan.'}
                                        </CommandEmpty>
                                        {categories.map((category) => (
                                            <CommandItem
                                                key={category.id}
                                                value={category.name}
                                                onSelect={() => {
                                                    setCategoryName(
                                                        category.name,
                                                    );
                                                    set(
                                                        'category_id',
                                                        String(category.id),
                                                    );
                                                    setCategoryOpen(false);
                                                }}
                                            >
                                                {category.name}
                                                <Check
                                                    className={cn(
                                                        'ml-auto',
                                                        categoryName ===
                                                            category.name
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                            </CommandItem>
                                        ))}
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        <p className="text-xs text-muted-foreground">
                            Ketik nama baru untuk membuat kategori saat
                            disimpan.
                        </p>
                        <InputError
                            message={errors.category_id || errors.category_name}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="keterangan">Keterangan</Label>
                        <Textarea
                            id="keterangan"
                            value={form.keterangan}
                            onChange={(e) => set('keterangan', e.target.value)}
                        />
                        <InputError message={errors.keterangan} />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit('/inventory')}
                        >
                            Batal
                        </Button>
                        <Button onClick={submit} disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
