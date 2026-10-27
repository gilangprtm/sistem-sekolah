import { Head, router } from '@inertiajs/react';
import {
    ExternalLink,
    LockKeyhole,
    Pencil,
    Plus,
    Search,
    Trash2,
    UnlockKeyhole,
} from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
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
import {
    destroy,
    index,
    store,
    update,
    availability,
} from '@/routes/teacher/quizzes';
import { unlock } from '@/routes/teacher/quizzes/attempts';
import type { BreadcrumbItem } from '@/types';

type Subject = { id: number; code: string; name: string };
type Quiz = {
    id: number;
    subject: Subject;
    academic_year: string | null;
    academic_period: string | null;
    link: string;
    is_open: boolean;
};
type Attempt = { id: number; student_name: string | null; exit_count: number };
type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    quizzes: Paginated<Quiz>;
    monitoring: {
        quiz_id: number;
        subject: string | null;
        attempts: Paginated<Attempt>;
    } | null;
    subjects: Subject[];
    activeContext: { year: string; period: string } | null;
    filters: {
        search: string;
        subject_id: number | null;
        availability: string;
    };
    filterOptions: { subjects: { id: number; name: string }[] };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Guru', href: index.url() },
    { title: 'Ulangan', href: index.url() },
];

export default function TeacherQuizzes({
    quizzes,
    monitoring,
    subjects,
    activeContext,
    filters,
    filterOptions,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Quiz | null>(null);
    const [subjectId, setSubjectId] = useState('');
    const [link, setLink] = useState('');
    const [search, setSearch] = useState(filters.search);
    const [filterSubject, setFilterSubject] = useState(
        filters.subject_id?.toString() ?? '',
    );
    const [filterAvailability, setFilterAvailability] = useState(
        filters.availability,
    );
    const [selectedQuizzes, setSelectedQuizzes] = useState<number[]>([]);
    const monitorTarget = monitoring?.quiz_id ?? null;
    const [unlockTarget, setUnlockTarget] = useState<Attempt | null>(null);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Quiz | null>(null);
    const navigate = (params: Record<string, string | number>) =>
        router.get(
            index.url(),
            {
                search: search.trim(),
                subject_id: filterSubject,
                availability: filterAvailability,
                ...params,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => setSelectedQuizzes([]),
            },
        );
    const resetFilters = () => {
        setSearch('');
        setFilterSubject('');
        setFilterAvailability('');
        navigate({
            search: '',
            subject_id: '',
            availability: '',
            page: 1,
            per_page: quizzes.per_page,
        });
    };
    const openForm = (quiz?: Quiz) => {
        setEditing(quiz ?? null);
        setSubjectId(quiz ? String(quiz.subject.id) : '');
        setLink(quiz?.link ?? '');
        setErrors({});
        setOpen(true);
    };
    const columns: DataTableColumn<Quiz>[] = [
        {
            id: 'subject',
            header: 'Mata Pelajaran',
            cell: (quiz) => (
                <>
                    <span className="font-medium">{quiz.subject.name}</span>
                    <span className="ml-2 text-muted-foreground">
                        {quiz.subject.code}
                    </span>
                </>
            ),
        },
        {
            id: 'link',
            header: 'Google Form',
            cell: (quiz) => (
                <a
                    href={quiz.link}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1 text-primary underline"
                >
                    Buka Form <ExternalLink className="size-3" />
                </a>
            ),
        },
        {
            id: 'state',
            header: 'Akses Siswa',
            cell: (quiz) =>
                quiz.is_open ? 'Buka untuk semua siswa' : 'Terkunci',
        },
        {
            id: 'period',
            header: 'Tahun Ajaran / Semester',
            cell: (quiz) =>
                [quiz.academic_year, quiz.academic_period]
                    .filter(Boolean)
                    .join(' · ') || '—',
        },
    ];
    const monitorColumns: DataTableColumn<Attempt>[] = [
        {
            id: 'student',
            header: 'Nama Siswa',
            cell: (attempt) => attempt.student_name ?? 'Siswa',
        },
        {
            id: 'exits',
            header: 'Keluar',
            cell: (attempt) => `${attempt.exit_count}/3`,
        },
    ];
    const submit = () => {
        setProcessing(true);
        const data = { subject_id: subjectId, link };
        const callbacks = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: setErrors,
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(update.url(editing.id), data, callbacks);
        } else {
            router.post(store.url(), data, callbacks);
        }
    };
    const toggle = (quiz: Quiz) =>
        router.patch(
            availability.url(quiz.id),
            { is_open: !quiz.is_open },
            { preserveScroll: true },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ulangan" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Ulangan"
                        description="Kelola Ulangan yang Anda buat untuk semua siswa."
                    />
                    <Button
                        onClick={() => openForm()}
                        disabled={!activeContext || subjects.length === 0}
                        className="w-full md:w-auto"
                    >
                        <Plus />
                        Tambah Ulangan
                    </Button>
                </div>
                <div className="rounded-xl border p-4 text-sm">
                    {activeContext ? (
                        <>
                            Tahun Ajaran: <strong>{activeContext.year}</strong>{' '}
                            · Semester: <strong>{activeContext.period}</strong>{' '}
                            · Ulangan baru tersimpan dalam keadaan terkunci.
                        </>
                    ) : (
                        <p className="text-destructive">
                            Tahun Ajaran dan semester aktif belum tersedia.
                        </p>
                    )}
                </div>
                {subjects.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada Mata Pelajaran aktif yang Anda ampu. Hubungi
                        Kurikulum untuk memeriksa penugasan Guru Mata Pelajaran.
                    </p>
                )}
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate({ page: 1, per_page: quizzes.per_page });
                    }}
                    className="@container grid gap-4 rounded-xl border p-4"
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-[2_1_18rem] gap-2">
                            <Label htmlFor="quizzes-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    id="quizzes-search"
                                    className="pl-8"
                                    value={search}
                                    placeholder="Kode atau nama Mata Pelajaran..."
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid min-w-0 flex-[1_1_12rem] gap-2">
                            <Label htmlFor="quizzes-filter-subject">
                                Mata Pelajaran
                            </Label>
                            <SearchableCombobox
                                id="quizzes-filter-subject"
                                value={filterSubject}
                                onChange={setFilterSubject}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...filterOptions.subjects.map(
                                        (subject) => ({
                                            value: String(subject.id),
                                            label: subject.name,
                                        }),
                                    ),
                                ]}
                                placeholder="Semua"
                                searchPlaceholder="Search mata pelajaran…"
                                emptyMessage="Mata pelajaran tidak ditemukan."
                            />
                        </div>
                        <div className="grid min-w-0 flex-[1_1_12rem] gap-2">
                            <Label htmlFor="quizzes-filter-availability">
                                Akses Siswa
                            </Label>
                            <SearchableCombobox
                                id="quizzes-filter-availability"
                                value={filterAvailability}
                                onChange={setFilterAvailability}
                                options={[
                                    { value: '', label: 'Semua' },
                                    { value: 'open', label: 'Buka' },
                                    { value: 'locked', label: 'Terkunci' },
                                ]}
                                placeholder="Semua"
                                searchPlaceholder="Search akses siswa…"
                                emptyMessage="Status tidak ditemukan."
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
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
                <DataTableShell
                    data={quizzes.data}
                    columns={columns}
                    getRowKey={(quiz) => quiz.id}
                    emptyState="Belum ada Ulangan yang sesuai."
                    selection={{
                        selectedKeys: selectedQuizzes,
                        onSelectionChange: setSelectedQuizzes,
                        selectAllLabel: 'Pilih semua Ulangan di halaman ini',
                        getRowLabel: (quiz) =>
                            `Pilih Ulangan ${quiz.subject.name}`,
                        selectedCountLabel: (count) =>
                            `${count} Ulangan dipilih.`,
                        clearSelectionLabel: 'Batal pilih',
                    }}
                    rowActions={{
                        variant: 'menu',
                        render: (quiz) => (
                            <>
                                <DropdownMenuItem onSelect={() => toggle(quiz)}>
                                    {quiz.is_open ? (
                                        <>
                                            <LockKeyhole />
                                            Kunci
                                        </>
                                    ) : (
                                        <>
                                            <UnlockKeyhole />
                                            Buka untuk Semua Siswa
                                        </>
                                    )}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => {
                                        navigate({
                                            search,
                                            page: quizzes.current_page,
                                            per_page: quizzes.per_page,
                                            monitor_quiz_id: quiz.id,
                                        });
                                    }}
                                >
                                    Monitoring Siswa
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => openForm(quiz)}
                                >
                                    <Pencil />
                                    Ubah
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    variant="destructive"
                                    onSelect={() => setDeleteTarget(quiz)}
                                >
                                    <Trash2 />
                                    Hapus
                                </DropdownMenuItem>
                            </>
                        ),
                    }}
                    pagination={{
                        resource: quizzes,
                        noun: 'ulangan',
                        onPageChange: (page) =>
                            navigate({
                                search,
                                page,
                                per_page: quizzes.per_page,
                            }),
                        onPerPageChange: (per_page) =>
                            navigate({ search, page: 1, per_page }),
                    }}
                />
                <Dialog
                    open={monitorTarget !== null}
                    onOpenChange={(visible) => {
                        if (!visible) {
                            navigate({
                                search,
                                page: quizzes.current_page,
                                per_page: quizzes.per_page,
                            });
                        }
                    }}
                >
                    <ModalShell size="large">
                        <DialogHeader>
                            <DialogTitle>Monitoring Siswa Terkunci</DialogTitle>
                            <DialogDescription>
                                {monitoring?.subject} · Siswa yang terkunci
                                karena tiga kali keluar atau telah menekan
                                Selesai.
                            </DialogDescription>
                        </DialogHeader>
                        <DataTableShell
                            data={monitoring?.attempts.data ?? []}
                            columns={monitorColumns}
                            getRowKey={(attempt) => attempt.id}
                            emptyState="Tidak ada attempt terkunci."
                            rowActions={{
                                variant: 'custom',
                                render: (attempt) => (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setUnlockTarget(attempt);
                                            setReason('');
                                            setErrors({});
                                        }}
                                    >
                                        Buka blokir
                                    </Button>
                                ),
                            }}
                            pagination={
                                monitoring
                                    ? {
                                          resource: monitoring.attempts,
                                          noun: 'siswa',
                                          onPageChange: (page) =>
                                              navigate({
                                                  search,
                                                  page: quizzes.current_page,
                                                  per_page: quizzes.per_page,
                                                  monitor_quiz_id:
                                                      monitoring.quiz_id,
                                                  monitor_page: page,
                                              }),
                                          onPerPageChange: (per_page) =>
                                              navigate({
                                                  search,
                                                  page: quizzes.current_page,
                                                  per_page: quizzes.per_page,
                                                  monitor_quiz_id:
                                                      monitoring.quiz_id,
                                                  monitor_page: 1,
                                                  monitor_per_page: per_page,
                                              }),
                                      }
                                    : undefined
                            }
                        />
                    </ModalShell>
                </Dialog>
                <Dialog open={open} onOpenChange={setOpen}>
                    <ModalShell>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Ubah Ulangan' : 'Tambah Ulangan'}
                            </DialogTitle>
                            <DialogDescription>
                                Ulangan mengikuti Mata Pelajaran, memakai Google
                                Form, dan mulai dalam keadaan terkunci.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="quiz-subject">
                                    Mata Pelajaran
                                </Label>
                                <SearchableCombobox
                                    id="quiz-subject"
                                    value={subjectId}
                                    onChange={setSubjectId}
                                    disabled={!subjects.length}
                                    options={subjects.map((subject) => ({
                                        value: String(subject.id),
                                        label: `${subject.code} — ${subject.name}`,
                                    }))}
                                    placeholder="Pilih Mata Pelajaran"
                                    searchPlaceholder="Cari Mata Pelajaran"
                                    emptyMessage="Mata Pelajaran tidak ditemukan."
                                />
                                <InputError message={errors.subject_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="quiz-google-form">
                                    Link Google Form
                                </Label>
                                <Input
                                    id="quiz-google-form"
                                    type="url"
                                    value={link}
                                    onChange={(event) =>
                                        setLink(event.target.value)
                                    }
                                    placeholder="https://forms.gle/..."
                                />
                                <InputError message={errors.link} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button disabled={processing} onClick={submit}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </ModalShell>
                </Dialog>
                <Dialog
                    open={unlockTarget !== null}
                    onOpenChange={(visible) => {
                        if (!visible && !processing) {
                            setUnlockTarget(null);
                            setReason('');
                            setErrors({});
                        }
                    }}
                >
                    <ModalShell>
                        <DialogHeader>
                            <DialogTitle>Buka blokir siswa</DialogTitle>
                            <DialogDescription>
                                Blokir siswa ini saja yang dibuka. Status
                                Ulangan Buka/Kunci tidak berubah.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="quiz-unlock-reason">Alasan</Label>
                            <Input
                                id="quiz-unlock-reason"
                                value={reason}
                                onChange={(event) =>
                                    setReason(event.target.value)
                                }
                                aria-invalid={Boolean(errors.reason)}
                            />
                            <InputError message={errors.reason} />
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setUnlockTarget(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                disabled={processing}
                                onClick={() => {
                                    if (!unlockTarget) {
                                        return;
                                    }

                                    setProcessing(true);
                                    router.post(
                                        unlock.url(unlockTarget.id),
                                        { reason },
                                        {
                                            preserveScroll: true,
                                            onError: setErrors,
                                            onSuccess: () => {
                                                setUnlockTarget(null);
                                                setReason('');
                                            },
                                            onFinish: () =>
                                                setProcessing(false),
                                        },
                                    );
                                }}
                            >
                                Buka blokir
                            </Button>
                        </DialogFooter>
                    </ModalShell>
                </Dialog>
                <Dialog
                    open={deleteTarget !== null}
                    onOpenChange={(visible) => {
                        if (!visible) {
                            setDeleteTarget(null);
                        }
                    }}
                >
                    <ModalShell>
                        <DialogHeader>
                            <DialogTitle>Hapus Ulangan?</DialogTitle>
                            <DialogDescription>
                                Penghapusan juga menghapus riwayat attempt siswa
                                Ulangan ini.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setDeleteTarget(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (deleteTarget) {
                                        router.delete(
                                            destroy.url(deleteTarget.id),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setDeleteTarget(null),
                                            },
                                        );
                                    }
                                }}
                            >
                                Hapus Permanen
                            </Button>
                        </DialogFooter>
                    </ModalShell>
                </Dialog>
            </div>
        </AppLayout>
    );
}
