import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
    roles: {
        data: RoleItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    permissions: PermissionItem[];
    filters: { search?: string; per_page?: number };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles', href: '/roles' }];

export default function RolesIndex({
    roles,
    permissions,
    filters,
}: RolesPageProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<RoleItem | null>(null);
    const [form, setForm] = useState({
        name: '',
        permissions: [] as number[],
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [expandedGroups, setExpandedGroups] = useState<string[]>([]);

    const permissionGroups = useMemo(() => {
        const groups = new Map<string, PermissionItem[]>();

        permissions.forEach((permission) => {
            const [module] = permission.name.split('.');
            const items = groups.get(module) ?? [];
            items.push(permission);
            groups.set(module, items);
        });

        return Array.from(groups.entries()).map(([module, items]) => ({
            module,
            items,
        }));
    }, [permissions]);

    const toggleGroup = (module: string) => {
        setExpandedGroups((current) =>
            current.includes(module)
                ? current.filter((item) => item !== module)
                : [...current, module],
        );
    };

    const toggleGroupPermissions = (items: PermissionItem[]) => {
        const ids = items.map((item) => item.id);
        const allSelected = ids.every((id) => form.permissions.includes(id));

        setForm((prev) => ({
            ...prev,
            permissions: allSelected
                ? prev.permissions.filter((id) => !ids.includes(id))
                : Array.from(new Set([...prev.permissions, ...ids])),
        }));
    };

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

    const navigate = (page: number, perPage = roles.per_page) => {
        router.get(
            '/roles',
            { search, page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );
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
                            placeholder="Cari nama role..."
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
                                    <TableHead>Role</TableHead>
                                    <TableHead>Jumlah User</TableHead>
                                    <TableHead>Permissions</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {roles.data.map((role) => (
                                    <TableRow key={role.id}>
                                        <TableCell className="font-medium">
                                            {role.name}
                                        </TableCell>
                                        <TableCell>
                                            {role.users_count}
                                        </TableCell>
                                        <TableCell>
                                            {role.permissions.length === 0 ? (
                                                '-'
                                            ) : (
                                                <span className="flex flex-wrap gap-1">
                                                    {role.permissions
                                                        .slice(0, 4)
                                                        .map((permission) => (
                                                            <span
                                                                key={
                                                                    permission.id
                                                                }
                                                                className="rounded bg-muted px-1.5 py-0.5 text-xs"
                                                            >
                                                                {
                                                                    permission.name
                                                                }
                                                            </span>
                                                        ))}
                                                    {role.permissions.length >
                                                        4 && (
                                                        <span className="text-xs text-muted-foreground">
                                                            +
                                                            {role.permissions
                                                                .length - 4}
                                                        </span>
                                                    )}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${role.name}`}
                                            >
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        openEdit(role)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                {role.name !==
                                                    'Super Admin' && (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            remove(role)
                                                        }
                                                    >
                                                        Hapus
                                                    </DropdownMenuItem>
                                                )}
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {roles.data.length === 0 && (
                                    <DataTableEmptyState colSpan={4}>
                                        Tidak ada data role.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={roles}
                        noun="role"
                        onPageChange={navigate}
                        onPerPageChange={(perPage) => navigate(1, perPage)}
                    />
                </DataTableShell>

                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="max-h-[85vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? `Edit Role: ${editing.name}`
                                    : 'Tambah Role'}
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
                                    onChange={(e) =>
                                        setForm({
                                            ...form,
                                            name: e.target.value,
                                        })
                                    }
                                    disabled={editing?.name === 'Super Admin'}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Permissions</Label>
                                <div className="grid gap-2 rounded-lg border p-3">
                                    {permissionGroups.map(
                                        ({ module, items }) => {
                                            const expanded =
                                                expandedGroups.includes(module);
                                            const selectedCount = items.filter(
                                                (item) =>
                                                    form.permissions.includes(
                                                        item.id,
                                                    ),
                                            ).length;

                                            return (
                                                <div
                                                    key={module}
                                                    className="rounded-md border"
                                                >
                                                    <button
                                                        type="button"
                                                        className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm font-medium hover:bg-muted/50"
                                                        onClick={() =>
                                                            toggleGroup(module)
                                                        }
                                                    >
                                                        <span className="capitalize">
                                                            {module}
                                                        </span>
                                                        <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                            {selectedCount}/
                                                            {items.length}
                                                            <span aria-hidden="true">
                                                                {expanded
                                                                    ? '−'
                                                                    : '+'}
                                                            </span>
                                                        </span>
                                                    </button>
                                                    {expanded && (
                                                        <div className="grid gap-2 border-t p-3 sm:grid-cols-2">
                                                            <label className="flex items-center gap-2 text-sm font-medium sm:col-span-2">
                                                                <Checkbox
                                                                    checked={
                                                                        selectedCount ===
                                                                        items.length
                                                                    }
                                                                    onCheckedChange={() =>
                                                                        toggleGroupPermissions(
                                                                            items,
                                                                        )
                                                                    }
                                                                />
                                                                Pilih semua{' '}
                                                                {module}
                                                            </label>
                                                            {items.map(
                                                                (perm) => (
                                                                    <label
                                                                        key={
                                                                            perm.id
                                                                        }
                                                                        className="flex items-center gap-2 text-sm"
                                                                    >
                                                                        <Checkbox
                                                                            checked={form.permissions.includes(
                                                                                perm.id,
                                                                            )}
                                                                            onCheckedChange={() =>
                                                                                togglePermission(
                                                                                    perm.id,
                                                                                )
                                                                            }
                                                                        />
                                                                        {perm.name
                                                                            .split(
                                                                                '.',
                                                                            )
                                                                            .slice(
                                                                                1,
                                                                            )
                                                                            .join(
                                                                                '.',
                                                                            )}
                                                                    </label>
                                                                ),
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        },
                                    )}
                                </div>
                                <InputError message={errors.permissions} />
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
