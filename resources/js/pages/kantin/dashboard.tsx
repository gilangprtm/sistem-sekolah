import { Head, router } from '@inertiajs/react';
import { BarChart3, ShoppingBasket, Users, WalletCards } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDateTime } from '@/lib/date-time';
import { formatRupiah } from '@/lib/utils';
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
type PaginatedResource<Row> = {
    data: Row[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    period: Period;
    summary: Summary;
    transactions: PaginatedResource<Transaction>;
    topBuyers: PaginatedResource<TopBuyer>;
    topItems: PaginatedResource<TopItem>;
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

const transactionColumns: DataTableColumn<Transaction>[] = [
    {
        id: 'time',
        header: 'Waktu',
        cell: (transaction) => formatWitaDateTime(transaction.created_at),
        cellClassName: 'whitespace-nowrap',
    },
    {
        id: 'student',
        header: 'Siswa',
        cell: (transaction) => transaction.full_name,
    },
    {
        id: 'total',
        header: 'Total',
        headerClassName: 'text-right',
        cell: (transaction) => formatRupiah(transaction.total),
        cellClassName: 'text-right font-medium',
    },
];
const topBuyerColumns: DataTableColumn<TopBuyer>[] = [
    {
        id: 'student',
        header: 'Siswa',
        cell: (buyer) => buyer.full_name,
    },
    {
        id: 'transactions',
        header: 'Transaksi',
        headerClassName: 'text-right',
        cell: (buyer) => buyer.transactions,
        cellClassName: 'text-right',
    },
    {
        id: 'total',
        header: 'Total',
        headerClassName: 'text-right',
        cell: (buyer) => formatRupiah(buyer.total),
        cellClassName: 'text-right font-medium',
    },
];
const topItemColumns: DataTableColumn<TopItem>[] = [
    {
        id: 'item',
        header: 'Barang',
        cell: (item) => item.name,
        cellClassName: 'font-medium',
    },
    {
        id: 'unit',
        header: 'Satuan',
        cell: (item) => item.satuan,
    },
    {
        id: 'quantity',
        header: 'Terjual',
        headerClassName: 'text-right',
        cell: (item) => item.quantity,
        cellClassName: 'text-right',
    },
    {
        id: 'total',
        header: 'Total',
        headerClassName: 'text-right',
        cell: (item) => formatRupiah(item.total),
        cellClassName: 'text-right font-medium',
    },
];

export default function KantinDashboard({
    period,
    summary,
    transactions,
    topBuyers,
    topItems,
}: Props) {
    const [selectedTransactions, setSelectedTransactions] = useState<number[]>(
        [],
    );
    const [selectedBuyers, setSelectedBuyers] = useState<number[]>([]);
    const [selectedItems, setSelectedItems] = useState<number[]>([]);

    const navigate = (params: Record<string, string | number>) =>
        router.get('/kantin/dashboard', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    const paginationParams = {
        period,
        transaction_page: transactions.current_page,
        buyer_page: topBuyers.current_page,
        item_page: topItems.current_page,
        per_page: transactions.per_page,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Kantin" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
                            onClick={() => {
                                setSelectedTransactions([]);
                                setSelectedBuyers([]);
                                setSelectedItems([]);
                                navigate({
                                    period: option.value,
                                    transaction_page: 1,
                                    buyer_page: 1,
                                    item_page: 1,
                                    per_page: transactions.per_page,
                                });
                            }}
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
                                {formatRupiah(summary.revenue)}
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
                <Tabs defaultValue="transactions" className="gap-4">
                    <TabsList
                        variant="line"
                        className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"
                        aria-label="Daftar dashboard Kantin"
                    >
                        <TabsTrigger value="transactions">
                            Transaksi Siswa
                        </TabsTrigger>
                        <TabsTrigger value="buyers">Top Pembeli</TabsTrigger>
                        <TabsTrigger value="items">Top Barang</TabsTrigger>
                    </TabsList>
                    <TabsContent value="transactions" className="grid gap-3">
                        <DataTableShell
                            data={transactions.data}
                            columns={transactionColumns}
                            getRowKey={(transaction) => transaction.id}
                            emptyState="Belum ada transaksi pada periode ini."
                            selection={{
                                selectedKeys: selectedTransactions,
                                onSelectionChange: setSelectedTransactions,
                                selectedCount: selectedTransactions.length,
                                selectAllLabel: 'Pilih semua transaksi',
                                getRowLabel: (transaction) =>
                                    `Pilih transaksi ${transaction.id}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} baris dipilih.`,
                            }}
                            pagination={{
                                resource: transactions,
                                onPageChange: (transaction_page) =>
                                    navigate({
                                        ...paginationParams,
                                        transaction_page,
                                    }),
                                onPerPageChange: (per_page) => {
                                    setSelectedTransactions([]);
                                    setSelectedBuyers([]);
                                    setSelectedItems([]);
                                    navigate({
                                        period,
                                        transaction_page: 1,
                                        buyer_page: 1,
                                        item_page: 1,
                                        per_page,
                                    });
                                },
                                noun: 'transaksi',
                            }}
                        />
                    </TabsContent>
                    <TabsContent value="buyers" className="grid gap-3">
                        <DataTableShell
                            data={topBuyers.data}
                            columns={topBuyerColumns}
                            getRowKey={(buyer) => buyer.id}
                            emptyState="Belum ada data pembeli."
                            selection={{
                                selectedKeys: selectedBuyers,
                                onSelectionChange: setSelectedBuyers,
                                selectedCount: selectedBuyers.length,
                                selectAllLabel: 'Pilih semua pembeli',
                                getRowLabel: (buyer) =>
                                    `Pilih pembeli ${buyer.full_name}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} baris dipilih.`,
                            }}
                            pagination={{
                                resource: topBuyers,
                                onPageChange: (buyer_page) =>
                                    navigate({
                                        ...paginationParams,
                                        buyer_page,
                                    }),
                                onPerPageChange: (per_page) => {
                                    setSelectedTransactions([]);
                                    setSelectedBuyers([]);
                                    setSelectedItems([]);
                                    navigate({
                                        period,
                                        transaction_page: 1,
                                        buyer_page: 1,
                                        item_page: 1,
                                        per_page,
                                    });
                                },
                                noun: 'pembeli',
                            }}
                        />
                    </TabsContent>
                    <TabsContent value="items" className="grid gap-3">
                        <DataTableShell
                            data={topItems.data}
                            columns={topItemColumns}
                            getRowKey={(item) => item.id}
                            emptyState="Belum ada barang terjual."
                            selection={{
                                selectedKeys: selectedItems,
                                onSelectionChange: setSelectedItems,
                                selectedCount: selectedItems.length,
                                selectAllLabel: 'Pilih semua barang',
                                getRowLabel: (item) =>
                                    `Pilih barang ${item.name}`,
                                selectedCountLabel: (count, total) =>
                                    `${count} dari ${total} baris dipilih.`,
                            }}
                            pagination={{
                                resource: topItems,
                                onPageChange: (item_page) =>
                                    navigate({
                                        ...paginationParams,
                                        item_page,
                                    }),
                                onPerPageChange: (per_page) => {
                                    setSelectedTransactions([]);
                                    setSelectedBuyers([]);
                                    setSelectedItems([]);
                                    navigate({
                                        period,
                                        transaction_page: 1,
                                        buyer_page: 1,
                                        item_page: 1,
                                        per_page,
                                    });
                                },
                                noun: 'barang',
                            }}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}
