import { Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';

type Props = {
    category: string;
    subjectCode: string | null;
    fallbackHref: string;
    message: string;
};

export default function ExamLockedScreen({
    category,
    subjectCode,
    fallbackHref,
    message,
}: Props) {
    const heading = `${category} ${subjectCode ?? ''}`.trim();

    return (
        <>
            <Head title={heading} />
            <SecondaryPageShell title={heading} fallbackHref={fallbackHref}>
                <main className="mx-auto flex min-h-[calc(100dvh-3.5rem)] w-full max-w-xl flex-col items-center justify-center gap-4 p-6 text-center">
                    <LockKeyhole
                        className="size-12 text-destructive"
                        aria-hidden="true"
                    />
                    <p className="text-sm font-medium text-primary">
                        {heading}
                    </p>
                    <h1 className="text-2xl font-bold">
                        Percobaan ujian terkunci
                    </h1>
                    <p className="text-sm text-muted-foreground">{message}</p>
                </main>
            </SecondaryPageShell>
        </>
    );
}
