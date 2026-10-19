import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Option = { id: number; full_name?: string; code?: string; name?: string };
type Relation = {
    id: number;
    code: string;
    suffix: string;
    teacher: Option;
    subject: Option;
};
type Props = {
    teacherSubjects: {
        data: Relation[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search?: string; per_page?: number };
    teachers: Option[];
    subjects: Option[];
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Guru Mata Pelajaran', href: '/kurikulum/teacher-subjects' },
];

export default function TeacherSubjectsIndex({
    teacherSubjects,
    filters,
    teachers,
    subjects,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selected, setSelected] = useState<number[]>([]);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        teacher_id: '',
        subject_id: '',
        suffix: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const params = (): Record<string, string> => (search ? { search } : {});
    const navigate = (extra: Record<string, string | number>) =>
        router.get(
            '/kurikulum/teacher-subjects',
            { ...params(), ...extra },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const submit = () =>
        router.post('/kurikulum/teacher-subjects', form, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: setErrors,
        });
    const remove = (relation: Relation) => {
        if (confirm(`Hapus relasi ${relation.code}?`)) {
            router.delete(`/kurikulum/teacher-subjects/${relation.id}`, {
                preserveScroll: true,
            });
        }
    };

    const columns: DataTableColumn<Relation>[] = [
        {
            id: 'code',
            header: 'Kode',
            cell: (relation) => relation.code,
            cellClassName: 'font-medium',
        },
        {
            id: 'teacher',
            header: 'Guru',
            cell: (relation) => relation.teacher.full_name,
        },
        {
            id: 'subject',
            header: 'Mata Pelajaran',
            cell: (relation) =>
                `${relation.subject.code} — ${relation.subject.name}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Guru Mata Pelajaran" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Guru Mata Pelajaran"
                        description="Kelola relasi Guru dan Mata Pelajaran"
                    />
                    <Button
                        onClick={() => {
                            setForm({
                                teacher_id: '',
                                subject_id: '',
                                suffix: '',
                            });
                            setErrors({});
                            setOpen(true);
                        }}
                    >
                        <Plus />
                        Tambah Relasi
                    </Button>
                </div>
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="flex items-end gap-3">
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="teacher-subject-search">
                                Search
                            </Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="teacher-subject-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        navigate({
                                            page: 1,
                                            per_page: teacherSubjects.per_page,
                                        })
                                    }
                                    placeholder="Kode, Guru, atau Mata Pelajaran..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <Button
                            onClick={() =>
                                navigate({
                                    page: 1,
                                    per_page: teacherSubjects.per_page,
                                })
                            }
                        >
                            Filter
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                navigate({
                                    page: 1,
                                    per_page: teacherSubjects.per_page,
                                });
                            }}
                        >
                            Reset
                        </Button>
                    </div>
                </div>
                <DataTableShell
                    data={teacherSubjects.data}
                    columns={columns}
                    getRowKey={(relation) => relation.id}
                    emptyState="Belum ada relasi Guru Mata Pelajaran."
                    selection={{
                        selectedKeys: selected,
                        onSelectionChange: setSelected,
                        selectAllLabel: 'Pilih semua relasi',
                        getRowLabel: (relation) => `Pilih ${relation.code}`,
                        selectedCountLabel: (count, total) =>
                            `${count} dari ${total} baris dipilih.`,
                    }}
                    rowActions={{
                        label: (relation) => `Aksi ${relation.code}`,
                        render: (relation) => (
                            <DropdownMenuItem
                                variant="destructive"
                                onClick={() => remove(relation)}
                            >
                                Hapus Relasi
                            </DropdownMenuItem>
                        ),
                    }}
                    pagination={{
                        resource: teacherSubjects,
                        noun: 'relasi',
                        onPageChange: (page) =>
                            navigate({
                                page,
                                per_page: teacherSubjects.per_page,
                            }),
                        onPerPageChange: (perPage) =>
                            navigate({ page: 1, per_page: perPage }),
                    }}
                />
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Tambah Guru Mata Pelajaran
                            </DialogTitle>
                            <DialogDescription>
                                Suffix digabung langsung dengan kode Mata
                                Pelajaran.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Guru</Label>
                                <SearchableCombobox
                                    value={form.teacher_id}
                                    options={teachers.map((teacher) => ({
                                        value: String(teacher.id),
                                        label: teacher.full_name ?? '',
                                    }))}
                                    onChange={(value) =>
                                        setForm({ ...form, teacher_id: value })
                                    }
                                    placeholder="Pilih Guru"
                                    searchPlaceholder="Cari Guru..."
                                    emptyMessage="Guru aktif tidak ditemukan."
                                />
                                <InputError message={errors.teacher_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Mata Pelajaran</Label>
                                <SearchableCombobox
                                    value={form.subject_id}
                                    options={subjects.map((subject) => ({
                                        value: String(subject.id),
                                        label: `${subject.code} — ${subject.name}`,
                                    }))}
                                    onChange={(value) =>
                                        setForm({ ...form, subject_id: value })
                                    }
                                    placeholder="Pilih Mata Pelajaran"
                                    searchPlaceholder="Cari Mata Pelajaran..."
                                    emptyMessage="Mata Pelajaran aktif tidak ditemukan."
                                />
                                <InputError message={errors.subject_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="teacher-subject-suffix">
                                    Suffix
                                </Label>
                                <Input
                                    id="teacher-subject-suffix"
                                    value={form.suffix}
                                    onChange={(event) =>
                                        setForm({
                                            ...form,
                                            suffix: event.target.value,
                                        })
                                    }
                                    maxLength={20}
                                    placeholder="Contoh: -01"
                                />
                                <InputError message={errors.suffix} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button onClick={submit}>Simpan</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
