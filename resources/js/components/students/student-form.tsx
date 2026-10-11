import { Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type StudentAccount = {
    id: number;
    email: string;
};

export type StudentFormValue = {
    user_id: string;
    nis: string;
    nisn: string;
    tahun_angkatan: string;
    photo: File | null;
    remove_photo: boolean;
    full_name: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    address: string;
    phone: string;
    mother_name: string;
    father_name: string;
    mother_occupation: string;
    father_occupation: string;
    parent_phone: string;
    status: string;
};

export type StudentFormStudent = {
    id: number;
    user_id: number | null;
    nis: string | null;
    nisn: string | null;
    tahun_angkatan: number | null;
    photo_url: string | null;
    full_name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    phone: string | null;
    mother_name: string | null;
    father_name: string | null;
    mother_occupation: string | null;
    father_occupation: string | null;
    parent_phone: string | null;
    status: string;
};

type Props = {
    mode: 'create' | 'edit';
    student?: StudentFormStudent;
    availableAccounts: StudentAccount[];
};

const emptyForm: StudentFormValue = {
    user_id: '',
    nis: '',
    nisn: '',
    tahun_angkatan: '',
    photo: null,
    remove_photo: false,
    full_name: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    address: '',
    phone: '',
    mother_name: '',
    father_name: '',
    mother_occupation: '',
    father_occupation: '',
    parent_phone: '',
    status: 'active',
};

function toFormValue(student?: StudentFormStudent): StudentFormValue {
    if (!student) {
        return emptyForm;
    }

    return {
        user_id: student.user_id?.toString() ?? '',
        nis: student.nis ?? '',
        nisn: student.nisn ?? '',
        tahun_angkatan: student.tahun_angkatan?.toString() ?? '',
        photo: null,
        remove_photo: false,
        full_name: student.full_name,
        gender: student.gender ?? '',
        birth_place: student.birth_place ?? '',
        birth_date: student.birth_date ?? '',
        address: student.address ?? '',
        phone: student.phone ?? '',
        mother_name: student.mother_name ?? '',
        father_name: student.father_name ?? '',
        mother_occupation: student.mother_occupation ?? '',
        father_occupation: student.father_occupation ?? '',
        parent_phone: student.parent_phone ?? '',
        status: student.status,
    };
}

export default function StudentForm({
    mode,
    student,
    availableAccounts,
}: Props) {
    const [form, setForm] = useState<StudentFormValue>(() =>
        toFormValue(student),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [birthDateOpen, setBirthDateOpen] = useState(false);

    const update = (
        key: keyof StudentFormValue,
        value: string | boolean | File | null,
    ) => setForm((current) => ({ ...current, [key]: value }));

    const handleYearChange = (value: string) => {
        update('tahun_angkatan', value.replace(/\D/g, ''));
    };

    const handlePhotoChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        if (
            file !== null &&
            (!file.type.startsWith('image/') || file.size > 1024 * 1024)
        ) {
            update('photo', null);
            setErrors((current) => ({
                ...current,
                photo: 'Foto harus berupa gambar dengan ukuran maksimal 1 MB.',
            }));
            event.target.value = '';

            return;
        }

        update('photo', file);
        update('remove_photo', false);
        setErrors((current) => {
            const next = { ...current };
            delete next.photo;

            return next;
        });
    };

    const submit = () => {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setProcessing(false),
            onError: (requestErrors: Record<string, string>) => {
                setErrors(requestErrors);
                setProcessing(false);
            },
        };

        if (mode === 'edit' && student) {
            router.post(
                `/students/${student.id}`,
                { ...form, _method: 'patch' },
                options,
            );

            return;
        }

        router.post('/students', form, options);
    };

    const selectedBirthDate = form.birth_date
        ? parseISO(form.birth_date)
        : undefined;

    return (
        <div className="grid min-w-0 gap-6 rounded-xl border p-4 md:p-6">
            <div>
                <Heading
                    variant="small"
                    title={mode === 'edit' ? 'Edit Siswa' : 'Tambah Siswa'}
                    description="Lengkapi biodata Siswa dan, bila perlu, hubungkan akun role Siswa yang tersedia."
                />
            </div>
            <div className="grid min-w-0 gap-6">
                <section className="grid min-w-0 gap-4 border-b pb-6">
                    <div>
                        <h2 className="text-base font-semibold">
                            Identitas Siswa
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Data utama yang digunakan pada daftar Master Siswa.
                        </p>
                    </div>
                    <div className="grid min-w-0 gap-4 md:grid-cols-2">
                        <div className="grid min-w-0 gap-2 md:col-span-2">
                            <Label htmlFor="student-account">Akun Siswa</Label>
                            <SearchableCombobox
                                value={form.user_id}
                                options={availableAccounts.map((account) => ({
                                    value: String(account.id),
                                    label: account.email,
                                }))}
                                onChange={(value) => update('user_id', value)}
                                placeholder="Tidak dihubungkan"
                                searchPlaceholder="Cari email akun..."
                                emptyMessage="Akun Siswa tidak ditemukan."
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                className="w-fit px-0 text-sm font-normal text-muted-foreground hover:bg-transparent hover:text-foreground"
                                onClick={() => update('user_id', '')}
                            >
                                Hapus hubungan akun
                            </Button>
                            <InputError message={errors.user_id} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-tahun-angkatan">
                                Tahun Angkatan
                            </Label>
                            <Input
                                id="student-tahun-angkatan"
                                type="text"
                                inputMode="numeric"
                                pattern="[0-9]*"
                                minLength={4}
                                maxLength={4}
                                min={1900}
                                max={new Date().getFullYear()}
                                value={form.tahun_angkatan}
                                onChange={(event) =>
                                    handleYearChange(event.target.value)
                                }
                            />
                            <InputError message={errors.tahun_angkatan} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-photo">Foto Siswa</Label>
                            {student?.photo_url && !form.remove_photo && (
                                <div className="flex items-center gap-3">
                                    <img
                                        src={student.photo_url}
                                        alt={`Foto ${student.full_name}`}
                                        className="size-16 rounded-md object-cover"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            update('remove_photo', true)
                                        }
                                    >
                                        Hapus foto saat disimpan
                                    </Button>
                                </div>
                            )}
                            <Input
                                id="student-photo"
                                type="file"
                                accept="image/*"
                                onChange={handlePhotoChange}
                            />
                            <p className="text-xs text-muted-foreground">
                                Hanya gambar, maksimal 1 MB.
                            </p>
                            <InputError message={errors.photo} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-full-name">
                                Nama Lengkap
                            </Label>
                            <Input
                                id="student-full-name"
                                value={form.full_name}
                                onChange={(event) =>
                                    update('full_name', event.target.value)
                                }
                                autoFocus={mode === 'create'}
                            />
                            <InputError message={errors.full_name} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-nis">NIS</Label>
                            <Input
                                id="student-nis"
                                value={form.nis}
                                onChange={(event) =>
                                    update('nis', event.target.value)
                                }
                            />
                            <InputError message={errors.nis} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-nisn">NISN</Label>
                            <Input
                                id="student-nisn"
                                value={form.nisn}
                                onChange={(event) =>
                                    update('nisn', event.target.value)
                                }
                            />
                            <InputError message={errors.nisn} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-gender">
                                Jenis Kelamin
                            </Label>
                            <Select
                                value={form.gender || 'none'}
                                onValueChange={(value) =>
                                    update(
                                        'gender',
                                        value === 'none' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="student-gender">
                                    <SelectValue placeholder="Pilih" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Tidak diisi
                                    </SelectItem>
                                    <SelectItem value="L">Laki-laki</SelectItem>
                                    <SelectItem value="P">Perempuan</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.gender} />
                        </div>
                    </div>
                </section>
                <section className="grid min-w-0 gap-4 border-b pb-6">
                    <div>
                        <h2 className="text-base font-semibold">
                            Tempat dan Tanggal Lahir
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Tanggal lahir tidak boleh melewati hari ini.
                        </p>
                    </div>
                    <div className="grid min-w-0 gap-4 md:grid-cols-2">
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-birth-place">
                                Tempat Lahir
                            </Label>
                            <Input
                                id="student-birth-place"
                                value={form.birth_place}
                                onChange={(event) =>
                                    update('birth_place', event.target.value)
                                }
                            />
                            <InputError message={errors.birth_place} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-birth-date">
                                Tanggal Lahir
                            </Label>
                            <Popover
                                open={birthDateOpen}
                                onOpenChange={setBirthDateOpen}
                            >
                                <PopoverTrigger asChild>
                                    <Button
                                        id="student-birth-date"
                                        type="button"
                                        variant="outline"
                                        className={cn(
                                            'w-full justify-start text-left font-normal',
                                            !selectedBirthDate &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        <CalendarIcon className="mr-2 h-4 w-4" />
                                        {selectedBirthDate
                                            ? format(
                                                  selectedBirthDate,
                                                  'd MMMM yyyy',
                                                  { locale: indonesianLocale },
                                              )
                                            : 'Pilih tanggal lahir'}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        captionLayout="dropdown"
                                        selected={selectedBirthDate}
                                        defaultMonth={selectedBirthDate}
                                        onSelect={(date) => {
                                            update(
                                                'birth_date',
                                                date
                                                    ? format(date, 'yyyy-MM-dd')
                                                    : '',
                                            );
                                            setBirthDateOpen(false);
                                        }}
                                        disabled={{ after: new Date() }}
                                    />
                                </PopoverContent>
                            </Popover>
                            <InputError message={errors.birth_date} />
                        </div>
                    </div>
                </section>
                <section className="grid min-w-0 gap-4 border-b pb-6">
                    <div>
                        <h2 className="text-base font-semibold">
                            Kontak Siswa
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Simpan nomor telepon siswa untuk kebutuhan
                            administrasi sekolah.
                        </p>
                    </div>
                    <div className="grid min-w-0 gap-4 md:grid-cols-2">
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-phone">
                                Nomor Telepon Siswa
                            </Label>
                            <Input
                                id="student-phone"
                                type="tel"
                                inputMode="tel"
                                value={form.phone}
                                onChange={(event) =>
                                    update('phone', event.target.value)
                                }
                            />
                            <InputError message={errors.phone} />
                        </div>
                    </div>
                </section>
                <section className="grid min-w-0 gap-4 border-b pb-6">
                    <div>
                        <h2 className="text-base font-semibold">
                            Data Orangtua
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Data orangtua hanya digunakan untuk administrasi
                            sekolah.
                        </p>
                    </div>
                    <div className="grid min-w-0 gap-4 md:grid-cols-2">
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-mother-name">
                                Nama Ibu
                            </Label>
                            <Input
                                id="student-mother-name"
                                value={form.mother_name}
                                onChange={(event) =>
                                    update('mother_name', event.target.value)
                                }
                            />
                            <InputError message={errors.mother_name} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-father-name">
                                Nama Bapak
                            </Label>
                            <Input
                                id="student-father-name"
                                value={form.father_name}
                                onChange={(event) =>
                                    update('father_name', event.target.value)
                                }
                            />
                            <InputError message={errors.father_name} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-mother-occupation">
                                Pekerjaan Ibu
                            </Label>
                            <Input
                                id="student-mother-occupation"
                                value={form.mother_occupation}
                                onChange={(event) =>
                                    update(
                                        'mother_occupation',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.mother_occupation} />
                        </div>
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-father-occupation">
                                Pekerjaan Bapak
                            </Label>
                            <Input
                                id="student-father-occupation"
                                value={form.father_occupation}
                                onChange={(event) =>
                                    update(
                                        'father_occupation',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={errors.father_occupation} />
                        </div>
                        <div className="grid min-w-0 gap-2 md:col-span-2">
                            <Label htmlFor="student-parent-phone">
                                Nomor Telepon Orang Tua
                            </Label>
                            <Input
                                id="student-parent-phone"
                                type="tel"
                                inputMode="tel"
                                value={form.parent_phone}
                                onChange={(event) =>
                                    update('parent_phone', event.target.value)
                                }
                            />
                            <InputError message={errors.parent_phone} />
                        </div>
                    </div>
                </section>
                <section className="grid min-w-0 gap-4">
                    <div>
                        <h2 className="text-base font-semibold">
                            Status dan Alamat
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Simpan status administrasi dan alamat yang tersedia.
                        </p>
                    </div>
                    <div className="grid min-w-0 gap-4 md:grid-cols-2">
                        <div className="grid min-w-0 gap-2">
                            <Label htmlFor="student-status">Status</Label>
                            <Select
                                value={form.status}
                                onValueChange={(value) =>
                                    update('status', value)
                                }
                            >
                                <SelectTrigger id="student-status">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">
                                        Aktif
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Tidak Aktif
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>
                        <div className="grid min-w-0 gap-2 md:col-span-2">
                            <Label htmlFor="student-address">Alamat</Label>
                            <Textarea
                                id="student-address"
                                value={form.address}
                                onChange={(event) =>
                                    update('address', event.target.value)
                                }
                            />
                            <InputError message={errors.address} />
                        </div>
                    </div>
                </section>
            </div>
            <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" asChild>
                    <Link href="/students">Batal</Link>
                </Button>
                <Button type="button" onClick={submit} disabled={processing}>
                    {processing ? 'Menyimpan...' : 'Simpan Siswa'}
                </Button>
            </div>
        </div>
    );
}
