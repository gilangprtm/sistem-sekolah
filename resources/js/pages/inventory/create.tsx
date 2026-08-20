import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
    { title: 'Tambah', href: '/inventory/create' },
];

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
    qty: '1',
};

export default function InventoryCreate() {
    const [form, setForm] = useState(initialForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const set = (field: string, value: string) => setForm((prev) => ({ ...prev, [field]: value }));

    const submit = () => {
        setProcessing(true);
        router.post('/inventory', form, {
            preserveScroll: true,
            onSuccess: () => setProcessing(false),
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
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
                                onChange={(e) => set('kode_barang', e.target.value)}
                                placeholder="A.01.01"
                            />
                            <InputError message={errors.kode_barang} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="nama_jenis_barang">Nama/Jenis Barang *</Label>
                            <Input
                                id="nama_jenis_barang"
                                value={form.nama_jenis_barang}
                                onChange={(e) => set('nama_jenis_barang', e.target.value)}
                            />
                            <InputError message={errors.nama_jenis_barang} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="merk_type">Merk/Type</Label>
                            <Input
                                id="merk_type"
                                value={form.merk_type}
                                onChange={(e) => set('merk_type', e.target.value)}
                            />
                            <InputError message={errors.merk_type} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="no_identitas">No Sertifikat/No Pabrik/No Chasis/No Mesin</Label>
                            <Input
                                id="no_identitas"
                                value={form.no_identitas}
                                onChange={(e) => set('no_identitas', e.target.value)}
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
                            <Label htmlFor="asal_perolehan">Asal/Cara Perolehan</Label>
                            <Input
                                id="asal_perolehan"
                                value={form.asal_perolehan}
                                onChange={(e) => set('asal_perolehan', e.target.value)}
                            />
                            <InputError message={errors.asal_perolehan} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tahun_pembelian">Tahun Pembelian</Label>
                            <Input
                                id="tahun_pembelian"
                                type="number"
                                value={form.tahun_pembelian}
                                onChange={(e) => set('tahun_pembelian', e.target.value)}
                            />
                            <InputError message={errors.tahun_pembelian} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="ukuran_konstruksi">Ukuran/Konstruksi (P,S,D)</Label>
                            <Input
                                id="ukuran_konstruksi"
                                value={form.ukuran_konstruksi}
                                onChange={(e) => set('ukuran_konstruksi', e.target.value)}
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
                                type="number"
                                value={form.harga}
                                onChange={(e) => set('harga', e.target.value)}
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
                        <Label htmlFor="keterangan">Keterangan</Label>
                        <Textarea
                            id="keterangan"
                            value={form.keterangan}
                            onChange={(e) => set('keterangan', e.target.value)}
                        />
                        <InputError message={errors.keterangan} />
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => router.visit('/inventory')}>
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
