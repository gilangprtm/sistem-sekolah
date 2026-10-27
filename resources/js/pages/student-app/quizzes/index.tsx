import { Head, Link } from '@inertiajs/react';
import { ClipboardCheck, LockKeyhole } from 'lucide-react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';
import { quizzes as index } from '@/routes/student-app';
import { show } from '@/routes/student-app/quizzes';

type Quiz = {
    id: number;
    subject: string | null;
    teacher: string | null;
    attempt: { status: string; interruption_count: number } | null;
};
type Props = {
    quizzes: { data: Quiz[]; current_page: number; last_page: number };
};

export default function StudentQuizzes({ quizzes }: Props) {
    return (
        <>
            <Head title="Ulangan" />
            <SecondaryPageShell
                title="Ulangan"
                fallbackHref="/student/services"
            >
                <section className="space-y-3 p-4" aria-label="Daftar Ulangan">
                    {quizzes.data.length === 0 ? (
                        <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                            Belum ada Ulangan yang dibuka.
                        </p>
                    ) : (
                        quizzes.data.map((quiz) => (
                            <article
                                key={quiz.id}
                                className="flex items-center justify-between gap-3 rounded-xl border bg-card p-4"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold">
                                        {quiz.subject ?? 'Mata Pelajaran'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Guru: {quiz.teacher ?? 'Guru'}
                                    </p>
                                    {quiz.attempt?.status === 'locked' && (
                                        <p className="mt-1 inline-flex items-center gap-1 text-sm text-destructive">
                                            <LockKeyhole className="size-4" />
                                            Terkunci, hubungi Guru pembuat
                                        </p>
                                    )}
                                </div>
                                {quiz.attempt?.status !== 'locked' && (
                                    <Button asChild>
                                        <Link
                                            href={show.url(quiz.id, {
                                                query:
                                                    quiz.attempt &&
                                                    quiz.attempt
                                                        .interruption_count > 0
                                                        ? { reopen: 1 }
                                                        : {},
                                            })}
                                        >
                                            <ClipboardCheck />
                                            {quiz.attempt
                                                ? 'Lanjutkan'
                                                : 'Mulai'}
                                        </Link>
                                    </Button>
                                )}
                            </article>
                        ))
                    )}
                    {quizzes.last_page > 1 && (
                        <nav
                            aria-label="Halaman Ulangan"
                            className="flex items-center justify-between gap-2"
                        >
                            <Button
                                variant="outline"
                                asChild
                                disabled={quizzes.current_page === 1}
                            >
                                <Link
                                    href={index.url({
                                        query: {
                                            page: Math.max(
                                                1,
                                                quizzes.current_page - 1,
                                            ),
                                        },
                                    })}
                                >
                                    Sebelumnya
                                </Link>
                            </Button>
                            <span className="text-sm">
                                {quizzes.current_page}/{quizzes.last_page}
                            </span>
                            <Button
                                variant="outline"
                                asChild
                                disabled={
                                    quizzes.current_page === quizzes.last_page
                                }
                            >
                                <Link
                                    href={index.url({
                                        query: {
                                            page: Math.min(
                                                quizzes.last_page,
                                                quizzes.current_page + 1,
                                            ),
                                        },
                                    })}
                                >
                                    Berikutnya
                                </Link>
                            </Button>
                        </nav>
                    )}
                </section>
            </SecondaryPageShell>
        </>
    );
}
