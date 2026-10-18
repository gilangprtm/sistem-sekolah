import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Bell,
    BookOpen,
    ClipboardCheck,
    Download,
    Ellipsis,
    Info,
    Library,
    Megaphone,
    ShoppingBasket,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    dismissStudentPwaInstall,
    installStudentPwa,
    useStudentPwaInstall,
} from '@/hooks/use-student-pwa-install';
import { formatWitaDate } from '@/lib/date-time';

type NewsItem = {
    id: number;
    title: string;
    excerpt: string;
    imageUrl: string | null;
    publishedAt: string;
    url: string;
};

type Props = {
    news: NewsItem[];
    student: {
        full_name: string;
        nis: string | null;
        photo_url: string | null;
        has_kantin_pin: boolean;
    } | null;
    rombel: string | null;
};

const menuItems = [
    { label: 'Pengumuman', icon: Megaphone },
    { label: 'Kantin', icon: ShoppingBasket },
    { label: 'Ujian', icon: ClipboardCheck },
    { label: 'Absensi', icon: Bell },
    { label: 'Perpustakaan', icon: Library },
    { label: 'Lainnya', icon: Ellipsis },
];

function NewsSection({ news }: { news: NewsItem[] }) {
    const [failedNewsImages, setFailedNewsImages] = useState<Set<number>>(
        () => new Set(),
    );

    return (
        <section aria-labelledby="student-news-heading" className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <h3 id="student-news-heading" className="font-semibold">
                    Berita Sekolah
                </h3>
                <a
                    href="https://smpn17denpasar.sch.id/berita"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-xs font-medium text-primary"
                >
                    Lihat semua <ArrowUpRight className="size-3.5" />
                </a>
            </div>
            {news.length > 0 ? (
                <div className="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-2">
                    {news.map((item) => (
                        <a
                            key={item.id}
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group w-[76%] max-w-72 shrink-0 snap-start overflow-hidden rounded-2xl border bg-card shadow-xs"
                        >
                            <div className="aspect-[16/9] overflow-hidden bg-muted">
                                {item.imageUrl &&
                                !failedNewsImages.has(item.id) ? (
                                    <img
                                        src={item.imageUrl}
                                        alt=""
                                        loading="lazy"
                                        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        onError={() =>
                                            setFailedNewsImages((current) => {
                                                const next = new Set(current);
                                                next.add(item.id);

                                                return next;
                                            })
                                        }
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center text-muted-foreground">
                                        <BookOpen className="size-8" />
                                    </div>
                                )}
                            </div>
                            <div className="space-y-2 p-3">
                                <p className="text-xs text-muted-foreground">
                                    {formatWitaDate(item.publishedAt)}
                                </p>
                                <h4 className="line-clamp-2 text-sm leading-5 font-semibold">
                                    {item.title}
                                </h4>
                                <p className="line-clamp-2 text-xs leading-5 text-muted-foreground">
                                    {item.excerpt}
                                </p>
                            </div>
                        </a>
                    ))}
                </div>
            ) : (
                <p className="rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
                    Berita sekolah belum tersedia saat ini.
                </p>
            )}
        </section>
    );
}

export default function StudentDashboard({ student, news }: Props) {
    const firstName = student?.full_name?.trim().split(/\s+/)[0] ?? 'Siswa';
    const { canInstall, isDismissed, isInstalled, isIos } =
        useStudentPwaInstall();
    const [isInstalling, setIsInstalling] = useState(false);
    const showInstallPrompt =
        !isInstalled && !isDismissed && (canInstall || isIos);

    return (
        <>
            <Head title="Beranda Siswa" />

            <section className="space-y-6">
                {showInstallPrompt && (
                    <div
                        className="rounded-2xl border border-primary/20 bg-primary/10 p-4 text-foreground dark:border-primary/30 dark:bg-primary/10 dark:text-foreground"
                        role="status"
                    >
                        <div className="flex items-start gap-3">
                            <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/20 text-primary dark:bg-primary/20 dark:text-primary">
                                {canInstall ? (
                                    <Download
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <Info
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold">
                                    Pasang Portal Siswa
                                </p>
                                {canInstall ? (
                                    <p className="mt-1 text-xs leading-5 text-foreground/80 dark:text-foreground/80">
                                        Akses lebih cepat dari layar utama
                                        perangkat.
                                    </p>
                                ) : (
                                    <p className="mt-1 text-xs leading-5 text-foreground/80 dark:text-foreground/80">
                                        Di Safari iPhone/iPad: ketuk Bagikan,
                                        lalu pilih Tambahkan ke Layar Utama.
                                    </p>
                                )}
                                {canInstall && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="mt-3"
                                        disabled={isInstalling}
                                        onClick={async () => {
                                            setIsInstalling(true);

                                            try {
                                                await installStudentPwa();
                                            } finally {
                                                setIsInstalling(false);
                                            }
                                        }}
                                    >
                                        <Download aria-hidden="true" />
                                        {isInstalling
                                            ? 'Menyiapkan…'
                                            : 'Pasang aplikasi'}
                                    </Button>
                                )}
                            </div>
                            <button
                                type="button"
                                className="rounded-md p-1 text-primary hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:text-primary dark:hover:bg-primary/20"
                                aria-label="Tutup petunjuk pemasangan aplikasi"
                                onClick={dismissStudentPwaInstall}
                            >
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                )}

                <div className="relative overflow-hidden rounded-3xl bg-primary p-5 text-primary-foreground shadow-sm">
                    <img
                        src="/images/hero-background.png"
                        alt=""
                        className="absolute inset-0 size-full object-cover opacity-55"
                    />
                    <div className="absolute inset-0 bg-gradient-to-br from-primary/95 via-primary/80 to-primary/65" />
                    <div className="absolute -top-12 -right-10 size-40 rounded-full bg-white/10" />
                    <div className="absolute right-12 -bottom-16 size-32 rounded-full bg-primary-foreground/10" />
                    <div className="relative flex min-h-36 flex-col justify-end">
                        <img
                            src="/images/logo-sekolah.png"
                            alt=""
                            className="mb-4 size-12 object-contain"
                        />
                        <p className="text-sm text-primary-foreground/80">
                            Selamat datang, {firstName}
                        </p>
                        <h3 className="mt-1 text-xl font-semibold">
                            Portal Siswa SMPN 17 Denpasar
                        </h3>
                        <p className="mt-1 text-xs text-primary-foreground/80">
                            Vidyayā Vindate Khīrtim
                        </p>
                    </div>
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">Layanan Siswa</h3>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        {menuItems.map(({ label, icon: Icon }) => (
                            <Link
                                key={label}
                                href={
                                    label === 'Kantin'
                                        ? '/student/kantin'
                                        : label === 'Ujian'
                                          ? '/student/uas'
                                          : '#'
                                }
                                aria-disabled={
                                    label === 'Kantin' || label === 'Ujian'
                                        ? undefined
                                        : 'true'
                                }
                                className="flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border bg-card px-2 py-3 text-center shadow-xs"
                                onClick={
                                    label === 'Kantin' || label === 'Ujian'
                                        ? undefined
                                        : (event) => event.preventDefault()
                                }
                            >
                                <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <Icon className="size-5" />
                                </span>
                                <span className="text-xs font-medium">
                                    {label}
                                </span>
                            </Link>
                        ))}
                    </div>
                </div>

                <NewsSection news={news} />

                {!student && (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Akun ini belum terhubung dengan data siswa. Hubungi
                        administrator sekolah untuk menghubungkan akun.
                    </div>
                )}
            </section>
        </>
    );
}
