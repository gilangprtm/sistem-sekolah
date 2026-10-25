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
        answerHtml: Record<string, string>;
        submitted_page_ids: string[];
    };
};

export default function StudentCourseShow({ course }: Props) {
    const [saving, setSaving] = useState(false);
    const confirm = useConfirmation();
    const { errors } = usePage().props;
    const save = async (
        pageId: string,
        answers: Record<string, string>,
        answerFormats: Record<string, 'html'>,
    ) => {
        if (saving) {
            return;
        }

        if (
            !(await confirm({
                title: 'Kumpulkan jawaban?',
                description:
                    'Hanya jawaban pada tab ini yang dikumpulkan dan tidak dapat diubah. Tab lain tetap dapat dikerjakan.',
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
                answer_formats: answerFormats,
                page_id: pageId,
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
                        key={`${course.id}-${course.revision_id}`}
                        title={course.title}
                        description={course.description}
                        content={course.content}
                        mode="student"
                        initialAnswers={course.answers}
                        initialAnswerHtml={course.answerHtml}
                        status={course.status}
                        submittedPageIds={course.submitted_page_ids}
                        saving={saving}
                        onSave={save}
                    />
                </section>
            </SecondaryPageShell>
        </>
    );
}
