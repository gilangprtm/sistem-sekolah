import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, BookOpen, CalendarDays, Megaphone } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatWitaDate } from '@/lib/date-time';
import type { Auth } from '@/types';

type NewsItem = {
    id: number;
    title: string;
    excerpt?: string | null;
    imageUrl?: string | null;
    publishedAt?: string | null;
    url: string;
};

type DashboardProps = {
    auth: Auth;
    news: NewsItem[];
};

function NewsSection({ news }: { news: NewsItem[] }) {
    const [failedNewsImages, setFailedNewsImages] = useState<Set<number>>(
        () => new Set(),
    );

    return (
        <section className="space-y-4" aria-labelledby="school-news-heading">
            <div className="flex items-end justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-sm font-medium text-primary">
                        Kabar sekolah
                    </p>
                    <h2
                        id="school-news-heading"
                        className="text-xl font-semibold tracking-tight"
                    >
                        Berita &amp; pengumuman
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Informasi terbaru dari SMP Negeri 17 Denpasar.
                    </p>
                </div>
                <a
                    href="https://smpn17denpasar.sch.id/berita"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="hidden min-h-11 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium text-primary outline-none hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring sm:inline-flex"
                >
                    Semua berita{' '}
                    <ArrowUpRight className="size-4" aria-hidden="true" />
                </a>
            </div>

            {news.length > 0 ? (
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {news.map((item) => (
                        <a
                            key={item.id}
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group min-w-0 overflow-hidden rounded-xl border bg-card transition-colors outline-none hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <div className="aspect-[16/9] overflow-hidden bg-muted">
                                {item.imageUrl &&
                                !failedNewsImages.has(item.id) ? (
                                    <img
                                        src={item.imageUrl}
                                        alt=""
                                        loading="lazy"
                                        className="size-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                                        onError={() =>
                                            setFailedNewsImages((current) =>
                                                new Set(current).add(item.id),
                                            )
                                        }
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                        <BookOpen
                                            className="size-8"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}
                            </div>
                            <div className="space-y-1.5 p-3">
                                {item.publishedAt && (
                                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <CalendarDays
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {formatWitaDate(item.publishedAt)}
                                    </p>
                                )}
                                <h3 className="line-clamp-2 text-sm leading-5 font-semibold">
                                    {item.title}
                                </h3>
                                {item.excerpt && (
                                    <p className="line-clamp-2 text-xs leading-4 text-muted-foreground">
                                        {item.excerpt}
                                    </p>
                                )}
                            </div>
                        </a>
                    ))}
                </div>
            ) : (
                <Card>
                    <CardContent className="flex items-start gap-3 py-6">
                        <div className="grid size-10 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
                            <Megaphone className="size-5" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <CardTitle className="text-sm">
                                Belum ada berita terbaru
                            </CardTitle>
                            <CardDescription>
                                Silakan kunjungi situs sekolah untuk melihat
                                informasi yang tersedia.
                            </CardDescription>
                            <a
                                href="https://smpn17denpasar.sch.id/berita"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex min-h-11 items-center gap-1 text-sm font-medium text-primary outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                Buka berita sekolah{' '}
                                <ArrowUpRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </a>
                        </div>
                    </CardContent>
                </Card>
            )}
            <a
                href="https://smpn17denpasar.sch.id/berita"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-medium text-primary outline-none hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring sm:hidden"
            >
                Semua berita{' '}
                <ArrowUpRight className="size-4" aria-hidden="true" />
            </a>
        </section>
    );
}

export default function Dashboard() {
    const { auth, news } = usePage<DashboardProps>().props;
    const permissions = auth.permissions ?? [];
    const isSuperAdmin = (auth.roles ?? []).includes('Super Admin');
    const canInventory = isSuperAdmin || permissions.includes('inventory.view');
    const canInventoryDashboard =
        isSuperAdmin || permissions.includes('inventory.dashboard.view');

    return (
        <>
            <Head title="Beranda" />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <Heading
                    variant="small"
                    title="Beranda"
                    description="Portal informasi SMP Negeri 17 Denpasar"
                />

                <section
                    aria-labelledby="school-welcome-heading"
                    className="relative isolate overflow-hidden rounded-2xl border bg-slate-950 text-white shadow-sm"
                >
                    <img
                        src="/images/hero-background.png"
                        alt=""
                        className="absolute inset-0 -z-20 size-full object-cover object-center"
                    />
                    <div className="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/90 via-slate-950/65 to-slate-950/15" />
                    <div className="max-w-3xl space-y-4 px-5 py-10 sm:px-8 sm:py-14 lg:px-12 lg:py-16">
                        <img
                            src="/images/logo-sekolah.png"
                            alt=""
                            className="size-12 object-contain"
                        />
                        <p className="text-sm font-medium text-white/85">
                            Portal SMP Negeri 17 Denpasar
                        </p>
                        <h1
                            id="school-welcome-heading"
                            className="text-2xl leading-tight font-semibold tracking-tight sm:text-4xl"
                        >
                            Selamat datang, {auth.user.name}
                        </h1>
                        <p className="max-w-xl text-sm leading-6 text-white/85 sm:text-base">
                            Temukan berita, pengumuman, dan akses layanan
                            sekolah dalam satu tempat.
                        </p>
                    </div>
                </section>

                <NewsSection news={news} />

                {(canInventory || canInventoryDashboard) && (
                    <section
                        className="space-y-4"
                        aria-labelledby="quick-access-heading"
                    >
                        <div className="space-y-1">
                            <h2
                                id="quick-access-heading"
                                className="text-lg font-semibold tracking-tight"
                            >
                                Akses cepat
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Layanan yang tersedia sesuai akses akun Anda.
                            </p>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Modul Inventaris
                                    </CardTitle>
                                    <CardDescription>
                                        Kelola aset dan inventaris sekolah.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    {canInventory && (
                                        <Link
                                            href="/inventaris/inventory"
                                            className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm font-medium outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            Daftar Inventaris
                                        </Link>
                                    )}
                                    {canInventoryDashboard && (
                                        <Link
                                            href="/inventaris/inventory/dashboard"
                                            className="inline-flex min-h-11 items-center rounded-md border px-3 text-sm font-medium outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            Dashboard Inventaris
                                        </Link>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Beranda', href: '/dashboard' }],
};
