import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type StudentCard = {
    student: {
        id: number;
        nis: string | null;
        tahun_angkatan: number | null;
        full_name: string;
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

const CARD_WIDTH = 1011;
const CARD_HEIGHT = 639;
const CARD_BACKGROUND = '/images/base_kartupelajar.png';
const QR_X = 820;
const QR_Y = 445;
const QR_SIZE = 130;
const PHOTO_X = 43;
const PHOTO_Y = 202;
const PHOTO_WIDTH = 249;
const PHOTO_HEIGHT = 337;
const INDONESIAN_MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

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

async function imageDataUrl(source: string): Promise<string> {
    if (source.startsWith('data:')) {
        return source;
    }

    const response = await fetch(source, { credentials: 'same-origin' });

    if (!response.ok) {
        throw new Error('Aset kartu tidak dapat dimuat.');
    }

    const blob = await response.blob();

    return await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = () =>
            reject(new Error('Aset kartu tidak dapat dimuat.'));
        reader.readAsDataURL(blob);
    });
}

async function loadImage(source: string): Promise<HTMLImageElement> {
    const image = new Image();
    image.src = await imageDataUrl(source);

    await new Promise<void>((resolve, reject) => {
        image.onload = () => resolve();
        image.onerror = () =>
            reject(new Error('Aset kartu tidak dapat dirender.'));
    });

    return image;
}

function formatBirthDate(value: string | null): string | null {
    if (value === null || value === '') {
        return null;
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (match === null) {
        return value;
    }

    const [, year, month, day] = match;
    const monthIndex = Number(month) - 1;

    return monthIndex < 0 || monthIndex >= INDONESIAN_MONTHS.length
        ? value
        : `${day} ${INDONESIAN_MONTHS[monthIndex]} ${year}`;
}

function initialsForStudent(name: string): string {
    return name
        .split(/\s+/)
        .map((part) => part[0])
        .filter(Boolean)
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function barcodePattern(value: string): boolean[] {
    const pattern = [true, false, true, false, true, true, false];

    for (const character of value || '-') {
        const code = character.charCodeAt(0);

        for (let bit = 6; bit >= 0; bit -= 1) {
            pattern.push(((code >> bit) & 1) === 1);
        }

        pattern.push(false);
    }

    pattern.push(true, false, true);

    return pattern;
}

function drawTextFit(
    context: CanvasRenderingContext2D,
    value: string,
    x: number,
    y: number,
    maxWidth: number,
    fontSize: number,
): void {
    let currentSize = fontSize;
    context.font = `700 ${currentSize}px Arial, sans-serif`;

    while (context.measureText(value).width > maxWidth && currentSize > 10) {
        currentSize -= 1;
        context.font = `700 ${currentSize}px Arial, sans-serif`;
    }

    context.fillText(value, x, y);
}

function drawRoundedImage(
    context: CanvasRenderingContext2D,
    image: HTMLImageElement,
    x: number,
    y: number,
    width: number,
    height: number,
    radius: number,
): void {
    context.save();
    context.beginPath();
    context.roundRect(x, y, width, height, radius);
    context.clip();
    context.drawImage(image, x, y, width, height);
    context.restore();
}

function drawBarcode(
    context: CanvasRenderingContext2D,
    value: string,
    x: number,
    y: number,
    width: number,
    height: number,
): void {
    const pattern = barcodePattern(value);
    const moduleWidth = width / pattern.length;

    context.fillStyle = '#024059';
    pattern.forEach((isBar, index) => {
        if (isBar) {
            context.fillRect(x + index * moduleWidth, y, moduleWidth, height);
        }
    });
}

function drawQrCode(
    context: CanvasRenderingContext2D,
    image: HTMLImageElement,
): void {
    context.imageSmoothingEnabled = false;
    context.drawImage(image, QR_X, QR_Y, QR_SIZE, QR_SIZE);
}

async function renderCard(card: StudentCard): Promise<Blob> {
    const background = await loadImage(CARD_BACKGROUND);
    const photo =
        card.student.photo_url === null
            ? null
            : await loadImage(card.student.photo_url);
    const qr = card.qrCode === null ? null : await loadImage(card.qrCode);
    const canvas = document.createElement('canvas');
    canvas.width = CARD_WIDTH;
    canvas.height = CARD_HEIGHT;
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('Browser tidak mendukung pembuatan gambar kartu.');
    }

    context.drawImage(background, 0, 0, CARD_WIDTH, CARD_HEIGHT);

    if (photo === null) {
        context.fillStyle = '#d7d8df';
        context.fillRect(PHOTO_X, PHOTO_Y, PHOTO_WIDTH, PHOTO_HEIGHT);
        context.fillStyle = '#53627a';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.font = '700 48px Arial, sans-serif';
        context.fillText(
            initialsForStudent(card.student.full_name),
            PHOTO_X + PHOTO_WIDTH / 2,
            PHOTO_Y + PHOTO_HEIGHT / 2,
        );
        context.textAlign = 'start';
        context.textBaseline = 'alphabetic';
    } else {
        drawRoundedImage(
            context,
            photo,
            PHOTO_X,
            PHOTO_Y,
            PHOTO_WIDTH,
            PHOTO_HEIGHT,
            26,
        );
    }

    const textColor = '#024059';
    const textX = 329;
    const valueX = 546;
    const birthDetails = [
        card.student.birth_place,
        formatBirthDate(card.student.birth_date),
    ]
        .filter(Boolean)
        .join(', ');
    const fields = [
        { label: 'Nama Lengkap', value: card.student.full_name, y: 303 },
        { label: 'NISN', value: card.student.nis || '-', y: 340 },
        { label: 'T.T.L', value: birthDetails || '-', y: 376 },
        { label: 'Alamat', value: card.student.address || '-', y: 412 },
    ];

    context.fillStyle = textColor;
    drawTextFit(context, 'KARTU PELAJAR SISWA', textX, 245, 650, 48);

    fields.forEach(({ label, value, y }) => {
        context.font = '700 25px Arial, sans-serif';
        context.fillText(label, textX, y);
        context.fillText(':', 527, y);
        drawTextFit(context, value, valueX, y, 430, 25);
    });

    if (qr === null) {
        drawBarcode(
            context,
            card.student.nis || String(card.student.id),
            QR_X,
            QR_Y,
            QR_SIZE,
            QR_SIZE,
        );
    } else {
        drawQrCode(context, qr);
    }

    return await new Promise<Blob>((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (blob === null) {
                reject(
                    new Error('Gambar kartu kosong dan tidak dapat diunduh.'),
                );

                return;
            }

            resolve(blob);
        }, 'image/png');
    });
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
            blob: await renderCard(student),
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
            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        variant="small"
                        title="Unduh Semua Kartu Pelajar"
                        description={`${students.length} kartu siap diunduh sebagai ZIP`}
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

                {students.length === 0 ? (
                    <div className="rounded-xl border p-8 text-center text-muted-foreground">
                        Belum ada data siswa untuk diunduh.
                    </div>
                ) : (
                    <div className="rounded-xl border p-4 text-sm text-muted-foreground">
                        Semua kartu akan mengikuti template resmi, data siswa,
                        foto, QR code, dan format tanggal yang sama dengan
                        halaman kartu individual.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
