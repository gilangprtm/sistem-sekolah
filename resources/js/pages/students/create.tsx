import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import StudentForm from '@/components/students/student-form';
import type { StudentAccount } from '@/components/students/student-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Siswa', href: '/students' },
    { title: 'Tambah', href: '/students/create' },
];

type Props = {
    availableAccounts: StudentAccount[];
};

export default function StudentsCreate({ availableAccounts }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tambah Siswa" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-start gap-3">
                    <Button asChild variant="outline" size="icon">
                        <Link
                            href="/students"
                            aria-label="Kembali ke daftar Siswa"
                        >
                            <ArrowLeft />
                        </Link>
                    </Button>
                    <div className="min-w-0">
                        <h1 className="text-lg font-semibold">Tambah Siswa</h1>
                        <p className="text-sm text-muted-foreground">
                            Buat profil biodata Siswa baru.
                        </p>
                    </div>
                </div>
                <StudentForm
                    mode="create"
                    availableAccounts={availableAccounts}
                />
            </div>
        </AppLayout>
    );
}
