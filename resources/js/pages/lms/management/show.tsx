import { Head, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Check } from 'lucide-react';
import { useState } from 'react';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import AlertError from '@/components/alert-error';
import CourseLearningPreview from '@/components/lms/course-learning-preview';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDate } from '@/lib/date-time';
import { design, management } from '@/routes/lms';
import { activate, archive, restore } from '@/routes/lms/management';
import type { BreadcrumbItem } from '@/types';
import type { CourseContent } from '@/types/lms';

type Revision = {
    id: number;
    number: number;
    title: string;
    description: string | null;
    status: string;
    content: CourseContent;
    designer: string;
    created_at: string;
    is_active: boolean;
    revision_id: string;
};
type Props = {
    course: {
        id: number;
        subject: string;
        grade_level: string;
        designer: string;
        archived_at: string | null;
        revisions: Revision[];
    };
    actions: {
        can_activate: boolean;
        can_archive: boolean;
        can_restore: boolean;
    };
};

export default function LmsCourseManagementShow({ course, actions }: Props) {
    const revision = course.revisions[0];
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const confirm = useConfirmation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'LMS', href: design.url() },
        { title: 'Course Management', href: management.url() },
        { title: revision.title, href: '#' },
    ];
    const activateCourse = async () => {
        if (
            busy ||
            !(await confirm({
                title: 'Aktifkan Course?',
                description:
                    'Revisi ini menjadi versi aktif untuk Guru dan Siswa. Siswa mengerjakan dari awal; jawaban dan nilai revisi sebelumnya tetap tersimpan sebagai riwayat.',
            }))
        ) {
            return;
        }

        setError('');
        router.post(
            activate.url(course.id),
            { revision_id: revision.revision_id },
            {
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (errors) => setError(Object.values(errors).join(' ')),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${revision.title} · Course Management`} />
            <SecondaryPageShell
                title="Pratinjau Course"
                description={`${course.subject} · Kelas ${course.grade_level} · Perancang ${revision.designer}`}
                backHref={management.url()}
                backLabel="Kembali ke Course Management"
                actions={
                    <>
                        {actions.can_activate && (
                            <Button disabled={busy} onClick={activateCourse}>
                                <Check /> Aktifkan
                            </Button>
                        )}
                        {actions.can_restore && (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={async () => {
                                    if (
                                        busy ||
                                        !(await confirm({
                                            title: 'Pulihkan Course sebagai Draft?',
                                            description:
                                                'Course akan keluar dari arsip sebagai Draft. Course perlu diaktifkan kembali untuk menjadi aktif.',
                                        }))
                                    ) {
                                        return;
                                    }

                                    setError('');
                                    router.post(
                                        restore.url(course.id),
                                        { revision_id: revision.revision_id },
                                        {
                                            onStart: () => setBusy(true),
                                            onFinish: () => setBusy(false),
                                            onError: (errors) =>
                                                setError(
                                                    Object.values(errors).join(
                                                        ' ',
                                                    ),
                                                ),
                                        },
                                    );
                                }}
                            >
                                <ArchiveRestore /> Pulihkan sebagai Draft
                            </Button>
                        )}
                        {actions.can_archive && !course.archived_at && (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={async () => {
                                    if (
                                        await confirm({
                                            title: 'Arsipkan Course?',
                                            description:
                                                'Course dihentikan untuk penggunaan aktif. Riwayat dan hasil tersimpan tetap dipertahankan.',
                                        })
                                    ) {
                                        router.post(
                                            archive.url(course.id),
                                            {
                                                revision_id:
                                                    revision.revision_id,
                                            },
                                            {
                                                onStart: () => setBusy(true),
                                                onFinish: () => setBusy(false),
                                                onError: (errors) =>
                                                    setError(
                                                        Object.values(
                                                            errors,
                                                        ).join(' '),
                                                    ),
                                            },
                                        );
                                    }
                                }}
                            >
                                <Archive /> Arsipkan
                            </Button>
                        )}
                    </>
                }
            >
                {error && (
                    <AlertError
                        title="Tindakan belum berhasil"
                        errors={[error]}
                    />
                )}
                {course.archived_at && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Course diarsipkan pada{' '}
                        {formatWitaDate(course.archived_at)}. Riwayat
                        dipertahankan.
                    </p>
                )}
                <CourseLearningPreview
                    key={`${course.id}-${revision.revision_id}`}
                    title={revision.title}
                    description={revision.description}
                    content={revision.content}
                />
            </SecondaryPageShell>
        </AppLayout>
    );
}
