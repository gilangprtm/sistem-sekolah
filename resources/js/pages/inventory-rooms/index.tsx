import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronsLeft,
    ChevronsRight,
    MoreHorizontal,
    PackageSearch,
    Plus,
    Search,
} from 'lucide-react';
import { useState } from 'react';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    { title: 'Inventaris Ruangan', href: '/inventory-rooms' },
];

const emptyForm: RoomForm = { name: '', code: '', description: '' };

function getPageNumbers(currentPage: number, pageCount: number) {
    if (pageCount <= 3) {
        return Array.from({ length: pageCount }, (_, index) => index + 1);
    }

    if (currentPage <= 2) {
        return [1, 2, 3];
    }

    if (currentPage >= pageCount - 1) {
        return [pageCount - 2, pageCount - 1, pageCount];
    }

    return [currentPage - 1, currentPage, currentPage + 1];
}

export default function InventoryRoomsIndex({ rooms, filters }: Props) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState<RoomForm>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [placement, setPlacement] = useState(filters.placement ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const pageNumbers = getPageNumbers(rooms.current_page, rooms.last_page);

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
        router.get('/inventory-rooms', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const applyFilters = () => {
        navigate({ ...filterParams(), page: 1, per_page: rooms.per_page });
    };

    const resetFilters = () => {
        setSearch('');
        setPlacement('');
        navigate({ page: 1, per_page: rooms.per_page });
    };

    const goToPage = (page: number) => {
        navigate({ ...filterParams(), page, per_page: rooms.per_page });
    };

    const submit = () => {
        setProcessing(true);
        router.post('/inventory-rooms', form, {
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

        router.delete(`/inventory-rooms/${room.id}`, { preserveScroll: true });
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
                        <Link href="/inventory-rooms/create">
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

                <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                    <div className="flex flex-col gap-3 border-b px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {rooms.total} baris dipilih.
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSelected([])}
                            disabled={!selected.length}
                        >
                            Hapus pilihan
                        </Button>
                    </div>
                    <div className="overflow-x-auto">
                        <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                rooms.data.length > 0 &&
                                                selected.length ===
                                                    rooms.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? rooms.data.map(
                                                              (room) => room.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua ruangan"
                                        />
                                    </TableHead>
                                    <TableHead>Kode Ruangan</TableHead>
                                    <TableHead>Nama Ruangan</TableHead>
                                    <TableHead className="text-right">
                                        Jumlah Unit
                                    </TableHead>
                                    <TableHead>Keterangan</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rooms.data.map((room) => (
                                    <TableRow key={room.id}>
                                        <TableCell className="w-12">
                                            <Checkbox
                                                checked={selected.includes(
                                                    room.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  room.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      room.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih ${room.code}`}
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {room.code}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {room.name}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {room.units_count}
                                        </TableCell>
                                        <TableCell>
                                            {room.description || '-'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        className="text-muted-foreground"
                                                        aria-label={`Aksi ${room.name}`}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem
                                                        onClick={() =>
                                                            router.get(
                                                                `/inventory-rooms/${room.id}/edit`,
                                                            )
                                                        }
                                                    >
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onClick={() =>
                                                            remove(room)
                                                        }
                                                    >
                                                        Hapus
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {rooms.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-center text-muted-foreground"
                                        >
                                            <div className="flex flex-col items-center gap-2 py-8">
                                                <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                                                Belum ada ruangan.
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <div className="flex flex-col gap-3 border-t px-4 py-4 md:flex-row md:items-center md:justify-between">
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {rooms.total} baris dipilih.
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end sm:gap-6">
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Baris per halaman
                                </span>
                                <Select
                                    value={`${rooms.per_page}`}
                                    onValueChange={(value) =>
                                        navigate({
                                            ...filterParams(),
                                            page: 1,
                                            per_page: value,
                                        })
                                    }
                                >
                                    <SelectTrigger className="h-8 w-18">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[10, 20, 30, 40, 50].map((size) => (
                                            <SelectItem
                                                key={size}
                                                value={`${size}`}
                                            >
                                                {size}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="text-sm font-medium text-muted-foreground">
                                Halaman {rooms.current_page} dari{' '}
                                {rooms.last_page}
                            </div>
                            {rooms.last_page > 1 && (
                                <Pagination className="mx-0 w-auto justify-start sm:justify-end">
                                    <PaginationContent className="gap-1">
                                        <PaginationItem className="hidden lg:block">
                                            <PaginationLink
                                                href="#"
                                                aria-label="Halaman pertama"
                                                aria-disabled={
                                                    rooms.current_page === 1
                                                }
                                                className={
                                                    rooms.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        rooms.current_page > 1
                                                    ) {
                                                        goToPage(1);
                                                    }
                                                }}
                                            >
                                                <ChevronsLeft />
                                            </PaginationLink>
                                        </PaginationItem>
                                        <PaginationItem>
                                            <PaginationPrevious
                                                href="#"
                                                text="Prev"
                                                aria-disabled={
                                                    rooms.current_page === 1
                                                }
                                                className={
                                                    rooms.current_page === 1
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        rooms.current_page > 1
                                                    ) {
                                                        goToPage(
                                                            rooms.current_page -
                                                                1,
                                                        );
                                                    }
                                                }}
                                            />
                                        </PaginationItem>
                                        {pageNumbers[0] > 1 && (
                                            <PaginationItem>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        )}
                                        {pageNumbers.map((page) => (
                                            <PaginationItem key={page}>
                                                <PaginationLink
                                                    href="#"
                                                    isActive={
                                                        page ===
                                                        rooms.current_page
                                                    }
                                                    onClick={(event) => {
                                                        event.preventDefault();
                                                        goToPage(page);
                                                    }}
                                                >
                                                    {page}
                                                </PaginationLink>
                                            </PaginationItem>
                                        ))}
                                        {pageNumbers[pageNumbers.length - 1] <
                                            rooms.last_page && (
                                            <PaginationItem>
                                                <PaginationEllipsis />
                                            </PaginationItem>
                                        )}
                                        <PaginationItem>
                                            <PaginationNext
                                                href="#"
                                                text="Next"
                                                aria-disabled={
                                                    rooms.current_page ===
                                                    rooms.last_page
                                                }
                                                className={
                                                    rooms.current_page ===
                                                    rooms.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        rooms.current_page <
                                                        rooms.last_page
                                                    ) {
                                                        goToPage(
                                                            rooms.current_page +
                                                                1,
                                                        );
                                                    }
                                                }}
                                            />
                                        </PaginationItem>
                                        <PaginationItem className="hidden lg:block">
                                            <PaginationLink
                                                href="#"
                                                aria-label="Halaman terakhir"
                                                aria-disabled={
                                                    rooms.current_page ===
                                                    rooms.last_page
                                                }
                                                className={
                                                    rooms.current_page ===
                                                    rooms.last_page
                                                        ? 'pointer-events-none opacity-50'
                                                        : undefined
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();

                                                    if (
                                                        rooms.current_page <
                                                        rooms.last_page
                                                    ) {
                                                        goToPage(
                                                            rooms.last_page,
                                                        );
                                                    }
                                                }}
                                            >
                                                <ChevronsRight />
                                            </PaginationLink>
                                        </PaginationItem>
                                    </PaginationContent>
                                </Pagination>
                            )}
                        </div>
                    </div>
                </div>

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
