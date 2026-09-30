import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import TeacherForm from '@/components/teachers/teacher-form';
import type { TeacherFormTeacher } from '@/components/teachers/teacher-form';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Account = { id: number; email: string };
type Props = {
    teacher: TeacherFormTeacher;
    availableAccounts: { guru: Account[]; staff: Account[] };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Guru & Staff', href: '/teachers' },
    { title: 'Edit', href: '#' },
];
export default function TeachersEdit({ teacher, availableAccounts }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Guru & Staff: ${teacher.full_name}`} />
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
                            Edit Guru & Staff
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Perbarui profil dan akun terhubung.
                        </p>
                    </div>
                </div>
                <TeacherForm
                    mode="edit"
                    teacher={teacher}
                    availableAccounts={availableAccounts}
                />
            </div>
        </AppLayout>
    );
}
