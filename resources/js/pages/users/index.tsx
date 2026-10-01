import { Head, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
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

type GeneratedAccounts = {
    count: number;
    first_sequence: number;
    last_sequence: number;
    emails: string[];
    security_warning: string;
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
    filters: {
        search?: string;
        role?: string;
        status?: string;
        per_page?: number;
    };
    filterOptions: {
        statuses: { value: string; label: string }[];
    };
    generated_accounts?: GeneratedAccounts;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Users', href: '/users' }];

export default function UsersIndex({
    users,
    roles,
    filters,
    filterOptions,
    generated_accounts,
}: UsersPageProps) {
    const { auth } = usePage<UsersPageProps>().props;
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
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
    const [generatorOpen, setGeneratorOpen] = useState(false);
    const [generatorYear, setGeneratorYear] = useState(
        new Date().getFullYear().toString(),
    );
    const [generatorCount, setGeneratorCount] = useState('1');

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

    const openGenerator = () => {
        setErrors({});
        setGeneratorOpen(true);
    };

    const generateStudentAccounts = () => {
        setProcessing(true);
        router.post(
            '/users/generate-student-accounts',
            {
                year: Number(generatorYear),
                count: Number(generatorCount),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setGeneratorOpen(false);
                    setProcessing(false);
                },
                onError: (errs) => {
                    setErrors(errs);
                    setProcessing(false);
                },
            },
        );
    };

    const remove = (user: UserItem) => {
        if (!confirm(`Hapus user "${user.name}"? Tindakan ini permanen.`)) {
            return;
        }

        router.delete(`/users/${user.id}`, {
            preserveScroll: true,
        });
    };

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (role) {
            params.role = role;
        }

        if (status) {
            params.status = status;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) => {
        router.get('/users', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        navigate({ ...filterParams(), page: 1, per_page: users.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setRole('');
        setStatus('');
        setSelected([]);
        navigate({ page: 1, per_page: users.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: users.per_page });
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
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={openGenerator}>
                            Generate Akun Siswa
                        </Button>
                        <Button onClick={openCreate}>Tambah User</Button>
                    </div>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-5">
                            <Label htmlFor="user-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="user-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Nama / email..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Role</Label>
                            <SearchableCombobox
                                value={role}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...roles.map((item) => ({
                                        value: `${item.id}`,
                                        label: item.name,
                                    })),
                                ]}
                                onChange={setRole}
                                placeholder="Semua"
                                searchPlaceholder="Cari role..."
                                emptyMessage="Role tidak ditemukan."
                            />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label>Status</Label>
                            <SearchableCombobox
                                value={status}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.statuses,
                                ]}
                                onChange={setStatus}
                                placeholder="Semua"
                                searchPlaceholder="Cari status..."
                                emptyMessage="Status tidak ditemukan."
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

                <DataTableShell>
                    <DataTableToolbar>
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {users.total} baris dipilih.
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSelected([])}
                            disabled={!selected.length}
                        >
                            Hapus pilihan
                        </Button>
                    </DataTableToolbar>
                    <div className="overflow-x-auto">
                        <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                users.data.length > 0 &&
                                                selected.length ===
                                                    users.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? users.data.map(
                                                              (user) => user.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua user"
                                        />
                                    </TableHead>
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
                                        <TableCell className="w-12">
                                            <Checkbox
                                                checked={selected.includes(
                                                    user.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  user.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      user.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih ${user.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {user.name}
                                        </TableCell>
                                        <TableCell>{user.email}</TableCell>
                                        <TableCell>
                                            {user.roles.length === 0
                                                ? '-'
                                                : user.roles
                                                      .map((item) => item.name)
                                                      .join(', ')}
                                        </TableCell>
                                        <TableCell>
                                            {user.email_verified_at
                                                ? 'Verified'
                                                : 'Unverified'}
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
                                    <DataTableEmptyState colSpan={6}>
                                        Tidak ada data user.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={users}
                        noun="user"
                        onPageChange={goToPage}
                        onPerPageChange={(perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            })
                        }
                    />
                </DataTableShell>

                {generated_accounts && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-950/30">
                        <p className="font-medium">
                            {generated_accounts.count} akun Siswa berhasil
                            dibuat.
                        </p>
                        <p>{generated_accounts.emails.join(', ')}</p>
                        <p className="mt-2 text-amber-800 dark:text-amber-200">
                            {generated_accounts.security_warning}
                        </p>
                    </div>
                )}

                <Dialog open={generatorOpen} onOpenChange={setGeneratorOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Generate Akun Siswa</DialogTitle>
                            <DialogDescription>
                                Akun dibuat dengan nama default Siswa dan
                                password awal bersama. Wajib ganti password
                                melalui alur reset.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="student-year">Tahun</Label>
                                <Input
                                    id="student-year"
                                    value={generatorYear}
                                    onChange={(event) =>
                                        setGeneratorYear(event.target.value)
                                    }
                                    inputMode="numeric"
                                />
                                <InputError message={errors.year} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="student-count">
                                    Jumlah (1–100)
                                </Label>
                                <Input
                                    id="student-count"
                                    value={generatorCount}
                                    onChange={(event) =>
                                        setGeneratorCount(event.target.value)
                                    }
                                    inputMode="numeric"
                                />
                                <InputError message={errors.count} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setGeneratorOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                onClick={generateStudentAccounts}
                                disabled={processing}
                            >
                                {processing ? 'Membuat...' : 'Generate'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

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
