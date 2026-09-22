import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type InventoryType = {
    id: number;
    name: string;
    slug: string;
    inventory_items_count: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Jenis Inventaris', href: '/inventory-types' },
];

export default function InventoryTypesIndex({
    types,
}: {
    types: InventoryType[];
}) {
    const [name, setName] = useState('');
    const [editing, setEditing] = useState<number | null>(null);
    const [error, setError] = useState('');
    const submit = () => {
        setError('');
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setName('');
                setEditing(null);
            },
            onError: (errors: Record<string, string>) =>
                setError(errors.name ?? ''),
        };

        if (editing) {
            router.patch(`/inventory-types/${editing}`, { name }, options);
        } else {
            router.post('/inventory-types', { name }, options);
        }
    };

    const remove = (type: InventoryType) => {
        if (confirm(`Hapus jenis “${type.name}”?`)) {
            router.delete(`/inventory-types/${type.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jenis Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Jenis Inventaris"
                    description="Kelola jenis inventaris sekolah"
                />
                <div className="grid gap-2 rounded-xl border p-4 md:max-w-xl">
                    <Label htmlFor="type-name">Nama</Label>
                    <div className="flex gap-2">
                        <Input
                            id="type-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="Aset"
                        />
                        <Button onClick={submit}>
                            {editing ? 'Simpan' : 'Tambah'}
                        </Button>
                    </div>
                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}
                </div>
                <div className="rounded-xl border p-4">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="p-2">Nama</th>
                                <th className="p-2">Slug</th>
                                <th className="p-2">Jumlah Kelompok</th>
                                <th className="p-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {types.map((type) => (
                                <tr className="border-b" key={type.id}>
                                    <td className="p-2">{type.name}</td>
                                    <td className="p-2">{type.slug}</td>
                                    <td className="p-2">
                                        {type.inventory_items_count}
                                    </td>
                                    <td className="p-2 text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setEditing(type.id);
                                                setName(type.name);
                                            }}
                                        >
                                            Edit
                                        </Button>{' '}
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => remove(type)}
                                        >
                                            Hapus
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
