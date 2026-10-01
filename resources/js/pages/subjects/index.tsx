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

type Subject = { id: number; code: string; name: string; status: string };
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
    { title: 'Mata Pelajaran', href: '/subjects' },
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
    const [form, setForm] = useState({ code: '', name: '', status: 'active' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const filterParams = (): Record<string, string> => ({
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
    });
    const navigate = (params: Record<string, string | number>) =>
        router.get('/subjects', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    const openCreate = () => {
        setEditing(null);
        setForm({ code: '', name: '', status: 'active' });
        setErrors({});
        setOpen(true);
    };
    const openEdit = (subject: Subject) => {
        setEditing(subject);
        setForm({
            code: subject.code,
            name: subject.name,
            status: subject.status,
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
            router.patch(`/subjects/${editing.id}`, form, options);
        } else {
            router.post('/subjects', form, options);
        }
    };

    const archive = (subject: Subject) => {
        if (confirm(`Arsipkan mata pelajaran "${subject.name}"?`)) {
            router.post(
                `/subjects/${subject.id}/archive`,
                {},
                { preserveScroll: true },
            );
        }
    };

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
                    <Button onClick={openCreate}>Tambah Mata Pelajaran</Button>
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
                <DataTableShell>
                    <DataTableToolbar>
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {subjects.total} baris
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
                                                subjects.data.length > 0 &&
                                                selected.length ===
                                                    subjects.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? subjects.data.map(
                                                              (item) => item.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua mata pelajaran"
                                        />
                                    </TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {subjects.data.map((subject) => (
                                    <TableRow key={subject.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.includes(
                                                    subject.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  subject.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      subject.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih ${subject.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {subject.code}
                                        </TableCell>
                                        <TableCell>{subject.name}</TableCell>
                                        <TableCell>
                                            {subject.status === 'active'
                                                ? 'Aktif'
                                                : 'Arsip'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${subject.name}`}
                                            >
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        openEdit(subject)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                {subject.status ===
                                                    'active' && (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            archive(subject)
                                                        }
                                                    >
                                                        Arsipkan
                                                    </DropdownMenuItem>
                                                )}
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {subjects.data.length === 0 && (
                                    <DataTableEmptyState colSpan={5}>
                                        Tidak ada data mata pelajaran.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={subjects}
                        noun="mata pelajaran"
                        onPageChange={(page) =>
                            navigate({
                                ...filterParams(),
                                page,
                                per_page: subjects.per_page,
                            })
                        }
                        onPerPageChange={(perPage) =>
                            navigate({
                                ...filterParams(),
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
