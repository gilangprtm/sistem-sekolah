import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDateTime } from '@/lib/date-time';
import { index } from '@/routes/humas/silaras';
import type { BreadcrumbItem } from '@/types';

type Aspiration = {
    id: number;
    student: { id: number; full_name: string; nisn: string | null };
    content: string;
    created_at: string | null;
};

type Props = {
    aspirations: {
        data: Aspiration[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search: string };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Silaras', href: index.url() }];

export default function SilarasIndex({ aspirations, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [selectedAspiration, setSelectedAspiration] =
        useState<Aspiration | null>(null);
    const [selectedAspirations, setSelectedAspirations] = useState<number[]>(
        [],
    );
    const navigate = (
        page = 1,
        perPage = aspirations.per_page,
        query = search,
    ) => {
        setSelectedAspirations([]);
        router.get(
            index.url(),
            { search: query, page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const columns: DataTableColumn<Aspiration>[] = [
        {
            id: 'sent-at',
            header: 'Waktu Dikirim',
            cell: (aspiration) =>
                aspiration.created_at
                    ? formatWitaDateTime(aspiration.created_at)
                    : '-',
        },
        {
            id: 'student',
            header: 'Siswa',
            cell: (aspiration) => aspiration.student.full_name,
            cellClassName: 'font-medium',
        },
        {
            id: 'nisn',
            header: 'NISN',
            cell: (aspiration) => aspiration.student.nisn ?? '-',
        },
        {
            id: 'content',
            header: 'Aspirasi',
            cell: (aspiration) => (
                <p className="max-w-3xl min-w-64 [overflow-wrap:anywhere] whitespace-pre-wrap">
                    {aspiration.content}
                </p>
            ),
            cellClassName: 'whitespace-normal',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Silaras" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Silaras"
                    description="Sistem Layanan Saran dan Aspirasi Tujuh Belas"
                />
                <form
                    className="@container grid gap-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate();
                    }}
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-[2_1_18rem] gap-2">
                            <Label htmlFor="silaras-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="silaras-search"
                                    className="pl-8"
                                    placeholder="Nama siswa atau NISN…"
                                    maxLength={150}
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit">Filter</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    navigate(1, aspirations.per_page, '');
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </form>
                <DataTableShell
                    data={aspirations.data}
                    columns={columns}
                    getRowKey={(aspiration) => aspiration.id}
                    emptyState="Belum ada aspirasi yang sesuai."
                    selection={{
                        selectedKeys: selectedAspirations,
                        onSelectionChange: setSelectedAspirations,
                        selectAllLabel: 'Pilih semua aspirasi di halaman ini',
                        getRowLabel: (aspiration) =>
                            `Pilih aspirasi dari ${aspiration.student.full_name}`,
                        selectedCountLabel: (count) =>
                            `${count} aspirasi dipilih.`,
                        clearSelectionLabel: 'Batal pilih',
                    }}
                    rowActions={{
                        label: (aspiration) =>
                            `Lihat aspirasi dari ${aspiration.student.full_name}`,
                        render: (aspiration) => (
                            <DropdownMenuItem
                                onClick={() =>
                                    setSelectedAspiration(aspiration)
                                }
                            >
                                Lihat
                            </DropdownMenuItem>
                        ),
                    }}
                    pagination={{
                        resource: aspirations,
                        noun: 'aspirasi',
                        onPageChange: (page) =>
                            navigate(
                                page,
                                aspirations.per_page,
                                filters.search,
                            ),
                        onPerPageChange: (perPage) =>
                            navigate(1, perPage, filters.search),
                    }}
                />
            </div>
            <Dialog
                open={selectedAspiration !== null}
                onOpenChange={(open) => !open && setSelectedAspiration(null)}
            >
                <ModalShell size="medium">
                    <DialogHeader>
                        <DialogTitle>Aspirasi Siswa</DialogTitle>
                    </DialogHeader>
                    {selectedAspiration && (
                        <div className="grid gap-4">
                            <div className="grid gap-1">
                                <p className="font-medium">
                                    {selectedAspiration.student.full_name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    NISN:{' '}
                                    {selectedAspiration.student.nisn ?? '-'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {selectedAspiration.created_at
                                        ? formatWitaDateTime(
                                              selectedAspiration.created_at,
                                          )
                                        : '-'}
                                </p>
                            </div>
                            <p className="text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
                                {selectedAspiration.content}
                            </p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setSelectedAspiration(null)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </ModalShell>
            </Dialog>
        </AppLayout>
    );
}
