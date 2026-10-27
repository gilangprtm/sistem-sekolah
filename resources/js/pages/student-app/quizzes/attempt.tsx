import { Head, Link } from '@inertiajs/react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';
import StudentExam from '@/pages/student-app/exam';
import type { ExamEndpoints } from '@/pages/student-app/exam';
import { quizzes as index } from '@/routes/student-app';
import { show, state } from '@/routes/student-app/quizzes';
import { complete, event, start } from '@/routes/student-app/quizzes/attempt';

type Props = {
    attemptId: number | null;
    linkId: number;
    category: string;
    subject: string | null;
    subjectCode: string | null;
    formUrl: string | null;
    started: boolean;
    interruptionCount: number;
    reopen: boolean;
    locked: boolean;
};

export default function StudentQuizAttempt(props: Props) {
    if (props.locked) {
        return (
            <>
                <Head title="Ulangan terkunci" />
                <SecondaryPageShell title="Ulangan" fallbackHref={index.url()}>
                    <div className="flex min-h-80 flex-col items-center justify-center gap-3 p-6 text-center">
                        <h1 className="text-xl font-semibold">
                            Ulangan terkunci
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Hubungi Guru pembuat Ulangan untuk meminta pembukaan
                            blokir.
                        </p>
                        <Button asChild variant="outline">
                            <Link href={index.url()}>
                                Kembali ke daftar Ulangan
                            </Link>
                        </Button>
                    </div>
                </SecondaryPageShell>
            </>
        );
    }

    const endpoints: ExamEndpoints = {
        start: start.url(props.linkId),
        show: show.url(props.linkId),
        events: props.attemptId ? event.url(props.attemptId) : '',
        complete: props.attemptId ? complete.url(props.attemptId) : '',
        index: index.url(),
        state: state.url(props.linkId),
    };

    return <StudentExam {...props} endpoints={endpoints} />;
}
