import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardCheck,
    HeartHandshake,
    Library,
    ShoppingBasket,
} from 'lucide-react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import SilarasAspirationDialog from '@/components/student-app/silaras-aspiration-dialog';
import { courses, dashboard, kantin, uas } from '@/routes/student-app';

const services = [
    {
        label: 'Kantin',
        description: 'Menu, saldo, dan transaksi kantin.',
        icon: ShoppingBasket,
        href: kantin.url(),
    },
    {
        label: 'Ujian',
        description: 'Informasi dan akses ujian.',
        icon: ClipboardCheck,
        href: uas.url(),
    },
    {
        label: 'Course',
        description: 'Materi dan kegiatan belajar.',
        icon: BookOpen,
        href: courses.url(),
    },
    {
        label: 'Absensi',
        description: 'Belum tersedia.',
        icon: ClipboardCheck,
        href: null,
    },
    {
        label: 'Silaras',
        description: 'Sampaikan aspirasi.',
        icon: HeartHandshake,
        href: null,
        opensModal: true,
    },
    {
        label: 'Perpustakaan',
        description: 'Belum tersedia.',
        icon: Library,
        href: null,
    },
];

export default function StudentServices() {
    return (
        <>
            <Head title="Layanan Siswa" />
            <SecondaryPageShell
                title="Layanan Siswa"
                fallbackHref={dashboard.url()}
            >
                <section
                    aria-label="Daftar layanan siswa"
                    className="grid grid-cols-2 gap-3 p-4"
                >
                    {services.map(
                        ({
                            label,
                            description,
                            icon: Icon,
                            href,
                            opensModal,
                        }) => {
                            const content = (
                                <>
                                    <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <Icon
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-medium">
                                            {label}
                                        </span>
                                        <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                            {description}
                                        </span>
                                    </span>
                                </>
                            );

                            return opensModal ? (
                                <SilarasAspirationDialog
                                    key={label}
                                    trigger={content}
                                    triggerClassName="flex min-h-24 min-w-0 flex-col items-start gap-3 rounded-2xl border bg-card p-3 text-left shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                />
                            ) : href ? (
                                <Link
                                    key={label}
                                    href={href}
                                    className="flex min-h-24 min-w-0 flex-col items-start gap-3 rounded-2xl border bg-card p-3 text-left shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {content}
                                </Link>
                            ) : (
                                <div
                                    key={label}
                                    aria-disabled="true"
                                    className="flex min-h-24 min-w-0 flex-col items-start gap-3 rounded-2xl border bg-card p-3 text-left opacity-60 shadow-xs"
                                >
                                    {content}
                                </div>
                            );
                        },
                    )}
                </section>
            </SecondaryPageShell>
        </>
    );
}
