import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem } from '@/types';

type PermissionItem = {
    id: number;
    name: string;
};

type RoleItem = {
    id: number;
    name: string;
    users_count: number;
    permissions: PermissionItem[];
};

type RolesPageProps = {
    auth: Auth;
    roles: RoleItem[];
    permissions: PermissionItem[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roles', href: '/roles' },
];

export default function RolesIndex({ roles, permissions }: RolesPageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<RoleItem | null>(null);
    const [form, setForm] = useState({
        name: '',
        permissions: [] as number[],
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', permissions: [] });
        setErrors({});
        setDialogOpen(true);
    };

    const openEdit = (role: RoleItem) => {
        setEditing(role);
        setForm({
            name: role.name,
            permissions: role.permissions.map((p) => p.id),
        });
        setErrors({});
        setDialogOpen(true);
    };

    const togglePermission = (permId: number) => {
        setForm((prev) => ({
            ...prev,
            permissions: prev.permissions.includes(permId)
                ? prev.permissions.filter((id) => id !== permId)
                : [...prev.permissions, permId],
        }));
    };

    const submit = () => {
        setProcessing(true);
        const payload = { name: form.name, permissions: form.permissions };

        if (editing) {
            router.patch(`/roles/${editing.id}`, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setDialogOpen(false);
                    setProcessing(false);
                },
                onError: (errs) => {
                    setErrors(errs);
                    setProcessing(false);
                },
            });
        } else {
            router.post('/roles', payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setDialogOpen(false);
                    setProcessing(false);
                },
                onError: (errs) => {
                    setErrors(errs);
                    setProcessing(false);
                },
            });
        }
    };

    const remove = (role: RoleItem) => {
        if (!confirm(`Hapus role "${role.name}"?`)) {
            return;
        }

        router.delete(`/roles/${role.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Role & Permission"
                        description="Kelola role dan permission sistem"
                    />
                    <Button onClick={openCreate}>Tambah Role</Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead>Jumlah User</TableHead>
                                <TableHead>Permissions</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium">{role.name}</TableCell>
                                    <TableCell>{role.users_count}</TableCell>
                                    <TableCell>
                                        {role.permissions.length === 0 ? (
                                            <span className="text-muted-foreground">-</span>
                                        ) : (
                                            <span className="flex flex-wrap gap-1">
                                                {role.permissions.slice(0, 4).map((p) => (
                                                    <span
                                                        key={p.id}
                                                        className="rounded bg-muted px-1.5 py-0.5 text-xs"
                                                    >
                                                        {p.name}
                                                    </span>
                                                ))}
                                                {role.permissions.length > 4 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        +{role.permissions.length - 4}
                                                    </span>
                                                )}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => openEdit(role)}
                                            >
                                                Edit
                                            </Button>
                                            {role.name !== 'Super Admin' && (
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => remove(role)}
                                                >
                                                    Hapus
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? `Edit Role: ${editing.name}` : 'Tambah Role'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing
                                    ? 'Ubah nama role atau permission.'
                                    : 'Buat role baru.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="role-name">Nama Role</Label>
                                <Input
                                    id="role-name"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    disabled={editing?.name === 'Super Admin'}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Permissions</Label>
                                <div className="grid gap-2 rounded-lg border p-3">
                                    {permissions.map((perm) => (
                                        <label
                                            key={perm.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.permissions.includes(perm.id)}
                                                onCheckedChange={() => togglePermission(perm.id)}
                                            />
                                            {perm.name}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.permissions} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDialogOpen(false)}>
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
