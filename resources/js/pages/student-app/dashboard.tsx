import { Head } from '@inertiajs/react';
import {
    ArrowUpRight,
    Award,
    Bell,
    BookOpen,
    CalendarDays,
    ClipboardList,
    Download,
    Info,
    Library,
    Megaphone,
    Sparkles,
    Trophy,
    UserRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    dismissStudentPwaInstall,
    installStudentPwa,
    useStudentPwaInstall,
} from '@/hooks/use-student-pwa-install';

type NewsItem = {
    id: number;
    title: string;
    excerpt: string;
    imageUrl: string;
    publishedAt: string;
    url: string;
};

type Props = {
    news: NewsItem[];
    student: {
        full_name: string;
        nis: string | null;
    } | null;
    rombel: string | null;
};

const menuItems = [
    { label: 'Jadwal', icon: CalendarDays },
    { label: 'Tugas', icon: ClipboardList },
    { label: 'Nilai', icon: Award },
    { label: 'Presensi', icon: Bell },
    { label: 'Perpustakaan', icon: Library },
    { label: 'Pengumuman', icon: Megaphone },
    { label: 'Profil', icon: UserRound },
    { label: 'Prestasi', icon: Trophy },
    { label: 'Ekstrakurikuler', icon: Sparkles },
];

export default function StudentDashboard({ student, rombel, news }: Props) {
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
                <div className="flex items-center gap-3">
                    <div className="grid size-12 shrink-0 place-items-center rounded-full bg-violet-100 text-violet-700">
                        <UserRound className="size-6" />
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm text-muted-foreground">Halo,</p>
                        <h2 className="truncate text-xl font-semibold tracking-tight">
                            {student?.full_name ?? 'Siswa'}
                        </h2>
                        <p className="truncate text-xs text-muted-foreground">
                            {[
                                rombel,
                                student?.nis ? `NIS ${student.nis}` : null,
                            ]
                                .filter(Boolean)
                                .join(' · ') || 'Portal Siswa'}
                        </p>
                    </div>
                </div>

                {showInstallPrompt && (
                    <div
                        className="rounded-2xl border border-violet-200 bg-violet-50 p-4 text-violet-950 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-100"
                        role="status"
                    >
                        <div className="flex items-start gap-3">
                            <div className="grid size-9 shrink-0 place-items-center rounded-xl bg-violet-200 text-violet-800 dark:bg-violet-900 dark:text-violet-100">
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
                                    <p className="mt-1 text-xs leading-5 text-violet-800 dark:text-violet-200">
                                        Akses lebih cepat dari layar utama
                                        perangkat.
                                    </p>
                                ) : (
                                    <p className="mt-1 text-xs leading-5 text-violet-800 dark:text-violet-200">
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
                                className="rounded-md p-1 text-violet-700 hover:bg-violet-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-700 dark:text-violet-200 dark:hover:bg-violet-900"
                                aria-label="Tutup petunjuk pemasangan aplikasi"
                                onClick={dismissStudentPwaInstall}
                            >
                                <X className="size-4" aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                )}

                <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-violet-950 via-violet-800 to-fuchsia-700 p-5 text-white shadow-sm">
                    <div className="absolute -top-12 -right-10 size-40 rounded-full bg-white/10" />
                    <div className="absolute right-12 -bottom-16 size-32 rounded-full bg-fuchsia-300/10" />
                    <div className="relative flex min-h-36 flex-col justify-end">
                        <img
                            src="/images/logo-sekolah.png"
                            alt=""
                            className="mb-4 size-12 object-contain"
                        />
                        <p className="text-sm text-violet-100">
                            Selamat datang, {firstName}
                        </p>
                        <h3 className="mt-1 text-xl font-semibold">
                            Portal Siswa SMPN 17 Denpasar
                        </h3>
                        <p className="mt-1 text-xs text-violet-100">
                            Belajar · Berkarya · Berkarakter
                        </p>
                    </div>
                </div>

                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">Layanan Siswa</h3>
                        <span className="text-xs text-muted-foreground">
                            Segera tersedia
                        </span>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        {menuItems.map(({ label, icon: Icon }, index) => (
                            <div
                                key={label}
                                aria-disabled="true"
                                className="flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border bg-card px-2 py-3 text-center shadow-xs"
                            >
                                <span
                                    className={[
                                        'grid size-10 place-items-center rounded-xl',
                                        index % 3 === 0
                                            ? 'bg-violet-100 text-violet-700'
                                            : index % 3 === 1
                                              ? 'bg-pink-100 text-pink-700'
                                              : 'bg-sky-100 text-sky-700',
                                    ].join(' ')}
                                >
                                    <Icon className="size-5" />
                                </span>
                                <span className="text-xs font-medium">
                                    {label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                <section aria-labelledby="student-news-heading" className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <h3 id="student-news-heading" className="font-semibold">Berita Sekolah</h3>
                        <a
                            href="https://smpn17denpasar.sch.id/berita"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-1 text-xs font-medium text-violet-600"
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
                                        {item.imageUrl ? (
                                            <img
                                                src={item.imageUrl}
                                                alt=""
                                                loading="lazy"
                                                className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-muted-foreground">
                                                <BookOpen className="size-8" />
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-2 p-3">
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(item.publishedAt).toLocaleDateString('id-ID', {
                                                day: 'numeric',
                                                month: 'long',
                                                year: 'numeric',
                                            })}
                                        </p>
                                        <h4 className="line-clamp-2 text-sm font-semibold leading-5">
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

                {!student && (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Akun ini belum terhubung dengan data siswa. Hubungi
                        administrator sekolah untuk menghubungkan akun.
                    </div>
                )}

                <div className="flex items-center gap-3 rounded-2xl border bg-card p-4">
                    <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-100 text-violet-700">
                        <BookOpen className="size-5" />
                    </div>
                    <div>
                        <p className="text-sm font-medium">
                            Satu aplikasi untuk kebutuhan siswa
                        </p>
                        <p className="mt-0.5 text-xs leading-5 text-muted-foreground">
                            Fitur akan aktif bertahap sesuai modul yang tersedia
                            di sistem sekolah.
                        </p>
                    </div>
                </div>
            </section>
        </>
    );
}
