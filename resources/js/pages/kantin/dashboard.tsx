import { Head, router } from '@inertiajs/react';
import { BarChart3, ShoppingBasket, Users, WalletCards } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type Period = 'today' | 'week' | 'month' | 'year' | 'all';
type Summary = {
    revenue: string;
    transactions: number;
    buyers: number;
    itemsSold: number;
    activeItems: number;
};
type Transaction = {
    id: number;
    total: string;
    created_at: string;
    full_name: string;
    nis: string | null;
    nisn: string | null;
};
type TopBuyer = {
    id: number;
    full_name: string;
    nis: string | null;
    nisn: string | null;
    transactions: number;
    total: string;
};
type TopItem = {
    id: number;
    name: string;
    satuan: string;
    quantity: number;
    total: string;
};
type Props = {
    period: Period;
    summary: Summary;
    transactions: Transaction[];
    topBuyers: TopBuyer[];
    topItems: TopItem[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kantin', href: '/kantin/dashboard' },
    { title: 'Dashboard', href: '/kantin/dashboard' },
];

const periodOptions: { value: Period; label: string }[] = [
    { value: 'today', label: 'Hari Ini' },
    { value: 'week', label: 'Minggu Ini' },
    { value: 'month', label: 'Bulan Ini' },
    { value: 'year', label: 'Tahun Ini' },
    { value: 'all', label: 'All Transaksi' },
];

const currency = (value: string | number) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
const dateTime = (value: string) =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

export default function KantinDashboard({
    period,
    summary,
    transactions,
    topBuyers,
    topItems,
}: Props) {
    const navigate = (nextPeriod: Period) =>
        router.get(
            '/kantin/dashboard',
            { period: nextPeriod },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Kantin" />
            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Dashboard Kantin"
                    description="Ringkasan penjualan dan aktivitas Kantin."
                />
                <div
                    className="flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Periode dashboard Kantin"
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
                            onClick={() => navigate(option.value)}
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Total Penjualan
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between">
                            <span className="text-2xl font-bold">
                                {currency(summary.revenue)}
                            </span>
                            <WalletCards className="size-5 text-muted-foreground" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Total Transaksi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between">
                            <span className="text-2xl font-bold">
                                {summary.transactions}
                            </span>
                            <BarChart3 className="size-5 text-muted-foreground" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Siswa Belanja
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between">
                            <span className="text-2xl font-bold">
                                {summary.buyers}
                            </span>
                            <Users className="size-5 text-muted-foreground" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Barang Terjual
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between">
                            <span className="text-2xl font-bold">
                                {summary.itemsSold}
                            </span>
                            <ShoppingBasket className="size-5 text-muted-foreground" />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Barang Aktif
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-2xl font-bold">
                            {summary.activeItems}
                        </CardContent>
                    </Card>
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Transaksi Siswa Belanja</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Waktu</TableHead>
                                        <TableHead>Siswa</TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="text-center text-muted-foreground"
                                            >
                                                Belum ada transaksi pada periode
                                                ini.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        transactions.map((transaction) => (
                                            <TableRow key={transaction.id}>
                                                <TableCell className="whitespace-nowrap">
                                                    {dateTime(
                                                        transaction.created_at,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {transaction.full_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {transaction.nis ??
                                                            transaction.nisn ??
                                                            '-'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {currency(
                                                        transaction.total,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Top Pembeli</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Siswa</TableHead>
                                        <TableHead className="text-right">
                                            Transaksi
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {topBuyers.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={3}
                                                className="text-center text-muted-foreground"
                                            >
                                                Belum ada data pembeli.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        topBuyers.map((buyer) => (
                                            <TableRow key={buyer.id}>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {buyer.full_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {buyer.nis ??
                                                            buyer.nisn ??
                                                            '-'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {buyer.transactions}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {currency(buyer.total)}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Top Barang</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Barang</TableHead>
                                    <TableHead>Satuan</TableHead>
                                    <TableHead className="text-right">
                                        Terjual
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {topItems.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-center text-muted-foreground"
                                        >
                                            Belum ada barang terjual.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    topItems.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.name}
                                            </TableCell>
                                            <TableCell>{item.satuan}</TableCell>
                                            <TableCell className="text-right">
                                                {item.quantity}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {currency(item.total)}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
