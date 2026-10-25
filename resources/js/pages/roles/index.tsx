import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';

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
    filters: { search?: string; module?: string; per_page?: number };
    filterOptions: {
        modules: { value: string; label: string }[];
    };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Roles', href: '/roles' }];

export default function RolesIndex({
    roles,
    permissions,
    filters,
    filterOptions,
}: RolesPageProps) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [module, setModule] = useState(filters.module ?? '');
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

    const confirm = useConfirmation();

    const remove = async (role: RoleItem) => {
        if (
            !(await confirm({
                title: 'Hapus role?',
                description: `Hapus role "${role.name}"?`,
            }))
        ) {
            return;
        }

        router.delete(`/roles/${role.id}`, {
            preserveScroll: true,
        });
    };

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (module) {
            params.module = module;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) => {
        router.get('/roles', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        navigate({ ...filterParams(), page: 1, per_page: roles.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setModule('');
        setSelected([]);
        navigate({ page: 1, per_page: roles.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: roles.per_page });
    };

    const columns: DataTableColumn<RoleItem>[] = [
        {
            id: 'role',
            header: 'Role',
            cell: (role) => role.name,
            cellClassName: 'font-medium',
        },
        {
            id: 'users',
            header: 'Jumlah User',
            cell: (role) => role.users_count,
        },
        {
            id: 'permissions',
            header: 'Permissions',
            cell: (role) =>
                role.permissions.length === 0 ? (
                    '-'
                ) : (
                    <span className="flex flex-wrap gap-1">
                        {role.permissions.slice(0, 4).map((permission) => (
                            <span
                                key={permission.id}
                                className="rounded bg-muted px-1.5 py-0.5 text-xs"
                            >
                                {permission.name}
                            </span>
                        ))}
                        {role.permissions.length > 4 && (
                            <span className="text-xs text-muted-foreground">
                                +{role.permissions.length - 4}
                            </span>
                        )}
                    </span>
                ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Role & Permission"
                        description="Kelola role dan permission sistem"
                    />
                    <Button
                        onClick={openCreate}
                        className="w-full md:w-auto md:shrink-0"
                    >
                        <Plus />
                        Tambah Role
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-6">
                            <Label htmlFor="role-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="role-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Nama role..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-3">
                            <Label>Modul Permission</Label>
                            <SearchableCombobox
                                value={module}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.modules,
                                ]}
                                onChange={setModule}
                                placeholder="Semua"
                                searchPlaceholder="Cari modul..."
                                emptyMessage="Modul tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2 md:col-span-3">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <DataTableShell
                    data={roles.data}
                    columns={columns}
                    getRowKey={(role) => role.id}
                    emptyState="Tidak ada data role."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectedCount: selected.length,
                        selectAllLabel: 'Pilih semua role',
                        getRowLabel: (role) => `Pilih ${role.name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                        onSelectAll: (checked, rows) =>
                            setSelected(
                                checked ? rows.map((role) => role.id) : [],
                            ),
                    }}
                    rowActions={{
                        label: (role) => `Aksi ${role.name}`,
                        render: (role) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() => openEdit(role)}
                                >
                                    Edit
                                </DropdownMenuItem>
                                {role.name !== 'Super Admin' && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => remove(role)}
                                    >
                                        Hapus
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: roles,
                        noun: 'role',
                        onPageChange: goToPage,
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            }),
                    }}
                />
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <ModalShell>
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
                    </ModalShell>
                </Dialog>
            </div>
        </AppLayout>
    );
}
