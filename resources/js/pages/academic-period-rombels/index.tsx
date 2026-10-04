import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
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

type Period = {
    id: number;
    code: string;
    name: string;
    academic_year: { year: string };
};

type Rombel = {
    id: number;
    code: string;
    name: string;
    grade_level: string;
    parallel_code: string;
    status?: string;
};

type Usage = {
    id: number;
    rombel: Rombel;
};

type Props = {
    periods: Period[];
    selectedPeriodId: number | null;
    usages: Usage[];
    availableRombels: Rombel[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Penggunaan Rombel', href: '/academic-period-rombels' },
];

export default function AcademicPeriodRombelsIndex({
    periods,
    selectedPeriodId,
    usages,
    availableRombels,
}: Props) {
    const [open, setOpen] = useState(false);
    const [rombelId, setRombelId] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const selectedPeriod = periods.find(
        (period) => period.id === selectedPeriodId,
    );

    const selectPeriod = (value: string) => {
        router.get(
            '/academic-period-rombels',
            { academic_period_id: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const openCreate = () => {
        setRombelId('');
        setErrors({});
        setOpen(true);
    };

    const submit = () => {
        router.post(
            '/academic-period-rombels',
            {
                academic_period_id: selectedPeriodId,
                rombel_id: Number(rombelId),
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onError: setErrors,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Penggunaan Rombel" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        variant="small"
                        title="Penggunaan Rombel"
                        description="Pilih rombel yang digunakan pada Tahun Ajaran dan Semester aktif."
                    />
                    <Button
                        disabled={
                            !selectedPeriodId || availableRombels.length === 0
                        }
                        onClick={openCreate}
                    >
                        Tambah Rombel
                    </Button>
                </div>
                <div className="grid gap-2 rounded-xl border p-4 sm:max-w-xl">
                    <label
                        htmlFor="academic-period"
                        className="text-sm font-medium"
                    >
                        Tahun Ajaran & Semester
                    </label>
                    <SearchableCombobox
                        value={selectedPeriodId?.toString() ?? ''}
                        options={periods.map((period) => ({
                            value: period.id.toString(),
                            label: `${period.academic_year.year} — ${period.name}`,
                        }))}
                        onChange={selectPeriod}
                        placeholder="Pilih semester"
                        searchPlaceholder="Cari semester..."
                        emptyMessage="Semester aktif tidak ditemukan."
                    />
                </div>
                <DataTableShell>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Nama Rombel</TableHead>
                                    <TableHead>Tingkat</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {usages.map((usage) => (
                                    <TableRow key={usage.id}>
                                        <TableCell className="font-medium">
                                            {usage.rombel.code}
                                        </TableCell>
                                        <TableCell>
                                            {usage.rombel.name}
                                        </TableCell>
                                        <TableCell>
                                            {usage.rombel.grade_level}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${usage.rombel.name}`}
                                            >
                                                <DropdownMenuItem
                                                    variant="destructive"
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                `Lepas penggunaan ${usage.rombel.name}?`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/academic-period-rombels/${usage.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Lepas penggunaan
                                                </DropdownMenuItem>
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {usages.length === 0 && (
                                    <DataTableEmptyState colSpan={4}>
                                        {selectedPeriod
                                            ? 'Belum ada rombel pada semester ini.'
                                            : 'Belum ada semester aktif.'}
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </DataTableShell>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Tambah Penggunaan Rombel</DialogTitle>
                            <DialogDescription>
                                Rombel ini akan tersedia untuk dependency Tugas
                                Mengajar pada semester terpilih.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <label
                                htmlFor="rombel"
                                className="text-sm font-medium"
                            >
                                Rombel
                            </label>
                            <SearchableCombobox
                                value={rombelId}
                                options={availableRombels.map((rombel) => ({
                                    value: rombel.id.toString(),
                                    label: `${rombel.code} — ${rombel.name}`,
                                }))}
                                onChange={setRombelId}
                                placeholder="Pilih rombel"
                                searchPlaceholder="Cari rombel..."
                                emptyMessage="Rombel aktif tidak ditemukan."
                            />
                            <InputError message={errors.rombel_id} />
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                disabled={!rombelId || !selectedPeriodId}
                                onClick={submit}
                            >
                                Simpan
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
