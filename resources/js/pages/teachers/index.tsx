import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Teacher = {
    id: number;
    staff_type: string;
    full_name: string;
    gender: string | null;
    status: string;
    user?: { email: string } | null;
};
type Props = {
    teachers: {
        data: Teacher[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: { search?: string; per_page?: number };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Guru & Staff', href: '/teachers' },
];
export default function TeachersIndex({ teachers, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const navigate = (page: number, perPage = teachers.per_page) =>
        router.get(
            '/teachers',
            { search, page, per_page: perPage },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const remove = (teacher: Teacher) => {
        if (
            confirm(
                `Hapus data ${teacher.staff_type === 'guru' ? 'Guru' : 'Staff'} "${teacher.full_name}"?`,
            )
        ) {
            router.delete(`/teachers/${teacher.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Guru & Staff" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Master Guru & Staff"
                        description="Kelola profil Guru dan Staff serta akun yang terhubung"
                    />
                    <Button asChild>
                        <Link href="/teachers/create">Tambah Guru & Staff</Link>
                    </Button>
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
                            placeholder="Cari nama, tipe, atau email..."
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
                                    <TableHead>Tipe</TableHead>
                                    <TableHead>Akun</TableHead>
                                    <TableHead>Gender</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {teachers.data.map((teacher) => (
                                    <TableRow key={teacher.id}>
                                        <TableCell className="font-medium">
                                            {teacher.full_name}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.staff_type === 'guru'
                                                ? 'Guru'
                                                : 'Staff'}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.user?.email ||
                                                'Belum terhubung'}
                                        </TableCell>
                                        <TableCell>
                                            {teacher.gender || '-'}
                                        </TableCell>
                                        <TableCell>{teacher.status}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={`/teachers/${teacher.id}/edit`}
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        remove(teacher)
                                                    }
                                                >
                                                    Hapus
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {teachers.data.length === 0 && (
                                    <DataTableEmptyState colSpan={6}>
                                        Belum ada data Guru & Staff.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={teachers}
                        noun="profil"
                        onPageChange={navigate}
                        onPerPageChange={(perPage) => navigate(1, perPage)}
                    />
                </DataTableShell>
            </div>
        </AppLayout>
    );
}
