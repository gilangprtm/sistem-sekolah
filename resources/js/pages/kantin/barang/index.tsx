import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    ChevronsUpDown,
    Plus,
    Search,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import {
    canonicalizeRupiahInput,
    formatCurrencyInput,
    formatRupiah,
} from '@/lib/utils';
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
    image_url: string | null;
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
    image: null as File | null,
    remove_image: false,
    status: 'active',
};

export default function KantinBarangIndex({
    barangs,
    categories,
    filters,
    filterOptions,
}: Props) {
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
            harga: formatCurrencyInput(
                canonicalizeRupiahInput(barang.harga)?.replace('.', ',') ??
                    barang.harga,
            ),
            description: barang.description ?? '',
            image: null,
            remove_image: false,
            status: barang.status,
        });
        setErrors({});
        setRequestError(null);
        setOpen(true);
    };

    const cleanHarga = (value: string): string =>
        canonicalizeRupiahInput(value) ?? value.trim();

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
        const payload = new FormData();
        Object.entries({
            ...form,
            harga: cleanHarga(form.harga),
            category_id: form.category_id || '',
            category_name: form.category_name || '',
            remove_image: form.remove_image ? '1' : '0',
        }).forEach(([key, value]) => {
            if (value !== null && value !== undefined && key !== 'image') {
                payload.append(key, String(value));
            }
        });

        if (form.image) {
            payload.append('image', form.image);
        }

        if (editing) {
            payload.append('_method', 'PATCH');
            router.post(`/kantin/barang/${editing.id}`, payload, options);
        } else {
            router.post('/kantin/barang', payload, options);
        }
    };

    const confirm = useConfirmation();

    const archive = async (barang: Barang) => {
        if (
            await confirm({
                title: 'Arsipkan barang?',
                description: `Arsipkan barang "${barang.name}"?`,
            })
        ) {
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
    const columns: DataTableColumn<Barang>[] = [
        {
            id: 'image',
            header: 'Gambar',
            cell: (barang) =>
                barang.image_url ? (
                    <img
                        src={barang.image_url}
                        alt={barang.name}
                        className="size-12 rounded-md object-cover"
                    />
                ) : (
                    <div className="grid size-12 place-items-center rounded-md bg-muted text-xs text-muted-foreground">
                        —
                    </div>
                ),
        },
        {
            id: 'code',
            header: 'Kode',
            cell: (barang) => barang.kode_barang,
            cellClassName: 'font-medium',
        },
        {
            id: 'name',
            header: 'Nama',
            cell: (barang) => barang.name,
        },
        {
            id: 'category',
            header: 'Kategori',
            cell: (barang) => barang.category.name,
        },
        {
            id: 'brand',
            header: 'Brand',
            cell: (barang) => barang.brand ?? '—',
        },
        {
            id: 'unit',
            header: 'Satuan',
            cell: (barang) => barang.satuan,
        },
        {
            id: 'price',
            header: 'Harga',
            cell: (barang) => formatRupiah(barang.harga),
        },
        {
            id: 'status',
            header: 'Status',
            cell: (barang) => (barang.status === 'active' ? 'Aktif' : 'Arsip'),
        },
    ];

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
                    <Button onClick={openCreate}>
                        <Plus />
                        Tambah Barang
                    </Button>
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
                <DataTableShell
                    data={barangs.data}
                    columns={columns}
                    getRowKey={(barang) => barang.id}
                    emptyState="Belum ada barang Kantin."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectedCount: selected.length,
                        selectAllLabel: 'Pilih semua barang',
                        getRowLabel: (barang) => `Pilih ${barang.name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (barang) => `Aksi ${barang.name}`,
                        render: (barang) => (
                            <>
                                <DropdownMenuItem
                                    onSelect={() => openEdit(barang)}
                                >
                                    Edit
                                </DropdownMenuItem>
                                {barang.status === 'active' && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => archive(barang)}
                                    >
                                        Arsipkan
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: barangs,
                        onPageChange: (page) =>
                            navigate({
                                ...filters,
                                page,
                                per_page: barangs.per_page,
                            }),
                        onPerPageChange: (perPage) =>
                            navigate({
                                ...filters,
                                page: 1,
                                per_page: perPage,
                            }),
                        noun: 'barang',
                    }}
                />
            </div>
            <Dialog open={open} onOpenChange={setOpen}>
                <ModalShell size="medium">
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
                    <div className="@container py-2">
                        <div className="grid gap-4">
                            <div className="grid gap-4 @min-[28rem]:grid-cols-[minmax(0,0.9fr)_minmax(0,1.3fr)] @min-[28rem]:items-start">
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
                                                className="w-full min-w-0 justify-between font-normal"
                                            >
                                                <span className="truncate">
                                                    {form.category_name ||
                                                        'Pilih atau ketik kategori'}
                                                </span>
                                                <ChevronsUpDown className="shrink-0 opacity-50" />
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
                                                            category_name:
                                                                value,
                                                        }))
                                                    }
                                                />
                                                <CommandList>
                                                    <CommandEmpty>
                                                        {form.category_name.trim()
                                                            ? `Buat kategori “${form.category_name.trim()}”`
                                                            : 'Kategori tidak ditemukan.'}
                                                    </CommandEmpty>
                                                    {categories.map(
                                                        (category) => (
                                                            <CommandItem
                                                                key={
                                                                    category.id
                                                                }
                                                                value={
                                                                    category.name
                                                                }
                                                                onSelect={() => {
                                                                    setForm(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            category_id:
                                                                                String(
                                                                                    category.id,
                                                                                ),
                                                                            category_name:
                                                                                category.name,
                                                                        }),
                                                                    );
                                                                    setCategoryOpen(
                                                                        false,
                                                                    );
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
                                                        ),
                                                    )}
                                                </CommandList>
                                            </Command>
                                        </PopoverContent>
                                    </Popover>
                                    <InputError
                                        id="kantin-category-error"
                                        role="alert"
                                        message={
                                            errors.category_id ||
                                            errors.category_name
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="kantin-name">
                                        Nama Barang *
                                    </Label>
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
                            </div>
                            <div className="grid items-start gap-4 @min-[20rem]:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)] @min-[28rem]:grid-cols-[minmax(0,1fr)_minmax(0,0.8fr)_minmax(0,1.2fr)]">
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
                                    <Label htmlFor="kantin-satuan">
                                        Satuan *
                                    </Label>
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
                                <div className="grid gap-2 @min-[20rem]:col-span-2 @min-[28rem]:col-span-1">
                                    <Label htmlFor="kantin-harga">
                                        Harga *
                                    </Label>
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
                                                harga: formatCurrencyInput(
                                                    event.target.value,
                                                    current.harga,
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
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="kantin-image">
                                    Gambar Produk
                                </Label>
                                {editing?.image_url &&
                                    !form.remove_image &&
                                    !form.image && (
                                        <img
                                            src={editing.image_url}
                                            alt={editing.name}
                                            className="h-32 w-full rounded-lg object-cover"
                                        />
                                    )}
                                <div className="flex min-w-0 items-center gap-2">
                                    <Input
                                        id="kantin-image"
                                        className="min-w-0 flex-1"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={(event) =>
                                            setForm((current) => ({
                                                ...current,
                                                image:
                                                    event.target.files?.[0] ??
                                                    null,
                                                remove_image: false,
                                            }))
                                        }
                                    />
                                    {editing?.image_url && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            aria-label="Hapus gambar"
                                            className="shrink-0"
                                            onClick={() =>
                                                setForm((current) => ({
                                                    ...current,
                                                    image: null,
                                                    remove_image: true,
                                                }))
                                            }
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    JPG, PNG, atau WebP. Maksimal 1 MB (1024
                                    KB).
                                </p>
                                <InputError
                                    id="kantin-image-error"
                                    role="alert"
                                    message={errors.image}
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
                </ModalShell>
            </Dialog>
        </AppLayout>
    );
}
