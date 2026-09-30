import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
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
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
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

type Role = {
    id: number;
    name: string;
};

type UserItem = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string;
    roles: { id: number; name: string }[];
};

type UsersPageProps = {
    auth: Auth;
    users: {
        data: UserItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    roles: Role[];
    filters: { search?: string; per_page?: number };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: '/users' }];

export default function UsersIndex({ users, roles, filters }: UsersPageProps) {
    const { auth } = usePage<UsersPageProps>().props;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<UserItem | null>(null);
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        roles: [] as number[],
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', email: '', password: '', roles: [] });
        setErrors({});
        setDialogOpen(true);
    };

    const openEdit = (user: UserItem) => {
        setEditing(user);
        setForm({
            name: user.name,
            email: user.email,
            password: '',
            roles: user.roles.map((r) => r.id),
        });
        setErrors({});
        setDialogOpen(true);
    };

    const toggleRole = (roleId: number) => {
        setForm((prev) => ({
            ...prev,
            roles: prev.roles.includes(roleId)
                ? prev.roles.filter((id) => id !== roleId)
                : [...prev.roles, roleId],
        }));
    };

    const submit = () => {
        setProcessing(true);
        const payload = {
            name: form.name,
            email: form.email,
            password: form.password,
            roles: form.roles,
        };

        if (editing) {
            router.patch(`/users/${editing.id}`, payload, {
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
            router.post('/users', payload, {
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

    const remove = (user: UserItem) => {
        if (!confirm(`Hapus user "${user.name}"? Tindakan ini permanen.`)) {
            return;
        }

        router.delete(`/users/${user.id}`, {
            preserveScroll: true,
        });
    };

    const navigate = (page: number, perPage = users.per_page) => {
        router.get(
            '/users',
            { search, page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Manajemen User"
                        description="Kelola akun pengguna dan role"
                    />
                    <Button onClick={openCreate}>Tambah User</Button>
                </div>

                <DataTableToolbar className="rounded-xl border">
                    <div className="flex w-full flex-col gap-2 sm:flex-row">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    navigate(1);
                                }
                            }}
                            placeholder="Cari nama / email..."
                            className="sm:max-w-sm"
                        />
                        <Button variant="secondary" onClick={() => navigate(1)}>
                            Cari
                        </Button>
                    </div>
                </DataTableToolbar>

                <DataTableShell>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            {user.roles.length === 0
                                                ? '-'
                                                : user.roles
                                                      .map((role) => role.name)
                                                      .join(', ')}
                                        </TableCell>
                                        <TableCell>
                                            {user.email_verified_at ? (
                                                <span className="text-emerald-600">
                                                    Verified
                                                </span>
                                            ) : (
                                                <span className="text-amber-600">
                                                    Unverified
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${user.name}`}
                                            >
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        openEdit(user)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                {user.id !== auth.user?.id && (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            remove(user)
                                                        }
                                                    >
                                                        Hapus
                                                    </DropdownMenuItem>
                                                )}
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {users.data.length === 0 && (
                                    <DataTableEmptyState colSpan={5}>
                                        Tidak ada data user.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={users}
                        noun="user"
                        onPageChange={navigate}
                        onPerPageChange={(perPage) => navigate(1, perPage)}
                    />
                </DataTableShell>

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? `Edit User: ${editing.name}`
                                    : 'Tambah User'}
                            </DialogTitle>
                            <DialogDescription>
                                {editing
                                    ? 'Ubah nama, email, password (opsional), atau role.'
                                    : 'Buat akun user baru.'}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="user-name">Nama</Label>
                                <Input
                                    id="user-name"
                                    value={form.name}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            name: e.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-email">Email</Label>
                                <Input
                                    id="user-email"
                                    type="email"
                                    value={form.email}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            email: e.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password">
                                    Password{' '}
                                    {editing
                                        ? '(kosongkan jika tidak diubah)'
                                        : ''}
                                </Label>
                                <Input
                                    id="user-password"
                                    type="password"
                                    value={form.password}
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            password: e.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Role</Label>
                                <div className="grid gap-2">
                                    {roles.map((role) => (
                                        <label
                                            key={role.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.roles.includes(
                                                    role.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleRole(role.id)
                                                }
                                            />
                                            {role.name}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.roles} />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
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
