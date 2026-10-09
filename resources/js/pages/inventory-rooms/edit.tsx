import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, PackageSearch, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { Textarea } from '@/components/ui/textarea';
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

type Room = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    unit_ids: number[];
    units: Unit[];
};

type Props = {
    room: Room;
    lookupUnits: Paginator;
    lookupFilters: { search?: string };
};
type Form = { name: string; code: string; description: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris Ruangan', href: '/inventaris/inventory-rooms' },
    { title: 'Edit', href: '#' },
];
const conditionLabels: Record<string, string> = {
    B: 'Baik',
    KB: 'Kurang Baik',
    RB: 'Rusak Berat',
};

export default function InventoryRoomsEdit({
    room,
    lookupUnits,
    lookupFilters,
}: Props) {
    const [form, setForm] = useState<Form>({
        name: room.name,
        code: room.code,
        description: room.description ?? '',
    });
    const [selectedUnits, setSelectedUnits] = useState<number[]>(room.unit_ids);
    const [selectedUnitDetails, setSelectedUnitDetails] = useState<
        Record<number, Unit>
    >(() => Object.fromEntries(room.units.map((unit) => [unit.id, unit])));
    const [lookupSelectedUnits, setLookupSelectedUnits] = useState<number[]>(
        [],
    );
    const [lookupOpen, setLookupOpen] = useState(false);
    const [lookupSearch, setLookupSearch] = useState(
        lookupFilters.search ?? '',
    );
    const [selectedPage, setSelectedPage] = useState(1);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const visibleUnits = lookupUnits.data;
    const selectedUnitRows = useMemo(
        () =>
            selectedUnits.map((id) => selectedUnitDetails[id]).filter(Boolean),
        [selectedUnitDetails, selectedUnits],
    );
    const selectedPerPage = 10;
    const selectedLastPage = Math.max(
        1,
        Math.ceil(selectedUnitRows.length / selectedPerPage),
    );
    const paginatedSelectedUnitRows = selectedUnitRows.slice(
        (selectedPage - 1) * selectedPerPage,
        selectedPage * selectedPerPage,
    );

    const openLookup = () => {
        setLookupSelectedUnits(selectedUnits);
        setLookupOpen(true);
    };

    const loadLookupPage = (page: number) => {
        router.get(
            `/inventaris/inventory-rooms/${room.id}/edit`,
            {
                lookup_search: lookupSearch || undefined,
                lookup_page: page,
                lookup_per_page: lookupUnits.per_page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const confirmLookup = () => {
        setSelectedUnits(lookupSelectedUnits);
        setSelectedPage(1);
        setLookupOpen(false);
    };
    const toggleLookupUnit = (
        unitId: number,
        checked: boolean | 'indeterminate',
    ) => {
        if (checked === true) {
            const unit = visibleUnits.find(
                (candidate) => candidate.id === unitId,
            );

            if (unit) {
                setSelectedUnitDetails((current) => ({
                    ...current,
                    [unitId]: unit,
                }));
            }
        }

        setLookupSelectedUnits((current) =>
            checked === true
                ? current.includes(unitId)
                    ? current
                    : [...current, unitId]
                : current.filter((id) => id !== unitId),
        );
    };

    const removeSelectedUnit = (unitId: number) => {
        setSelectedUnits((current) => current.filter((id) => id !== unitId));
        setSelectedPage(1);
        setSelectedUnitDetails((current) => {
            const next = { ...current };
            delete next[unitId];

            return next;
        });
    };

    const submit = () => {
        setProcessing(true);
        router.patch(
            `/inventaris/inventory-rooms/${room.id}`,
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

    const unitCells = (unit: Unit, lookup = false) => (
        <>
            {lookup && (
                <TableCell>
                    <Checkbox
                        checked={lookupSelectedUnits.includes(unit.id)}
                        onCheckedChange={(checked) =>
                            toggleLookupUnit(unit.id, checked)
                        }
                        aria-label={`Pilih ${unit.display_code}`}
                    />
                </TableCell>
            )}
            <TableCell className="font-mono text-xs">
                {unit.display_code}
            </TableCell>
            <TableCell className="font-medium">
                <div>{unit.item_name}</div>
                {unit.item_brand && (
                    <div className="text-xs font-normal text-muted-foreground">
                        {unit.item_brand}
                    </div>
                )}
            </TableCell>
            <TableCell>
                {unit.condition} —{' '}
                {conditionLabels[unit.condition] ?? unit.condition}
            </TableCell>
            <TableCell>
                {unit.room === null
                    ? 'Belum ditempatkan'
                    : `${unit.room.name} (${unit.room.code})`}
            </TableCell>
        </>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${room.name}`} />
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
                        title="Edit Ruangan Inventaris"
                        description="Perbarui ruangan dan kelola register yang ditempatkan saat ini"
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
                                Tambah Register
                            </Button>
                        </div>
                        <InputError message={errors.unit_ids} />
                        <div className="overflow-hidden rounded-xl border border-border/70 bg-background">
                            <Table className="**:data-[slot=table-cell]:px-4 **:data-[slot=table-head]:px-4">
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead>Kode Register</TableHead>
                                        <TableHead>Nama/Jenis</TableHead>
                                        <TableHead>Kondisi</TableHead>
                                        <TableHead>Ruangan Saat Ini</TableHead>
                                        <TableHead className="text-right">
                                            Aksi
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {paginatedSelectedUnitRows.map((unit) => (
                                        <TableRow key={unit.id}>
                                            {unitCells(unit)}
                                            <TableCell className="text-right">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        removeSelectedUnit(
                                                            unit.id,
                                                        )
                                                    }
                                                    aria-label={`Hapus ${unit.display_code} dari pilihan`}
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {selectedUnitRows.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                <div className="flex flex-col items-center gap-2">
                                                    <PackageSearch className="h-8 w-8 text-muted-foreground/50" />
                                                    Belum ada register dipilih.
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        {selectedUnitRows.length > 0 && (
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>
                                    Halaman {selectedPage} dari{' '}
                                    {selectedLastPage} ·{' '}
                                    {selectedUnitRows.length} register
                                </span>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setSelectedPage((page) => page - 1)
                                        }
                                        disabled={selectedPage <= 1}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setSelectedPage((page) => page + 1)
                                        }
                                        disabled={
                                            selectedPage >= selectedLastPage
                                        }
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </section>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button asChild variant="outline">
                            <Link href="/inventaris/inventory-rooms">
                                Batal
                            </Link>
                        </Button>
                        <Button onClick={submit} disabled={processing}>
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </Button>
                    </div>
                </div>
            </div>
            <Dialog open={lookupOpen} onOpenChange={setLookupOpen}>
                <DialogContent className="max-w-5xl">
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
                                    value={lookupSearch}
                                    onChange={(event) =>
                                        setLookupSearch(event.target.value)
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
                        <div className="max-h-[55vh] overflow-auto rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            Pilih
                                        </TableHead>
                                        <TableHead>Kode Register</TableHead>
                                        <TableHead>Nama/Jenis</TableHead>
                                        <TableHead>Kondisi</TableHead>
                                        <TableHead>Ruangan Saat Ini</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {visibleUnits.map((unit) => (
                                        <TableRow key={unit.id}>
                                            {unitCells(unit, true)}
                                        </TableRow>
                                    ))}
                                    {visibleUnits.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                Register tidak ditemukan.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        <div className="flex items-center justify-between text-sm text-muted-foreground">
                            <span>
                                Halaman {lookupUnits.current_page} dari{' '}
                                {lookupUnits.last_page} · {lookupUnits.total}{' '}
                                register
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        loadLookupPage(
                                            lookupUnits.current_page - 1,
                                        )
                                    }
                                    disabled={lookupUnits.current_page <= 1}
                                >
                                    Sebelumnya
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        loadLookupPage(
                                            lookupUnits.current_page + 1,
                                        )
                                    }
                                    disabled={
                                        lookupUnits.current_page >=
                                        lookupUnits.last_page
                                    }
                                >
                                    Berikutnya
                                </Button>
                            </div>
                        </div>
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
