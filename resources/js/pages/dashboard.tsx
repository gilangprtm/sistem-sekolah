import { Head, Link } from '@inertiajs/react';
import {
    LayoutDashboard,
    Lock,
    PackageSearch,
    Users,
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
import { dashboard } from '@/routes';
import type { Auth } from '@/types';

type DashboardProps = {
    auth: Auth;
    stats: {
        users_count?: number;
        roles_count?: number;
    };
    isSuperAdmin: boolean;
};

export default function Dashboard({ stats, isSuperAdmin }: DashboardProps) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Dashboard"
                    description="Ringkasan sistem"
                />

                <div className="grid gap-4 md:grid-cols-3">
                    {isSuperAdmin && (
                        <>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Total User
                                    </CardTitle>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {stats.users_count ?? 0}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">
                                        Total Role
                                    </CardTitle>
                                    <Lock className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {stats.roles_count ?? 0}
                                    </div>
                                </CardContent>
                            </Card>
                        </>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <PackageSearch className="h-4 w-4" />
                                Modul Inventaris
                            </CardTitle>
                            <CardDescription>
                                Kelola aset & inventaris sekolah
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-2">
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/inventory">Daftar Inventaris</Link>
                                </Button>
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/inventory/dashboard">
                                        <LayoutDashboard className="h-4 w-4" />
                                        Dashboard
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
