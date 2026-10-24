import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useState } from 'react';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import AlertError from '@/components/alert-error';
import CourseContentEditor from '@/components/lms/course-content-editor';
import CourseFields from '@/components/lms/course-fields';
import type {
    CourseFormData,
    SubjectOption,
} from '@/components/lms/course-fields';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { design } from '@/routes/lms';
import { edit } from '@/routes/lms/design';
import type { BreadcrumbItem } from '@/types';
import type { CourseContent } from '@/types/lms';

type Props = {
    course: {
        id: number;
        title: string;
        subject_id: number;
        grade_level: string;
        description: string | null;
        revision_id: string;
        revision_number: number;
        editable: boolean;
        content: CourseContent;
    };
    contentUpdateUrl: string;
    subjects: SubjectOption[];
    gradeLevels: string[];
};

function editorContent(content: CourseContent): CourseContent {
    return {
        materials: content.materials.map((material) => ({
            ...material,
            body: material.body ?? '',
            media: material.media ?? [],
            questions: material.questions ?? [],
            sections: (material.sections ?? []).map((section) => ({
                ...section,
                body: section.body ?? '',
                media: section.media ?? [],
                questions: section.questions ?? [],
            })),
        })),
        assets: content.assets ?? {},
        uploads: {},
    };
}

export default function LmsCourseDesignEdit({
    course,
    contentUpdateUrl,
    subjects,
    gradeLevels,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Perancangan Course', href: design.url() },
        { title: course.title, href: edit.url(course.id) },
    ];
    const [content, setContent] = useState(() => editorContent(course.content));
    const form = useForm<
        CourseFormData & {
            content_json: string;
            uploads: Record<string, File>;
            _method: string;
        }
    >({
        title: course.title,
        subject_id: String(course.subject_id),
        grade_level: course.grade_level,
        description: course.description ?? '',
        designer_id: '',
        revision_id: course.revision_id,
        content_json: '',
        uploads: {},
        _method: 'patch',
    });
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const referencedIds = new Set(
            content.materials
                .flatMap((material) => [material, ...material.sections])
                .flatMap((page) => page.media.map((media) => media.asset_id)),
        );
        const uploads = Object.fromEntries(
            Object.entries(content.uploads ?? {}).filter(([id]) =>
                referencedIds.has(id),
            ),
        );
        form.transform((data) => ({
            ...data,
            uploads,
            content_json: JSON.stringify({ materials: content.materials }),
        }));
        form.post(contentUpdateUrl, {
            preserveScroll: 'errors',
        });
    };
    const errors = Object.entries(form.errors);
    const disabled = form.processing || !course.editable;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${course.title} · Perancangan Course`} />
            <SecondaryPageShell
                title={course.title}
                description={`Penyusunan draf · Revisi ${course.revision_number}`}
                backHref={design.url()}
                backLabel="Kembali ke Perancangan Course"
                footerActions={
                    course.editable ? (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={design.url()}>Kembali</Link>
                            </Button>
                            <Button
                                type="submit"
                                form="course-identity-form"
                                disabled={disabled}
                            >
                                <Save aria-hidden="true" />
                                {form.processing ? 'Menyimpan…' : 'Simpan Draf'}
                            </Button>
                        </>
                    ) : undefined
                }
            >
                <form
                    id="course-identity-form"
                    onSubmit={submit}
                    className="grid min-w-0 gap-5"
                >
                    <Card className="w-full max-w-4xl">
                        <CardHeader>
                            <CardTitle>Identitas Course</CardTitle>
                            <CardDescription>
                                {course.editable
                                    ? 'Simpan Draf menyimpan identitas dan seluruh materi. Draf belum dipublikasikan.'
                                    : 'Revisi ini hanya dapat dibaca; penyuntingan draf dikunci.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <CourseFields
                                data={form.data}
                                setData={form.setData}
                                errors={form.errors}
                                subjects={subjects}
                                gradeLevels={gradeLevels}
                                disabled={disabled}
                            />
                        </CardContent>
                    </Card>
                    <Card className="w-full min-w-0">
                        <CardHeader>
                            <CardTitle>Materi Course</CardTitle>
                            <CardDescription>
                                Susun Materi berurutan, Bagian bertab opsional,
                                teks berformat, media, dan soal. Semua perubahan
                                menunggu Simpan Draf.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <CourseContentEditor
                                key={course.id}
                                content={content}
                                disabled={disabled}
                                onChange={setContent}
                            />
                        </CardContent>
                    </Card>
                    {errors.length > 0 && (
                        <AlertError
                            title="Draf belum tersimpan. Periksa data berikut:"
                            errors={errors.map(
                                ([key, message]) => `${key}: ${message}`,
                            )}
                        />
                    )}
                    {form.progress && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            Mengunggah: {form.progress.percentage ?? 0}%
                        </p>
                    )}
                </form>
            </SecondaryPageShell>
        </AppLayout>
    );
}
