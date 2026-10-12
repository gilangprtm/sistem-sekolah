import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, LoaderCircle } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { StudentCardVisual } from '@/components/student-card-visual';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { renderStudentCardPng } from '@/lib/student-card-export';
import type { BreadcrumbItem } from '@/types';

type StudentCard = {
    student: {
        id: number;
        nis: string | null;
        nisn: string | null;
        tahun_angkatan: number | null;
        full_name: string;
        gender: string | null;
        birth_place: string | null;
        birth_date: string | null;
        address: string | null;
        photo_url: string | null;
    };
    qrCode: string | null;
};

type Props = {
    students: StudentCard[];
    filters: {
        search?: string;
        gender?: string;
        status?: string;
        tahun_angkatan?: string;
    };
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Kesiswaan', href: '/students/cards' },
    { title: 'Kartu Pelajar', href: '/students/cards' },
    { title: 'Unduh Semua Kartu', href: '/students/cards/print' },
];

function fileNameForStudent(name: string, id: number): string {
    const slug = name
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

    return `kartu-pelajar-${slug || 'siswa'}-${id}.png`;
}

function archiveName(filters: Props['filters'], count: number): string {
    const suffix = filters.tahun_angkatan
        ? `angkatan-${filters.tahun_angkatan}`
        : 'semua';

    return `kartu-pelajar-${suffix}-${count}-siswa.zip`;
}

async function crc32(blob: Blob): Promise<number> {
    let crc = 0xffffffff;
    const reader = blob.stream().getReader();

    try {
        while (true) {
            const { done, value } = await reader.read();

            if (done) {
                break;
            }

            for (const byte of value) {
                crc ^= byte;

                for (let bit = 0; bit < 8; bit += 1) {
                    crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
                }
            }
        }
    } finally {
        reader.releaseLock();
    }

    return (crc ^ 0xffffffff) >>> 0;
}

function writeUint16(view: DataView, offset: number, value: number): void {
    view.setUint16(offset, value, true);
}

function writeUint32(view: DataView, offset: number, value: number): void {
    view.setUint32(offset, value, true);
}

async function createZip(files: { name: string; blob: Blob }[]): Promise<Blob> {
    if (files.length > 0xffff) {
        throw new Error('Jumlah kartu melebihi batas format ZIP.');
    }

    const parts: BlobPart[] = [];
    const centralParts: Uint8Array[] = [];
    let offset = 0;

    for (const file of files) {
        const size = file.blob.size;
        const name = new TextEncoder().encode(file.name);

        if (
            size > 0xffffffff ||
            name.length > 0xffff ||
            offset + 30 + name.length + size > 0xffffffff
        ) {
            throw new Error('Ukuran arsip melebihi batas format ZIP.');
        }

        const checksum = await crc32(file.blob);
        const local = new Uint8Array(30 + name.length);
        const localView = new DataView(local.buffer);
        writeUint32(localView, 0, 0x04034b50);
        writeUint16(localView, 4, 20);
        writeUint16(localView, 6, 0);
        writeUint16(localView, 8, 0);
        writeUint16(localView, 10, 0);
        writeUint16(localView, 12, 0);
        writeUint32(localView, 14, checksum);
        writeUint32(localView, 18, size);
        writeUint32(localView, 22, size);
        writeUint16(localView, 26, name.length);
        local.set(name, 30);
        parts.push(local, file.blob);

        const central = new Uint8Array(46 + name.length);
        const centralView = new DataView(central.buffer);
        writeUint32(centralView, 0, 0x02014b50);
        writeUint16(centralView, 4, 20);
        writeUint16(centralView, 6, 20);
        writeUint16(centralView, 8, 0);
        writeUint16(centralView, 10, 0);
        writeUint16(centralView, 12, 0);
        writeUint16(centralView, 14, 0);
        writeUint32(centralView, 16, checksum);
        writeUint32(centralView, 20, size);
        writeUint32(centralView, 24, size);
        writeUint16(centralView, 28, name.length);
        writeUint16(centralView, 30, 0);
        writeUint16(centralView, 32, 0);
        writeUint16(centralView, 34, 0);
        writeUint16(centralView, 36, 0);
        writeUint32(centralView, 38, 0);
        writeUint32(centralView, 42, offset);
        central.set(name, 46);
        centralParts.push(central);
        offset += local.length + size;
    }

    const centralSize = centralParts.reduce(
        (sum, part) => sum + part.length,
        0,
    );

    if (centralSize > 0xffffffff) {
        throw new Error('Ukuran arsip melebihi batas format ZIP.');
    }

    const end = new Uint8Array(22);
    const endView = new DataView(end.buffer);
    writeUint32(endView, 0, 0x06054b50);
    writeUint16(endView, 8, files.length);
    writeUint16(endView, 10, files.length);
    writeUint32(endView, 12, centralSize);
    writeUint32(endView, 16, offset);

    for (const part of centralParts) {
        parts.push(part);
    }

    parts.push(end);

    return new Blob(parts, { type: 'application/zip' });
}

function getCardElement(id: number): HTMLElement {
    const element = document
        .getElementById(`student-card-${id}`)
        ?.querySelector('article');

    if (!(element instanceof HTMLElement)) {
        throw new Error(`Kartu siswa ${id} tidak ditemukan.`);
    }

    return element;
}

async function downloadCards(
    students: StudentCard[],
    filters: Props['filters'],
    prepareCard: (student: StudentCard) => Promise<void>,
    onProgress?: (completed: number, total: number) => void,
): Promise<void> {
    const files: { name: string; blob: Blob }[] = [];

    for (const [index, student] of students.entries()) {
        await prepareCard(student);
        files.push({
            name: fileNameForStudent(
                student.student.full_name,
                student.student.id,
            ),
            blob: await renderStudentCardPng(
                getCardElement(student.student.id),
            ),
        });
        onProgress?.(index + 1, students.length);
    }

    const archive = await createZip(files);
    files.length = 0;
    const url = URL.createObjectURL(archive);
    const link = document.createElement('a');
    link.href = url;
    link.download = archiveName(filters, students.length);
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export default function StudentCardsPrint({ students, filters }: Props) {
    const [isDownloading, setIsDownloading] = useState(false);
    const [activeStudent, setActiveStudent] = useState<StudentCard | null>(
        students[0] ?? null,
    );
    const [readyStudentId, setReadyStudentId] = useState<number | null>(null);
    const [downloadProgress, setDownloadProgress] = useState({
        completed: 0,
        total: students.length,
    });
    const activeStudentIdRef = useRef<number | null>(
        students[0]?.student.id ?? null,
    );
    const readyStudentIdRef = useRef<number | null>(null);
    const pendingCardReadyRef = useRef<{
        studentId: number;
        resolve: () => void;
    } | null>(null);

    const handleCardReady = useCallback(
        (studentId: number, ready: boolean) => {
            if (activeStudentIdRef.current !== studentId) {
                return;
            }

            readyStudentIdRef.current = ready ? studentId : null;
            setReadyStudentId(ready ? studentId : null);

            const pending = pendingCardReadyRef.current;

            if (ready && pending?.studentId === studentId) {
                pendingCardReadyRef.current = null;
                pending.resolve();
            }
        },
        [],
    );
    const prepareCard = useCallback((student: StudentCard): Promise<void> => {
        const studentId = student.student.id;

        if (readyStudentIdRef.current === studentId) {
            return Promise.resolve();
        }

        activeStudentIdRef.current = studentId;
        readyStudentIdRef.current = null;
        setReadyStudentId(null);

        return new Promise<void>((resolve) => {
            pendingCardReadyRef.current = { studentId, resolve };
            setActiveStudent(student);
        });
    }, []);
    const cardReady =
        activeStudent !== null && readyStudentId === activeStudent.student.id;
    const activeStudentId = activeStudent?.student.id ?? null;
    const reportCardReady = useCallback(
        (ready: boolean) => {
            if (activeStudentId !== null) {
                handleCardReady(activeStudentId, ready);
            }
        },
        [activeStudentId, handleCardReady],
    );
    const backQuery = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value !== undefined),
    ).toString();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Unduh Semua Kartu Pelajar" />
            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        variant="small"
                        title="Unduh Semua Kartu Pelajar"
                        description={`${students.length} siswa cocok dengan filter aktif`}
                    />
                    <div className="flex gap-2">
                        {isDownloading ? (
                            <Button variant="outline" disabled>
                                <ArrowLeft />
                                Kembali
                            </Button>
                        ) : (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/students/cards${backQuery ? `?${backQuery}` : ''}`}
                                >
                                    <ArrowLeft />
                                    Kembali
                                </Link>
                            </Button>
                        )}
                        {students.length > 0 &&
                            (isDownloading || cardReady) && (
                                <Button
                                    disabled={isDownloading}
                                    onClick={async () => {
                                        setIsDownloading(true);
                                        setDownloadProgress({
                                            completed: 0,
                                            total: students.length,
                                        });

                                        try {
                                            await downloadCards(
                                                students,
                                                filters,
                                                prepareCard,
                                                (completed, total) =>
                                                    setDownloadProgress({
                                                        completed,
                                                        total,
                                                    }),
                                            );
                                        } catch (error) {
                                            toast.error(
                                                error instanceof Error
                                                    ? error.message
                                                    : 'Kartu tidak dapat diunduh. Coba lagi.',
                                            );
                                        } finally {
                                            setIsDownloading(false);
                                            setDownloadProgress({
                                                completed: 0,
                                                total: students.length,
                                            });
                                        }
                                    }}
                                >
                                    {isDownloading ? (
                                        <LoaderCircle className="animate-spin" />
                                    ) : (
                                        <Download />
                                    )}
                                    {isDownloading ? 'Menyiapkan…' : 'Unduh ZIP'}
                                </Button>
                            )}
                        {students.length > 0 &&
                            !cardReady &&
                            !isDownloading && (
                                <span
                                    className="self-center text-sm text-muted-foreground"
                                    role="status"
                                >
                                    Memuat pratinjau kartu…
                                </span>
                            )}
                    </div>
                </div>

                {isDownloading && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-[2px]"
                        role="presentation"
                    >
                        <div
                            className="w-full max-w-sm rounded-xl bg-background p-6 text-center shadow-xl ring-1 ring-foreground/10"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="bulk-download-title"
                            aria-describedby="bulk-download-status"
                        >
                            <LoaderCircle
                                className="mx-auto size-10 animate-spin text-primary"
                                aria-hidden="true"
                            />
                            <h2
                                id="bulk-download-title"
                                className="mt-4 text-base font-semibold"
                            >
                                Menyiapkan kartu pelajar
                            </h2>
                            <p
                                id="bulk-download-status"
                                className="mt-2 text-sm text-muted-foreground"
                                role="status"
                                aria-live="polite"
                            >
                                Membuat {downloadProgress.total} kartu ({' '}
                                {downloadProgress.completed} dari{' '}
                                {downloadProgress.total} selesai). Jangan tutup
                                halaman ini.
                            </p>
                        </div>
                    </div>
                )}

                {activeStudent !== null && (
                    <div className="mx-auto w-full max-w-[1011px]">
                        <div id={'student-card-' + activeStudent.student.id}>
                            <StudentCardVisual
                                key={activeStudent.student.id}
                                student={activeStudent.student}
                                qrCode={activeStudent.qrCode}
                                onReadyChange={reportCardReady}
                            />
                        </div>
                    </div>
                )}

                {students.length === 0 ? (
                    <div className="rounded-xl border p-8 text-center text-muted-foreground">
                        Belum ada data siswa untuk diunduh.
                    </div>
                ) : (
                    <div className="rounded-xl border p-4 text-sm text-muted-foreground">
                        Pratinjau dimuat satu per satu. ZIP akan memuat semua
                        siswa yang cocok dengan filter aktif.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
