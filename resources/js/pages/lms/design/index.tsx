import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import CourseFields from '@/components/lms/course-fields';
import type {
    CourseFormData,
    DesignerOption,
    SubjectOption,
} from '@/components/lms/course-fields';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDate } from '@/lib/date-time';
import { design } from '@/routes/lms';
import { edit, revise, store } from '@/routes/lms/design';
import type { BreadcrumbItem } from '@/types';
import { courseStatusLabels } from '@/types/lms';

type Course = {
    id: number;
    title: string;
    description: string | null;
    subject: string;
    grade_level: string;
    designer: string;
    revision_number: number;
    status: string;
    editable: boolean;
    can_revise: boolean;
    revision_id: string;
    updated_at: string | null;
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
        search?: string;
        subject_id?: number | null;
        grade_level?: string;
        per_page?: number;
    };
    filterOptions: { subjects: SubjectOption[]; gradeLevels: string[] };
    designers: DesignerOption[];
    canCreate: boolean;
    canChooseDesigner: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'LMS', href: design.url() },
    { title: 'Perancangan Course', href: design.url() },
];

export default function LmsCourseDesignIndex({
    courses,
    filters,
    filterOptions,
    designers,
    canCreate,
    canChooseDesigner,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [subjectId, setSubjectId] = useState(
        filters.subject_id?.toString() ?? '',
    );
    const [gradeLevel, setGradeLevel] = useState(filters.grade_level ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [open, setOpen] = useState(false);
    const confirm = useConfirmation();
    const [transitionErrors, setTransitionErrors] = useState<string[]>([]);
    const transition = async (course: Course) => {
        if (
            await confirm({
                title: 'Buat revisi baru?',
                description:
                    'Revisi terakhir disalin ke Draft baru. Versi aktif tetap digunakan sampai revisi baru diaktifkan.',
            })
        ) {
            router.post(
                revise.url(course.id),
                { revision_id: course.revision_id },
                {
                    onError: (errors) =>
                        setTransitionErrors(Object.values(errors)),
                    onBefore: () => setTransitionErrors([]),
                },
            );
        }
    };
    const form = useForm<CourseFormData>({
        title: '',
        subject_id: '',
        grade_level: '',
        description: '',
        designer_id: '',
        revision_id: '',
    });
    const filterParams = (): Record<string, string> => ({
        subject_id: subjectId,
        grade_level: gradeLevel,
        ...(search.trim() ? { search: search.trim() } : {}),
    });
    const navigate = (page: number, perPage = courses.per_page) =>
        router.get(
            design.url(),
            { ...filterParams(), page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setOpen(true);
    };
    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    };

    const columns: DataTableColumn<Course>[] = [
        {
            id: 'course',
            header: 'Course',
            cell: (course) => (
                <div className="grid gap-1">
                    <span className="font-medium">{course.title}</span>
                    {course.description && (
                        <span className="line-clamp-2 max-w-2xl text-sm text-muted-foreground">
                            {course.description}
                        </span>
                    )}
                    <span className="text-xs text-muted-foreground">
                        {course.subject} · Kelas {course.grade_level}
                    </span>
                </div>
            ),
        },
        {
            id: 'designer',
            header: 'Perancang',
            cell: (course) => course.designer,
        },
        {
            id: 'revision',
            header: 'Status',
            cell: (course) => (
                <div className="grid gap-1">
                    <span>
                        {courseStatusLabels[course.status] ?? course.status}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Revisi {course.revision_number || 1}
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
            <Head title="Perancangan Course · LMS" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Perancangan Course"
                        description="Susun course baku milik sekolah berdasarkan mata pelajaran dan jenjang."
                    />
                    {canCreate && (
                        <Button
                            onClick={openCreate}
                            className="w-full md:w-auto md:shrink-0"
                        >
                            <Plus />
                            Tambah Course
                        </Button>
                    )}
                </div>
                <div className="@container grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 @min-[40rem]:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="course-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="course-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' && navigate(1)
                                    }
                                    placeholder="Judul atau deskripsi..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label>Mata Pelajaran</Label>
                            <SearchableCombobox
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
                                searchPlaceholder="Search mata pelajaran..."
                                emptyMessage="Mata pelajaran tidak ditemukan."
                            />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label>Jenjang</Label>
                            <SearchableCombobox
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
                                searchPlaceholder="Search jenjang..."
                                emptyMessage="Jenjang tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button onClick={() => navigate(1)}>Filter</Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    setSubjectId('');
                                    setGradeLevel('');
                                    router.get(
                                        design.url(),
                                        { page: 1, per_page: courses.per_page },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                            replace: true,
                                        },
                                    );
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
                {transitionErrors.length > 0 && (
                    <AlertError
                        title="Tindakan belum berhasil"
                        errors={transitionErrors}
                    />
                )}
                <DataTableShell
                    data={courses.data}
                    columns={columns}
                    getRowKey={(course) => course.id}
                    emptyState="Belum ada course. Ubah filter atau buat rancangan course baru."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua course di halaman ini',
                        getRowLabel: (course) => `Pilih course ${course.title}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} course dipilih.`,
                    }}
                    rowActions={{
                        label: (course) => `Aksi course ${course.title}`,
                        render: (course) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() =>
                                        router.visit(edit.url(course.id))
                                    }
                                >
                                    {course.editable ? 'Buka draf' : 'Lihat'}
                                </DropdownMenuItem>
                                {course.can_revise && (
                                    <DropdownMenuItem
                                        onClick={() => transition(course)}
                                    >
                                        Revisi
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
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!value) {
                        form.clearErrors();
                    }

                    setOpen(value);
                }}
            >
                <ModalShell size="large">
                    <form onSubmit={submit} className="grid gap-5">
                        <DialogHeader>
                            <DialogTitle>Rancang Course Baru</DialogTitle>
                            <DialogDescription>
                                Course ini merupakan aset sekolah. Lengkapi
                                materi setelah Draft tersimpan, lalu aktifkan
                                melalui Course Management.
                            </DialogDescription>
                        </DialogHeader>
                        <CourseFields
                            data={form.data}
                            setData={form.setData}
                            errors={form.errors}
                            subjects={filterOptions.subjects}
                            gradeLevels={filterOptions.gradeLevels}
                            designers={
                                canChooseDesigner ? designers : undefined
                            }
                            disabled={form.processing}
                        />
                        <InputError message={form.errors.revision_id} />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Menyimpan…' : 'Simpan Draf'}
                            </Button>
                        </DialogFooter>
                    </form>
                </ModalShell>
            </Dialog>
        </AppLayout>
    );
}
