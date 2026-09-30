import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Student = {
    id: number;
    user_id: number | null;
    nis: string | null;
    full_name: string;
    gender: string | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    status: string;
    user?: { name: string; email: string } | null;
};
type Account = {
    id: number;
    name: string;
    email: string;
};
type Props = {
    students: {
        data: Student[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    availableAccounts: Account[];
    filters: { search?: string; per_page?: number };
};
type Form = {
    user_id: string;
    nis: string;
    full_name: string;
    gender: string;
    birth_place: string;
    birth_date: string;
    address: string;
    status: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Siswa', href: '/students' }];
const emptyForm: Form = {
    user_id: '',
    nis: '',
    full_name: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    address: '',
    status: 'active',
};

export default function StudentsIndex({
    students,
    availableAccounts,
    filters,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Student | null>(null);
    const [form, setForm] = useState<Form>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const accountOptions =
        editing?.user &&
        !availableAccounts.some((account) => account.id === editing.user_id)
            ? [
                  ...availableAccounts,
                  {
                      id: editing.user_id as number,
                      name: editing.user.name,
                      email: editing.user.email,
                  },
              ]
            : availableAccounts;

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setErrors({});
        setOpen(true);
    };
    const openEdit = (student: Student) => {
        setEditing(student);
        setForm({
            user_id: student.user_id?.toString() ?? '',
            nis: student.nis ?? '',
            full_name: student.full_name,
            gender: student.gender ?? '',
            birth_place: student.birth_place ?? '',
            birth_date: student.birth_date ?? '',
            address: student.address ?? '',
            status: student.status,
        });
        setErrors({});
        setOpen(true);
    };
    const update = (key: keyof Form, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = () => {
        setProcessing(true);
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setProcessing(false);
            },
            onError: (requestErrors: Record<string, string>) => {
                setErrors(requestErrors);
                setProcessing(false);
            },
        };

        if (editing) {
            router.patch(`/students/${editing.id}`, form, options);
        } else {
            router.post('/students', form, options);
        }
    };
    const remove = (student: Student) => {
        if (confirm(`Hapus data Siswa "${student.full_name}"?`)) {
            router.delete(`/students/${student.id}`, { preserveScroll: true });
        }
    };
    const navigate = (page: number, perPage = students.per_page) =>
        router.get(
            '/students',
            { search, page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Siswa" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Master Siswa"
                        description="Kelola data profil Siswa dan akun yang terhubung"
                    />
                    <Button onClick={openCreate}>Tambah Siswa</Button>
                </div>
                <DataTableToolbar className="rounded-xl border">
                    <div className="flex w-full flex-col gap-2 sm:flex-row">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    navigate(1);
                                }
                            }}
                            placeholder="Cari nama, NIS, atau email..."
                            className="sm:max-w-sm"
                        />
                        <Button variant="secondary" onClick={() => navigate(1)}>
                            Cari
                        </Button>
                    </div>
                </DataTableToolbar>
                <DataTableShell>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>NIS</TableHead>
                                    <TableHead>Akun</TableHead>
                                    <TableHead>Gender</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {students.data.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="font-medium">
                                            {student.full_name}
                                        </TableCell>
                                        <TableCell>
                                            {student.nis || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {student.user?.email ||
                                                'Belum terhubung'}
                                        </TableCell>
                                        <TableCell>
                                            {student.gender || '-'}
                                        </TableCell>
                                        <TableCell>{student.status}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        openEdit(student)
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        remove(student)
                                                    }
                                                >
                                                    Hapus
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {students.data.length === 0 && (
                                    <DataTableEmptyState colSpan={6}>
                                        Belum ada data Siswa.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={students}
                        noun="siswa"
                        onPageChange={navigate}
                        onPerPageChange={(perPage) => navigate(1, perPage)}
                    />
                </DataTableShell>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing ? 'Edit Siswa' : 'Tambah Siswa'}
                            </DialogTitle>
                            <DialogDescription>
                                Isi data profil Siswa. Akun hanya dapat dipilih
                                dari role Siswa yang belum terhubung.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid max-h-[65vh] gap-4 overflow-y-auto pr-2">
                            <div className="grid gap-2">
                                <Label htmlFor="student-account">
                                    Akun Siswa
                                </Label>
                                <Select
                                    value={form.user_id || 'none'}
                                    onValueChange={(value) =>
                                        update(
                                            'user_id',
                                            value === 'none' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger id="student-account">
                                        <SelectValue placeholder="Tidak dihubungkan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Tidak dihubungkan
                                        </SelectItem>
                                        {accountOptions.map((account) => (
                                            <SelectItem
                                                key={account.id}
                                                value={account.id.toString()}
                                            >
                                                {account.name} ({account.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.user_id} />
                            </div>
                            <div className="grid gap-2">
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
                            <div className="grid gap-2">
                                <Label htmlFor="student-full-name">
                                    Nama Lengkap
                                </Label>
                                <Input
                                    id="student-full-name"
                                    value={form.full_name}
                                    onChange={(event) =>
                                        update('full_name', event.target.value)
                                    }
                                />
                                <InputError message={errors.full_name} />
                            </div>
                            <div className="grid gap-2">
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
                                        <SelectItem value="L">
                                            Laki-laki
                                        </SelectItem>
                                        <SelectItem value="P">
                                            Perempuan
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.gender} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="student-birth-place">
                                    Tempat Lahir
                                </Label>
                                <Input
                                    id="student-birth-place"
                                    value={form.birth_place}
                                    onChange={(event) =>
                                        update(
                                            'birth_place',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={errors.birth_place} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="student-birth-date">
                                    Tanggal Lahir
                                </Label>
                                <Input
                                    id="student-birth-date"
                                    type="date"
                                    value={form.birth_date}
                                    onChange={(event) =>
                                        update('birth_date', event.target.value)
                                    }
                                />
                                <InputError message={errors.birth_date} />
                            </div>
                            <div className="grid gap-2">
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
                            <div className="grid gap-2">
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
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button onClick={submit} disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
