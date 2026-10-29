import ExamLockedScreen from '@/components/student-app/exam-locked-screen';

type Props = {
    category: string;
    subject: string | null;
    subjectCode: string | null;
    interruptionCount: number;
};

export default function StudentExamLocked({ category, subjectCode }: Props) {
    return (
        <ExamLockedScreen
            category={category}
            subjectCode={subjectCode}
            fallbackHref="/student/uas"
            message="Hubungi administrator ujian untuk pemeriksaan dan pembukaan kembali."
        />
    );
}
