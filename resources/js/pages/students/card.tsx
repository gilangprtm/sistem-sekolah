import { Head, usePage } from '@inertiajs/react';
import { Download, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import SecondaryPageShell from '@/components/admin/secondary-page-shell';
import { StudentCardVisual } from '@/components/student-card-visual';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    renderStudentCardPng,
    saveStudentCardBlob,
} from '@/lib/student-card-export';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
    full_name: string;
    gender: string | null;
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
    const barcodeValue = student.nis || student.nisn || String(student.id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Kartu Pelajar - ${student.full_name}`} />
            <div className="contents print:[&>div]:p-0 print:[&>div>header]:hidden">
                <SecondaryPageShell
                    title="Kartu Pelajar"
                    description={`Kartu pelajar untuk ${student.full_name}`}
                    backHref="/students/cards"
                    backLabel="Kembali ke daftar kartu pelajar"
                    actions={
                        canPrint && (
                            <Button
                                disabled={isDownloading}
                                onClick={async () => {
                                    if (cardRef.current === null) {
                                        return;
                                    }

                                    setIsDownloading(true);

                                    try {
                                        saveStudentCardBlob(
                                            await renderStudentCardPng(
                                                cardRef.current,
                                            ),
                                            fileNameForStudent(
                                                student.full_name,
                                            ),
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
                        )
                    }
                >
                    <div className="flex justify-center print:block">
                        <StudentCardVisual
                            ref={cardRef}
                            student={student}
                            qrCode={qrCode}
                            barcodeValue={barcodeValue}
                            className="max-w-[1011px] print:rounded-none print:shadow-none"
                        />
                    </div>
                </SecondaryPageShell>
            </div>
        </AppLayout>
    );
}
