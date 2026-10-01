import { Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as locale } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
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

type Account = { id: number; email: string };
export type TeacherFormTeacher = {
    id: number;
    user_id: number | null;
    staff_type: string;
    nip: string | null;
    nuptk: string | null;
    full_name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    status: string;
};
type Form = {
    user_id: string;
    staff_type: string;
    nip: string;
    nuptk: string;
    full_name: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    address: string;
    status: string;
};
type Props = {
    mode: 'create' | 'edit';
    teacher?: TeacherFormTeacher;
    availableAccounts: { guru: Account[]; staff: Account[] };
};
const empty: Form = {
    user_id: '',
    staff_type: 'guru',
    nip: '',
    nuptk: '',
    full_name: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    address: '',
    status: 'active',
};
const valueOf = (teacher?: TeacherFormTeacher): Form =>
    teacher
        ? {
              user_id: teacher.user_id?.toString() ?? '',
              staff_type: teacher.staff_type,
              nip: teacher.nip ?? '',
              nuptk: teacher.nuptk ?? '',
              full_name: teacher.full_name,
              gender: teacher.gender ?? '',
              birth_place: teacher.birth_place ?? '',
              birth_date: teacher.birth_date ?? '',
              address: teacher.address ?? '',
              status: teacher.status,
          }
        : empty;

export default function TeacherForm({
    mode,
    teacher,
    availableAccounts,
}: Props) {
    const [form, setForm] = useState<Form>(() => valueOf(teacher));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [calendarOpen, setCalendarOpen] = useState(false);
    const update = (key: keyof Form, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = () => {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => setProcessing(false),
            onError: (next: Record<string, string>) => {
                setErrors(next);
                setProcessing(false);
            },
        };

        if (mode === 'edit' && teacher) {
            router.patch(`/teachers/${teacher.id}`, form, options);

            return;
        }

        router.post('/teachers', form, options);
    };
    const date = form.birth_date ? parseISO(form.birth_date) : undefined;
    const accounts =
        form.staff_type === 'staff'
            ? availableAccounts.staff
            : availableAccounts.guru;

    return (
        <div className="grid gap-6 rounded-xl border p-4 md:p-6">
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="teacher-type">Tipe</Label>
                    <Select
                        value={form.staff_type}
                        onValueChange={(value) => {
                            update('staff_type', value);
                            update('user_id', '');
                        }}
                    >
                        <SelectTrigger id="teacher-type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="guru">Guru</SelectItem>
                            <SelectItem value="staff">Staff</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.staff_type} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-account">Akun (email)</Label>
                    <SearchableCombobox
                        value={form.user_id}
                        options={accounts.map((account) => ({
                            value: String(account.id),
                            label: account.email,
                        }))}
                        onChange={(value) => update('user_id', value)}
                        placeholder="Tidak dihubungkan"
                        searchPlaceholder="Cari email akun..."
                        emptyMessage="Akun sesuai role tidak ditemukan."
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        className="w-fit px-0 text-sm font-normal text-muted-foreground"
                        onClick={() => update('user_id', '')}
                    >
                        Hapus hubungan akun
                    </Button>
                    <InputError message={errors.user_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-nip">NIP</Label>
                    <Input
                        id="teacher-nip"
                        value={form.nip}
                        onChange={(event) => update('nip', event.target.value)}
                        inputMode="numeric"
                        maxLength={18}
                    />
                    <InputError message={errors.nip} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-nuptk">NUPTK</Label>
                    <Input
                        id="teacher-nuptk"
                        value={form.nuptk}
                        onChange={(event) =>
                            update('nuptk', event.target.value)
                        }
                        inputMode="numeric"
                        maxLength={16}
                    />
                    <InputError message={errors.nuptk} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-name">Nama Lengkap</Label>
                    <Input
                        id="teacher-name"
                        value={form.full_name}
                        onChange={(event) =>
                            update('full_name', event.target.value)
                        }
                        autoFocus={mode === 'create'}
                    />
                    <InputError message={errors.full_name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-gender">Jenis Kelamin</Label>
                    <Select
                        value={form.gender || 'none'}
                        onValueChange={(value) =>
                            update('gender', value === 'none' ? '' : value)
                        }
                    >
                        <SelectTrigger id="teacher-gender">
                            <SelectValue placeholder="Pilih" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Tidak diisi</SelectItem>
                            <SelectItem value="L">Laki-laki</SelectItem>
                            <SelectItem value="P">Perempuan</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.gender} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-birth-place">Tempat Lahir</Label>
                    <Input
                        id="teacher-birth-place"
                        value={form.birth_place}
                        onChange={(event) =>
                            update('birth_place', event.target.value)
                        }
                    />
                    <InputError message={errors.birth_place} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-birth-date">Tanggal Lahir</Label>
                    <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                className="justify-start text-left font-normal"
                            >
                                <CalendarIcon className="mr-2 h-4 w-4" />
                                {date
                                    ? format(date, 'd MMMM yyyy', { locale })
                                    : 'Pilih tanggal lahir'}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-auto p-0">
                            <Calendar
                                mode="single"
                                selected={date}
                                defaultMonth={date}
                                disabled={{ after: new Date() }}
                                onSelect={(selected) => {
                                    update(
                                        'birth_date',
                                        selected
                                            ? format(selected, 'yyyy-MM-dd')
                                            : '',
                                    );
                                    setCalendarOpen(false);
                                }}
                            />
                        </PopoverContent>
                    </Popover>
                    <InputError message={errors.birth_date} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="teacher-status">Status</Label>
                    <Select
                        value={form.status}
                        onValueChange={(value) => update('status', value)}
                    >
                        <SelectTrigger id="teacher-status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Aktif</SelectItem>
                            <SelectItem value="inactive">
                                Tidak Aktif
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>
                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="teacher-address">Alamat</Label>
                    <Textarea
                        id="teacher-address"
                        value={form.address}
                        onChange={(event) =>
                            update('address', event.target.value)
                        }
                    />
                    <InputError message={errors.address} />
                </div>
            </div>
            <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" asChild>
                    <Link href="/teachers">Batal</Link>
                </Button>
                <Button type="button" onClick={submit} disabled={processing}>
                    {processing ? 'Menyimpan...' : 'Simpan'}
                </Button>
            </div>
        </div>
    );
}
