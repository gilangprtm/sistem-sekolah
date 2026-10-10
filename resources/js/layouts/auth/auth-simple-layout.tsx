import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
    heroBackground = false,
}: AuthLayoutProps) {
    return (
        <div
            className={
                heroBackground
                    ? 'relative isolate flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden p-6 text-white md:p-10'
                    : 'flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10'
            }
        >
            {heroBackground && (
                <>
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 -z-20 bg-cover bg-center"
                        style={{
                            backgroundImage:
                                "url('/images/hero-background.png')",
                        }}
                    />
                    <div
                        aria-hidden="true"
                        className="absolute inset-0 -z-10 bg-slate-950/75"
                    />
                </>
            )}
            <div
                className={
                    heroBackground
                        ? 'relative z-10 w-full max-w-sm rounded-2xl border border-white/25 bg-slate-800/75 p-6 text-white shadow-2xl backdrop-blur-md md:p-8 [&_[data-slot=checkbox]]:border-white/60 [&_[data-slot=checkbox]]:bg-white/10 [&_[data-slot=checkbox][data-checked]]:bg-white [&_[data-slot=checkbox][data-checked]]:text-slate-900 [&_a]:text-white [&_a]:decoration-white/50 [&_a:hover]:decoration-white [&_button:not([data-slot=button])]:text-white/75 [&_button:not([data-slot=button]):hover]:text-white [&_button[data-slot=button][data-variant=default]]:bg-white/90 [&_button[data-slot=button][data-variant=default]]:text-slate-900 [&_button[data-slot=button][data-variant=default]]:hover:bg-white [&_button[data-slot=button][data-variant=outline]]:border-white/40 [&_button[data-slot=button][data-variant=outline]]:bg-white/10 [&_button[data-slot=button][data-variant=outline]]:text-white [&_div.relative>div>div]:bg-white/30 [&_div.relative>div>span]:bg-slate-700/80 [&_div.relative>div>span]:text-white/75 [&_div.text-muted-foreground]:text-white/75 [&_input]:border-white/30 [&_input]:bg-slate-950/35 [&_input]:text-white [&_input]:placeholder:text-white/60 [&_input]:focus-visible:border-white/70 [&_label]:text-white [&_p.text-green-600]:text-emerald-200 [&_p.text-muted-foreground]:text-white/75 [&_p.text-red-400]:text-red-200 [&_p.text-red-600]:text-red-200'
                        : 'w-full max-w-sm'
                }
            >
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon
                                    className={`size-9 fill-current ${heroBackground ? 'text-white' : 'text-[var(--foreground)] dark:text-white'}`}
                                />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">{title}</h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
