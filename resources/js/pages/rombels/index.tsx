import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
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
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Rombel', href: '/kurikulum/rombels' },
];

export default function RombelsIndex({
    rombels,
    filters,
    filterOptions,
}: Props) {
    const confirm = useConfirmation();
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
        router.get('/kurikulum/rombels', values, {
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
    const columns: DataTableColumn<Rombel>[] = [
        {
            id: 'code',
            header: 'Kode',
            cell: (rombel) => rombel.code,
            cellClassName: 'font-medium',
        },
        { id: 'name', header: 'Nama', cell: (rombel) => rombel.name },
        {
            id: 'grade',
            header: 'Tingkat',
            cell: (rombel) => rombel.grade_level,
        },
        {
            id: 'status',
            header: 'Status',
            cell: (rombel) => (rombel.status === 'active' ? 'Aktif' : 'Arsip'),
        },
    ];

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: setErrors,
        };

        if (editing) {
            router.patch(`/kurikulum/rombels/${editing.id}`, form, options);
        } else {
            router.post('/kurikulum/rombels', form, options);
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
                    <Button onClick={openCreate}>
                        <Plus />
                        Tambah Rombel
                    </Button>
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
                <DataTableShell
                    data={rombels.data}
                    columns={columns}
                    getRowKey={(rombel) => rombel.id}
                    emptyState="Tidak ada data rombel."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua rombel',
                        getRowLabel: (rombel) => `Pilih rombel ${rombel.name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (rombel) => `Aksi ${rombel.name}`,
                        render: (rombel) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() => openEdit(rombel)}
                                >
                                    Edit
                                </DropdownMenuItem>
                                {rombel.status === 'active' && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={async () => {
                                            if (
                                                await confirm({
                                                    title: 'Arsipkan rombel?',
                                                    description: `Arsipkan rombel "${rombel.name}"?`,
                                                })
                                            ) {
                                                router.post(
                                                    `/kurikulum/rombels/${rombel.id}/archive`,
                                                    {},
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                    >
                                        Arsipkan
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: rombels,
                        noun: 'rombel',
                        onPageChange: (page) =>
                            navigate({
                                ...params(),
                                page,
                                per_page: rombels.per_page,
                            }),
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...params(),
                                page: 1,
                                per_page: perPage,
                            }),
                    }}
                />
                <Dialog open={open} onOpenChange={setOpen}>
                    <ModalShell size="medium">
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit Rombel' : 'Tambah Rombel'}
                            </DialogTitle>
                            <DialogDescription>
                                Kode rombel dibuat otomatis dari tingkat dan
                                kode paralel.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="@container grid gap-4">
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
                            <div className="grid items-start gap-4 @min-[20rem]:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] @min-[28rem]:grid-cols-[minmax(0,0.9fr)_minmax(0,1fr)_minmax(0,1.2fr)]">
                                <div className="grid min-w-0 gap-2">
                                    <Label>Tingkat</Label>
                                    <SearchableCombobox
                                        value={form.grade_level}
                                        options={['VII', 'VIII', 'IX'].map(
                                            (value) => ({
                                                value,
                                                label: value,
                                            }),
                                        )}
                                        onChange={(value) =>
                                            setForm({
                                                ...form,
                                                grade_level: value,
                                            })
                                        }
                                        placeholder="Pilih tingkat"
                                        searchPlaceholder="Cari tingkat..."
                                        emptyMessage="Tingkat tidak ditemukan."
                                    />
                                    <InputError message={errors.grade_level} />
                                </div>
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="rombel-parallel">
                                        Kode Paralel
                                    </Label>
                                    <Input
                                        id="rombel-parallel"
                                        value={form.parallel_code}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                parallel_code:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors.parallel_code}
                                    />
                                </div>
                                <div className="grid min-w-0 gap-2 @min-[20rem]:col-span-2 @min-[28rem]:col-span-1">
                                    <Label>Status</Label>
                                    <SearchableCombobox
                                        value={form.status}
                                        options={[
                                            { value: 'active', label: 'Aktif' },
                                            {
                                                value: 'inactive',
                                                label: 'Arsip',
                                            },
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
                    </ModalShell>
                </Dialog>
            </div>
        </AppLayout>
    );
}
