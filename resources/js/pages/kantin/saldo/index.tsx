import { Head, router } from '@inertiajs/react';
import { WalletCards } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableShell from '@/components/data-table/data-table-shell';
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
import { useFlashToast } from '@/hooks/use-flash-toast';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
    full_name: string;
    kantin_saldo?: { saldo: string } | null;
};
type Transaction = {
    id: number;
    type: string;
    amount: string;
    balance_after: string;
    created_at: string;
    student: { nis: string | null; full_name: string };
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
    ledger: {
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
const currency = (value: string | number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 2,
    }).format(Number(value));

const periodOptions: { value: Period; label: string }[] = [
    { value: 'today', label: 'Hari Ini' },
    { value: 'week', label: 'Minggu Ini' },
    { value: 'month', label: 'Bulan Ini' },
    { value: 'year', label: 'Tahun Ini' },
    { value: 'all', label: 'All Transaksi' },
];

const formatAmountInput = (value: string): string => {
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

export default function KantinSaldoIndex({
    students,
    canTopUp,
    ledger,
    filters,
    summary,
}: PageProps) {
    useFlashToast();

    const [search, setSearch] = useState(filters.search ?? '');
    const [period, setPeriod] = useState<Period>(filters.period ?? 'all');
    const [studentsForTopUp, setStudentsForTopUp] = useState<Student[]>([]);
    const [studentSearch, setStudentSearch] = useState('');
    const [studentId, setStudentId] = useState('');
    const [amount, setAmount] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dialogOpen, setDialogOpen] = useState(false);

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
            { student_id: studentId, amount },
            {
                onError: setErrors,
                onSuccess: () => {
                    resetForm();
                    setDialogOpen(false);
                },
            },
        );

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
                                    ledger_page: 1,
                                    per_page: ledger.per_page,
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
                                ledger_page: 1,
                                per_page: ledger.per_page,
                            });
                        }
                    }}
                    placeholder="Cari nama, NIS, atau NISN siswa..."
                    className="max-w-md"
                />
                <DataTableShell>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>NIS / NISN</TableHead>
                                <TableHead>Siswa</TableHead>
                                <TableHead>Saldo</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.data.map((student) => (
                                <TableRow key={student.id}>
                                    <TableCell>
                                        {student.nis ?? student.nisn ?? '-'}
                                    </TableCell>
                                    <TableCell>{student.full_name}</TableCell>
                                    <TableCell className="font-medium">
                                        {currency(
                                            student.kantin_saldo?.saldo ?? 0,
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <DataTablePagination
                        resource={students}
                        onPageChange={(page) =>
                            navigate({
                                search,
                                period,
                                page,
                                per_page: students.per_page,
                            })
                        }
                        onPerPageChange={(per_page) =>
                            navigate({ search, period, page: 1, per_page })
                        }
                        noun="siswa"
                    />
                </DataTableShell>
                <DataTableShell>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Waktu</TableHead>
                                <TableHead>Siswa</TableHead>
                                <TableHead>Jenis</TableHead>
                                <TableHead>Jumlah</TableHead>
                                <TableHead>Saldo setelah</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ledger.data.map((transaction) => (
                                <TableRow key={transaction.id}>
                                    <TableCell>
                                        {new Date(
                                            transaction.created_at,
                                        ).toLocaleString('id-ID')}
                                    </TableCell>
                                    <TableCell>
                                        {transaction.student.full_name}
                                    </TableCell>
                                    <TableCell>
                                        {transaction.type === 'top_up'
                                            ? 'Top Up'
                                            : transaction.type}
                                    </TableCell>
                                    <TableCell>
                                        {currency(transaction.amount)}
                                    </TableCell>
                                    <TableCell>
                                        {currency(transaction.balance_after)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <DataTablePagination
                        resource={ledger}
                        onPageChange={(page) =>
                            navigate({
                                search,
                                period,
                                ledger_page: page,
                                per_page: ledger.per_page,
                            })
                        }
                        onPerPageChange={(per_page) =>
                            navigate({
                                search,
                                period,
                                ledger_page: 1,
                                per_page,
                            })
                        }
                        noun="transaksi"
                    />
                </DataTableShell>
            </div>
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
                                            formatAmountInput(
                                                event.target.value,
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
