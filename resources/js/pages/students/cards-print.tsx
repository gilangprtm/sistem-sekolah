import { Head } from '@inertiajs/react';
import { Download, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import { StudentCardVisual } from '@/components/student-card-visual';
import { Button } from '@/components/ui/button';
import { Dialog, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import ModalShell from '@/components/ui/modal-shell';
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

function crc32(bytes: Uint8Array): number {
    let crc = 0xffffffff;

    for (const byte of bytes) {
        crc ^= byte;

        for (let bit = 0; bit < 8; bit += 1) {
            crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
        }
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
    const localParts: Uint8Array[] = [];
    const centralParts: Uint8Array[] = [];
    let offset = 0;

    for (const file of files) {
        const data = new Uint8Array(await file.blob.arrayBuffer());
        const name = new TextEncoder().encode(file.name);
        const local = new Uint8Array(30 + name.length);
        const localView = new DataView(local.buffer);
        writeUint32(localView, 0, 0x04034b50);
        writeUint16(localView, 4, 20);
        writeUint16(localView, 6, 0);
        writeUint16(localView, 8, 0);
        writeUint16(localView, 10, 0);
        writeUint16(localView, 12, 0);
        writeUint32(localView, 14, crc32(data));
        writeUint32(localView, 18, data.length);
        writeUint32(localView, 22, data.length);
        writeUint16(localView, 26, name.length);
        local.set(name, 30);
        localParts.push(local, data);

        const central = new Uint8Array(46 + name.length);
        const centralView = new DataView(central.buffer);
        writeUint32(centralView, 0, 0x02014b50);
        writeUint16(centralView, 4, 20);
        writeUint16(centralView, 6, 20);
        writeUint16(centralView, 8, 0);
        writeUint16(centralView, 10, 0);
        writeUint16(centralView, 12, 0);
        writeUint16(centralView, 14, 0);
        writeUint32(centralView, 16, crc32(data));
        writeUint32(centralView, 20, data.length);
        writeUint32(centralView, 24, data.length);
        writeUint16(centralView, 28, name.length);
        writeUint16(centralView, 30, 0);
        writeUint16(centralView, 32, 0);
        writeUint16(centralView, 34, 0);
        writeUint16(centralView, 36, 0);
        writeUint32(centralView, 38, 0);
        writeUint32(centralView, 42, offset);
        central.set(name, 46);
        centralParts.push(central);
        offset += local.length + data.length;
    }

    const centralSize = centralParts.reduce(
        (sum, part) => sum + part.length,
        0,
    );
    const end = new Uint8Array(22);
    const endView = new DataView(end.buffer);
    writeUint32(endView, 0, 0x06054b50);
    writeUint16(endView, 8, files.length);
    writeUint16(endView, 10, files.length);
    writeUint32(endView, 12, centralSize);
    writeUint32(endView, 16, offset);

    const archiveBytes = new Uint8Array(offset + centralSize + end.length);
    let position = 0;

    for (const part of [...localParts, ...centralParts, end]) {
        archiveBytes.set(part, position);
        position += part.length;
    }

    return new Blob([archiveBytes.buffer], { type: 'application/zip' });
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
    onProgress?: (completed: number, total: number) => void,
): Promise<void> {
    const files = [];

    for (const [index, student] of students.entries()) {
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
    const [downloadProgress, setDownloadProgress] = useState({
        completed: 0,
        total: students.length,
    });
    const backQuery = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value !== undefined),
    ).toString();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Unduh Semua Kartu Pelajar" />
            <SecondaryPageShell
                title="Unduh Semua Kartu Pelajar"
                description={`${students.length} kartu siap diunduh sebagai ZIP`}
                backHref={`/students/cards${backQuery ? `?${backQuery}` : ''}`}
                backLabel="Kembali ke daftar kartu pelajar"
                actions={
                    <Button
                        disabled={isDownloading || students.length === 0}
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
                }
            >
                <Dialog open={isDownloading}>
                    <ModalShell
                        className="text-center"
                        showCloseButton={false}
                        onEscapeKeyDown={(event) => event.preventDefault()}
                        onPointerDownOutside={(event) => event.preventDefault()}
                    >
                        <LoaderCircle
                            className="mx-auto size-10 animate-spin text-primary"
                            aria-hidden="true"
                        />
                        <DialogTitle id="bulk-download-title">
                            Menyiapkan kartu pelajar
                        </DialogTitle>
                        <DialogDescription
                            id="bulk-download-status"
                            className="mt-2 text-sm text-muted-foreground"
                            role="status"
                            aria-live="polite"
                        >
                            Membuat {downloadProgress.total} kartu ({' '}
                            {downloadProgress.completed} dari{' '}
                            {downloadProgress.total} selesai). Jangan tutup
                            halaman ini.
                        </DialogDescription>
                    </ModalShell>
                </Dialog>

                {students.length > 0 && (
                    <section
                        className="grid gap-3"
                        aria-labelledby="card-preview-heading"
                    >
                        <h2
                            id="card-preview-heading"
                            className="text-sm font-medium"
                        >
                            Contoh kartu pelajar
                        </h2>
                        {students.map(({ student, qrCode }, index) => (
                            <div
                                key={student.id}
                                id={`student-card-${student.id}`}
                                className={
                                    index === 0
                                        ? 'w-full max-w-xl'
                                        : 'pointer-events-none fixed top-0 -left-[10000px] w-[1011px]'
                                }
                                aria-hidden={index > 0 ? true : undefined}
                            >
                                <StudentCardVisual
                                    student={student}
                                    qrCode={qrCode}
                                />
                            </div>
                        ))}
                    </section>
                )}

                {students.length === 0 ? (
                    <div className="rounded-xl border p-8 text-center text-muted-foreground">
                        Belum ada data siswa untuk diunduh.
                    </div>
                ) : (
                    <div className="rounded-xl border p-4 text-sm text-muted-foreground">
                        Hanya satu contoh kartu ditampilkan. Unduh ZIP tetap
                        mencakup seluruh {students.length} siswa sesuai filter,
                        dengan data dan foto masing-masing. Semua kartu memakai
                        template resmi yang sama dengan halaman kartu
                        individual.
                    </div>
                )}
            </SecondaryPageShell>
        </AppLayout>
    );
}
