import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import ModalShell from '@/components/ui/modal-shell';
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
    { title: 'Penggunaan Rombel', href: '/kurikulum/academic-period-rombels' },
];

export default function AcademicPeriodRombelsIndex({
    periods,
    selectedPeriodId,
    usages,
    availableRombels,
}: Props) {
    const confirm = useConfirmation();
    const [open, setOpen] = useState(false);
    const [rombelId, setRombelId] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const selectedPeriod = periods.find(
        (period) => period.id === selectedPeriodId,
    );

    const selectPeriod = (value: string) => {
        router.get(
            '/kurikulum/academic-period-rombels',
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

    const columns: DataTableColumn<Usage>[] = [
        {
            id: 'code',
            header: 'Kode',
            cell: (usage) => usage.rombel.code,
            cellClassName: 'font-medium',
        },
        {
            id: 'name',
            header: 'Nama Rombel',
            cell: (usage) => usage.rombel.name,
        },
        {
            id: 'grade',
            header: 'Tingkat',
            cell: (usage) => usage.rombel.grade_level,
        },
    ];

    const submit = () => {
        router.post(
            '/kurikulum/academic-period-rombels',
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
                        <Plus />
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
                <DataTableShell
                    data={usages}
                    columns={columns}
                    getRowKey={(usage) => usage.id}
                    emptyState={
                        selectedPeriod
                            ? 'Belum ada rombel pada semester ini.'
                            : 'Belum ada semester aktif.'
                    }
                    rowActions={{
                        label: (usage) => `Aksi ${usage.rombel.name}`,
                        render: (usage) => (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={async () => {
                                    if (
                                        await confirm({
                                            title: 'Lepas penggunaan rombel?',
                                            description: `Lepas penggunaan ${usage.rombel.name}?`,
                                        })
                                    ) {
                                        router.delete(
                                            `/kurikulum/academic-period-rombels/${usage.id}`,
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                Lepas penggunaan
                            </DropdownMenuItem>
                        ),
                    }}
                />
                <Dialog open={open} onOpenChange={setOpen}>
                    <ModalShell>
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
                    </ModalShell>
                </Dialog>
            </div>
        </AppLayout>
    );
}
