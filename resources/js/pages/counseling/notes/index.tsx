import { Head, router, useHttp } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { CalendarIcon, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import {
    Dialog,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import {
    formatWitaDate,
    formatWitaDateTime,
    witaDateKey,
} from '@/lib/date-time';
import {
    destroy,
    index,
    show,
    store,
    students as studentLookup,
    update,
} from '@/routes/counseling/notes';
import type { BreadcrumbItem } from '@/types';

type Student = { id: number; full_name: string; nisn: string | null };
type Note = {
    id: number;
    student: Student;
    counseling_date: string;
    creator_name: string;
    updater_name: string | null;
    created_at: string | null;
    updated_at: string | null;
};
type Detail = Note & { content: string };
type Props = {
    notes: {
        data: Note[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search: string };
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
};
type Editor = { mode: 'create' | 'view' | 'edit'; note: Note | null };
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catatan Konseling', href: index.url() },
];
const studentLabel = (student: Student) =>
    `${student.full_name}${student.nisn ? ` — ${student.nisn}` : ''}`;

function NoteModal({
    editor,
    onClose,
    onSaved,
}: {
    editor: Editor;
    onClose: () => void;
    onSaved: () => void;
}) {
    const readOnly = editor.mode === 'view';
    const [loaded, setLoaded] = useState(editor.mode === 'create');
    const [detail, setDetail] = useState<Detail | null>(null);
    const [message, setMessage] = useState('');
    const [uncertain, setUncertain] = useState(false);
    const [studentSearch, setStudentSearch] = useState('');
    const [options, setOptions] = useState<Student[]>([]);
    const [selectedStudent, setSelectedStudent] = useState<Student | null>(
        editor.note?.student ?? null,
    );
    const [lookupError, setLookupError] = useState('');
    const [calendarOpen, setCalendarOpen] = useState(false);
    const loading = useHttp<Record<string, never>, { note: Detail }>({});
    const lookup = useHttp<Record<string, never>, { students: Student[] }>({});
    const form = useHttp<
        { student_id: string; counseling_date: string; content: string },
        { id: number }
    >({
        student_id: '',
        counseling_date: witaDateKey(new Date()),
        content: '',
    });
    const { get: getNote, cancel: cancelNote } = loading;
    const { setData } = form;
    const { get: getStudents, cancel: cancelStudents } = lookup;
    const selectedDate = form.data.counseling_date
        ? parseISO(form.data.counseling_date)
        : undefined;

    useEffect(() => {
        if (!editor.note) {
            return;
        }

        void getNote(show.url(editor.note.id), {
            onSuccess: ({ note }) => {
                setDetail(note);
                setSelectedStudent(note.student);
                setData({
                    student_id: String(note.student.id),
                    counseling_date: note.counseling_date,
                    content: note.content,
                });
                setLoaded(true);
            },
            onHttpException: () =>
                setMessage(
                    'Catatan tidak dapat dimuat. Tutup dan muat ulang daftar.',
                ),
            onNetworkError: () =>
                setMessage(
                    'Koneksi bermasalah. Tutup dan buka kembali catatan.',
                ),
        }).catch(() => undefined);

        return cancelNote;
    }, [editor.note, getNote, cancelNote, setData]);

    useEffect(() => {
        if (readOnly) {
            return;
        }

        const timer = window.setTimeout(() => {
            setLookupError('');
            void getStudents(
                studentLookup.url({ query: { search: studentSearch } }),
                {
                    onSuccess: (response) => setOptions(response.students),
                    onHttpException: () =>
                        setLookupError('Daftar siswa tidak dapat dimuat.'),
                    onNetworkError: () =>
                        setLookupError(
                            'Koneksi bermasalah. Ulangi pencarian siswa.',
                        ),
                },
            ).catch(() => undefined);
        }, 250);

        return () => {
            window.clearTimeout(timer);
            cancelStudents();
        };
    }, [studentSearch, readOnly, getStudents, cancelStudents]);

    const choices = selectedStudent
        ? [
              selectedStudent,
              ...options.filter((student) => student.id !== selectedStudent.id),
          ]
        : options;
    const close = () => {
        if (!form.processing) {
            form.resetAndClearErrors();
            onClose();
        }
    };
    const save = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing || !loaded || readOnly || uncertain) {
            return;
        }

        setMessage('');
        const requestOptions = {
            onSuccess: () => {
                form.resetAndClearErrors();
                toast.success('Catatan konseling berhasil disimpan.');
                onSaved();
            },
            onHttpException: () => {
                setUncertain(true);
                setMessage(
                    'Penyimpanan tidak dapat dipastikan. Tutup dan periksa daftar sebelum mencoba kembali.',
                );
            },
            onNetworkError: () => {
                setUncertain(true);
                setMessage(
                    'Koneksi terputus. Status penyimpanan belum diketahui; tutup dan muat ulang daftar sebelum mencoba kembali.',
                );
            },
        };
        const request = editor.note
            ? form.put(update.url(editor.note.id), requestOptions)
            : form.post(store.url(), requestOptions);
        void request.catch(() => undefined);
    };

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <ModalShell
                size="medium"
                showCloseButton={!form.processing}
                onEscapeKeyDown={(event) =>
                    form.processing && event.preventDefault()
                }
                onInteractOutside={(event) =>
                    form.processing && event.preventDefault()
                }
            >
                <DialogHeader>
                    <DialogTitle>
                        {readOnly
                            ? 'Catatan Konseling'
                            : editor.note
                              ? 'Ubah Catatan Konseling'
                              : 'Tambah Catatan Konseling'}
                    </DialogTitle>
                </DialogHeader>
                {message && (
                    <p role="alert" className="text-sm text-destructive">
                        {message}
                    </p>
                )}
                {!loaded && (
                    <p role="status" className="text-sm text-muted-foreground">
                        Memuat catatan…
                    </p>
                )}
                {loaded && readOnly && detail && (
                    <div className="grid gap-4">
                        <div className="grid gap-1">
                            <p className="font-medium">
                                {studentLabel(detail.student)}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {formatWitaDate(
                                    `${detail.counseling_date}T00:00:00+08:00`,
                                )}
                            </p>
                        </div>
                        <p className="text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
                            {detail.content}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Dibuat oleh {detail.creator_name}
                            {detail.created_at
                                ? ` · ${formatWitaDateTime(detail.created_at)} WITA`
                                : ''}
                        </p>
                        {detail.updater_name && (
                            <p className="text-xs text-muted-foreground">
                                Terakhir diubah oleh {detail.updater_name}
                                {detail.updated_at
                                    ? ` · ${formatWitaDateTime(detail.updated_at)} WITA`
                                    : ''}
                            </p>
                        )}
                    </div>
                )}
                {loaded && !readOnly && (
                    <form onSubmit={save} className="grid gap-4">
                        <fieldset
                            disabled={form.processing || uncertain}
                            className="grid min-w-0 gap-4"
                        >
                            <div className="grid min-w-0 grid-cols-1 items-start gap-4 md:grid-cols-[minmax(0,3fr)_minmax(10rem,1fr)]">
                                <div className="grid min-w-0 gap-2">
                                    <Label>Siswa</Label>
                                    <SearchableCombobox
                                        value={form.data.student_id}
                                        options={choices.map((student) => ({
                                            value: String(student.id),
                                            label: studentLabel(student),
                                        }))}
                                        onChange={(value) => {
                                            form.setData('student_id', value);
                                            setSelectedStudent(
                                                choices.find(
                                                    (student) =>
                                                        String(student.id) ===
                                                        value,
                                                ) ?? null,
                                            );
                                        }}
                                        onSearchChange={(value) => {
                                            setOptions([]);
                                            setStudentSearch(value);
                                        }}
                                        placeholder="Pilih siswa"
                                        searchPlaceholder="Cari nama atau NISN…"
                                        emptyMessage={
                                            lookup.processing
                                                ? 'Memuat siswa…'
                                                : 'Siswa tidak ditemukan. Coba kata kunci lain.'
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Cari nama atau NISN. Maksimal 30 hasil
                                        ditampilkan.
                                    </p>
                                    <InputError
                                        message={
                                            lookupError ||
                                            form.errors.student_id
                                        }
                                    />
                                </div>
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="counseling-date">
                                        Tanggal Konseling
                                    </Label>
                                    <Popover
                                        open={calendarOpen}
                                        onOpenChange={setCalendarOpen}
                                    >
                                        <PopoverTrigger asChild>
                                            <Button
                                                id="counseling-date"
                                                type="button"
                                                variant="outline"
                                                className="w-full min-w-0 justify-start text-left font-normal"
                                                aria-invalid={Boolean(
                                                    form.errors.counseling_date,
                                                )}
                                            >
                                                <CalendarIcon className="mr-2 size-4 shrink-0" />
                                                <span className="truncate">
                                                    {selectedDate
                                                        ? format(
                                                              selectedDate,
                                                              'd MMM yyyy',
                                                              {
                                                                  locale: indonesianLocale,
                                                              },
                                                          )
                                                        : 'Pilih tanggal'}
                                                </span>
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            className="w-auto p-0"
                                            align="start"
                                        >
                                            <Calendar
                                                mode="single"
                                                captionLayout="dropdown"
                                                locale={indonesianLocale}
                                                selected={selectedDate}
                                                defaultMonth={selectedDate}
                                                disabled={
                                                    form.processing || uncertain
                                                }
                                                onSelect={(date) => {
                                                    form.setData(
                                                        'counseling_date',
                                                        date
                                                            ? format(
                                                                  date,
                                                                  'yyyy-MM-dd',
                                                              )
                                                            : '',
                                                    );
                                                    setCalendarOpen(false);
                                                }}
                                            />
                                        </PopoverContent>
                                    </Popover>
                                    <InputError
                                        message={form.errors.counseling_date}
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="counseling-content">
                                    Catatan
                                </Label>
                                <Textarea
                                    id="counseling-content"
                                    rows={6}
                                    required
                                    maxLength={5000}
                                    value={form.data.content}
                                    onChange={(event) =>
                                        form.setData(
                                            'content',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(form.errors.content)}
                                />
                                <InputError message={form.errors.content} />
                            </div>
                        </fieldset>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={close}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    uncertain ||
                                    !form.data.student_id ||
                                    !form.data.counseling_date ||
                                    !form.data.content.trim()
                                }
                            >
                                {form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan Catatan'}
                            </Button>
                        </DialogFooter>
                    </form>
                )}
                {(readOnly || !loaded) && (
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            Tutup
                        </Button>
                    </DialogFooter>
                )}
            </ModalShell>
        </Dialog>
    );
}

export default function CounselingNotesIndex({
    notes,
    filters,
    canCreate,
    canUpdate,
    canDelete,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [selectedNotes, setSelectedNotes] = useState<number[]>([]);
    const [editor, setEditor] = useState<Editor | null>(null);
    const [deleting, setDeleting] = useState(false);
    const deletion = useHttp<Record<string, never>, { deleted: boolean }>({});
    const confirm = useConfirmation();
    const navigate = (page = 1, perPage = notes.per_page, query = search) => {
        setSelectedNotes([]);
        router.get(
            index.url(),
            { search: query, page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };
    const refresh = () => {
        setSelectedNotes([]);
        setEditor(null);
        router.reload({ only: ['notes'] });
    };
    const remove = async (note: Note) => {
        if (deleting || deletion.processing) {
            return;
        }

        setDeleting(true);

        try {
            if (
                await confirm({
                    title: 'Hapus catatan konseling?',
                    description: `Hapus catatan ${note.student.full_name} tanggal ${formatWitaDate(`${note.counseling_date}T00:00:00+08:00`)}? Catatan yang dihapus tidak dapat dikembalikan.`,
                })
            ) {
                await deletion
                    .delete(destroy.url(note.id), {
                        onSuccess: () => {
                            toast.success(
                                'Catatan konseling berhasil dihapus.',
                            );
                            navigate(1, notes.per_page, filters.search);
                        },
                        onHttpException: () => {
                            toast.error(
                                'Penghapusan tidak dapat dipastikan. Muat ulang daftar sebelum mencoba kembali.',
                            );
                        },
                        onNetworkError: () => {
                            toast.error(
                                'Koneksi terputus. Muat ulang daftar untuk memeriksa status penghapusan.',
                            );
                        },
                    })
                    .catch(() => undefined);
            }
        } finally {
            setDeleting(false);
        }
    };
    const columns: DataTableColumn<Note>[] = [
        {
            id: 'date',
            header: 'Tanggal Konseling',
            cell: (note) =>
                formatWitaDate(`${note.counseling_date}T00:00:00+08:00`),
        },
        {
            id: 'student',
            header: 'Siswa',
            cell: (note) => note.student.full_name,
            cellClassName: 'font-medium',
        },
        {
            id: 'nisn',
            header: 'NISN',
            cell: (note) => note.student.nisn ?? '-',
        },
        {
            id: 'author',
            header: 'Dibuat Oleh',
            cell: (note) => note.creator_name,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catatan Konseling" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Heading
                        variant="small"
                        title="Catatan Konseling"
                        description="Kelola catatan konseling siswa secara privat."
                    />
                    {canCreate && (
                        <Button
                            className="w-full md:w-auto md:shrink-0"
                            onClick={() =>
                                setEditor({ mode: 'create', note: null })
                            }
                        >
                            <Plus />
                            Tambah Catatan
                        </Button>
                    )}
                </div>
                <form
                    className="@container grid gap-4 rounded-xl border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        navigate();
                    }}
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-[2_1_18rem] gap-2">
                            <Label htmlFor="counseling-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="counseling-search"
                                    className="pl-8"
                                    placeholder="Nama siswa atau NISN…"
                                    maxLength={150}
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit">Filter</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    navigate(1, notes.per_page, '');
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </form>
                <DataTableShell
                    data={notes.data}
                    columns={columns}
                    getRowKey={(note) => note.id}
                    emptyState="Belum ada catatan konseling yang sesuai."
                    selection={{
                        selectedKeys: selectedNotes,
                        onSelectionChange: setSelectedNotes,
                        selectedCount: selectedNotes.length,
                        selectAllLabel:
                            'Pilih semua catatan konseling di halaman ini',
                        getRowLabel: (note) =>
                            `Pilih catatan ${note.student.full_name}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} catatan dipilih.`,
                        onSelectAll: (checked, rows) =>
                            setSelectedNotes(
                                checked ? rows.map((note) => note.id) : [],
                            ),
                    }}
                    rowActions={{
                        label: (note) =>
                            `Aksi catatan ${note.student.full_name}`,
                        render: (note) => (
                            <>
                                <DropdownMenuItem
                                    onClick={() =>
                                        setEditor({ mode: 'view', note })
                                    }
                                >
                                    Lihat
                                </DropdownMenuItem>
                                {canUpdate && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            setEditor({ mode: 'edit', note })
                                        }
                                    >
                                        Ubah
                                    </DropdownMenuItem>
                                )}
                                {canDelete && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        disabled={deleting}
                                        onClick={() => void remove(note)}
                                    >
                                        Hapus
                                    </DropdownMenuItem>
                                )}
                            </>
                        ),
                    }}
                    pagination={{
                        resource: notes,
                        noun: 'catatan',
                        onPageChange: (page) =>
                            navigate(page, notes.per_page, filters.search),
                        onPerPageChange: (perPage) =>
                            navigate(1, perPage, filters.search),
                    }}
                />
            </div>
            {editor && (
                <NoteModal
                    editor={editor}
                    onClose={() => setEditor(null)}
                    onSaved={refresh}
                />
            )}
        </AppLayout>
    );
}
