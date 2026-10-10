import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpen, GraduationCap, LogIn } from 'lucide-react';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    const isAuthenticated = Boolean(auth.user);

    return (
        <>
            <Head title="Sistem Sekolah" />
            <div className="relative isolate min-h-screen overflow-hidden bg-slate-950 text-white">
                <div
                    aria-hidden="true"
                    className="absolute inset-0 -z-20 bg-cover bg-center"
                    style={{
                        backgroundImage: "url('/images/hero-background.png')",
                    }}
                />
                <div
                    aria-hidden="true"
                    className="absolute inset-0 -z-10 bg-slate-950/75"
                />

                <header className="mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-12">
                    <Link
                        href={isAuthenticated ? dashboard() : '/'}
                        className="flex items-center gap-3 rounded-lg focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                        aria-label="Sistem Sekolah SMP Negeri 17 Denpasar"
                    >
                        <img
                            src="/images/logo-sekolah.png"
                            alt=""
                            className="size-11 object-contain sm:size-14"
                            width="1080"
                            height="1080"
                        />
                        <span className="hidden text-sm font-semibold tracking-wide sm:block sm:text-base">
                            SMP Negeri 17 Denpasar
                        </span>
                    </Link>

                    <nav
                        aria-label="Navigasi utama"
                        className="flex items-center gap-2 sm:gap-3"
                    >
                        {isAuthenticated ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex min-h-10 items-center gap-2 rounded-full border border-white/40 bg-white/10 px-4 text-sm font-medium backdrop-blur transition hover:bg-white/20 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                            >
                                Dashboard
                                <ArrowRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-medium text-white/90 transition hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                                >
                                    <LogIn
                                        aria-hidden="true"
                                        className="size-4"
                                    />
                                    Masuk
                                </Link>
                                <Link
                                    href={register()}
                                    className="inline-flex min-h-10 items-center rounded-full bg-white px-4 text-sm font-semibold text-slate-900 shadow-lg transition hover:bg-white/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                                >
                                    Daftar
                                </Link>
                            </>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex min-h-[calc(100vh-6.5rem)] w-full max-w-7xl items-center px-5 pt-8 pb-14 sm:px-8 lg:px-12 lg:pb-24">
                    <section className="max-w-3xl">
                        <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-2 text-sm font-medium text-white/90 backdrop-blur">
                            <GraduationCap
                                aria-hidden="true"
                                className="size-4 text-[#A99BEF]"
                            />
                            <span>Portal Digital Sekolah</span>
                        </div>
                        <h1 className="max-w-3xl text-4xl leading-tight font-bold tracking-tight text-balance sm:text-6xl lg:text-7xl">
                            Sistem Sekolah yang terhubung, mudah, dan bermakna.
                        </h1>
                        <p className="mt-6 max-w-2xl text-base leading-7 text-white/80 sm:text-lg sm:leading-8">
                            Satu ruang digital untuk mendukung pembelajaran,
                            administrasi, dan kolaborasi seluruh warga SMP
                            Negeri 17 Denpasar.
                        </p>
                        <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                            {isAuthenticated ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-[#160F42] px-6 text-sm font-semibold text-white shadow-xl ring-1 ring-white/20 transition hover:bg-[#30236D] focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                                >
                                    Buka Dashboard
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-[#160F42] px-6 text-sm font-semibold text-white shadow-xl ring-1 ring-white/20 transition hover:bg-[#30236D] focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                                >
                                    Masuk ke Sistem
                                    <ArrowRight
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </Link>
                            )}
                            <span className="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-white/25 bg-black/10 px-6 text-sm text-white/80 backdrop-blur">
                                <BookOpen
                                    aria-hidden="true"
                                    className="size-5"
                                />
                                Belajar dan bertumbuh bersama
                            </span>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
