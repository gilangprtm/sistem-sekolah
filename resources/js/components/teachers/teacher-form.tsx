import { Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as locale } from 'date-fns/locale';
import { CalendarIcon, X } from 'lucide-react';
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
        <div className="@container/teacher-fields grid min-w-0 gap-6 rounded-xl border p-4 md:p-6">
            <div className="grid min-w-0 gap-4">
                <div className="grid min-w-0 items-start gap-4 @min-[640px]/teacher-fields:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem]">
                    <div className="grid min-w-0 content-start gap-2">
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
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-account">Akun (email)</Label>
                        <div className="flex min-w-0 items-center gap-2">
                            <SearchableCombobox
                                id="teacher-account"
                                className="min-w-0 flex-1"
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
                            {form.user_id && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    aria-label="Hapus hubungan akun"
                                    title="Hapus hubungan akun"
                                    onClick={() => update('user_id', '')}
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            )}
                        </div>
                        <InputError message={errors.user_id} />
                    </div>
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-type">Tipe</Label>
                        <Select
                            value={form.staff_type}
                            onValueChange={(value) => {
                                update('staff_type', value);
                                update('user_id', '');
                            }}
                        >
                            <SelectTrigger id="teacher-type" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="guru">Guru</SelectItem>
                                <SelectItem value="staff">Staff</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.staff_type} />
                    </div>
                </div>
                <div className="grid min-w-0 items-start gap-4 @min-[640px]/teacher-fields:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem]">
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-nip">NIP</Label>
                        <Input
                            id="teacher-nip"
                            value={form.nip}
                            onChange={(event) =>
                                update('nip', event.target.value)
                            }
                            inputMode="numeric"
                            maxLength={18}
                        />
                        <InputError message={errors.nip} />
                    </div>
                    <div className="grid min-w-0 content-start gap-2">
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
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-status">Status</Label>
                        <Select
                            value={form.status}
                            onValueChange={(value) => update('status', value)}
                        >
                            <SelectTrigger
                                id="teacher-status"
                                className="w-full"
                            >
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
                </div>
                <div className="grid min-w-0 items-start gap-4 @min-[640px]/teacher-fields:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_8rem]">
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-birth-place">
                            Tempat Lahir
                        </Label>
                        <Input
                            id="teacher-birth-place"
                            value={form.birth_place}
                            onChange={(event) =>
                                update('birth_place', event.target.value)
                            }
                        />
                        <InputError message={errors.birth_place} />
                    </div>
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-birth-date">
                            Tanggal Lahir
                        </Label>
                        <Popover
                            open={calendarOpen}
                            onOpenChange={setCalendarOpen}
                        >
                            <PopoverTrigger asChild>
                                <Button
                                    id="teacher-birth-date"
                                    type="button"
                                    variant="outline"
                                    className="w-full justify-start text-left font-normal"
                                >
                                    <CalendarIcon className="mr-2 h-4 w-4" />
                                    {date
                                        ? format(date, 'd MMMM yyyy', {
                                              locale,
                                          })
                                        : 'Pilih tanggal lahir'}
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-auto p-0">
                                <Calendar
                                    mode="single"
                                    captionLayout="dropdown"
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
                    <div className="grid min-w-0 content-start gap-2">
                        <Label htmlFor="teacher-gender">Jenis Kelamin</Label>
                        <Select
                            value={form.gender || 'none'}
                            onValueChange={(value) =>
                                update('gender', value === 'none' ? '' : value)
                            }
                        >
                            <SelectTrigger
                                id="teacher-gender"
                                className="w-full"
                            >
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
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="teacher-address">Alamat</Label>
                    <Textarea
                        id="teacher-address"
                        className="min-h-24"
                        value={form.address}
                        onChange={(event) =>
                            update('address', event.target.value)
                        }
                    />
                    <InputError message={errors.address} />
                </div>
            </div>
            <div className="flex flex-col-reverse gap-2 border-t pt-4 @min-[480px]/teacher-fields:flex-row @min-[480px]/teacher-fields:justify-end">
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
