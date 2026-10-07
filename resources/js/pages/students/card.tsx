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
        reader.onerror = () => reject(reader.error);
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

function drawWrappedText(
    context: CanvasRenderingContext2D,
    value: string,
    x: number,
    y: number,
    maxWidth: number,
    lineHeight: number,
    maxLines = 2,
): number {
    const words = value.split(/\s+/).filter(Boolean);
    let line = '';
    let lineCount = 0;

    for (const word of words) {
        const candidate = line === '' ? word : `${line} ${word}`;

        if (context.measureText(candidate).width > maxWidth && line !== '') {
            context.fillText(line, x, y + lineCount * lineHeight);
            line = word;
            lineCount += 1;

            if (lineCount >= maxLines - 1) {
                break;
            }
        } else {
            line = candidate;
        }
    }

    if (lineCount < maxLines && line !== '') {
        context.fillText(line, x, y + lineCount * lineHeight);
        lineCount += 1;
    }

    return lineCount;
}

async function downloadCardImage(
    card: HTMLElement,
    student: Student,
    placement: Placement,
    qrCode: string | null,
): Promise<void> {
    const bounds = card.getBoundingClientRect();
    const width = Math.ceil(bounds.width);
    const height = Math.ceil(bounds.height);

    if (width <= 0 || height <= 0) {
        throw new Error(
            'Ukuran kartu tidak valid. Muat ulang halaman lalu coba lagi.',
        );
    }

    const logo = await loadImage('/images/logo-sekolah.png');
    const photo =
        student.photo_url === null ? null : await loadImage(student.photo_url);
    const qr = qrCode === null ? null : await loadImage(qrCode);
    const scale = Math.max(window.devicePixelRatio || 1, 1);
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(width * scale);
    canvas.height = Math.ceil(height * scale);
    const context = canvas.getContext('2d');

    if (context === null) {
        throw new Error('Browser tidak mendukung pembuatan gambar kartu.');
    }

    context.scale(scale, scale);
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, width, height);
    context.strokeStyle = '#cbd5e1';
    context.lineWidth = 1;
    context.strokeRect(0.5, 0.5, width - 1, height - 1);
    context.drawImage(logo, 12, 12, 32, 32);
    context.fillStyle = '#0f172a';
    context.font = '700 7px Arial, sans-serif';
    context.fillText('KARTU PELAJAR', 52, 20);
    context.font = '600 8px Arial, sans-serif';
    context.fillText('SMP Negeri 17 Denpasar', 52, 32);
    context.strokeStyle = '#e2e8f0';
    context.beginPath();
    context.moveTo(12, 50);
    context.lineTo(width - 12, 50);
    context.stroke();

    const textX = 12;
    const textWidth = width - 12 - 16 - 64 - 8;
    let textY = 64;
    const drawField = (label: string, value: string, lines = 1): void => {
        context.fillStyle = '#64748b';
        context.font = '600 6px Arial, sans-serif';
        context.fillText(label.toUpperCase(), textX, textY);
        context.fillStyle = '#0f172a';
        context.font =
            label === 'Nama Lengkap'
                ? '700 8px Arial, sans-serif'
                : '400 8px Arial, sans-serif';
        const count = drawWrappedText(
            context,
            value || '-',
            textX,
            textY + 9,
            textWidth,
            9,
            lines,
        );
        textY += 9 + count * 9 + 3;
    };

    drawField('Nama Lengkap', student.full_name);
    drawField('NIS', student.nis ?? '-');
    drawField(
        'Tempat, Tanggal Lahir',
        [student.birth_place, student.birth_date].filter(Boolean).join(', ') ||
            '-',
    );
    drawField('Alamat', student.address ?? '-', 2);

    if (placement !== null) {
        drawField(
            'Kelas',
            `${placement.rombel || '-'} (${placement.academic_year})`,
        );
    }

    const imageX = width - 12 - 64;
    const imageY = qr === null ? height - 12 - 64 : height - 12 - 128;

    if (photo === null) {
        context.fillStyle = '#f1f5f9';
        context.fillRect(imageX, imageY, 64, 64);
        context.fillStyle = '#64748b';
        context.font = '600 12px Arial, sans-serif';
        context.textAlign = 'center';
        context.fillText(
            student.full_name
                .split(/\s+/)
                .map((part) => part[0])
                .join('')
                .slice(0, 2)
                .toUpperCase(),
            imageX + 32,
            imageY + 38,
        );
        context.textAlign = 'start';
    } else {
        context.drawImage(photo, imageX, imageY, 64, 64);
    }

    if (qr !== null) {
        context.drawImage(qr, imageX, height - 12 - 64, 64, 64);
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

export default function StudentCard({ student, placement, qrCode }: Props) {
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
    const birthDetails = [student.birth_place, student.birth_date]
        .filter(Boolean)
        .join(', ');

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
                                            placement,
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
                        className="relative flex aspect-[3/2] h-[5.4cm] w-[8.56cm] flex-col overflow-hidden rounded-lg border border-slate-300 bg-white p-3 text-slate-900 shadow-sm print:rounded-none print:shadow-none"
                        aria-label={`Kartu Pelajar ${student.full_name}`}
                    >
                        <header className="flex items-center gap-2 border-b border-slate-200 pb-2">
                            <img
                                src="/images/logo-sekolah.png"
                                alt="Logo SMP Negeri 17 Denpasar"
                                className="size-8 object-contain"
                            />
                            <div className="min-w-0">
                                <p className="text-[7px] font-bold tracking-wide uppercase">
                                    Kartu Pelajar
                                </p>
                                <p className="truncate text-[8px] font-semibold">
                                    SMP Negeri 17 Denpasar
                                </p>
                            </div>
                        </header>

                        <div className="flex min-h-0 flex-1 gap-2 pt-2">
                            <div className="min-w-0 flex-1 space-y-1 text-[8px] leading-tight">
                                <div>
                                    <p className="text-[6px] text-slate-500 uppercase">
                                        Nama Lengkap
                                    </p>
                                    <p className="font-bold">
                                        {student.full_name}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[6px] text-slate-500 uppercase">
                                        NIS
                                    </p>
                                    <p>{student.nis || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-[6px] text-slate-500 uppercase">
                                        Tempat, Tanggal Lahir
                                    </p>
                                    <p>{birthDetails || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-[6px] text-slate-500 uppercase">
                                        Alamat
                                    </p>
                                    <p className="line-clamp-2">
                                        {student.address || '-'}
                                    </p>
                                </div>
                                {placement !== null && (
                                    <div>
                                        <p className="text-[6px] text-slate-500 uppercase">
                                            Kelas
                                        </p>
                                        <p>
                                            {placement.rombel || '-'} (
                                            {placement.academic_year})
                                        </p>
                                    </div>
                                )}
                            </div>
                            <div className="flex w-16 shrink-0 flex-col items-center justify-end gap-2">
                                {student.photo_url !== null ? (
                                    <img
                                        src={student.photo_url}
                                        alt={`Foto ${student.full_name}`}
                                        className="size-16 rounded-sm object-cover"
                                    />
                                ) : (
                                    <div className="flex size-16 items-center justify-center rounded-sm bg-slate-100 text-[12px] font-semibold text-slate-500">
                                        {student.full_name
                                            .split(' ')
                                            .map((part) => part[0])
                                            .join('')
                                            .slice(0, 2)
                                            .toUpperCase()}
                                    </div>
                                )}
                                {qrCode !== null && (
                                    <img
                                        src={qrCode}
                                        alt="QR Code akun siswa"
                                        className="size-16 object-contain"
                                    />
                                )}
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </AppLayout>
    );
}
