import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import CourseLearningPreview from '@/components/lms/course-learning-preview';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import { courses } from '@/routes/student-app';
import { answers as saveAnswers } from '@/routes/student-app/courses';
import type { LearningCourseContent } from '@/types/lms';

type Props = {
    course: {
        id: number;
        title: string;
        description: string | null;
        subject: string;
        grade_level: string;
        revision_id: number;
        revision_number: number;
        content: LearningCourseContent;
        status: string;
        attempt_token: string;
        answers: Record<string, string>;
    };
};

export default function StudentCourseShow({ course }: Props) {
    const [saving, setSaving] = useState(false);
    const confirm = useConfirmation();
    const { errors } = usePage().props;
    const save = async (answers: Record<string, string>, submit: boolean) => {
        if (saving) {
            return;
        }

        if (
            submit &&
            !(await confirm({
                title: 'Kumpulkan jawaban?',
                description: 'Setelah dikumpulkan, jawaban tidak dapat diubah.',
            }))
        ) {
            return;
        }

        router.post(
            saveAnswers.url(course.id),
            {
                revision_id: course.revision_id,
                attempt_token: course.attempt_token,
                answers,
                submit,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={`${course.title} · Course Saya`} />
            <SecondaryPageShell
                title={course.title}
                fallbackHref={courses.url()}
            >
                <section className="grid min-w-0 gap-3 p-4">
                    <p className="text-sm text-muted-foreground">
                        {course.subject} · Kelas {course.grade_level}
                    </p>
                    {Object.keys(errors).length > 0 && (
                        <AlertError errors={Object.values(errors)} />
                    )}
                    <CourseLearningPreview
                        key={`${course.id}-${course.attempt_token}`}
                        title={course.title}
                        description={course.description}
                        content={course.content}
                        mode="student"
                        initialAnswers={course.answers}
                        status={course.status}
                        saving={saving}
                        onSave={save}
                    />
                </section>
            </SecondaryPageShell>
        </>
    );
}
