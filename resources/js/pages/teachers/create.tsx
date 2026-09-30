import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import TeacherForm from '@/components/teachers/teacher-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Account = { id: number; email: string };
type Props = { availableAccounts: { guru: Account[]; staff: Account[] } };
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Guru & Staff', href: '/teachers' },
    { title: 'Tambah', href: '/teachers/create' },
];
export default function TeachersCreate({ availableAccounts }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Guru & Staff" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-start gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link
                            href="/teachers"
                            aria-label="Kembali ke daftar Guru dan Staff"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-lg font-semibold">
                            Tambah Guru & Staff
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Buat profil Guru atau Staff baru.
                        </p>
                    </div>
                </div>
                <TeacherForm
                    mode="create"
                    availableAccounts={availableAccounts}
                />
            </div>
        </AppLayout>
    );
}
