import { Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
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
    full_name: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    address: string;
    status: string;
};

export type StudentFormStudent = {
    id: number;
    user_id: number | null;
    nis: string | null;
    full_name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
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
    full_name: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    address: '',
    status: 'active',
};

function toFormValue(student?: StudentFormStudent): StudentFormValue {
    if (!student) {
        return emptyForm;
    }

    return {
        user_id: student.user_id?.toString() ?? '',
        nis: student.nis ?? '',
        full_name: student.full_name,
        gender: student.gender ?? '',
        birth_place: student.birth_place ?? '',
        birth_date: student.birth_date ?? '',
        address: student.address ?? '',
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

    const update = (key: keyof StudentFormValue, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));

    const submit = () => {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => setProcessing(false),
            onError: (requestErrors: Record<string, string>) => {
                setErrors(requestErrors);
                setProcessing(false);
            },
        };

        if (mode === 'edit' && student) {
            router.patch(`/students/${student.id}`, form, options);

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
