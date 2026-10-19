import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, PackageSearch, Plus, Search } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useInventoryRoomUnitSelection } from '@/hooks/use-inventory-room-unit-selection';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Unit = {
    id: number;
    display_code: string;
    item_name: string;
    item_brand: string | null;
    condition: string;
    room: { id: number; name: string; code: string } | null;
};

type Paginator = {
    data: Unit[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    lookupUnits: Paginator;
    lookupFilters: { search?: string };
};
type Form = { name: string; code: string; description: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris Ruangan', href: '/inventaris/inventory-rooms' },
    { title: 'Tambah', href: '/inventaris/inventory-rooms/create' },
];
const conditionLabels: Record<string, string> = {
    B: 'Baik',
    KB: 'Kurang Baik',
    RB: 'Rusak Berat',
};

export default function InventoryRoomsCreate({
    lookupUnits,
    lookupFilters,
}: Props) {
    const [form, setForm] = useState<Form>({
        name: '',
        code: '',
        description: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const {
        currentLookupSearch,
        lookupOpen,
        lookupSelectedUnits,
        paginatedSelectedUnitRows,
        selectedPaginator,
        selectedUnits,
        setCurrentLookupSearch,
        setLookupOpen,
        setSelectedPage,
        loadLookupPage,
        openLookup,
        confirmLookup,
        removeSelectedUnit,
        toggleLookupUnit,
        setLookupSelection,
        setSelectedPerPage,
    } = useInventoryRoomUnitSelection({
        lookupUnits,
        lookupSearch: lookupFilters.search,
        lookupUrl: '/inventaris/inventory-rooms/create',
    });
    const visibleUnits = lookupUnits.data;
    const selectedUnitColumns: DataTableColumn<Unit>[] = [
        {
            id: 'code',
            header: 'Kode Register',
            cell: (unit) => unit.display_code,
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'item',
            header: 'Nama/Jenis',
            cell: (unit) => (
                <>
                    <div>{unit.item_name}</div>
                    {unit.item_brand && (
                        <div className="text-xs font-normal text-muted-foreground">
                            {unit.item_brand}
                        </div>
                    )}
                </>
            ),
            cellClassName: 'font-medium',
        },
        {
            id: 'condition',
            header: 'Kondisi',
            cell: (unit) =>
                `${unit.condition} — ${conditionLabels[unit.condition] ?? unit.condition}`,
        },
        {
            id: 'room',
            header: 'Ruangan Saat Ini',
            cell: (unit) =>
                unit.room === null
                    ? 'Belum ditempatkan'
                    : `${unit.room.name} (${unit.room.code})`,
        },
    ];
    const lookupColumns: DataTableColumn<Unit>[] = [
        {
            id: 'code',
            header: 'Kode Register',
            cell: (unit) => unit.display_code,
            cellClassName: 'font-mono text-xs',
        },
        {
            id: 'item',
            header: 'Nama/Jenis',
            cell: (unit) => (
                <>
                    <div>{unit.item_name}</div>
                    {unit.item_brand && (
                        <div className="text-xs font-normal text-muted-foreground">
                            {unit.item_brand}
                        </div>
                    )}
                </>
            ),
            cellClassName: 'font-medium',
        },
        {
            id: 'condition',
            header: 'Kondisi',
            cell: (unit) =>
                `${unit.condition} — ${conditionLabels[unit.condition] ?? unit.condition}`,
        },
        {
            id: 'room',
            header: 'Ruangan Saat Ini',
            cell: (unit) =>
                unit.room === null
                    ? 'Belum ditempatkan'
                    : `${unit.room.name} (${unit.room.code})`,
        },
    ];

    const submit = () => {
        setProcessing(true);
        router.post(
            '/inventaris/inventory-rooms',
            { ...form, unit_ids: selectedUnits },
            {
                preserveScroll: true,
                onSuccess: () => setProcessing(false),
                onError: (validationErrors) => {
                    setErrors(validationErrors);
                    setProcessing(false);
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Ruangan Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-start gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link
                            href="/inventaris/inventory-rooms"
                            aria-label="Kembali ke daftar ruangan inventaris"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <Heading
                        variant="small"
                        title="Tambah Ruangan Inventaris"
                        description="Buat ruangan dan pilih register yang ditempatkan saat ini"
                    />
                </div>
                <div className="grid gap-4 rounded-xl border p-4 md:p-6">
                    <section className="grid gap-4 border-b pb-6">
                        <div className="grid gap-2">
                            <Label htmlFor="room-name">Nama Ruangan</Label>
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
                            <Label htmlFor="room-code">Kode Ruangan</Label>
                            <Input
                                id="room-code"
                                value={form.code}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        code: event.target.value,
                                    })
                                }
                                placeholder="R-1A"
                            />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="room-description">Keterangan</Label>
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
                    </section>
                    <section className="grid gap-3">
                        <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 className="text-base font-semibold">
                                    Register Ruangan
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {selectedUnits.length} register
                                    dikonfirmasi.
                                </p>
                            </div>
                            <Button type="button" onClick={openLookup}>
                                <Plus />
                                Tambah Register
                            </Button>
                        </div>
                        <InputError message={errors.unit_ids} />
                        <DataTableShell
                            data={paginatedSelectedUnitRows}
                            columns={selectedUnitColumns}
                            getRowKey={(unit) => unit.id}
                            rowActions={{
                                variant: 'custom',
                                cellClassName: 'text-center',
                                render: (unit) => (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive"
                                        onClick={() =>
                                            removeSelectedUnit(unit.id)
                                        }
                                        aria-label={`Hapus ${unit.display_code} dari pilihan`}
                                    >
                                        Hapus
                                    </Button>
                                ),
                            }}
                            emptyState={
                                <div className="flex flex-col items-center gap-2">
                                    <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                                    Belum ada register dipilih.
                                </div>
                            }
                            tableClassName="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4"
                            pagination={{
                                resource: selectedPaginator,
                                onPageChange: setSelectedPage,
                                onPerPageChange: (perPage) => {
                                    setSelectedPerPage(perPage);
                                    setSelectedPage(1);
                                },
                                noun: 'register',
                            }}
                        />
                    </section>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button asChild variant="outline">
                            <Link href="/inventaris/inventory-rooms">
                                Batal
                            </Link>
                        </Button>
                        <Button onClick={submit} disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan Ruangan'}
                        </Button>
                    </div>
                </div>
            </div>
            <Dialog open={lookupOpen} onOpenChange={setLookupOpen}>
                <DialogContent className="w-[calc(100%-2rem)] max-w-none sm:max-w-none xl:max-w-[90vw] 2xl:max-w-[1600px]">
                    <DialogHeader>
                        <DialogTitle>Tambah Register</DialogTitle>
                        <DialogDescription>
                            Cari dan pilih register, lalu klik OK untuk
                            mengonfirmasi pilihan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-3">
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={currentLookupSearch}
                                    onChange={(event) =>
                                        setCurrentLookupSearch(
                                            event.target.value,
                                        )
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            loadLookupPage(1);
                                        }
                                    }}
                                    placeholder="Cari kode, nama, atau ruangan..."
                                    className="pl-8"
                                    aria-label="Cari register dalam lookup"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => loadLookupPage(1)}
                            >
                                Cari
                            </Button>
                        </div>
                        <DataTableShell
                            data={visibleUnits}
                            columns={lookupColumns}
                            getRowKey={(unit) => unit.id}
                            emptyState="Register tidak ditemukan."
                            className="max-h-[55vh] overflow-auto"
                            tableClassName="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4"
                            selection={{
                                selectedKeys: lookupSelectedUnits,
                                onSelectionChange: setLookupSelection,
                                onRowSelectionChange: (unit, checked) =>
                                    toggleLookupUnit(unit.id, checked),
                                onSelectAll: (checked, rows) =>
                                    setLookupSelection(
                                        checked
                                            ? Array.from(
                                                  new Set([
                                                      ...lookupSelectedUnits,
                                                      ...rows.map(
                                                          (unit) => unit.id,
                                                      ),
                                                  ]),
                                              )
                                            : lookupSelectedUnits.filter(
                                                  (id) =>
                                                      !rows.some(
                                                          (unit) =>
                                                              unit.id === id,
                                                      ),
                                              ),
                                        rows,
                                    ),
                                onClearSelection: () => setLookupSelection([]),
                                selectedCount: lookupSelectedUnits.length,
                                selectAllLabel:
                                    'Pilih semua register di halaman',
                                getRowLabel: (unit) =>
                                    `Pilih ${unit.display_code}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} register dipilih.`,
                                clearSelectionLabel: 'Hapus semua pilihan',
                            }}
                            pagination={{
                                resource: lookupUnits,
                                onPageChange: loadLookupPage,
                                onPerPageChange: (perPage) =>
                                    loadLookupPage(1, perPage),
                                noun: 'register',
                            }}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setLookupOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="button" onClick={confirmLookup}>
                            OK ({lookupSelectedUnits.length})
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
