import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDate } from '@/lib/date-time';
import { design, management } from '@/routes/lms';
import { activate, archive, restore, show } from '@/routes/lms/management';
import type { BreadcrumbItem } from '@/types';
import { courseManagementStatusLabels } from '@/types/lms';

type Course = {
    id: number;
    title: string;
    active_title: string | null;
    subject: string;
    grade_level: string;
    designer: string;
    revision_number: number;
    status: string;
    approved_revision_number: number | null;
    archived_at: string | null;
    updated_at: string | null;
    revision_id: string;
    can_archive: boolean;
    can_activate: boolean;
    can_restore: boolean;
};
type Props = {
    courses: {
        data: Course[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search: string;
        status: string;
        subject_id: number | null;
        grade_level: string;
        per_page: number;
    };
    filterOptions: {
        subjects: { id: number; name: string }[];
        gradeLevels: string[];
    };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LMS', href: design.url() },
    { title: 'Course Management', href: management.url() },
];

export default function LmsCourseManagementIndex({
    courses,
    filters,
    filterOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);
    const [subjectId, setSubjectId] = useState(
        filters.subject_id?.toString() ?? '',
    );
    const [gradeLevel, setGradeLevel] = useState(filters.grade_level);
    const [selected, setSelected] = useState<number[]>([]);
    const [errors, setErrors] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const confirm = useConfirmation();
    const navigate = (page = 1, perPage = courses.per_page) =>
        router.get(
            management.url(),
            {
                page,
                per_page: perPage,
                search: search.trim(),
                status,
                subject_id: subjectId,
                grade_level: gradeLevel,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const transitionCourse = async (
        course: Course,
        action: 'activate' | 'archive' | 'restore',
    ) => {
        if (busy) {
            return;
        }

        const description = {
            activate: {
                title: 'Aktifkan Course?',
                description:
                    'Revisi ini akan ditetapkan sebagai versi aktif Course.',
            },
            archive: {
                title: 'Arsipkan Course?',
                description:
                    'Course tidak lagi tersedia untuk penggunaan aktif. Riwayat revisi dan hasil yang sudah tercatat tetap disimpan.',
            },
            restore: {
                title: 'Pulihkan Course sebagai Draft?',
                description:
                    'Course akan keluar dari arsip sebagai Draft. Course perlu diaktifkan kembali untuk menjadi aktif.',
            },
        }[action];

        if (!(await confirm(description))) {
            return;
        }

        setErrors([]);
        router.post(
            action === 'activate'
                ? activate.url(course.id)
                : action === 'archive'
                  ? archive.url(course.id)
                  : restore.url(course.id),
            { revision_id: course.revision_id },
            {
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: (errors) => setErrors(Object.values(errors)),
            },
        );
    };
    const columns: DataTableColumn<Course>[] = [
        {
            id: 'course',
            header: 'Course',
            cell: (course) => (
                <div className="grid gap-1">
                    <span className="font-medium">{course.title}</span>
                    <span className="text-xs text-muted-foreground">
                        {course.subject} · Kelas {course.grade_level}
                    </span>
                    {course.active_title && (
                        <span className="text-xs text-muted-foreground">
                            Versi aktif: {course.active_title} · Revisi{' '}
                            {course.approved_revision_number}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: 'designer',
            header: 'Perancang',
            cell: (course) => course.designer,
        },
        {
            id: 'status',
            header: 'Status',
            cell: (course) => (
                <div className="grid gap-1">
                    <span>
                        {courseManagementStatusLabels[course.status] ??
                            course.status}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Revisi terakhir {course.revision_number}
                    </span>
                </div>
            ),
        },
        {
            id: 'updated',
            header: 'Diperbarui',
            cell: (course) =>
                course.updated_at ? formatWitaDate(course.updated_at) : '—',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Course Management · LMS" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <Heading
                    variant="small"
                    title="Course Management"
                    description="Aktifkan Course yang siap digunakan dan kelola arsip."
                />
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-4">
                            <Label htmlFor="management-search">Cari</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    id="management-search"
                                    className="pl-8"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' && navigate()
                                    }
                                    placeholder="Judul atau deskripsi…"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="management-subject">
                                Mata Pelajaran
                            </Label>
                            <SearchableCombobox
                                id="management-subject"
                                value={subjectId}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.subjects.map(
                                        (subject) => ({
                                            value: String(subject.id),
                                            label: subject.name,
                                        }),
                                    ),
                                ]}
                                onChange={setSubjectId}
                                placeholder="Semua"
                                searchPlaceholder="Cari mata pelajaran…"
                                emptyMessage="Mata pelajaran tidak ditemukan."
                            />
                        </div>
                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="management-status">Status</Label>
                            <SearchableCombobox
                                id="management-status"
                                value={status}
                                options={[
                                    { value: '', label: 'Semua status' },
                                    ...Object.entries(
                                        courseManagementStatusLabels,
                                    ).map(([value, label]) => ({
                                        value,
                                        label,
                                    })),
                                ]}
                                onChange={setStatus}
                                placeholder="Semua status"
                                searchPlaceholder="Cari status…"
                                emptyMessage="Status tidak ditemukan."
                            />
                        </div>
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="management-grade">Jenjang</Label>
                            <SearchableCombobox
                                id="management-grade"
                                value={gradeLevel}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.gradeLevels.map(
                                        (grade) => ({
                                            value: grade,
                                            label: `Kelas ${grade}`,
                                        }),
                                    ),
                                ]}
                                onChange={setGradeLevel}
                                placeholder="Semua"
                                searchPlaceholder="Cari jenjang…"
                                emptyMessage="Jenjang tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2 md:col-span-12">
                            <Button onClick={() => navigate()}>Filter</Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    setStatus('');
                                    setSubjectId('');
                                    setGradeLevel('');
                                    router.get(
                                        management.url(),
                                        {
                                            per_page: courses.per_page,
                                        },
                                        { preserveState: true, replace: true },
                                    );
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
                {errors.length > 0 && <AlertError errors={errors} />}
                <DataTableShell
                    data={courses.data}
                    columns={columns}
                    getRowKey={(course) => course.id}
                    emptyState="Belum ada Course yang sesuai dengan filter."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua Course di halaman ini',
                        getRowLabel: (course) => `Pilih ${course.title}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} Course dipilih.`,
                    }}
                    rowActions={{
                        label: (course) => `Aksi ${course.title}`,
                        render: (course) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.visit(show.url(course.id))
                                    }
                                >
                                    Detail
                                </DropdownMenuItem>
                                {course.can_activate && (
                                    <DropdownMenuItem
                                        disabled={busy}
                                        onClick={() =>
                                            transitionCourse(course, 'activate')
                                        }
                                    >
                                        Aktifkan
                                    </DropdownMenuItem>
                                )}
                                {course.can_archive && (
                                    <DropdownMenuItem
                                        disabled={busy}
                                        onClick={() =>
                                            transitionCourse(course, 'archive')
                                        }
                                    >
                                        Arsip
                                    </DropdownMenuItem>
                                )}
                                {course.can_restore && (
                                    <DropdownMenuItem
                                        disabled={busy}
                                        onClick={() =>
                                            transitionCourse(course, 'restore')
                                        }
                                    >
                                        Pulihkan
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: courses,
                        onPageChange: (page) => navigate(page),
                        onPerPageChange: (perPage) => navigate(1, perPage),
                        noun: 'course',
                    }}
                />
            </div>
        </AppLayout>
    );
}
