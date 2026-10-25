import { Head, Link } from '@inertiajs/react';
import { BookOpenCheck, EllipsisVertical } from 'lucide-react';
import SecondaryPageShell from '@/components/student-app/secondary-page-shell';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
                            className="flex min-h-24 min-w-0 items-center gap-3 rounded-[1.25rem] border border-border bg-card p-3 text-card-foreground"
                        >
                            <Link
                                href={show.url(course.id)}
                                aria-label={`Buka course ${course.title}`}
                                className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-muted/60 text-muted-foreground focus-visible:outline-ring"
                            >
                                <BookOpenCheck
                                    className="size-7"
                                    strokeWidth={1.7}
                                    aria-hidden="true"
                                />
                            </Link>
                            <div className="min-w-0 flex-1">
                                <h2 className="truncate text-base font-semibold">
                                    <Link
                                        href={show.url(course.id)}
                                        className="hover:underline"
                                    >
                                        {course.title}
                                    </Link>
                                </h2>
                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                    {course.subject}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Kelas {course.grade_level}
                                </p>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Aksi course ${course.title}`}
                                        className="size-10 shrink-0 self-start rounded-full text-foreground"
                                    >
                                        <EllipsisVertical
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem asChild>
                                        <Link href={show.url(course.id)}>
                                            <BookOpenCheck aria-hidden="true" />
                                            Buka Course
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </article>
                    ))}
                </section>
            </SecondaryPageShell>
        </>
    );
}
