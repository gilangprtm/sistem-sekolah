import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpenCheck,
    EllipsisVertical,
    Grid2X2,
    List,
    Search,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { myCourses } from '@/routes/lms';
import { show } from '@/routes/lms/my-courses';
import type { BreadcrumbItem } from '@/types';

type Course = {
    id: number;
    title: string;
    description: string | null;
    subject: string;
    grade_level: string;
    revision_number: number;
};
type Props = {
    courses: {
        data: Course[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        search: string;
        subject_id: number | null;
        grade_level: string;
        per_page: number;
    };
    filterOptions: {
        subjects: { id: number; name: string }[];
        gradeLevels: string[];
    };
    activePeriod: { name: string; academic_year: string } | null;
    isAdmin: boolean;
};

export default function MyCoursesIndex({
    courses,
    filters,
    filterOptions,
    activePeriod,
    isAdmin,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [subject, setSubject] = useState(
        filters.subject_id?.toString() ?? '',
    );
    const [grade, setGrade] = useState(filters.grade_level);
    const [layout, setLayout] = useState<'grid' | 'list'>('grid');
    const visit = (page = 1, perPage = courses.per_page) =>
        router.get(
            myCourses.url(),
            {
                search: search.trim(),
                subject_id: subject,
                grade_level: grade,
                page,
                per_page: perPage,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const resetFilters = () => {
        setSearch('');
        setSubject('');
        setGrade('');
        router.get(
            myCourses.url(),
            { page: 1, per_page: courses.per_page },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'LMS', href: myCourses.url() },
        { title: 'Course Saya', href: myCourses.url() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Course Saya · LMS" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Course Saya"
                    description={
                        isAdmin
                            ? 'Course aktif milik sekolah yang dapat ditinjau.'
                            : 'Course aktif yang tersedia dari jadwal mengajar terpublikasi Anda.'
                    }
                />
                {activePeriod ? (
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-muted/30 px-4 py-3 text-sm">
                        <span>Periode jadwal aktif</span>
                        <Badge variant="secondary">
                            {activePeriod.academic_year} · {activePeriod.name}
                        </Badge>
                    </div>
                ) : !isAdmin ? (
                    <div className="rounded-xl border border-dashed px-4 py-3 text-sm text-muted-foreground">
                        Belum ada semester aktif. Course akan tampil setelah
                        semester dan jadwal mengajar dipublikasikan.
                    </div>
                ) : null}
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit();
                    }}
                    className="@container grid gap-4 rounded-xl border p-4"
                >
                    <div className="grid grid-cols-1 items-end gap-3 @min-[40rem]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="my-courses-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    id="my-courses-search"
                                    className="pl-8"
                                    value={search}
                                    placeholder="Judul atau deskripsi..."
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="my-courses-subject">
                                Mata Pelajaran
                            </Label>
                            <SearchableCombobox
                                id="my-courses-subject"
                                value={subject}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.subjects.map((item) => ({
                                        value: String(item.id),
                                        label: item.name,
                                    })),
                                ]}
                                onChange={setSubject}
                                placeholder="Semua"
                                searchPlaceholder="Search mata pelajaran…"
                                emptyMessage="Mata pelajaran tidak ditemukan."
                            />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="my-courses-grade">Jenjang</Label>
                            <SearchableCombobox
                                id="my-courses-grade"
                                value={grade}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.gradeLevels.map(
                                        (item) => ({
                                            value: item,
                                            label: `Kelas ${item}`,
                                        }),
                                    ),
                                ]}
                                onChange={setGrade}
                                placeholder="Semua"
                                searchPlaceholder="Search jenjang…"
                                emptyMessage="Jenjang tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit">Filter</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetFilters}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </form>
                <section
                    aria-labelledby="my-courses-heading"
                    className="@container grid gap-2"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2
                            id="my-courses-heading"
                            className="text-base font-medium"
                        >
                            Course{' '}
                            <span className="ml-1 text-sm font-normal text-muted-foreground">
                                ({courses.total})
                            </span>
                        </h2>
                        <ButtonGroup
                            aria-label="Tampilan course"
                            className="rounded-lg border"
                        >
                            <Button
                                type="button"
                                variant={
                                    layout === 'grid' ? 'secondary' : 'ghost'
                                }
                                size="sm"
                                aria-pressed={layout === 'grid'}
                                onClick={() => setLayout('grid')}
                            >
                                <Grid2X2 className="size-3.5" /> Grid View
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    layout === 'list' ? 'secondary' : 'ghost'
                                }
                                size="sm"
                                aria-pressed={layout === 'list'}
                                onClick={() => setLayout('list')}
                            >
                                <List className="size-3.5" /> List View
                            </Button>
                        </ButtonGroup>
                    </div>
                    {courses.data.length > 0 ? (
                        <div
                            className={
                                layout === 'grid'
                                    ? 'grid gap-3 @min-[36rem]:grid-cols-2 @min-[60rem]:grid-cols-4'
                                    : 'grid gap-2'
                            }
                        >
                            {courses.data.map((course) => (
                                <article
                                    key={course.id}
                                    className={cn(
                                        'min-w-0 rounded-2xl border bg-card p-3 text-card-foreground',
                                        layout === 'list' &&
                                            'flex items-center gap-3',
                                    )}
                                >
                                    <Link
                                        href={show.url(course.id)}
                                        aria-label={`Buka course ${course.title}`}
                                        className={cn(
                                            'relative flex items-center justify-center rounded-xl bg-muted/60 text-muted-foreground focus-visible:outline-ring',
                                            layout === 'grid'
                                                ? 'h-36 w-full'
                                                : 'size-14 shrink-0',
                                        )}
                                    >
                                        <BookOpenCheck
                                            className={
                                                layout === 'grid'
                                                    ? 'size-12'
                                                    : 'size-7'
                                            }
                                            strokeWidth={1.7}
                                            aria-hidden="true"
                                        />
                                        {layout === 'grid' && (
                                            <div className="absolute inset-x-3 bottom-3 flex justify-between gap-2 text-xs">
                                                <span>
                                                    Kelas {course.grade_level}
                                                </span>
                                            </div>
                                        )}
                                    </Link>
                                    <div
                                        className={cn(
                                            'flex min-w-0 items-start gap-2',
                                            layout === 'grid'
                                                ? 'mt-3'
                                                : 'flex-1',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <h3
                                                className="truncate text-sm font-semibold"
                                                title={course.title}
                                            >
                                                <Link
                                                    href={show.url(course.id)}
                                                    className="hover:underline"
                                                >
                                                    {course.title}
                                                </Link>
                                            </h3>
                                            <p
                                                className="mt-1 truncate text-sm text-muted-foreground"
                                                title={course.subject}
                                            >
                                                {course.subject}
                                            </p>
                                            {layout === 'list' && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Kelas {course.grade_level}
                                                </p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Aksi course ${course.title}`}
                                                >
                                                    <EllipsisVertical className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                align="end"
                                                className="w-56"
                                            >
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={show.url(
                                                            course.id,
                                                        )}
                                                    >
                                                        <BookOpenCheck />
                                                        Buka course
                                                    </Link>
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                            Belum ada course tersedia.{' '}
                            {activePeriod
                                ? 'Course akan muncul bila mata pelajaran dan jenjangnya cocok dengan jadwal mengajar yang sudah dipublikasikan.'
                                : 'Course yang sesuai akan tampil setelah jadwal mengajar dipublikasikan pada semester aktif.'}
                        </p>
                    )}
                    {courses.total > courses.data.length && (
                        <nav
                            aria-label="Halaman course"
                            className="flex flex-wrap items-center justify-center gap-2"
                        >
                            {courses.links.map((link, position) => (
                                <Button
                                    key={position}
                                    asChild={link.url !== null}
                                    disabled={link.url === null}
                                    size="sm"
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                >
                                    {link.url ? (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            aria-current={
                                                link.active ? 'page' : undefined
                                            }
                                        >
                                            {link.label
                                                .replace('&laquo;', '‹')
                                                .replace('&raquo;', '›')}
                                        </Link>
                                    ) : (
                                        link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')
                                    )}
                                </Button>
                            ))}
                        </nav>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
