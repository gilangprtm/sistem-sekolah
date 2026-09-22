import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import type { BreadcrumbItem } from '@/types';

type Category = {
    id: number;
    name: string;
    description: string | null;
    slug: string;
    inventory_items_count: number;
    total_units: number;
};

type Props = { categories: Category[] };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kategori', href: '/categories' },
];

export default function CategoriesIndex({ categories }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [form, setForm] = useState({ name: '', description: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', description: '' });
        setErrors({});
        setOpen(true);
    };

    const openEdit = (category: Category) => {
        setEditing(category);
        setForm({
            name: category.name,
            description: category.description ?? '',
        });
        setErrors({});
        setOpen(true);
    };

    const submit = () => {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setProcessing(false);
            },
            onError: (validationErrors: Record<string, string>) => {
                setErrors(validationErrors);
                setProcessing(false);
            },
        };

        if (editing) {
            router.patch(`/categories/${editing.id}`, form, options);
        } else {
            router.post('/categories', form, options);
        }
    };

    const remove = (category: Category) => {
        if (
            !confirm(
                `Hapus kategori "${category.name}"? Item terkait akan tetap ada tanpa kategori.`,
            )
        ) {
            return;
        }

        router.delete(`/categories/${category.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Kategori Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Kategori Inventaris"
                        description="Kelola kategori inventaris sekolah"
                    />
                    <Button onClick={openCreate}>Tambah Kategori</Button>
                </div>
                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Slug</TableHead>
                                <TableHead>Jumlah Kelompok</TableHead>
                                <TableHead>Jumlah Unit</TableHead>
                                <TableHead>Keterangan</TableHead>
                                <TableHead className="text-right">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.map((category) => (
                                <TableRow key={category.id}>
                                    <TableCell className="font-medium">
                                        {category.name}
                                    </TableCell>
                                    <TableCell>{category.slug}</TableCell>
                                    <TableCell>
                                        {category.inventory_items_count}
                                    </TableCell>
                                    <TableCell>
                                        {category.total_units}
                                    </TableCell>
                                    <TableCell>
                                        {category.description || '-'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    openEdit(category)
                                                }
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => remove(category)}
                                            >
                                                Hapus
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit Kategori' : 'Tambah Kategori'}
                            </DialogTitle>
                            <DialogDescription>
                                Isi nama dan keterangan kategori.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="category-name">Nama</Label>
                                <Input
                                    id="category-name"
                                    value={form.name}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            name: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="category-description">
                                    Keterangan
                                </Label>
                                <Textarea
                                    id="category-description"
                                    value={form.description}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            description: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.description} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button onClick={submit} disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
