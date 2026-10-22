import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import ModalShell from '@/components/ui/modal-shell';
import { Textarea } from '@/components/ui/textarea';
import { formatWitaDateTime } from '@/lib/date-time';
import {
    index as studentNotesIndex,
    store as studentNotesStore,
} from '@/routes/teacher/student-notes';

type Student = {
    id: number;
    full_name: string | null;
    photo_url: string | null;
};
type Note = {
    id: number;
    teacher_name: string;
    content: string;
    created_at: string | null;
};
type NotesResponse = {
    student: Student;
    can_create: boolean;
    notes: {
        data: Note[];
        current_page: number;
        last_page: number;
        total: number;
    };
};
type CreatedResponse = { note: Note };

export default function StudentNotesModal({
    student,
    onClose,
}: {
    student: Student;
    onClose: () => void;
}) {
    const [notes, setNotes] = useState<NotesResponse | null>(null);
    const [saveMessage, setSaveMessage] = useState('');
    const [saveError, setSaveError] = useState('');
    const [page, setPage] = useState(1);
    const [loadError, setLoadError] = useState('');
    const history = useHttp<Record<string, never>, NotesResponse>({});
    const saving = useHttp<{ content: string }, CreatedResponse>({
        content: '',
    });

    const { get, cancel } = history;

    useEffect(() => {
        void get(studentNotesIndex.url(student.id), {
            onSuccess: (response) => setNotes(response),
            onHttpException: () =>
                setLoadError(
                    'Riwayat catatan tidak dapat dimuat. Periksa akses Anda.',
                ),
            onNetworkError: () =>
                setLoadError('Koneksi bermasalah. Coba muat ulang catatan.'),
        }).catch(() => undefined);

        return cancel;
    }, [student.id, get, cancel]);

    const loadPage = (nextPage: number) => {
        setLoadError('');
        void history
            .get(
                studentNotesIndex.url(student.id, {
                    query: { page: nextPage },
                }),
                {
                    onSuccess: (response) => {
                        setNotes(response);
                        setPage(nextPage);
                    },
                    onHttpException: () =>
                        setLoadError('Riwayat catatan tidak dapat dimuat.'),
                    onNetworkError: () =>
                        setLoadError(
                            'Koneksi bermasalah. Coba muat ulang catatan.',
                        ),
                },
            )
            .catch(() => undefined);
    };

    const save = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        event.stopPropagation();

        if (
            !saving.data.content.trim() ||
            saving.processing ||
            history.processing
        ) {
            return;
        }

        setSaveMessage('');
        setSaveError('');
        void saving
            .post(studentNotesStore.url(student.id), {
                onSuccess: () => {
                    setSaveMessage('Catatan berhasil disimpan.');
                    saving.reset();
                    loadPage(1);
                },
                onHttpException: () =>
                    setSaveError(
                        'Catatan tidak dapat disimpan. Periksa akses dan muat ulang riwayat sebelum mencoba kembali.',
                    ),
                onNetworkError: () =>
                    setSaveError(
                        'Koneksi terputus. Status penyimpanan belum diketahui; muat ulang riwayat sebelum mencoba kembali.',
                    ),
            })
            .catch(() => undefined);
    };

    const currentStudent = notes?.student ?? student;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !saving.processing) {
                    onClose();
                }
            }}
        >
            <ModalShell
                size="medium"
                aria-describedby="student-notes-description"
                showCloseButton={!saving.processing}
                onEscapeKeyDown={(event) => {
                    if (saving.processing) {
                        event.preventDefault();
                    }
                }}
                onInteractOutside={(event) => {
                    if (saving.processing) {
                        event.preventDefault();
                    }
                }}
            >
                <DialogHeader>
                    <DialogTitle>Catatan Siswa</DialogTitle>
                    <DialogDescription id="student-notes-description">
                        Informasi pembelajaran yang dapat dibaca bersama oleh
                        guru.
                    </DialogDescription>
                </DialogHeader>
                {currentStudent && (
                    <div className="flex items-center gap-3 rounded-lg border bg-muted/30 p-3">
                        <Avatar className="aspect-[2/3] h-30 w-20 overflow-hidden rounded-md after:rounded-md">
                            <AvatarImage
                                src={currentStudent.photo_url ?? undefined}
                                alt={currentStudent.full_name ?? 'Siswa'}
                                className="aspect-auto rounded-md object-top"
                            />
                            <AvatarFallback className="rounded-md">
                                {(currentStudent.full_name ?? 'S').slice(0, 1)}
                            </AvatarFallback>
                        </Avatar>
                        <p className="font-semibold">
                            {currentStudent.full_name ?? 'Siswa'}
                        </p>
                    </div>
                )}
                {notes?.can_create && (
                    <form onSubmit={save} className="grid gap-3">
                        <label
                            htmlFor="student-note-content"
                            className="text-sm font-medium"
                        >
                            Tambah catatan
                        </label>
                        <Textarea
                            id="student-note-content"
                            value={saving.data.content}
                            onChange={(event) =>
                                saving.setData('content', event.target.value)
                            }
                            rows={4}
                            maxLength={5000}
                            required
                            disabled={saving.processing}
                            placeholder="Tuliskan informasi pembelajaran yang membantu guru menyesuaikan pendekatan…"
                            aria-invalid={Boolean(saving.errors.content)}
                            aria-describedby={
                                saving.errors.content
                                    ? 'student-note-error'
                                    : undefined
                            }
                        />
                        <InputError
                            id="student-note-error"
                            message={saving.errors.content}
                        />
                        {saveError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {saveError}
                            </p>
                        )}
                        {saveMessage && (
                            <p
                                role="status"
                                className="text-sm text-muted-foreground"
                            >
                                {saveMessage}
                            </p>
                        )}
                        <Button
                            type="submit"
                            disabled={
                                saving.processing ||
                                history.processing ||
                                !saving.data.content.trim()
                            }
                        >
                            {saving.processing
                                ? 'Menyimpan…'
                                : 'Simpan catatan'}
                        </Button>
                    </form>
                )}
                <section
                    className="grid gap-3"
                    aria-label="Riwayat catatan siswa"
                >
                    <h3 className="font-medium">Riwayat catatan</h3>
                    {history.processing && (
                        <p
                            role="status"
                            className="text-sm text-muted-foreground"
                        >
                            Memuat catatan…
                        </p>
                    )}
                    {loadError && (
                        <p role="alert" className="text-sm text-destructive">
                            {loadError}
                        </p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={history.processing || saving.processing}
                        onClick={() => loadPage(1)}
                    >
                        Muat ulang catatan
                    </Button>
                    {notes?.notes.data.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada catatan untuk siswa ini.
                        </p>
                    )}
                    <ol className="grid max-h-64 gap-3 overflow-y-auto">
                        {notes?.notes.data.map((note) => (
                            <li
                                key={note.id}
                                className="grid gap-1 rounded-lg border p-3"
                            >
                                <div className="flex flex-wrap justify-between gap-x-3 text-xs text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {note.teacher_name}
                                    </span>
                                    <time
                                        dateTime={note.created_at ?? undefined}
                                    >
                                        {note.created_at
                                            ? formatWitaDateTime(
                                                  note.created_at,
                                              )
                                            : ''}
                                    </time>
                                </div>
                                <p className="text-sm [overflow-wrap:anywhere] whitespace-pre-wrap">
                                    {note.content}
                                </p>
                            </li>
                        ))}
                    </ol>
                    {notes && notes.notes.last_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    page <= 1 ||
                                    history.processing ||
                                    saving.processing
                                }
                                onClick={() => loadPage(page - 1)}
                            >
                                Sebelumnya
                            </Button>
                            <span>
                                Halaman {page} dari {notes.notes.last_page} ·{' '}
                                {notes.notes.total} catatan
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    page >= notes.notes.last_page ||
                                    history.processing ||
                                    saving.processing
                                }
                                onClick={() => loadPage(page + 1)}
                            >
                                Berikutnya
                            </Button>
                        </div>
                    )}
                </section>
            </ModalShell>
        </Dialog>
    );
}
