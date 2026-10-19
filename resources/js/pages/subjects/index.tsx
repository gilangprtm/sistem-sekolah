import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Subject = {
    id: number;
    code: string;
    name: string;
    status: string;
    jp_per_class: number;
    color: string;
};
type Props = {
    subjects: {
        data: Subject[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search?: string; status?: string; per_page?: number };
    filterOptions: { statuses: { value: string; label: string }[] };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Mata Pelajaran', href: '/kurikulum/subjects' },
];

export default function SubjectsIndex({
    subjects,
    filters,
    filterOptions,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Subject | null>(null);
    const [form, setForm] = useState({
        code: '',
        name: '',
        status: 'active',
        jp_per_class: 1,
        color: '#F3F4F6',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const filterParams = (): Record<string, string> => ({
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
    });
    const navigate = (params: Record<string, string | number>) =>
        router.get('/kurikulum/subjects', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    const openCreate = () => {
        setEditing(null);
        setForm({
            code: '',
            name: '',
            status: 'active',
            jp_per_class: 1,
            color: '#F3F4F6',
        });
        setErrors({});
        setOpen(true);
    };
    const openEdit = (subject: Subject) => {
        setEditing(subject);
        setForm({
            code: subject.code,
            name: subject.name,
            status: subject.status,
            jp_per_class: subject.jp_per_class,
            color: subject.color,
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
            router.patch(`/kurikulum/subjects/${editing.id}`, form, options);
        } else {
            router.post('/kurikulum/subjects', form, options);
        }
    };

    const archive = (subject: Subject) => {
        if (confirm(`Arsipkan mata pelajaran "${subject.name}"?`)) {
            router.post(
                `/kurikulum/subjects/${subject.id}/archive`,
                {},
                { preserveScroll: true },
            );
        }
    };

    const columns: DataTableColumn<Subject>[] = [
        {
            id: 'code',
            header: 'Kode',
            cell: (subject) => subject.code,
            cellClassName: 'font-medium',
        },
        { id: 'name', header: 'Nama', cell: (subject) => subject.name },
        {
            id: 'status',
            header: 'Status',
            cell: (subject) =>
                subject.status === 'active' ? 'Aktif' : 'Arsip',
        },
        {
            id: 'jp-per-class',
            header: 'JP per Kelas',
            cell: (subject) => subject.jp_per_class,
        },
        {
            id: 'color',
            header: 'Warna',
            cell: (subject) => (
                <span
                    className="inline-flex size-6 rounded border border-black/10"
                    style={{ backgroundColor: subject.color }}
                    title={subject.color}
                    aria-label={`Warna ${subject.color}`}
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mata Pelajaran" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Mata Pelajaran"
                        description="Kelola master mata pelajaran sekolah"
                    />
                    <Button onClick={openCreate}>
                        <Plus />
                        Tambah Mata Pelajaran
                    </Button>
                </div>
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-6">
                            <Label htmlFor="subject-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="subject-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        navigate({
                                            ...filterParams(),
                                            page: 1,
                                            per_page: subjects.per_page,
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
                                        ...filterParams(),
                                        page: 1,
                                        per_page: subjects.per_page,
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
                                        per_page: subjects.per_page,
                                    });
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
                <DataTableShell
                    data={subjects.data}
                    columns={columns}
                    getRowKey={(subject) => subject.id}
                    emptyState="Tidak ada data mata pelajaran."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua mata pelajaran',
                        getRowLabel: (subject) => `Pilih ${subject.name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (subject) => `Aksi ${subject.name}`,
                        render: (subject) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() => openEdit(subject)}
                                >
                                    Edit
                                </DropdownMenuItem>
                                {subject.status === 'active' && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onClick={() => archive(subject)}
                                    >
                                        Arsipkan
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: subjects,
                        noun: 'mata pelajaran',
                        onPageChange: (page) =>
                            navigate({
                                ...filterParams(),
                                page,
                                per_page: subjects.per_page,
                            }),
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            }),
                    }}
                />
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? 'Edit Mata Pelajaran'
                                    : 'Tambah Mata Pelajaran'}
                            </DialogTitle>
                            <DialogDescription>
                                Kode harus berupa huruf kapital, angka,
                                underscore, atau tanda hubung.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="subject-code">Kode</Label>
                                <Input
                                    id="subject-code"
                                    value={form.code}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            code: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="subject-name">Nama</Label>
                                <Input
                                    id="subject-name"
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
                                <Label htmlFor="subject-jp-per-class">
                                    JP per Kelas
                                </Label>
                                <Input
                                    id="subject-jp-per-class"
                                    type="text"
                                    inputMode="numeric"
                                    pattern="[0-9]*"
                                    minLength={1}
                                    maxLength={2}
                                    value={form.jp_per_class}
                                    onChange={(event) => {
                                        const digitsOnly =
                                            event.target.value.replace(
                                                /\D/g,
                                                '',
                                            );

                                        setForm({
                                            ...form,
                                            jp_per_class: digitsOnly
                                                ? Number(digitsOnly)
                                                : 1,
                                        });
                                    }}
                                />
                                <InputError message={errors.jp_per_class} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="subject-color">
                                    Warna jadwal
                                </Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="subject-color"
                                        type="color"
                                        value={form.color}
                                        onChange={(event) =>
                                            setForm({
                                                ...form,
                                                color: event.target.value.toUpperCase(),
                                            })
                                        }
                                        className="h-9 w-14 cursor-pointer p-1"
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        {form.color}
                                    </span>
                                </div>
                                <InputError message={errors.color} />
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
