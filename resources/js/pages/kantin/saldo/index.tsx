import { Head, router } from '@inertiajs/react';
import { Plus, WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDateTime } from '@/lib/date-time';
import {
    canonicalizeRupiahInput,
    formatCurrencyInput,
    formatRupiah,
} from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
    full_name: string;
    kantin_saldo?: { saldo: string } | null;
};
type PurchaseDetail = {
    barang_name: string;
    quantity: number;
    harga: string;
    subtotal: string;
};
type Transaction = {
    id: number;
    type: string;
    amount: string;
    balance_before: string | null;
    balance_after: string | null;
    created_at: string;
    student: { nis: string | null; full_name: string };
    details?: PurchaseDetail[];
};
type Period = 'today' | 'week' | 'month' | 'year' | 'all';

type PageProps = {
    students: {
        data: Student[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    canTopUp: boolean;
    purchaseHistory: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    topUpHistory: {
        data: Transaction[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search?: string; period?: Period; per_page?: number };
    summary: {
        students: number;
        balance: string | number;
        transactions: number;
        topUp: { count: number; amount: string | number };
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kantin', href: '/kantin/saldo' },
    { title: 'Saldo Kantin', href: '/kantin/saldo' },
];
const currency = formatRupiah;

const periodOptions: { value: Period; label: string }[] = [
    { value: 'today', label: 'Hari Ini' },
    { value: 'week', label: 'Minggu Ini' },
    { value: 'month', label: 'Bulan Ini' },
    { value: 'year', label: 'Tahun Ini' },
    { value: 'all', label: 'All Transaksi' },
];

export default function KantinSaldoIndex({
    students,
    canTopUp,
    purchaseHistory,
    topUpHistory,
    filters,
    summary,
}: PageProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [period, setPeriod] = useState<Period>(filters.period ?? 'all');
    const [studentsForTopUp, setStudentsForTopUp] = useState<Student[]>([]);
    const [studentSearch, setStudentSearch] = useState('');
    const [studentId, setStudentId] = useState('');
    const [amount, setAmount] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedStudents, setSelectedStudents] = useState<number[]>([]);
    const [selectedPurchases, setSelectedPurchases] = useState<number[]>([]);
    const [selectedTopUps, setSelectedTopUps] = useState<number[]>([]);
    const [viewedTransaction, setViewedTransaction] =
        useState<Transaction | null>(null);

    const navigate = (params: Record<string, string | number>) =>
        router.get('/kantin/saldo', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });

    useEffect(() => {
        if (!dialogOpen) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            const params = new URLSearchParams();

            if (studentSearch.trim() !== '') {
                params.set('search', studentSearch.trim());
            }

            fetch(`/kantin/saldo/students?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('student lookup failed');
                    }

                    return response.json() as Promise<Student[]>;
                })
                .then(setStudentsForTopUp)
                .catch((error: unknown) => {
                    if (
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    ) {
                        return;
                    }

                    setStudentsForTopUp([]);
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [dialogOpen, studentSearch]);

    const studentOptions = useMemo(
        () =>
            studentsForTopUp.map((student) => ({
                value: String(student.id),
                label: `${student.full_name}${student.nis ? ` (${student.nis})` : ''}${student.nisn ? ` · ${student.nisn}` : ''}`,
            })),
        [studentsForTopUp],
    );

    const resetForm = () => {
        setStudentId('');
        setStudentSearch('');
        setAmount('');
        setErrors({});
    };

    const submitTopUp = () =>
        router.post(
            '/kantin/saldo/top-up',
            {
                student_id: studentId,
                amount: canonicalizeRupiahInput(amount) ?? amount.trim(),
            },
            {
                onError: setErrors,
                onSuccess: () => {
                    resetForm();
                    setDialogOpen(false);
                },
            },
        );
    const studentColumns: DataTableColumn<Student>[] = [
        {
            id: 'identity',
            header: 'NIS / NISN',
            cell: (student) => student.nis ?? student.nisn ?? '-',
        },
        {
            id: 'student',
            header: 'Siswa',
            cell: (student) => student.full_name,
        },
        {
            id: 'balance',
            header: 'Saldo',
            cell: (student) => currency(student.kantin_saldo?.saldo ?? 0),
            cellClassName: 'font-medium',
        },
    ];
    const transactionColumns: DataTableColumn<Transaction>[] = [
        {
            id: 'created-at',
            header: 'Waktu',
            cell: (transaction) => formatWitaDateTime(transaction.created_at),
        },
        {
            id: 'student',
            header: 'Siswa',
            cell: (transaction) => transaction.student.full_name,
        },
        {
            id: 'amount',
            header: 'Jumlah',
            cell: (transaction) => currency(transaction.amount),
        },
    ];
    const transactionActions = {
        label: (transaction: Transaction) => `Aksi transaksi ${transaction.id}`,
        render: (transaction: Transaction) => (
            <DropdownMenuItem
                onSelect={() => setViewedTransaction(transaction)}
            >
                Lihat
            </DropdownMenuItem>
        ),
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Saldo Kantin" />
            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        variant="small"
                        title="Ledger Saldo Siswa"
                        description="Pantau saldo dan riwayat transaksi saldo kantin."
                    />
                    {canTopUp && (
                        <Button
                            type="button"
                            onClick={() => setDialogOpen(true)}
                        >
                            <Plus />
                            Top Up Saldo
                        </Button>
                    )}
                </div>
                <div
                    className="flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Periode transaksi"
                >
                    {periodOptions.map((option) => (
                        <Button
                            key={option.value}
                            type="button"
                            variant={
                                period === option.value ? 'default' : 'outline'
                            }
                            role="tab"
                            aria-selected={period === option.value}
                            onClick={() => {
                                setPeriod(option.value);
                                navigate({
                                    search,
                                    period: option.value,
                                    page: 1,
                                    per_page: topUpHistory.per_page,
                                    purchase_page: 1,
                                    top_up_page: 1,
                                });
                            }}
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">Siswa</CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">
                            {summary.students}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Total Saldo
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">
                            {currency(summary.balance)}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                <WalletCards className="mr-2 inline h-4 w-4" />
                                Transaksi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">
                            {summary.transactions}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Total Top Up
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {currency(summary.topUp.amount)}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {summary.topUp.count} transaksi pada periode ini
                            </p>
                        </CardContent>
                    </Card>
                </div>
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            navigate({
                                search,
                                period,
                                page: 1,
                                purchase_page: 1,
                                top_up_page: 1,
                                per_page: topUpHistory.per_page,
                            });
                        }
                    }}
                    placeholder="Cari nama, NIS, atau NISN siswa..."
                    className="max-w-md"
                />
                <DataTableShell
                    data={students.data}
                    columns={studentColumns}
                    getRowKey={(student) => student.id}
                    emptyState="Belum ada data saldo siswa."
                    selection={{
                        selectedKeys: selectedStudents,
                        onSelectionChange: setSelectedStudents,
                        selectedCount: selectedStudents.length,
                        selectAllLabel: 'Pilih semua siswa',
                        getRowLabel: (student) =>
                            `Pilih siswa ${student.full_name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    pagination={{
                        resource: students,
                        onPageChange: (page) =>
                            navigate({
                                search,
                                period,
                                page,
                                purchase_page: purchaseHistory.current_page,
                                top_up_page: topUpHistory.current_page,
                                per_page: students.per_page,
                            }),
                        onPerPageChange: (per_page) => {
                            setSelectedStudents([]);
                            navigate({
                                search,
                                period,
                                page: 1,
                                purchase_page: 1,
                                top_up_page: 1,
                                per_page,
                            });
                        },
                        noun: 'siswa',
                    }}
                />
                <Tabs defaultValue="purchases" className="gap-4">
                    <TabsList
                        variant="line"
                        className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"
                        aria-label="History transaksi saldo"
                    >
                        <TabsTrigger value="purchases">
                            History Pembelian
                        </TabsTrigger>
                        <TabsTrigger value="top-ups">
                            History Top Up
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="purchases">
                        <DataTableShell
                            data={purchaseHistory.data}
                            columns={transactionColumns}
                            getRowKey={(transaction) => transaction.id}
                            emptyState="Belum ada history pembelian."
                            rowActions={transactionActions}
                            selection={{
                                selectedKeys: selectedPurchases,
                                onSelectionChange: setSelectedPurchases,
                                selectedCount: selectedPurchases.length,
                                selectAllLabel:
                                    'Pilih semua transaksi pembelian',
                                getRowLabel: (transaction) =>
                                    `Pilih transaksi pembelian ${transaction.id}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} baris dipilih.`,
                            }}
                            pagination={{
                                resource: purchaseHistory,
                                onPageChange: (page) =>
                                    navigate({
                                        search,
                                        period,
                                        purchase_page: page,
                                        top_up_page: topUpHistory.current_page,
                                        per_page: purchaseHistory.per_page,
                                    }),
                                onPerPageChange: (per_page) =>
                                    navigate({
                                        search,
                                        period,
                                        purchase_page: 1,
                                        top_up_page: topUpHistory.current_page,
                                        per_page,
                                    }),
                                noun: 'pembelian',
                            }}
                        />
                    </TabsContent>
                    <TabsContent value="top-ups">
                        <DataTableShell
                            data={topUpHistory.data}
                            columns={transactionColumns}
                            getRowKey={(transaction) => transaction.id}
                            emptyState="Belum ada history top up."
                            rowActions={transactionActions}
                            selection={{
                                selectedKeys: selectedTopUps,
                                onSelectionChange: setSelectedTopUps,
                                selectedCount: selectedTopUps.length,
                                selectAllLabel: 'Pilih semua transaksi top up',
                                getRowLabel: (transaction) =>
                                    `Pilih transaksi top up ${transaction.id}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} baris dipilih.`,
                            }}
                            pagination={{
                                resource: topUpHistory,
                                onPageChange: (page) =>
                                    navigate({
                                        search,
                                        period,
                                        purchase_page:
                                            purchaseHistory.current_page,
                                        top_up_page: page,
                                        per_page: topUpHistory.per_page,
                                    }),
                                onPerPageChange: (per_page) =>
                                    navigate({
                                        search,
                                        period,
                                        purchase_page:
                                            purchaseHistory.current_page,
                                        top_up_page: 1,
                                        per_page,
                                    }),
                                noun: 'top up',
                            }}
                        />
                    </TabsContent>
                </Tabs>
            </div>
            <Dialog
                open={viewedTransaction !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setViewedTransaction(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Detail Transaksi</DialogTitle>
                        <DialogDescription>
                            Informasi lengkap transaksi saldo kantin.
                        </DialogDescription>
                    </DialogHeader>
                    {viewedTransaction && (
                        <div className="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <p className="text-muted-foreground">Waktu</p>
                                <p className="font-medium">
                                    {formatWitaDateTime(
                                        viewedTransaction.created_at,
                                    )}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">Siswa</p>
                                <p className="font-medium">
                                    {viewedTransaction.student.full_name}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">
                                    {viewedTransaction.type === 'purchase'
                                        ? 'Total pembelian'
                                        : 'Nilai top up'}
                                </p>
                                <p className="font-medium">
                                    {currency(viewedTransaction.amount)}
                                </p>
                            </div>
                            {viewedTransaction.type === 'purchase' ? (
                                <div className="sm:col-span-2">
                                    <p className="mb-2 text-muted-foreground">
                                        Barang yang dibeli
                                    </p>
                                    <div className="space-y-2">
                                        {(viewedTransaction.details ?? []).map(
                                            (detail, index) => (
                                                <div
                                                    key={`${detail.barang_name}-${index}`}
                                                    className="flex items-center justify-between gap-4"
                                                >
                                                    <span>
                                                        {detail.barang_name} ×{' '}
                                                        {detail.quantity}
                                                    </span>
                                                    <span className="font-medium">
                                                        {currency(
                                                            detail.subtotal,
                                                        )}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="grid w-full grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 sm:col-span-2">
                                    <div className="min-w-0">
                                        <p className="text-muted-foreground">
                                            Saldo sebelum top up
                                        </p>
                                        <p className="font-medium">
                                            {currency(
                                                viewedTransaction.balance_before ??
                                                    0,
                                            )}
                                        </p>
                                    </div>
                                    <span aria-hidden="true">→</span>
                                    <div className="min-w-0 text-right">
                                        <p className="text-muted-foreground">
                                            Saldo setelah
                                        </p>
                                        <p className="font-medium">
                                            {currency(
                                                viewedTransaction.balance_after ??
                                                    0,
                                            )}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setViewedTransaction(null)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            {canTopUp && (
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Top Up Saldo</DialogTitle>
                            <DialogDescription>
                                Pilih siswa aktif dan masukkan nominal saldo.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label>Siswa</Label>
                                <SearchableCombobox
                                    value={studentId}
                                    options={studentOptions}
                                    onChange={setStudentId}
                                    onSearchChange={setStudentSearch}
                                    placeholder="Pilih NIS / NISN siswa"
                                    searchPlaceholder="Cari NIS, NISN, atau nama..."
                                />
                                <InputError message={errors.student_id} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="top-up-amount">
                                    Nominal Saldo
                                </Label>
                                <Input
                                    id="top-up-amount"
                                    value={amount}
                                    onChange={(event) =>
                                        setAmount(
                                            formatCurrencyInput(
                                                event.target.value,
                                                amount,
                                            ),
                                        )
                                    }
                                    placeholder="Contoh: 50.000 atau 5.000,50"
                                    inputMode="decimal"
                                />
                                <InputError message={errors.amount} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="button" onClick={submitTopUp}>
                                Simpan Top Up
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
