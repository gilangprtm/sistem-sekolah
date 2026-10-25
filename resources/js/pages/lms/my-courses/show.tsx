import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import CourseGradebook from '@/components/lms/course-gradebook';
import type { GradebookPageScore } from '@/components/lms/course-gradebook';
import CourseLearningPreview from '@/components/lms/course-learning-preview';
import CourseResponsePage from '@/components/lms/course-response-page';
import type { CoursePageGrading } from '@/components/lms/course-response-page';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { getCourseResponseMaterials } from '@/lib/lms-course-responses';
import { myCourses } from '@/routes/lms';
import { show } from '@/routes/lms/my-courses';
import { download } from '@/routes/lms/my-courses/grades';
import type { BreadcrumbItem } from '@/types';
import type { LearningCourseContent } from '@/types/lms';

const statusLabels: Record<string, string> = {
    not_started: 'Belum mulai',
    in_progress: 'Sedang mengerjakan',
    submitted: 'Sudah dikumpulkan',
};
type Props = {
    canGrade: boolean;
    gradebook: {
        pages: { id: string; title: string }[];
        students: {
            id: number;
            name: string;
            scores: Record<string, GradebookPageScore>;
        }[];
    } | null;
    responseRevisions: { id: number; number: number; current: boolean }[];
    selectedRevisionId: number;
    isHistory: boolean;
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
        submitted_page_ids: string[];
        grading?: Record<string, CoursePageGrading>;
    } | null;
};

export default function MyCourseShow({
    canGrade,
    gradebook,
    responseRevisions,
    selectedRevisionId,
    isHistory,
    course,
    classes,
    selectedClassId,
    students,
    selectedResponse,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('response');
    const responseTabs = selectedResponse
        ? getCourseResponseMaterials(selectedResponse.content)
        : [];
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'LMS', href: myCourses.url() },
        { title: 'Course Saya', href: myCourses.url() },
        { title: course.title, href: show.url(course.id) },
    ];
    const select = (
        rombelId: number,
        studentId?: number,
        revisionId?: number,
    ) =>
        router.get(
            show.url(course.id),
            {
                rombel_id: rombelId,
                ...(studentId ? { student_id: studentId } : {}),
                ...(revisionId ? { revision_id: revisionId } : {}),
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
                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    className="min-w-0 gap-6"
                >
                    <TabsList
                        variant="line"
                        className="h-11 w-full justify-start"
                        aria-label="Tampilan Course"
                    >
                        <TabsTrigger value="response">Response</TabsTrigger>
                        <TabsTrigger value="grades">Nilai Siswa</TabsTrigger>
                        <TabsTrigger value="preview">Pratinjau</TabsTrigger>
                    </TabsList>
                    {(activeTab === 'response' || activeTab === 'grades') && (
                        <div
                            className={`grid min-w-0 grid-cols-1 gap-5 ${activeTab === 'grades' ? 'sm:grid-cols-[repeat(2,minmax(0,24rem))_auto] sm:justify-start' : 'sm:grid-cols-[repeat(2,minmax(0,24rem))]'}`}
                        >
                            {classes.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada kelas dengan jadwal terpublikasi
                                    untuk Course ini pada semester aktif.
                                </p>
                            ) : (
                                <>
                                    <div className="grid min-w-0 gap-2">
                                        <Label htmlFor="response-class">
                                            Kelas
                                        </Label>
                                        <SearchableCombobox
                                            id="response-class"
                                            value={String(
                                                selectedClassId ?? '',
                                            )}
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
                                    {responseRevisions.length > 0 && (
                                        <div className="grid min-w-0 gap-2">
                                            <Label htmlFor="response-revision">
                                                Versi Course
                                            </Label>
                                            <SearchableCombobox
                                                id="response-revision"
                                                value={String(
                                                    selectedRevisionId,
                                                )}
                                                options={responseRevisions.map(
                                                    (revision) => ({
                                                        value: String(
                                                            revision.id,
                                                        ),
                                                        label: `Revisi ${revision.number} · ${revision.current ? 'Aktif terbaru' : 'Riwayat'}`,
                                                    }),
                                                )}
                                                disabled={loading}
                                                onChange={(value) =>
                                                    select(
                                                        selectedClassId!,
                                                        selectedResponse?.student_id,
                                                        Number(value),
                                                    )
                                                }
                                                placeholder="Pilih revisi"
                                                searchPlaceholder="Cari revisi..."
                                                emptyMessage="Revisi tidak ditemukan."
                                            />
                                        </div>
                                    )}
                                    {activeTab === 'grades' &&
                                        !loading &&
                                        gradebook &&
                                        selectedClassId !== null && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="w-full sm:w-auto sm:self-end"
                                            >
                                                <a
                                                    href={download.url(
                                                        course.id,
                                                        {
                                                            query: {
                                                                rombel_id:
                                                                    selectedClassId,
                                                                revision_id:
                                                                    selectedRevisionId,
                                                            },
                                                        },
                                                    )}
                                                >
                                                    <Download aria-hidden="true" />
                                                    Download Nilai
                                                </a>
                                            </Button>
                                        )}
                                    {isHistory && (
                                        <p
                                            role="status"
                                            className="text-sm text-muted-foreground sm:col-span-full"
                                        >
                                            Riwayat revisi lama — hanya baca.
                                            Jawaban dan nilai ini tidak
                                            digunakan pada revisi aktif terbaru.
                                        </p>
                                    )}
                                </>
                            )}
                        </div>
                    )}
                    <TabsContent value="grades" className="grid min-w-0 gap-5">
                        {classes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada kelas pada semester aktif.
                            </p>
                        ) : loading ? (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                Memuat nilai…
                            </p>
                        ) : gradebook ? (
                            <CourseGradebook
                                pages={gradebook.pages}
                                students={gradebook.students}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Anda tidak memiliki izin untuk melihat nilai
                                siswa.
                            </p>
                        )}
                    </TabsContent>
                    <TabsContent
                        value="response"
                        className="grid min-w-0 gap-5"
                    >
                        {classes.length > 0 &&
                            (loading ? (
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
                                            Seluruh siswa kelas, termasuk yang
                                            belum menjawab.
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
                                                            isHistory
                                                                ? selectedRevisionId
                                                                : undefined,
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
                                                Belum ada siswa berpenempatan
                                                aktif di kelas ini.
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
                                                        {selectedResponse.name}
                                                    </h2>
                                                </header>
                                                {selectedResponse.status ===
                                                    'not_started' && (
                                                    <p className="text-sm text-muted-foreground">
                                                        Siswa ini belum mulai
                                                        mengerjakan Course.
                                                    </p>
                                                )}
                                                {responseTabs.length > 0 ? (
                                                    <Tabs
                                                        key={`${selectedClassId}-${selectedRevisionId}-${selectedResponse.student_id}-${responseTabs.flatMap((material) => material.pages.map((page) => page.id)).join('-')}`}
                                                        defaultValue={
                                                            responseTabs[0]
                                                                .pages[0].id
                                                        }
                                                        className="grid min-w-0 gap-5"
                                                    >
                                                        <TabsList
                                                            variant="line"
                                                            aria-label="Materi dan bagian yang memiliki soal"
                                                            className="w-full flex-wrap justify-start gap-1 rounded-none bg-transparent p-0 group-data-horizontal/tabs:h-auto"
                                                        >
                                                            {responseTabs.flatMap(
                                                                (material) =>
                                                                    material.pages.map(
                                                                        (
                                                                            page,
                                                                        ) => (
                                                                            <TabsTrigger
                                                                                key={
                                                                                    page.id
                                                                                }
                                                                                value={
                                                                                    page.id
                                                                                }
                                                                                className="h-auto min-h-8 min-w-28 flex-1 rounded-none px-2.5 py-1.5 text-sm wrap-anywhere whitespace-normal"
                                                                            >
                                                                                {page.id ===
                                                                                material.id
                                                                                    ? `${material.number}. ${material.title}`
                                                                                    : page.title}
                                                                            </TabsTrigger>
                                                                        ),
                                                                    ),
                                                            )}
                                                        </TabsList>
                                                        {responseTabs.flatMap(
                                                            (material) =>
                                                                material.pages.map(
                                                                    (page) => (
                                                                        <TabsContent
                                                                            key={
                                                                                page.id
                                                                            }
                                                                            value={
                                                                                page.id
                                                                            }
                                                                            className="grid min-w-0 gap-3"
                                                                        >
                                                                            <CourseResponsePage
                                                                                key={`${selectedRevisionId}-${selectedResponse.student_id}-${page.id}-${selectedResponse.grading?.[page.id]?.token ?? 'read-only'}`}
                                                                                courseId={
                                                                                    course.id
                                                                                }
                                                                                revisionId={
                                                                                    selectedRevisionId
                                                                                }
                                                                                classId={
                                                                                    selectedClassId!
                                                                                }
                                                                                studentId={
                                                                                    selectedResponse.student_id
                                                                                }
                                                                                page={
                                                                                    page
                                                                                }
                                                                                answers={
                                                                                    selectedResponse.answers
                                                                                }
                                                                                grading={
                                                                                    selectedResponse
                                                                                        .grading?.[
                                                                                        page
                                                                                            .id
                                                                                    ]
                                                                                }
                                                                                submitted={selectedResponse.submitted_page_ids.includes(
                                                                                    page.id,
                                                                                )}
                                                                                canGrade={
                                                                                    canGrade
                                                                                }
                                                                            />
                                                                        </TabsContent>
                                                                    ),
                                                                ),
                                                        )}
                                                    </Tabs>
                                                ) : (
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
                            ))}
                    </TabsContent>
                    <TabsContent value="preview">
                        <CourseLearningPreview
                            key={`${course.id}-${selectedRevisionId}`}
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
