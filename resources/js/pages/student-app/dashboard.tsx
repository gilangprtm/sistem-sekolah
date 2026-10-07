import { Head } from '@inertiajs/react';

export default function StudentDashboard() {
    return (
        <>
            <Head title="Portal Siswa" />

            <section className="space-y-5">
                <div>
                    <p className="text-sm text-muted-foreground">Selamat datang</p>
                    <h2 className="text-2xl font-semibold tracking-tight">Portal Siswa</h2>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Fondasi aplikasi siswa sudah aktif. Fitur siswa akan ditambahkan per domain tanpa mencampurnya dengan halaman administrasi.
                    </p>
                </div>

                <div className="rounded-2xl border bg-card p-4 shadow-xs">
                    <p className="font-medium">Aplikasi siap dipasang</p>
                    <p className="mt-1 text-sm leading-6 text-muted-foreground">
                        Browser yang mendukung PWA dapat memasang Portal Siswa ke layar utama setelah service worker aktif.
                    </p>
                </div>
            </section>
        </>
    );
}
