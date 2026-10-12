import { Form, Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    Camera,
    CreditCard,
    KeyRound,
    Mail,
    UserRound,
} from 'lucide-react';
import type { ChangeEvent, ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { StudentCardVisual } from '@/components/student-card-visual';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Student = {
    id: number;
    nis: string | null;
    nisn: string | null;
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
    qrCode: string | null;
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

function formatStudentNumbers(nis: string | null, nisn: string | null): string {
    return `${nis?.trim() || '-'} / ${nisn?.trim() || '-'}`;
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

export default function StudentProfile({ user, student, qrCode }: Props) {
    const [photo, setPhoto] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [photoError, setPhotoError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [passwordDialogOpen, setPasswordDialogOpen] = useState(false);
    const [showStudentCard, setShowStudentCard] = useState(false);
    const resetPasswordForm = useRef<(() => void) | null>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);

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
            setPhotoError(
                'Foto harus berupa gambar dengan ukuran maksimal 1 MB.',
            );
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
                    setPhotoError(
                        errors.photo ?? 'Foto tidak dapat diperbarui.',
                    );
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
                    {showStudentCard && student ? (
                        <StudentCardVisual
                            student={student}
                            qrCode={qrCode}
                            barcodeValue={
                                student.nis ||
                                student.nisn ||
                                String(student.id)
                            }
                        />
                    ) : (
                        <div className="relative aspect-[2/3] w-32 overflow-hidden rounded-xl bg-violet-100 text-violet-700 shadow-sm sm:w-36">
                            {displayedPhoto ? (
                                <img
                                    src={displayedPhoto}
                                    alt={`Foto ${student?.full_name ?? user.name}`}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <div className="grid size-full place-items-center">
                                    <UserRound
                                        className="size-10"
                                        aria-hidden="true"
                                    />
                                </div>
                            )}
                        </div>
                    )}
                    <div className="mt-4 max-w-full min-w-0">
                        <p className="truncate font-semibold">
                            {student?.full_name ?? user.name}
                        </p>
                        <p className="mt-1 flex items-center justify-center gap-1 truncate text-xs text-muted-foreground">
                            <Mail className="size-3.5" aria-hidden="true" />
                            {user.email}
                        </p>
                        <div className="mt-3 flex flex-wrap justify-center gap-2">
                            {student && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    aria-pressed={showStudentCard}
                                    aria-label={
                                        showStudentCard
                                            ? 'Foto Profile'
                                            : 'Kartu Pelajar'
                                    }
                                    title={
                                        showStudentCard
                                            ? 'Foto Profile'
                                            : 'Kartu Pelajar'
                                    }
                                    onClick={() =>
                                        setShowStudentCard(
                                            (current) => !current,
                                        )
                                    }
                                >
                                    <CreditCard
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {showStudentCard
                                        ? 'Foto Profile'
                                        : 'Kartu Pelajar'}
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setPasswordDialogOpen(true)}
                            >
                                <KeyRound
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Ganti password
                            </Button>
                        </div>
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
                                    <Camera
                                        className="size-4"
                                        aria-hidden="true"
                                    />
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
                                        {processing
                                            ? 'Menyimpan...'
                                            : 'Simpan foto'}
                                    </Button>
                                )}
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Gunakan gambar maksimal 1 MB.
                            </p>
                            <InputError
                                message={photoError ?? undefined}
                                className="mt-2"
                            />
                        </div>

                        <div className="rounded-2xl border bg-card px-4">
                            <h3 className="border-b py-4 text-sm font-semibold">
                                Data siswa
                            </h3>
                            <dl>
                                <InfoRow
                                    label="NIS / NISN"
                                    value={formatStudentNumbers(
                                        student.nis,
                                        student.nisn,
                                    )}
                                />
                                <InfoRow
                                    label="Tahun angkatan"
                                    value={
                                        student.tahun_angkatan?.toString() ??
                                        '-'
                                    }
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
                                <InfoRow
                                    label="Alamat"
                                    value={student.address ?? '-'}
                                />
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

                <Dialog
                    open={passwordDialogOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            resetPasswordForm.current?.();
                        }

                        setPasswordDialogOpen(open);
                    }}
                >
                    <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Ganti password</DialogTitle>
                            <DialogDescription>
                                Gunakan password baru yang kuat dan jangan
                                bagikan kepada orang lain.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            action="/student/profile/password"
                            method="put"
                            options={{ preserveScroll: true }}
                            resetOnError={['password', 'password_confirmation']}
                            resetOnSuccess
                            onSuccess={() => {
                                setPasswordDialogOpen(false);
                                resetPasswordForm.current?.();
                            }}
                            onError={(errors) => {
                                if (errors.current_password) {
                                    currentPasswordInput.current?.focus();
                                } else if (errors.password) {
                                    passwordInput.current?.focus();
                                }
                            }}
                            className="grid gap-4"
                        >
                            {({
                                errors,
                                processing: passwordProcessing,
                                resetAndClearErrors,
                            }) => {
                                resetPasswordForm.current = resetAndClearErrors;

                                return (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="student-current-password">
                                                Password saat ini
                                            </Label>
                                            <PasswordInput
                                                id="student-current-password"
                                                ref={currentPasswordInput}
                                                name="current_password"
                                                autoComplete="current-password"
                                                placeholder="Masukkan password saat ini"
                                            />
                                            <InputError
                                                message={
                                                    errors.current_password
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="student-password">
                                                Password baru
                                            </Label>
                                            <PasswordInput
                                                id="student-password"
                                                ref={passwordInput}
                                                name="password"
                                                autoComplete="new-password"
                                                placeholder="Masukkan password baru"
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="student-password-confirmation">
                                                Konfirmasi password baru
                                            </Label>
                                            <PasswordInput
                                                id="student-password-confirmation"
                                                name="password_confirmation"
                                                autoComplete="new-password"
                                                placeholder="Ulangi password baru"
                                            />
                                            <InputError
                                                message={
                                                    errors.password_confirmation
                                                }
                                            />
                                        </div>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        resetAndClearErrors()
                                                    }
                                                >
                                                    Batal
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                disabled={passwordProcessing}
                                            >
                                                {passwordProcessing
                                                    ? 'Menyimpan...'
                                                    : 'Simpan password'}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                );
                            }}
                        </Form>
                    </DialogContent>
                </Dialog>

                <div className="flex items-start gap-3 rounded-2xl border bg-card p-4 text-sm text-muted-foreground">
                    <CalendarDays
                        className="mt-0.5 size-5 shrink-0 text-violet-700"
                        aria-hidden="true"
                    />
                    <p>
                        Data profil selain foto dikelola oleh administrator
                        sekolah. Hubungi administrator jika ada informasi yang
                        perlu diperbarui.
                    </p>
                </div>
            </section>
        </>
    );
}
