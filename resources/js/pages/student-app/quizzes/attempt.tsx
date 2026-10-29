import ExamLockedScreen from '@/components/student-app/exam-locked-screen';
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
            <ExamLockedScreen
                category={props.category}
                subjectCode={props.subjectCode}
                fallbackHref={index.url()}
                message="Hubungi Guru pembuat Ulangan untuk pemeriksaan dan pembukaan kembali."
            />
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
