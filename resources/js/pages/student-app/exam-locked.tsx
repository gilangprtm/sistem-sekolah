import { Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';

type Props = {
    category: string;
    subject: string | null;
    subjectCode: string | null;
    interruptionCount: number;
};

export default function StudentExamLocked({ category, subjectCode }: Props) {
    return (
        <>
            <Head title={`${category} ${subjectCode ?? ''}`.trim()} />
            <SecondaryPageShell
                title={`${category} ${subjectCode ?? ''}`.trim()}
                fallbackHref="/student/uas"
            >
                <main className="mx-auto flex min-h-[calc(100dvh-3.5rem)] w-full max-w-xl flex-col items-center justify-center gap-4 p-6 text-center">
                    <LockKeyhole
                        className="size-12 text-destructive"
                        aria-hidden="true"
                    />
                    <p className="text-sm font-medium text-violet-700">
                        {`${category} ${subjectCode ?? ''}`.trim()}
                    </p>
                    <h1 className="text-2xl font-bold">
                        Percobaan ujian terkunci
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Hubungi administrator ujian untuk pemeriksaan dan
                        pembukaan kembali.
                    </p>
                </main>
            </SecondaryPageShell>
        </>
    );
}
