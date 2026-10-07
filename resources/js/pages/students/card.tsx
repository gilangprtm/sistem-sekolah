import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    nis: string | null;
    full_name: string;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    photo_url: string | null;
};

type Placement = {
    academic_year: string;
    rombel: string | null;
} | null;

type Props = {
    student: Student;
    placement: Placement;
    qrPayload: string | null;
    qrCode: string | null;
};

const CARD_ASSET_WIDTH = 1011;
const CARD_ASSET_HEIGHT = 639;
const CARD_BACKGROUND = '/images/base_kartupelajar.png';
const QR_X = 820;
const QR_Y = 445;
const QR_SIZE = 130;
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

function fileNameForStudent(name: string): string {
    const slug = name
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

    return `kartu-pelajar-${slug || 'siswa'}.png`;
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
    const dataUrl = await imageDataUrl(source);

    await new Promise<void>((resolve, reject) => {
        image.onload = () => resolve();
        image.onerror = () =>
            reject(new Error('Aset kartu tidak dapat dirender.'));
        image.src = dataUrl;
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

function birthDetailsForStudent(student: Student): string {
    return [student.birth_place, formatBirthDate(student.birth_date)]
        .filter(Boolean)
        .join(', ');
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
    context.moveTo(x + radius, y);
    context.lineTo(x + width - radius, y);
    context.quadraticCurveTo(x + width, y, x + width, y + radius);
    context.lineTo(x + width, y + height - radius);
    context.quadraticCurveTo(
        x + width,
        y + height,
        x + width - radius,
        y + height,
    );
    context.lineTo(x + radius, y + height);
    context.quadraticCurveTo(x, y + height, x, y + height - radius);
    context.lineTo(x, y + radius);
    context.quadraticCurveTo(x, y, x + radius, y);
    context.closePath();
    context.clip();
    context.drawImage(image, x, y, width, height);
    context.restore();
}

function drawTextFit(
    context: CanvasRenderingContext2D,
    value: string,
    x: number,
    y: number,
    maxWidth: number,
    fontSize: number,
    weight: number,
): void {
    let currentSize = fontSize;
    context.font = `${weight} ${currentSize}px Arial, sans-serif`;

    while (context.measureText(value).width > maxWidth && currentSize > 10) {
        currentSize -= 1;
        context.font = `${weight} ${currentSize}px Arial, sans-serif`;
    }

    context.fillText(value, x, y);
}

function drawBarcode(
    context: CanvasRenderingContext2D,
    value: string,
    x: number,
    y: number,
    width: number,
    height: number,
    color: string,
): void {
    const pattern = barcodePattern(value);
    const moduleWidth = width / pattern.length;

    context.fillStyle = color;

    pattern.forEach((isBar, index) => {
        if (isBar) {
            context.fillRect(x + index * moduleWidth, y, moduleWidth, height);
        }
    });
}

function drawQrCode(
    context: CanvasRenderingContext2D,
    image: HTMLImageElement,
    x: number,
    y: number,
    width: number,
    height: number,
): void {
    const size = Math.min(width, height);
    const offsetX = x + (width - size) / 2;
    const offsetY = y + (height - size) / 2;

    context.imageSmoothingEnabled = false;
    context.drawImage(image, offsetX, offsetY, size, size);
}

async function downloadCardImage(
    card: HTMLElement,
    student: Student,
    qrCode: string | null,
): Promise<void> {
    const bounds = card.getBoundingClientRect();
    const width = Math.ceil(bounds.width);
    const height = Math.ceil(width * (CARD_ASSET_HEIGHT / CARD_ASSET_WIDTH));

    if (width <= 0 || height <= 0) {
        throw new Error(
            'Ukuran kartu tidak valid. Muat ulang halaman lalu coba lagi.',
        );
    }

    const background = await loadImage(CARD_BACKGROUND);
    const photo =
        student.photo_url === null ? null : await loadImage(student.photo_url);
    const qr = qrCode === null ? null : await loadImage(qrCode);
    const scale = Math.max(window.devicePixelRatio || 1, 1);
    const coordinateScale = width / CARD_ASSET_WIDTH;
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(width * scale);
    canvas.height = Math.ceil(height * scale);
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('Browser tidak mendukung pembuatan gambar kartu.');
    }

    context.scale(scale, scale);
    context.drawImage(background, 0, 0, width, height);

    const photoX = 43 * coordinateScale;
    const photoY = 202 * coordinateScale;
    const photoWidth = 249 * coordinateScale;
    const photoHeight = 337 * coordinateScale;

    if (photo === null) {
        context.fillStyle = '#d7d8df';
        context.fillRect(photoX, photoY, photoWidth, photoHeight);
        context.fillStyle = '#53627a';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.font = `700 ${48 * coordinateScale}px Arial, sans-serif`;
        context.fillText(
            initialsForStudent(student.full_name),
            photoX + photoWidth / 2,
            photoY + photoHeight / 2,
        );
        context.textAlign = 'start';
        context.textBaseline = 'alphabetic';
    } else {
        drawRoundedImage(
            context,
            photo,
            photoX,
            photoY,
            photoWidth,
            photoHeight,
            26 * coordinateScale,
        );
    }

    const textX = 329 * coordinateScale;
    const valueX = 546 * coordinateScale;
    const textWidth = width - valueX - 36 * coordinateScale;
    const textColor = '#024059';
    const barcodeValue = student.nis || String(student.id);
    const birthDetails = birthDetailsForStudent(student) || '-';
    const codeX = QR_X * coordinateScale;
    const codeY = QR_Y * coordinateScale;
    const codeWidth = QR_SIZE * coordinateScale;
    const codeHeight = QR_SIZE * coordinateScale;
    const fields = [
        { label: 'Nama Lengkap', value: student.full_name, y: 303 },
        { label: 'NISN', value: student.nis || '-', y: 340 },
        { label: 'T.T.L', value: birthDetails, y: 376 },
        { label: 'Alamat', value: student.address || '-', y: 412 },
    ];

    context.fillStyle = textColor;
    context.font = `700 ${48 * coordinateScale}px Arial, sans-serif`;
    drawTextFit(
        context,
        'KARTU PELAJAR SISWA',
        textX,
        245 * coordinateScale,
        width - textX - 25 * coordinateScale,
        48 * coordinateScale,
        700,
    );

    fields.forEach(({ label, value, y }) => {
        context.fillStyle = textColor;
        context.font = `700 ${25 * coordinateScale}px Arial, sans-serif`;
        context.fillText(label, textX, y * coordinateScale);
        context.fillText(':', 527 * coordinateScale, y * coordinateScale);
        drawTextFit(
            context,
            value,
            valueX,
            y * coordinateScale,
            textWidth,
            25 * coordinateScale,
            700,
        );
    });

    if (qr === null) {
        drawBarcode(
            context,
            barcodeValue,
            codeX,
            codeY,
            codeWidth,
            codeHeight,
            textColor,
        );
    } else {
        drawQrCode(context, qr, codeX, codeY, codeWidth, codeHeight);
    }

    const pngBlob = await new Promise<Blob>((resolve, reject) => {
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
    const downloadUrl = URL.createObjectURL(pngBlob);
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.download = fileNameForStudent(student.full_name);
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
}

function Barcode({ value, qrCode }: { value: string; qrCode: string | null }) {
    if (qrCode !== null) {
        return (
            <img
                src={qrCode}
                alt="QR Code akun siswa"
                className="size-full object-contain"
            />
        );
    }

    return (
        <div className="flex h-full w-full" aria-label={`Barcode ${value}`}>
            {barcodePattern(value).map((isBar, index) => (
                <span
                    key={`${value}-${index}`}
                    className={isBar ? 'bg-[#024059]' : 'bg-transparent'}
                    style={{ flex: '1 1 0%' }}
                />
            ))}
        </div>
    );
}

export default function StudentCard({ student, qrCode }: Props) {
    const { auth } = usePage<{
        auth?: { permissions?: string[]; roles?: string[] };
    }>().props;
    const canPrint =
        auth?.roles?.includes('Super Admin') ||
        auth?.permissions?.includes('student.card.print');
    const cardRef = useRef<HTMLElement>(null);
    const [isDownloading, setIsDownloading] = useState(false);
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Siswa', href: '/students' },
        { title: 'Kartu Pelajar', href: `/students/cards/${student.id}` },
    ];
    const birthDetails = birthDetailsForStudent(student);
    const barcodeValue = student.nis || String(student.id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Kartu Pelajar - ${student.full_name}`} />
            <div className="flex flex-1 flex-col gap-4 rounded-xl p-4 print:p-0">
                <div className="flex items-center justify-between gap-4 print:hidden">
                    <Heading
                        variant="small"
                        title="Kartu Pelajar"
                        description={`Kartu pelajar untuk ${student.full_name}`}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/students/cards">
                                <ArrowLeft />
                                Kembali
                            </Link>
                        </Button>
                        {canPrint && (
                            <Button
                                disabled={isDownloading}
                                onClick={async () => {
                                    if (cardRef.current === null) {
                                        return;
                                    }

                                    setIsDownloading(true);

                                    try {
                                        await downloadCardImage(
                                            cardRef.current,
                                            student,
                                            qrCode,
                                        );
                                    } catch (error) {
                                        toast.error(
                                            error instanceof Error
                                                ? error.message
                                                : 'Kartu tidak dapat diunduh. Coba lagi.',
                                        );
                                    } finally {
                                        setIsDownloading(false);
                                    }
                                }}
                            >
                                {isDownloading ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Download />
                                )}
                                {isDownloading ? 'Menyiapkan…' : 'Unduh Kartu'}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex justify-center print:block">
                    <article
                        ref={cardRef}
                        className="relative aspect-[1011/639] w-full max-w-[1011px] overflow-hidden rounded-[2.5%] border border-slate-300 bg-[#024059] bg-cover bg-center bg-no-repeat shadow-sm print:rounded-none print:shadow-none"
                        style={{
                            backgroundImage: `url(${CARD_BACKGROUND})`,
                            containerType: 'inline-size',
                        }}
                        aria-label={`Kartu Pelajar ${student.full_name}`}
                    >
                        <h1 className="sr-only">Kartu Pelajar Siswa</h1>
                        <div
                            className="absolute overflow-hidden rounded-[10%]"
                            style={{
                                left: '4.25%',
                                top: '31.61%',
                                width: '24.63%',
                                height: '52.74%',
                            }}
                        >
                            {student.photo_url !== null ? (
                                <img
                                    src={student.photo_url}
                                    alt={`Foto ${student.full_name}`}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center bg-[#d7d8df] text-[4.7cqw] font-bold text-[#53627a]">
                                    {initialsForStudent(student.full_name)}
                                </div>
                            )}
                        </div>
                        <div className="absolute top-[31.92%] left-[32.54%] max-w-[64%] truncate text-[clamp(1rem,4.75cqw,3rem)] leading-none font-bold text-[#024059]">
                            KARTU PELAJAR SISWA
                        </div>
                        <div className="absolute top-[44.3%] right-[3.5%] left-[32.54%] text-[clamp(0.5rem,2.47cqw,1.55rem)] leading-[1.45] font-bold text-[#024059]">
                            <div className="flex">
                                <span className="w-[21.5%] shrink-0">
                                    Nama Lengkap
                                </span>
                                <span className="w-[1.9%] shrink-0">:</span>
                                <span className="min-w-0 truncate">
                                    {student.full_name}
                                </span>
                            </div>
                            <div className="flex">
                                <span className="w-[21.5%] shrink-0">NISN</span>
                                <span className="w-[1.9%] shrink-0">:</span>
                                <span className="min-w-0 truncate">
                                    {student.nis || '-'}
                                </span>
                            </div>
                            <div className="flex">
                                <span className="w-[21.5%] shrink-0">
                                    T.T.L
                                </span>
                                <span className="w-[1.9%] shrink-0">:</span>
                                <span className="min-w-0 truncate">
                                    {birthDetails || '-'}
                                </span>
                            </div>
                            <div className="flex">
                                <span className="w-[21.5%] shrink-0">
                                    Alamat
                                </span>
                                <span className="w-[1.9%] shrink-0">:</span>
                                <span className="min-w-0 truncate">
                                    {student.address || '-'}
                                </span>
                            </div>
                        </div>
                        <div className="absolute right-[6%] bottom-[10%] aspect-square w-[13%]">
                            <Barcode value={barcodeValue} qrCode={qrCode} />
                        </div>
                    </article>
                </div>
            </div>
        </AppLayout>
    );
}
