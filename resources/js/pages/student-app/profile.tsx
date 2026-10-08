import { Head, router } from '@inertiajs/react';
import { CalendarDays, Camera, Mail, UserRound } from 'lucide-react';
import type { ChangeEvent, ReactNode } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Student = {
    nis: string | null;
    tahun_angkatan: number | null;
    full_name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    status: string | null;
    photo_url: string | null;
} | null;

type Props = {
    user: {
        name: string;
        email: string;
    };
    student: Student;
};

const monthNames = [
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

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return value;
    }

    const [, year, month, day] = match;
    const monthIndex = Number(month) - 1;

    return monthNames[monthIndex]
        ? `${day} ${monthNames[monthIndex]} ${year}`
        : value;
}

function displayGender(value: string | null): string {
    return value === 'L' ? 'Laki-laki' : value === 'P' ? 'Perempuan' : '-';
}

function displayStatus(value: string | null): string {
    return value === 'active'
        ? 'Aktif'
        : value === 'inactive'
          ? 'Tidak aktif'
          : '-';
}

function InfoRow({
    label,
    value,
}: {
    label: string;
    value: string;
}): ReactNode {
    return (
        <div className="flex items-start justify-between gap-4 border-b py-3 last:border-b-0">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-right text-sm font-medium">{value}</dd>
        </div>
    );
}

export default function StudentProfile({ user, student }: Props) {
    const [photo, setPhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [photoError, setPhotoError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const handlePhotoChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        if (
            file !== null &&
            (!file.type.startsWith('image/') || file.size > 1024 * 1024)
        ) {
            setPhoto(null);
            setPhotoPreview((current) => {
                if (current !== null) {
                    URL.revokeObjectURL(current);
                }

                return null;
            });
            setPhotoError('Foto harus berupa gambar dengan ukuran maksimal 1 MB.');
            event.target.value = '';

            return;
        }

        setPhoto(file);
        setPhotoPreview((current) => {
            if (current !== null) {
                URL.revokeObjectURL(current);
            }

            return file === null ? null : URL.createObjectURL(file);
        });
        setPhotoError(null);
    };

    const submitPhoto = () => {
        if (photo === null || student === null) {
            return;
        }

        setProcessing(true);
        router.post(
            '/student/profile/photo',
            { photo },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setPhoto(null);
                    setPhotoPreview((current) => {
                        if (current !== null) {
                            URL.revokeObjectURL(current);
                        }

                        return null;
                    });
                    setProcessing(false);
                },
                onError: (errors) => {
                    setPhotoError(errors.photo ?? 'Foto tidak dapat diperbarui.');
                    setProcessing(false);
                },
            },
        );
    };

    useEffect(() => {
        return () => {
            if (photoPreview !== null) {
                URL.revokeObjectURL(photoPreview);
            }
        };
    }, [photoPreview]);

    const displayedPhoto = photoPreview ?? student?.photo_url;

    return (
        <>
            <Head title="Profil Siswa" />

            <section
                className="space-y-5"
                aria-labelledby="student-profile-heading"
            >
                <div>
                    <p className="text-sm text-muted-foreground">
                        Akun dan data siswa
                    </p>
                    <h2
                        id="student-profile-heading"
                        className="text-2xl font-semibold tracking-tight"
                    >
                        Profil Siswa
                    </h2>
                </div>

                <div className="flex flex-col items-center rounded-2xl border bg-card p-5 text-center">
                    <div className="relative size-36 overflow-hidden rounded-full bg-violet-100 text-violet-700 shadow-sm">
                        {displayedPhoto ? (
                            <img
                                src={displayedPhoto}
                                alt={`Foto ${student?.full_name ?? user.name}`}
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="grid size-full place-items-center">
                                <UserRound className="size-10" aria-hidden="true" />
                            </div>
                        )}
                    </div>
                    <div className="mt-4 min-w-0 max-w-full">
                        <p className="truncate font-semibold">
                            {student?.full_name ?? user.name}
                        </p>
                        <p className="mt-1 flex items-center justify-center gap-1 truncate text-xs text-muted-foreground">
                            <Mail className="size-3.5" aria-hidden="true" />
                            {user.email}
                        </p>
                    </div>
                </div>

                {student ? (
                    <>
                        <div className="rounded-2xl border bg-card p-4">
                            <h3 className="mb-3 text-sm font-semibold">
                                Foto profil
                            </h3>
                            <div className="flex flex-wrap items-center gap-3">
                                <label
                                    htmlFor="student-profile-photo"
                                    className="inline-flex h-8 cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted"
                                >
                                    <Camera className="size-4" aria-hidden="true" />
                                    Pilih foto
                                </label>
                                <Input
                                    id="student-profile-photo"
                                    type="file"
                                    accept="image/*"
                                    className="sr-only"
                                    onChange={handlePhotoChange}
                                    disabled={processing}
                                />
                                {photo && (
                                    <Button
                                        type="button"
                                        onClick={submitPhoto}
                                        disabled={processing}
                                    >
                                        {processing ? 'Menyimpan...' : 'Simpan foto'}
                                    </Button>
                                )}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Gunakan gambar maksimal 1 MB.
                            </p>
                            <InputError message={photoError ?? undefined} className="mt-2" />
                        </div>

                        <div className="rounded-2xl border bg-card px-4">
                            <h3 className="border-b py-4 text-sm font-semibold">
                                Data siswa
                            </h3>
                            <dl>
                                <InfoRow label="NIS" value={student.nis ?? '-'} />
                                <InfoRow
                                    label="Tahun angkatan"
                                    value={student.tahun_angkatan?.toString() ?? '-'}
                                />
                                <InfoRow
                                    label="Jenis kelamin"
                                    value={displayGender(student.gender)}
                                />
                                <InfoRow
                                    label="Tempat, tanggal lahir"
                                    value={
                                        [
                                            student.birth_place,
                                            formatDate(student.birth_date),
                                        ]
                                            .filter((value) => value !== '-')
                                            .join(', ') || '-'
                                    }
                                />
                                <InfoRow label="Alamat" value={student.address ?? '-'} />
                                <InfoRow
                                    label="Status"
                                    value={displayStatus(student.status)}
                                />
                            </dl>
                        </div>
                    </>
                ) : (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Akun ini belum terhubung dengan data siswa. Hubungi
                        administrator sekolah untuk menghubungkan akun.
                    </div>
                )}

                <div className="flex items-start gap-3 rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
                    <CalendarDays
                        className="mt-0.5 size-5 shrink-0 text-violet-700"
                        aria-hidden="true"
                    />
                    <p>
                        Data profil selain foto dikelola oleh administrator sekolah.
                        Hubungi administrator jika ada informasi yang perlu diperbarui.
                    </p>
                </div>
            </section>
        </>
    );
}
