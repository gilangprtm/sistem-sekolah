import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    PackageSearch,
    PiggyBank,
    ThumbsUp,
    ThumbsDown,
    AlertTriangle,
    Layers,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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

type Kpi = {
    total_aset: number;
    total_nilai: number;
    baik: number;
    kurang_baik: number;
    rusak_berat: number;
    total_kelompok: number;
};

type Props = {
    kpis: Kpi;
    statistik_tahun: { tahun_pembelian: number; total: number }[];
    statistik_asal: { asal_perolehan: string; total: number }[];
    statistik_kondisi: { condition: string; label: string; total: number }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inventaris', href: '/inventory' },
    { title: 'Dashboard', href: '/inventory/dashboard' },
];

export default function InventoryDashboard({ kpis, statistik_tahun, statistik_asal, statistik_kondisi }: Props) {
    const formatRupiah = (value: number) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard Inventaris" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Dashboard Inventaris"
                        description="Statistik aset sekolah"
                    />
                    <Button asChild variant="outline">
                        <Link href="/inventory">
                            <ArrowLeft />
                            Daftar Inventaris
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Aset</CardTitle>
                            <PackageSearch className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{kpis.total_aset}</div>
                            <p className="text-xs text-muted-foreground">unit/register</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Nilai Aset</CardTitle>
                            <PiggyBank className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatRupiah(kpis.total_nilai)}</div>
                            <p className="text-xs text-muted-foreground">SUM(qty × harga)</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Aset Baik</CardTitle>
                            <ThumbsUp className="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-600">{kpis.baik}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Aset Kurang Baik</CardTitle>
                            <ThumbsDown className="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600">{kpis.kurang_baik}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Aset Rusak Berat</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{kpis.rusak_berat}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Kelompok Inventaris</CardTitle>
                            <Layers className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{kpis.total_kelompok}</div>
                            <p className="text-xs text-muted-foreground">kelompok barang</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Distribusi Kondisi</CardTitle>
                            <CardDescription>Per unit/register</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Kondisi</TableHead>
                                        <TableHead className="text-right">Jumlah</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {statistik_kondisi.map((k) => (
                                        <TableRow key={k.condition}>
                                            <TableCell>
                                                <span className="font-medium">{k.condition}</span> — {k.label}
                                            </TableCell>
                                            <TableCell className="text-right">{k.total}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Aset per Tahun Pembelian</CardTitle>
                            <CardDescription>Jumlah kelompok per tahun</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {statistik_tahun.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada data.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tahun</TableHead>
                                            <TableHead className="text-right">Kelompok</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {statistik_tahun.map((t) => (
                                            <TableRow key={t.tahun_pembelian}>
                                                <TableCell>{t.tahun_pembelian}</TableCell>
                                                <TableCell className="text-right">{t.total}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Aset per Asal/Cara Perolehan</CardTitle>
                            <CardDescription>Jumlah kelompok per sumber perolehan</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {statistik_asal.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada data.</p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Asal/Cara Perolehan</TableHead>
                                            <TableHead className="text-right">Kelompok</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {statistik_asal.map((a) => (
                                            <TableRow key={a.asal_perolehan}>
                                                <TableCell>{a.asal_perolehan}</TableCell>
                                                <TableCell className="text-right">{a.total}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
