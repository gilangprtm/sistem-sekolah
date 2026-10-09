import { Head, router } from '@inertiajs/react';
import { AlertCircle, Check, ChevronsUpDown, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Category = { id: number; name: string };
type Barang = {
    id: number;
    kode_barang: string;
    name: string;
    brand: string | null;
    satuan: string;
    harga: string;
    description: string | null;
    status: string;
    category: Category;
};
type Props = {
    barangs: {
        data: Barang[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    categories: Category[];
    filters: { search?: string; status?: string; per_page?: number };
    filterOptions: { statuses: { value: string; label: string }[] };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kantin', href: '/kantin/barang' },
    { title: 'Master Barang', href: '/kantin/barang' },
];

const initialForm = {
    category_id: '',
    category_name: '',
    name: '',
    brand: '',
    satuan: '',
    harga: '',
    description: '',
    status: 'active',
};

export default function KantinBarangIndex({
    barangs,
    categories,
    filters,
    filterOptions,
}: Props) {
    useFlashToast();

    useEffect(() => {
        const removeHttpExceptionListener = router.on('httpException', () => {
            toast.error('Terjadi kesalahan server. Silakan coba lagi.');
        });
        const removeNetworkErrorListener = router.on('networkError', () => {
            toast.error('Koneksi bermasalah. Periksa jaringan dan coba lagi.');
        });

        return () => {
            removeHttpExceptionListener();
            removeNetworkErrorListener();
        };
    }, []);

    const [selected, setSelected] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [open, setOpen] = useState(false);
    const [categoryOpen, setCategoryOpen] = useState(false);
    const [editing, setEditing] = useState<Barang | null>(null);
    const [form, setForm] = useState(initialForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [requestError, setRequestError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const navigate = (params: Record<string, string | number>) =>
        router.get('/kantin/barang', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    const openCreate = () => {
        setEditing(null);
        setForm(initialForm);
        setErrors({});
        setRequestError(null);
        setOpen(true);
    };

    const openEdit = (barang: Barang) => {
        setEditing(barang);
        setForm({
            category_id: String(barang.category.id),
            category_name: barang.category.name,
            name: barang.name,
            brand: barang.brand ?? '',
            satuan: barang.satuan,
            harga: formatRupiah(barang.harga),
            description: barang.description ?? '',
            status: barang.status,
        });
        setErrors({});
        setRequestError(null);
        setOpen(true);
    };

    const formatRupiah = (value: string | number): string => {
        const numericValue = Number(value);

        return Number.isFinite(numericValue)
            ? new Intl.NumberFormat('id-ID', {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
              }).format(numericValue)
            : '';
    };

    const formatHargaInput = (value: string): string => {
        const normalized = value.trim().replace(/[^\d,.]/g, '');
        const commaIndex = normalized.lastIndexOf(',');
        const dotIndex = normalized.lastIndexOf('.');
        const separatorIndex = Math.max(commaIndex, dotIndex);
        const fractional =
            separatorIndex >= 0 ? normalized.slice(separatorIndex + 1) : '';
        const isDecimal = fractional.length <= 2 && separatorIndex >= 0;
        const integer = isDecimal
            ? normalized.slice(0, separatorIndex).replace(/\D/g, '')
            : normalized.replace(/\D/g, '');

        if (!integer) {
            return '';
        }

        const formattedInteger = new Intl.NumberFormat('id-ID').format(
            Number(integer),
        );

        return isDecimal
            ? `${formattedInteger},${fractional.replace(/\D/g, '').slice(0, 2)}`
            : formattedInteger;
    };

    const cleanHarga = (value: string): string => {
        const normalized = value.trim().replace(/[^\d,.]/g, '');
        const lastComma = normalized.lastIndexOf(',');
        const lastDot = normalized.lastIndexOf('.');
        const separatorIndex = Math.max(lastComma, lastDot);

        if (separatorIndex < 0) {
            return `${normalized}.00`;
        }

        const fractional = normalized.slice(separatorIndex + 1);
        const integer = normalized.slice(0, separatorIndex).replace(/\D/g, '');

        return fractional.length <= 2 && integer
            ? `${integer}.${fractional.padEnd(2, '0')}`
            : normalized;
    };

    const satuanOptions = ['pcs', 'botol', 'bungkus', 'pak', 'lusin'].map(
        (value) => ({ value, label: value }),
    );

    const submit = () => {
        setRequestError(null);
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onSuccess: () => {
                setErrors({});
                setOpen(false);
            },
            onError: (validationErrors: Record<string, string>) => {
                setErrors(validationErrors);
                setRequestError(null);
            },
            onHttpException: () => {
                setRequestError(
                    'Terjadi kesalahan server. Coba lagi atau tutup dialog.',
                );
            },
            onNetworkError: () => {
                setRequestError(
                    'Koneksi bermasalah. Periksa jaringan lalu coba lagi.',
                );
            },
            onFinish: () => setProcessing(false),
        };
        const payload = {
            ...form,
            harga: cleanHarga(form.harga),
            category_id: form.category_id || undefined,
            category_name: form.category_name || undefined,
        };

        if (editing) {
            router.patch(`/kantin/barang/${editing.id}`, payload, options);
        } else {
            router.post('/kantin/barang', payload, options);
        }
    };

    const archive = (barang: Barang) => {
        if (confirm(`Arsipkan barang "${barang.name}"?`)) {
            router.post(
                `/kantin/barang/${barang.id}/archive`,
                {},
                {
                    preserveScroll: true,
                    onHttpException: () => {
                        toast.error(
                            'Gagal mengarsipkan barang. Silakan coba lagi.',
                        );
                    },
                    onNetworkError: () => {
                        toast.error(
                            'Koneksi bermasalah. Gagal mengarsipkan barang.',
                        );
                    },
                },
            );
        }
    };

    const filterParams = () => ({
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
        page: 1,
        per_page: barangs.per_page,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Barang Kantin" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Master Barang"
                        description="Kelola katalog barang yang dijual Kantin"
                    />
                    <Button onClick={openCreate}>Tambah Barang</Button>
                </div>
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-7">
                            <Label htmlFor="kantin-barang-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    id="kantin-barang-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        navigate(filterParams())
                                    }
                                    placeholder="Kode, nama, brand, kategori..."
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
                        <div className="flex gap-2 md:col-span-2">
                            <Button onClick={() => navigate(filterParams())}>
                                Filter
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    setStatus('');
                                    navigate({
                                        page: 1,
                                        per_page: barangs.per_page,
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
                            {selected.length} dari {barangs.total} baris
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
                                                barangs.data.length > 0 &&
                                                selected.length ===
                                                    barangs.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? barangs.data.map(
                                                              (item) => item.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua barang"
                                        />
                                    </TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Brand</TableHead>
                                    <TableHead>Satuan</TableHead>
                                    <TableHead>Harga</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {barangs.data.map((barang) => (
                                    <TableRow key={barang.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.includes(
                                                    barang.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  barang.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      barang.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih ${barang.name}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {barang.kode_barang}
                                        </TableCell>
                                        <TableCell>{barang.name}</TableCell>
                                        <TableCell>
                                            {barang.category.name}
                                        </TableCell>
                                        <TableCell>
                                            {barang.brand ?? '—'}
                                        </TableCell>
                                        <TableCell>{barang.satuan}</TableCell>
                                        <TableCell>
                                            Rp {formatRupiah(barang.harga)}
                                        </TableCell>
                                        <TableCell>
                                            {barang.status === 'active'
                                                ? 'Aktif'
                                                : 'Arsip'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${barang.name}`}
                                            >
                                                <DropdownMenuItem
                                                    onSelect={() =>
                                                        openEdit(barang)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                {barang.status === 'active' && (
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onSelect={() =>
                                                            archive(barang)
                                                        }
                                                    >
                                                        Arsipkan
                                                    </DropdownMenuItem>
                                                )}
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {barangs.data.length === 0 && (
                                    <DataTableEmptyState colSpan={9}>
                                        Belum ada barang Kantin.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={barangs}
                        onPageChange={(page) =>
                            navigate({
                                ...filters,
                                page,
                                per_page: barangs.per_page,
                            })
                        }
                        onPerPageChange={(perPage) =>
                            navigate({ ...filters, page: 1, per_page: perPage })
                        }
                        noun="barang"
                    />
                </DataTableShell>
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    {requestError && (
                        <Alert variant="destructive" role="alert">
                            <AlertCircle />
                            <AlertTitle>Gagal menyimpan</AlertTitle>
                            <AlertDescription>{requestError}</AlertDescription>
                        </Alert>
                    )}
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit Barang' : 'Tambah Barang'}
                        </DialogTitle>
                        <DialogDescription>
                            Kode barang dibuat otomatis berdasarkan kategori dan
                            urutan per kategori.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="kantin-category-combobox">
                                Kategori *
                            </Label>
                            <Popover
                                open={categoryOpen}
                                onOpenChange={setCategoryOpen}
                            >
                                <PopoverTrigger asChild>
                                    <Button
                                        id="kantin-category-combobox"
                                        type="button"
                                        variant="outline"
                                        role="combobox"
                                        aria-expanded={categoryOpen}
                                        className="justify-between font-normal"
                                    >
                                        {form.category_name ||
                                            'Pilih atau ketik kategori'}
                                        <ChevronsUpDown className="opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="start"
                                    className="w-[var(--radix-popover-trigger-width)] min-w-0 p-0"
                                >
                                    <Command>
                                        <CommandInput
                                            placeholder="Cari atau ketik kategori..."
                                            value={form.category_name}
                                            onValueChange={(value) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    category_id: '',
                                                    category_name: value,
                                                }))
                                            }
                                        />
                                        <CommandList>
                                            <CommandEmpty>
                                                {form.category_name.trim()
                                                    ? `Buat kategori “${form.category_name.trim()}”`
                                                    : 'Kategori tidak ditemukan.'}
                                            </CommandEmpty>
                                            {categories.map((category) => (
                                                <CommandItem
                                                    key={category.id}
                                                    value={category.name}
                                                    onSelect={() => {
                                                        setForm((current) => ({
                                                            ...current,
                                                            category_id: String(
                                                                category.id,
                                                            ),
                                                            category_name:
                                                                category.name,
                                                        }));
                                                        setCategoryOpen(false);
                                                    }}
                                                >
                                                    {category.name}
                                                    <Check
                                                        className={cn(
                                                            'ml-auto',
                                                            form.category_id ===
                                                                String(
                                                                    category.id,
                                                                )
                                                                ? 'opacity-100'
                                                                : 'opacity-0',
                                                        )}
                                                    />
                                                </CommandItem>
                                            ))}
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                            <InputError
                                id="kantin-category-error"
                                role="alert"
                                message={
                                    errors.category_id || errors.category_name
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kantin-name">Nama Barang *</Label>
                            <Input
                                id="kantin-name"
                                aria-describedby={
                                    errors.name
                                        ? 'kantin-name-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.name)}
                                value={form.name}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        name: event.target.value,
                                    }))
                                }
                            />
                            <InputError
                                id="kantin-name-error"
                                role="alert"
                                message={errors.name}
                            />
                        </div>
                        <div className="grid gap-2 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="kantin-brand">Brand</Label>
                                <Input
                                    id="kantin-brand"
                                    value={form.brand}
                                    onChange={(event) =>
                                        setForm((current) => ({
                                            ...current,
                                            brand: event.target.value,
                                        }))
                                    }
                                />
                                <InputError
                                    id="kantin-brand-error"
                                    role="alert"
                                    message={errors.brand}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kantin-satuan">Satuan *</Label>
                                <SearchableCombobox
                                    value={form.satuan}
                                    options={satuanOptions}
                                    onChange={(value) =>
                                        setForm((current) => ({
                                            ...current,
                                            satuan: value,
                                        }))
                                    }
                                    placeholder="Pilih satuan"
                                    searchPlaceholder="Cari satuan..."
                                    emptyMessage="Satuan tidak ditemukan."
                                />
                                <InputError
                                    id="kantin-satuan-error"
                                    role="alert"
                                    message={errors.satuan}
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kantin-harga">Harga *</Label>
                            <Input
                                id="kantin-harga"
                                aria-describedby={
                                    errors.harga
                                        ? 'kantin-harga-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.harga)}
                                inputMode="numeric"
                                value={form.harga}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        harga: formatHargaInput(
                                            event.target.value,
                                        ),
                                    }))
                                }
                                placeholder="Contoh: 5.000"
                            />
                            <InputError
                                id="kantin-harga-error"
                                role="alert"
                                message={errors.harga}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kantin-description">
                                Deskripsi
                            </Label>
                            <Textarea
                                id="kantin-description"
                                value={form.description}
                                onChange={(event) =>
                                    setForm((current) => ({
                                        ...current,
                                        description: event.target.value,
                                    }))
                                }
                            />
                            <InputError
                                id="kantin-description-error"
                                role="alert"
                                message={errors.description}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button onClick={submit} disabled={processing}>
                            {processing
                                ? 'Menyimpan...'
                                : editing
                                  ? 'Simpan perubahan'
                                  : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
