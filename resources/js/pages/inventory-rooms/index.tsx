import { Head, Link, router } from '@inertiajs/react';
import { PackageSearch, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Room = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    units_count: number;
};

type RoomForm = {
    name: string;
    code: string;
    description: string;
};

type Props = {
    rooms: {
        data: Room[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        placement?: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris Ruangan', href: '/inventaris/inventory-rooms' },
];

const emptyForm: RoomForm = { name: '', code: '', description: '' };

export default function InventoryRoomsIndex({ rooms, filters }: Props) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState<RoomForm>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [placement, setPlacement] = useState(filters.placement ?? '');
    const [selected, setSelected] = useState<number[]>([]);

    const columns: DataTableColumn<Room>[] = [
        {
            id: 'code',
            header: 'Kode Ruangan',
            cell: (room) => room.code,
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'name',
            header: 'Nama Ruangan',
            cell: (room) => room.name,
            cellClassName: 'font-medium',
        },
        {
            id: 'unit-count',
            header: 'Jumlah Unit',
            cell: (room) => room.units_count,
            headerClassName: 'text-right',
            cellClassName: 'text-right',
        },
        {
            id: 'description',
            header: 'Keterangan',
            cell: (room) => room.description || '-',
        },
    ];

    const filterParams = (): Record<string, string> => {
        const params: Record<string, string> = {};

        if (search) {
            params.search = search;
        }

        if (placement) {
            params.placement = placement;
        }

        return params;
    };

    const navigate = (params: Record<string, string | number>) => {
        router.get('/inventaris/inventory-rooms', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        setSelected([]);
        navigate({ ...filterParams(), page: 1, per_page: rooms.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setPlacement('');
        setSelected([]);
        navigate({ page: 1, per_page: rooms.per_page });
    };

    const goToPage = (page: number) => {
        setSelected([]);
        navigate({ ...filterParams(), page, per_page: rooms.per_page });
    };

    const submit = () => {
        setProcessing(true);
        router.post('/inventaris/inventory-rooms', form, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setProcessing(false);
            },
            onError: (validationErrors) => {
                setErrors(validationErrors);
                setProcessing(false);
            },
        });
    };

    const remove = (room: Room) => {
        if (
            !confirm(
                `Hapus ruangan "${room.name}"? Unit yang ditempatkan di ruangan ini akan menjadi tanpa ruangan.`,
            )
        ) {
            return;
        }

        router.delete(`/inventaris/inventory-rooms/${room.id}`, {
            preserveScroll: true,
            onSuccess: () =>
                setSelected((current) =>
                    current.filter((selectedId) => selectedId !== room.id),
                ),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inventaris Ruangan" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Inventaris Ruangan"
                        description="Kelola ruangan untuk penempatan unit inventaris saat ini"
                    />
                    <Button asChild>
                        <Link href="/inventaris/inventory-rooms/create">
                            <Plus />
                            Tambah Ruangan
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-5">
                            <Label htmlFor="room-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="room-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            applyFilters();
                                        }
                                    }}
                                    placeholder="Kode / nama / keterangan..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="room-placement">Penempatan</Label>
                            <Select
                                value={placement || 'all'}
                                onValueChange={(value) =>
                                    setPlacement(value === 'all' ? '' : value)
                                }
                            >
                                <SelectTrigger
                                    id="room-placement"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Semua" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua</SelectItem>
                                    <SelectItem value="assigned">
                                        Ada unit
                                    </SelectItem>
                                    <SelectItem value="unassigned">
                                        Belum ada unit
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex gap-2 md:col-span-4">
                            <Button onClick={applyFilters}>Filter</Button>
                            <Button variant="outline" onClick={resetFilters}>
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>

                <DataTableShell
                    data={rooms.data}
                    columns={columns}
                    getRowKey={(room) => room.id}
                    emptyState={
                        <>
                            <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                            Belum ada ruangan.
                        </>
                    }
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua ruangan',
                        getRowLabel: (room) => `Pilih ${room.code}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (room) => `Aksi ${room.name}`,
                        render: (room) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.get(
                                            `/inventaris/inventory-rooms/${room.id}/edit`,
                                        )
                                    }
                                >
                                    Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onClick={() => remove(room)}
                                >
                                    Hapus
                                </DropdownMenuItem>
                            </>
                        ),
                    }}
                    pagination={{
                        resource: rooms,
                        onPageChange: goToPage,
                        onPerPageChange: (perPage) => {
                            setSelected([]);
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            });
                        },
                        noun: 'ruangan',
                    }}
                    tableClassName="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4"
                />

                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Tambah Ruangan</DialogTitle>
                            <DialogDescription>
                                Isi kode, nama, dan keterangan ruangan.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="room-code">Kode</Label>
                                <Input
                                    id="room-code"
                                    value={form.code}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            code: event.target.value,
                                        })
                                    }
                                    placeholder="R-101"
                                />
                                <InputError message={errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="room-name">Nama</Label>
                                <Input
                                    id="room-name"
                                    value={form.name}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            name: event.target.value,
                                        })
                                    }
                                    placeholder="Ruang Kelas 1A"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="room-description">
                                    Keterangan
                                </Label>
                                <Textarea
                                    id="room-description"
                                    value={form.description}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            description: event.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors.description} />
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
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
