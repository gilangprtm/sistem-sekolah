import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { grade } from '@/routes/lms/my-courses';
import type { LearningCoursePage } from '@/types/lms';

export type CoursePageGrading = {
    weights_set: boolean;
    submitted: boolean;
    score: number | null;
    ungraded_essays: number;
    questions: Record<string, { maximum: number | null; score: number | null }>;
    token: string;
};

type Props = {
    courseId: number;
    revisionId: number;
    classId: number;
    studentId: number;
    page: LearningCoursePage;
    answers: Record<string, string>;
    grading?: CoursePageGrading;
    submitted: boolean;
    canGrade: boolean;
};

export default function CourseResponsePage({
    courseId,
    revisionId,
    classId,
    studentId,
    page,
    answers,
    grading,
    submitted,
    canGrade,
}: Props) {
    const form = useForm({
        rombel_id: classId,
        student_id: studentId,
        page_id: page.id,
        revision_id: revisionId,
        grading_token: grading?.token ?? '',
        scores: Object.fromEntries(
            page.questions
                .filter((question) => question.type === 'essay')
                .map((question) => [
                    question.id,
                    grading?.questions[question.id]?.score?.toString() ?? '',
                ]),
        ) as Record<string, string>,
    });
    const editable = canGrade && grading?.weights_set && submitted;
    const hasScores = Object.values(form.data.scores).some(
        (value) => value !== '',
    );

    return (
        <form
            className="grid min-w-0 gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    scores: Object.fromEntries(
                        Object.entries(data.scores).filter(
                            ([, value]) => value !== '',
                        ),
                    ),
                }));
                form.post(grade.url(courseId), { preserveScroll: true });
            }}
        >
            {(!submitted || grading) && (
                <div className="grid gap-2" role="status" aria-live="polite">
                    {!submitted && (
                        <p className="text-sm text-muted-foreground">
                            Belum dikumpulkan
                        </p>
                    )}
                    {grading && (!grading.weights_set || submitted) && (
                        <p className="text-sm font-medium">
                            {!grading.weights_set
                                ? 'Bobot belum diatur'
                                : grading.score === null
                                  ? `Nilai ditahan: ${grading.ungraded_essays} soal uraian belum dinilai.`
                                  : `Nilai akhir: ${grading.score}/100`}
                        </p>
                    )}
                </div>
            )}
            {page.questions.map((question, index) => {
                const answer = answers[question.id];
                const text =
                    question.type === 'multiple_choice'
                        ? question.options?.find(
                              (option) => option.id === answer,
                          )?.text
                        : answer;
                const result = grading?.questions[question.id];

                return (
                    <div
                        key={question.id}
                        className="grid min-w-0 gap-3 rounded-lg border p-4"
                    >
                        <p className="text-sm font-medium wrap-anywhere whitespace-pre-wrap">
                            Soal {index + 1}. {question.prompt}
                        </p>
                        <p className="text-sm wrap-anywhere whitespace-pre-wrap">
                            {text || 'Belum dijawab'}
                        </p>
                        {grading?.weights_set &&
                            result &&
                            (question.type === 'multiple_choice' ? (
                                <p className="text-sm text-muted-foreground">
                                    Nilai otomatis: {result.score ?? '—'}/
                                    {result.maximum}
                                </p>
                            ) : (
                                <div className="grid max-w-xs gap-2">
                                    <Label
                                        htmlFor={`score-${studentId}-${question.id}`}
                                    >
                                        Nilai uraian (maksimal {result.maximum})
                                    </Label>
                                    <Input
                                        id={`score-${studentId}-${question.id}`}
                                        type="number"
                                        min="0"
                                        max={result.maximum ?? 100}
                                        step="0.01"
                                        value={
                                            form.data.scores[question.id] ?? ''
                                        }
                                        placeholder="Belum dinilai"
                                        disabled={!editable || form.processing}
                                        onChange={(event) =>
                                            form.setData('scores', {
                                                ...form.data.scores,
                                                [question.id]:
                                                    event.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={
                                            form.errors[`scores.${question.id}`]
                                        }
                                    />
                                </div>
                            ))}
                    </div>
                );
            })}
            <InputError message={Object.values(form.errors)[0]} />
            {editable &&
                page.questions.some(
                    (question) => question.type === 'essay',
                ) && (
                    <>
                        <p className="text-sm text-muted-foreground">
                            Nilai kosong tetap belum dinilai. Isi 0 jika jawaban
                            memperoleh nilai nol. Nilai hanya terlihat oleh
                            guru.
                        </p>
                        <Button
                            type="submit"
                            disabled={form.processing || !hasScores}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan nilai'}
                        </Button>
                    </>
                )}
        </form>
    );
}
