import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import StudentForm from '@/components/students/student-form';
import type {
    StudentAccount,
    StudentFormStudent,
} from '@/components/students/student-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Siswa', href: '/students' },
    { title: 'Edit', href: '#' },
];

type Props = {
    student: StudentFormStudent;
    availableAccounts: StudentAccount[];
};

export default function StudentsEdit({ student, availableAccounts }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Siswa: ${student.full_name}`} />
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
                        <h1 className="text-lg font-semibold">Edit Siswa</h1>
                        <p className="text-sm text-muted-foreground">
                            Perbarui biodata dan hubungan akun Siswa.
                        </p>
                    </div>
                </div>
                <StudentForm
                    mode="edit"
                    student={student}
                    availableAccounts={availableAccounts}
                />
            </div>
        </AppLayout>
    );
}
