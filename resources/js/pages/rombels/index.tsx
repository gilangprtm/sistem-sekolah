import { Head, router } from '@inertiajs/react';
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
import type { BreadcrumbItem } from '@/types';

type Rombel = {
    id: number;
    code: string;
    name: string;
    grade_level: string;
    parallel_code: string;
    status: string;
};
type Props = {
    rombels: {
        data: Rombel[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search?: string; status?: string; per_page?: number };
    filterOptions: { statuses: { value: string; label: string }[] };
};
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rombel', href: '/rombels' }];

export default function RombelsIndex({
    rombels,
    filters,
    filterOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Rombel | null>(null);
    const [form, setForm] = useState({
        name: '',
        grade_level: 'VII',
        parallel_code: '',
        status: 'active',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const params = () => ({
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
    });
    const navigate = (values: Record<string, string | number>) =>
        router.get('/rombels', values, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    const openCreate = () => {
        setEditing(null);
        setForm({
            name: '',
            grade_level: 'VII',
            parallel_code: '',
            status: 'active',
        });
        setErrors({});
        setOpen(true);
    };
    const openEdit = (rombel: Rombel) => {
        setEditing(rombel);
        setForm({
            name: rombel.name,
            grade_level: rombel.grade_level,
            parallel_code: rombel.parallel_code,
            status: rombel.status,
        });
        setErrors({});
        setOpen(true);
    };
    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: setErrors,
        };

        if (editing) {
            router.patch(`/rombels/${editing.id}`, form, options);
        } else {
            router.post('/rombels', form, options);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rombel" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Rombel"
                        description="Kelola master rombel sekolah"
                    />
                    <Button onClick={openCreate}>Tambah Rombel</Button>
                </div>
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-6">
                            <Label htmlFor="rombel-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="rombel-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        navigate({
                                            ...params(),
                                            page: 1,
                                            per_page: rombels.per_page,
                                        })
                                    }
                                    placeholder="Kode atau nama..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-3">
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
                            <Button
                                onClick={() =>
                                    navigate({
                                        ...params(),
                                        page: 1,
                                        per_page: rombels.per_page,
                                    })
                                }
                            >
                                Filter
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    setStatus('');
                                    navigate({
                                        page: 1,
                                        per_page: rombels.per_page,
                                    });
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
                <DataTableShell>
                    <DataTableToolbar>
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {rombels.total} baris
                            dipilih.
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
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                rombels.data.length > 0 &&
                                                selected.length ===
                                                    rombels.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? rombels.data.map(
                                                              (rombel) =>
                                                                  rombel.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua rombel"
                                        />
                                    </TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Tingkat</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rombels.data.map((rombel) => (
                                    <TableRow key={rombel.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.includes(
                                                    rombel.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  rombel.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      rombel.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih rombel ${rombel.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {rombel.code}
                                        </TableCell>
                                        <TableCell>{rombel.name}</TableCell>
                                        <TableCell>
                                            {rombel.grade_level}
                                        </TableCell>
                                        <TableCell>
                                            {rombel.status === 'active'
                                                ? 'Aktif'
                                                : 'Arsip'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${rombel.name}`}
                                            >
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        openEdit(rombel)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                {rombel.status === 'active' && (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            confirm(
                                                                `Arsipkan rombel "${rombel.name}"?`,
                                                            ) &&
                                                            router.post(
                                                                `/rombels/${rombel.id}/archive`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Arsipkan
                                                    </DropdownMenuItem>
                                                )}
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {rombels.data.length === 0 && (
                                    <DataTableEmptyState colSpan={6}>
                                        Tidak ada data rombel.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={rombels}
                        noun="rombel"
                        onPageChange={(page) =>
                            navigate({
                                ...params(),
                                page,
                                per_page: rombels.per_page,
                            })
                        }
                        onPerPageChange={(perPage) =>
                            navigate({
                                ...params(),
                                page: 1,
                                per_page: perPage,
                            })
                        }
                    />
                </DataTableShell>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit Rombel' : 'Tambah Rombel'}
                            </DialogTitle>
                            <DialogDescription>
                                Kode rombel dibuat otomatis dari tingkat dan
                                kode paralel.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="rombel-name">Nama</Label>
                                <Input
                                    id="rombel-name"
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
                                <Label>Tingkat</Label>
                                <SearchableCombobox
                                    value={form.grade_level}
                                    options={['VII', 'VIII', 'IX'].map(
                                        (value) => ({ value, label: value }),
                                    )}
                                    onChange={(value) =>
                                        setForm({ ...form, grade_level: value })
                                    }
                                    placeholder="Pilih tingkat"
                                    searchPlaceholder="Cari tingkat..."
                                    emptyMessage="Tingkat tidak ditemukan."
                                />
                                <InputError message={errors.grade_level} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="rombel-parallel">
                                    Kode Paralel
                                </Label>
                                <Input
                                    id="rombel-parallel"
                                    value={form.parallel_code}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            parallel_code: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.parallel_code} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Status</Label>
                                <SearchableCombobox
                                    value={form.status}
                                    options={[
                                        { value: 'active', label: 'Aktif' },
                                        { value: 'inactive', label: 'Arsip' },
                                    ]}
                                    onChange={(value) =>
                                        setForm({ ...form, status: value })
                                    }
                                    placeholder="Pilih status"
                                    searchPlaceholder="Cari status..."
                                    emptyMessage="Status tidak ditemukan."
                                />
                                <InputError message={errors.status} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button onClick={submit}>Simpan</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
