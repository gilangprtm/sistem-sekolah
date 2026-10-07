import { Link } from '@inertiajs/react';
import { Home, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

const navigation = [
    { label: 'Beranda', href: '/student', icon: Home },
    { label: 'Profil', href: '/student/profile', icon: UserRound, disabled: true },
];

export default function StudentAppLayout({
    children,
}: Readonly<{ children: ReactNode }>) {
    return (
        <div className="min-h-dvh bg-muted/30">
            <div className="mx-auto flex min-h-dvh w-full max-w-lg flex-col bg-background shadow-sm">
                <header className="sticky top-0 z-40 border-b bg-background/95 px-4 py-3 backdrop-blur">
                    <p className="text-xs font-medium text-muted-foreground">SMPN 17 Denpasar</p>
                    <h1 className="text-base font-semibold">Portal Siswa</h1>
                </header>

                <main className="flex-1 px-4 py-5 pb-24">{children}</main>

                <nav
                    aria-label="Navigasi siswa"
                    className="fixed inset-x-0 bottom-0 z-50 mx-auto grid w-full max-w-lg grid-cols-2 border-t bg-background/95 px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur"
                >
                    {navigation.map(({ label, href, icon: Icon, disabled }) =>
                        disabled ? (
                            <span
                                key={label}
                                aria-disabled="true"
                                className="flex flex-col items-center gap-1 py-1.5 text-xs text-muted-foreground/50"
                            >
                                <Icon className="size-5" />
                                {label}
                            </span>
                        ) : (
                            <Link
                                key={label}
                                href={href}
                                className="flex flex-col items-center gap-1 py-1.5 text-xs font-medium text-foreground"
                            >
                                <Icon className="size-5" />
                                {label}
                            </Link>
                        ),
                    )}
                </nav>
            </div>
        </div>
    );
}
