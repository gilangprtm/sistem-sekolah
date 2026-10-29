import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import ExamConsentDialog from '@/components/student-app/exam-consent-dialog';
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
    const [selectedQuiz, setSelectedQuiz] = useState<Quiz | null>(null);
    const refreshInFlight = useRef(false);

    useEffect(() => {
        const refreshAttempts = () => {
            if (
                document.visibilityState !== 'visible' ||
                refreshInFlight.current
            ) {
                return;
            }

            refreshInFlight.current = true;
            router.reload({
                only: ['quizzes'],
                onFinish: () => {
                    refreshInFlight.current = false;
                },
            });
        };
        const stopListening = router.on('navigate', ({ detail }) => {
            if (detail.page.component === 'student-app/quizzes/index') {
                refreshAttempts();
            }
        });

        window.addEventListener('pageshow', refreshAttempts);
        window.addEventListener('focus', refreshAttempts);

        return () => {
            stopListening();
            window.removeEventListener('pageshow', refreshAttempts);
            window.removeEventListener('focus', refreshAttempts);
        };
    }, []);

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
                                </div>
                                {quiz.attempt &&
                                quiz.attempt.interruption_count > 0 ? (
                                    <Button
                                        asChild
                                        className="min-h-11 shrink-0 gap-2"
                                    >
                                        <Link
                                            href={show.url(quiz.id, {
                                                query: { reopen: 1 },
                                            })}
                                        >
                                            Buka Form
                                            <ExternalLink className="size-4" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button
                                        type="button"
                                        className="min-h-11 shrink-0 gap-2"
                                        onClick={() => setSelectedQuiz(quiz)}
                                    >
                                        Buka Form
                                        <ExternalLink className="size-4" />
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
                <ExamConsentDialog
                    open={selectedQuiz !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedQuiz(null);
                        }
                    }}
                    title="Mulai Ulangan"
                    subject={selectedQuiz?.subject ?? 'Mata Pelajaran'}
                    href={selectedQuiz ? show.url(selectedQuiz.id) : null}
                >
                    <p>
                        Ulangan {selectedQuiz?.subject ?? 'Mata Pelajaran'} akan
                        dimulai saat Anda memilih Mulai.
                    </p>
                    <p>
                        Aktivitas halaman Ulangan dicatat untuk audit. Jika
                        halaman Ulangan disembunyikan lalu dibuka kembali tiga
                        kali, percobaan terkunci. Hubungi Guru pembuat Ulangan
                        untuk meminta bantuan.
                    </p>
                    <p>
                        Setelah memilih Selesai, percobaan juga terkunci dan
                        hanya dapat dibuka kembali oleh Guru pembuat Ulangan.
                    </p>
                    <p className="text-muted-foreground">
                        Catatan aktivitas tidak memastikan penyebab perpindahan
                        halaman.
                    </p>
                </ExamConsentDialog>
            </SecondaryPageShell>
        </>
    );
}
