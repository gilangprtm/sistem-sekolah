import { Head } from '@inertiajs/react';
import { BookOpen, BookOpenCheck, LibraryBig } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { design, management, myCourses } from '@/routes/lms';
import type { BreadcrumbItem } from '@/types';

type LmsSection = 'design' | 'management' | 'my-courses';

type Props = {
    section: LmsSection;
};

const sections: Record<
    LmsSection,
    {
        title: string;
        description: string;
        emptyTitle: string;
        icon: typeof BookOpen;
        url: () => string;
    }
> = {
    design: {
        title: 'Perancangan Course',
        description: 'Ruang untuk merancang materi pembelajaran sekolah.',
        emptyTitle: 'Perancangan course akan tersedia pada tahap berikutnya.',
        icon: BookOpen,
        url: design.url,
    },
    management: {
        title: 'Course Management',
        description:
            'Pengelolaan Draft, Course aktif, dan arsip oleh Kurikulum.',
        emptyTitle: 'Pengelolaan course akan tersedia pada tahap berikutnya.',
        icon: LibraryBig,
        url: management.url,
    },
    'my-courses': {
        title: 'Course Saya',
        description: 'Course siap digunakan untuk kegiatan mengajar Anda.',
        emptyTitle: 'Course yang tersedia akan tampil di sini.',
        icon: BookOpenCheck,
        url: myCourses.url,
    },
};

export default function LmsIndex({ section }: Props) {
    const current = sections[section];
    const Icon = current.icon;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'LMS', href: design.url() },
        { title: current.title, href: current.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${current.title} · LMS`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title={current.title}
                    description={current.description}
                />
                <Card>
                    <CardContent className="flex min-h-56 flex-col items-center justify-center gap-3 p-6 text-center">
                        <div className="grid size-12 place-items-center rounded-xl bg-primary/10 text-primary">
                            <Icon className="size-6" aria-hidden="true" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">
                                {current.emptyTitle}
                            </h2>
                            <p className="max-w-lg text-sm text-muted-foreground">
                                Halaman ini saat ini hanya menampilkan kerangka
                                modul LMS. Belum ada course atau fungsi yang
                                dapat digunakan.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
