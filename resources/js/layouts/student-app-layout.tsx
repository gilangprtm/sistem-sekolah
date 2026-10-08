import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Home, UserRound } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';

const navigation = [
    { label: 'Beranda', href: '/student', icon: Home },
    { label: 'Jadwal', href: '/student/schedule', icon: CalendarDays },
    { label: 'Absen', icon: CalendarDays },
    { label: 'Profile', href: '/student/profile', icon: UserRound },
];

export default function StudentAppLayout({
    children,
}: Readonly<{ children: ReactNode }>) {
    const { url } = usePage();
    const currentPath = new URL(
        url,
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
    ).pathname;
    const isDashboard = currentPath === '/student';
    const isProfile = currentPath === '/student/profile';
    const isSchedule = currentPath === '/student/schedule';
    const [isNavigating, setIsNavigating] = useState(false);

    useEffect(() => {
        const removeStartListener = router.on('start', () => {
            setIsNavigating(true);
        });
        const removeFinishListener = router.on('finish', () => {
            setIsNavigating(false);
        });

        return () => {
            removeStartListener();
            removeFinishListener();
        };
    }, []);

    return (
        <div className="min-h-dvh bg-muted/30">
            <div className="mx-auto flex min-h-dvh w-full max-w-lg flex-col bg-background shadow-sm">
                {isNavigating && (
                    <div
                        className="fixed inset-x-0 top-0 z-[60] mx-auto h-1 max-w-lg overflow-hidden bg-violet-100"
                        role="status"
                        aria-label="Memuat halaman"
                    >
                        <div className="h-full w-1/3 animate-[pulse_1.2s_ease-in-out_infinite] bg-violet-700" />
                    </div>
                )}
                {!isDashboard && !isProfile && !isSchedule && (
                    <header className="sticky top-0 z-40 flex items-center gap-3 border-b bg-background/95 px-4 pt-[max(.75rem,env(safe-area-inset-top))] pb-3 backdrop-blur">
                        <Link
                            href="/student"
                            aria-label="Kembali ke beranda siswa"
                            className="grid size-9 shrink-0 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-700"
                        >
                            <ArrowLeft className="size-5" aria-hidden="true" />
                        </Link>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Portal Siswa
                            </p>
                            <h1 className="text-sm font-semibold">
                                Kembali ke beranda
                            </h1>
                        </div>
                    </header>
                )}

                <main className="flex-1 px-4 py-5 pb-28">{children}</main>

                <nav
                    aria-label="Navigasi siswa"
                    className="fixed inset-x-0 bottom-0 z-50 mx-auto grid w-full max-w-lg grid-cols-4 border-t bg-background/95 px-2 pt-2 pb-[max(.65rem,env(safe-area-inset-bottom))] backdrop-blur"
                >
                    {navigation.map(({ label, href, icon: Icon }) =>
                        href ? (
                            <Link
                                key={label}
                                href={href}
                                aria-current={
                                    currentPath === href ? 'page' : undefined
                                }
                                className={[
                                    'flex flex-col items-center gap-1 py-1.5 text-[11px] font-medium',
                                    currentPath === href
                                        ? 'text-violet-700'
                                        : 'text-muted-foreground',
                                ].join(' ')}
                            >
                                <Icon className="size-5" />
                                {label}
                            </Link>
                        ) : (
                            <span
                                key={label}
                                aria-disabled="true"
                                className="flex flex-col items-center gap-1 py-1.5 text-[11px] text-muted-foreground/45"
                            >
                                <Icon className="size-5" />
                                {label}
                            </span>
                        ),
                    )}
                </nav>
            </div>
        </div>
    );
}
