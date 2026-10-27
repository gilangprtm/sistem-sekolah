import { Head } from '@inertiajs/react';
import { BookOpen, BookOpenCheck } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { index as circulation } from '@/routes/library/circulation';
import { index as collection } from '@/routes/library/collection';
import type { BreadcrumbItem } from '@/types';

type Section = 'collection' | 'circulation';

type Props = {
    section: Section;
};

const sections: Record<
    Section,
    {
        title: string;
        description: string;
        icon: typeof BookOpen;
        url: () => string;
    }
> = {
    collection: {
        title: 'Koleksi Buku',
        description: 'Ruang pengelolaan katalog dan koleksi buku perpustakaan.',
        icon: BookOpen,
        url: collection.url,
    },
    circulation: {
        title: 'Sirkulasi',
        description: 'Ruang layanan peminjaman dan pengembalian buku.',
        icon: BookOpenCheck,
        url: circulation.url,
    },
};

export default function LibraryPlaceholder({ section }: Props) {
    const current = sections[section];
    const Icon = current.icon;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Perpustakaan', href: current.url() },
        { title: current.title, href: current.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${current.title} · Perpustakaan`} />
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
                            <h2 className="font-medium">Menu Perpustakaan</h2>
                            <p className="max-w-lg text-sm text-muted-foreground">
                                Kerangka halaman sudah tersedia. Fitur
                                pengelolaan Perpustakaan akan disiapkan pada
                                tahap berikutnya.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
