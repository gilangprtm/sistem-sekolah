import { Link } from '@inertiajs/react';
import { CalendarDays, ClipboardList, Home, Menu } from 'lucide-react';
import type { ReactNode } from 'react';

const navigation = [
    { label: 'Beranda', href: '/student', icon: Home, active: true },
    { label: 'Jadwal', icon: CalendarDays },
    { label: 'Tugas', icon: ClipboardList },
    { label: 'Lainnya', icon: Menu },
];

export default function StudentAppLayout({
    children,
}: Readonly<{ children: ReactNode }>) {
    return (
        <div className="min-h-dvh bg-muted/30">
            <div className="mx-auto flex min-h-dvh w-full max-w-lg flex-col bg-background shadow-sm">
                <header className="sticky top-0 z-40 flex items-center gap-3 border-b bg-background/95 px-4 pb-3 pt-[max(.75rem,env(safe-area-inset-top))] backdrop-blur">
                    <img
                        src="/images/logo-sekolah.png"
                        alt="Logo SMPN 17 Denpasar"
                        className="size-9 object-contain"
                    />
                    <div>
                        <p className="text-xs text-muted-foreground">SMPN 17 Denpasar</p>
                        <h1 className="text-sm font-semibold">Portal Siswa</h1>
                    </div>
                </header>

                <main className="flex-1 px-4 py-5 pb-28">{children}</main>

                <nav
                    aria-label="Navigasi siswa"
                    className="fixed inset-x-0 bottom-0 z-50 mx-auto grid w-full max-w-lg grid-cols-4 border-t bg-background/95 px-2 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur"
                >
                    {navigation.map(({ label, href, icon: Icon, active }) =>
                        href ? (
                            <Link
                                key={label}
                                href={href}
                                className={[
                                    'flex flex-col items-center gap-1 py-1.5 text-[11px] font-medium',
                                    active ? 'text-violet-700' : 'text-muted-foreground',
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
