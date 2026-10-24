import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import CourseLearningPreview from '@/components/lms/course-learning-preview';
import SearchableCombobox from '@/components/searchable-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { myCourses } from '@/routes/lms';
import { show } from '@/routes/lms/my-courses';
import type { BreadcrumbItem } from '@/types';
import type { LearningCourseContent } from '@/types/lms';

const statusLabels: Record<string, string> = {
    not_started: 'Belum mulai',
    in_progress: 'Sedang mengerjakan',
    submitted: 'Sudah dikumpulkan',
};
type Props = {
    course: {
        id: number;
        title: string;
        description: string | null;
        subject: string;
        grade_level: string;
        content: LearningCourseContent;
    };
    classes: { id: number; name: string; code: string }[];
    selectedClassId: number | null;
    students: {
        id: number;
        name: string;
        nis: string | null;
        status: string;
        answered_count: number;
    }[];
    selectedResponse: {
        student_id: number;
        name: string;
        status: string;
        answers: Record<string, string>;
        content: LearningCourseContent;
        submitted_at: string | null;
    } | null;
};

export default function MyCourseShow({
    course,
    classes,
    selectedClassId,
    students,
    selectedResponse,
}: Props) {
    const [loading, setLoading] = useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'LMS', href: myCourses.url() },
        { title: 'Course Saya', href: myCourses.url() },
        { title: course.title, href: show.url(course.id) },
    ];
    const select = (rombelId: number, studentId?: number) =>
        router.get(
            show.url(course.id),
            {
                rombel_id: rombelId,
                ...(studentId ? { student_id: studentId } : {}),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${course.title} · Course Saya`} />
            <SecondaryPageShell
                title={course.title}
                description={`${course.subject} · Kelas ${course.grade_level}`}
                backHref={myCourses.url()}
                backLabel="Kembali ke Course Saya"
            >
                <Tabs defaultValue="response" className="min-w-0 gap-6">
                    <TabsList
                        variant="line"
                        className="h-11 w-full justify-start"
                        aria-label="Tampilan Course"
                    >
                        <TabsTrigger value="response">Response</TabsTrigger>
                        <TabsTrigger value="preview">Pratinjau</TabsTrigger>
                    </TabsList>
                    <TabsContent
                        value="response"
                        className="grid min-w-0 gap-5"
                    >
                        {classes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada kelas dengan jadwal terpublikasi untuk
                                Course ini pada semester aktif.
                            </p>
                        ) : (
                            <>
                                <div className="grid max-w-sm gap-2">
                                    <Label htmlFor="response-class">
                                        Kelas
                                    </Label>
                                    <SearchableCombobox
                                        id="response-class"
                                        value={String(selectedClassId ?? '')}
                                        options={classes.map((item) => ({
                                            value: String(item.id),
                                            label: item.name || item.code,
                                        }))}
                                        disabled={loading}
                                        onChange={(value) =>
                                            select(Number(value))
                                        }
                                        placeholder="Pilih kelas"
                                        searchPlaceholder="Cari kelas..."
                                        emptyMessage="Kelas tidak ditemukan."
                                    />
                                </div>
                                {loading ? (
                                    <p
                                        role="status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Memuat respons…
                                    </p>
                                ) : (
                                    <div className="grid min-w-0 items-start gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
                                        <section
                                            aria-label="Seluruh siswa kelas"
                                            className="grid min-w-0 gap-3 rounded-xl border p-4"
                                        >
                                            <h2 className="font-semibold">
                                                Siswa ({students.length})
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                Seluruh siswa kelas, termasuk
                                                yang belum menjawab.
                                            </p>
                                            <div className="grid gap-2">
                                                {students.map((student) => (
                                                    <Button
                                                        key={student.id}
                                                        variant={
                                                            selectedResponse?.student_id ===
                                                            student.id
                                                                ? 'secondary'
                                                                : 'ghost'
                                                        }
                                                        className="h-auto min-h-12 justify-start whitespace-normal"
                                                        aria-pressed={
                                                            selectedResponse?.student_id ===
                                                            student.id
                                                        }
                                                        onClick={() =>
                                                            select(
                                                                selectedClassId!,
                                                                student.id,
                                                            )
                                                        }
                                                    >
                                                        <span className="grid min-w-0 gap-1 text-left">
                                                            <span className="wrap-anywhere">
                                                                {student.name}
                                                            </span>
                                                            <span className="text-xs font-normal text-muted-foreground">
                                                                {
                                                                    statusLabels[
                                                                        student
                                                                            .status
                                                                    ]
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    student.answered_count
                                                                }{' '}
                                                                jawaban
                                                            </span>
                                                        </span>
                                                    </Button>
                                                ))}
                                            </div>
                                            {students.length === 0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    Belum ada siswa
                                                    berpenempatan aktif di kelas
                                                    ini.
                                                </p>
                                            )}
                                        </section>
                                        <section
                                            aria-label="Jawaban siswa terpilih"
                                            className="grid min-w-0 gap-5 rounded-xl border p-4 sm:p-5"
                                        >
                                            {selectedResponse ? (
                                                <>
                                                    <header className="grid gap-2">
                                                        <h2 className="text-lg font-semibold wrap-anywhere">
                                                            {
                                                                selectedResponse.name
                                                            }
                                                        </h2>
                                                        <Badge variant="secondary">
                                                            {
                                                                statusLabels[
                                                                    selectedResponse
                                                                        .status
                                                                ]
                                                            }
                                                        </Badge>
                                                    </header>
                                                    {selectedResponse.status ===
                                                        'not_started' && (
                                                        <p className="text-sm text-muted-foreground">
                                                            Siswa ini belum
                                                            mulai mengerjakan
                                                            Course.
                                                        </p>
                                                    )}
                                                    {selectedResponse.content.materials.map(
                                                        (material, index) => (
                                                            <section
                                                                key={
                                                                    material.id
                                                                }
                                                                className="grid min-w-0 gap-4"
                                                            >
                                                                <h3 className="font-semibold wrap-anywhere">
                                                                    {index + 1}.{' '}
                                                                    {
                                                                        material.title
                                                                    }
                                                                </h3>
                                                                {[
                                                                    material,
                                                                    ...material.sections,
                                                                ].map(
                                                                    (page) => (
                                                                        <div
                                                                            key={
                                                                                page.id
                                                                            }
                                                                            className="grid min-w-0 gap-3"
                                                                        >
                                                                            {page.id !==
                                                                                material.id && (
                                                                                <h4 className="text-sm font-medium wrap-anywhere">
                                                                                    {
                                                                                        page.title
                                                                                    }
                                                                                </h4>
                                                                            )}
                                                                            {page.questions.map(
                                                                                (
                                                                                    question,
                                                                                    questionIndex,
                                                                                ) => {
                                                                                    const answer =
                                                                                        selectedResponse
                                                                                            .answers[
                                                                                            question
                                                                                                .id
                                                                                        ];
                                                                                    const text =
                                                                                        question.type ===
                                                                                        'multiple_choice'
                                                                                            ? question.options?.find(
                                                                                                  (
                                                                                                      option,
                                                                                                  ) =>
                                                                                                      option.id ===
                                                                                                      answer,
                                                                                              )
                                                                                                  ?.text
                                                                                            : answer;

                                                                                    return (
                                                                                        <div
                                                                                            key={
                                                                                                question.id
                                                                                            }
                                                                                            className="grid min-w-0 gap-2 rounded-lg border p-4"
                                                                                        >
                                                                                            <p className="text-sm font-medium wrap-anywhere whitespace-pre-wrap">
                                                                                                Soal{' '}
                                                                                                {questionIndex +
                                                                                                    1}

                                                                                                .{' '}
                                                                                                {
                                                                                                    question.prompt
                                                                                                }
                                                                                            </p>
                                                                                            <p className="text-sm wrap-anywhere whitespace-pre-wrap">
                                                                                                {text ||
                                                                                                    'Belum dijawab'}
                                                                                            </p>
                                                                                        </div>
                                                                                    );
                                                                                },
                                                                            )}
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </section>
                                                        ),
                                                    )}
                                                    {!selectedResponse.content.materials.some(
                                                        (material) =>
                                                            [
                                                                material,
                                                                ...material.sections,
                                                            ].some(
                                                                (page) =>
                                                                    page
                                                                        .questions
                                                                        .length >
                                                                    0,
                                                            ),
                                                    ) && (
                                                        <p className="text-sm text-muted-foreground">
                                                            Course ini belum
                                                            memiliki soal.
                                                        </p>
                                                    )}
                                                </>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Pilih siswa untuk melihat
                                                    jawaban dan soal yang belum
                                                    dijawab.
                                                </p>
                                            )}
                                        </section>
                                    </div>
                                )}
                            </>
                        )}
                    </TabsContent>
                    <TabsContent value="preview">
                        <CourseLearningPreview
                            title={course.title}
                            description={course.description}
                            content={course.content}
                        />
                    </TabsContent>
                </Tabs>
            </SecondaryPageShell>
        </AppLayout>
    );
}
