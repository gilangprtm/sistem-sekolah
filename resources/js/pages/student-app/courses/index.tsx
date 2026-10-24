import { Head, Link } from '@inertiajs/react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/student-app';
import { show } from '@/routes/student-app/courses';

type Props = {
    courses: {
        id: number;
        title: string;
        description: string | null;
        subject: string;
        grade_level: string;
    }[];
    period: { name: string } | null;
};

export default function StudentCourses({ courses, period }: Props) {
    return (
        <>
            <Head title="Course Saya" />
            <SecondaryPageShell
                title="Course Saya"
                fallbackHref={dashboard.url()}
            >
                <section className="grid min-w-0 gap-4 p-4">
                    {period && (
                        <p className="text-sm text-muted-foreground">
                            {period.name}
                        </p>
                    )}
                    {courses.length === 0 && (
                        <p className="rounded-xl border p-4 text-sm text-muted-foreground">
                            Belum ada Course aktif untuk kelas Anda. Pastikan
                            penempatan kelas dan jadwal sudah tersedia.
                        </p>
                    )}
                    {courses.map((course) => (
                        <article
                            key={course.id}
                            className="grid min-w-0 gap-3 rounded-xl border bg-card p-4"
                        >
                            <h2 className="font-semibold wrap-anywhere">
                                {course.title}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {course.subject} · Kelas {course.grade_level}
                            </p>
                            <Button asChild className="w-fit">
                                <Link href={show.url(course.id)}>
                                    Buka Course
                                </Link>
                            </Button>
                        </article>
                    ))}
                </section>
            </SecondaryPageShell>
        </>
    );
}
