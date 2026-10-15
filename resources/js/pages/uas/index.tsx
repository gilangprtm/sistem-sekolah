import { Head, router } from '@inertiajs/react';
import { ExternalLink, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DataTableEmptyState from '@/components/data-table/data-table-empty-state';
import DataTablePagination from '@/components/data-table/data-table-pagination';
import DataTableRowActions from '@/components/data-table/data-table-row-actions';
import DataTableShell from '@/components/data-table/data-table-shell';
import DataTableToolbar from '@/components/data-table/data-table-toolbar';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Subject = { id: number; code: string; name: string };
type UasLink = {
    id: number;
    category: 'UTS' | 'UAS';
    academic_year: string;
    academic_period: string;
    subject: Subject;
    link: string;
    opens_at: string | null;
};
type MonitoringAttempt = {
    id: number;
    student_name: string | null;
    interruption_count: number;
};
type Monitoring = {
    uas_link_id: number;
    subject: Subject;
    attempts: {
        data: MonitoringAttempt[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};
type Props = {
    links: {
        data: UasLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    monitoring: Monitoring | null;
    activeContext: {
        academic_year_id: number;
        academic_year: string;
        academic_period_id: number;
        academic_period: string;
    } | null;
    subjects: Subject[];
    years: { id: number; year: string }[];
    category: 'UTS' | 'UAS';
    filters: {
        academic_year_id?: number;
        category?: 'UTS' | 'UAS';
        search?: string;
        per_page?: number;
    };
};
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ujian', href: '/kurikulum/ujian' },
];

export default function UasIndex({
    links,
    monitoring,
    activeContext,
    subjects,
    years,
    category,
    filters,
}: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<UasLink | null>(null);
    const [subjectId, setSubjectId] = useState('');
    const [url, setUrl] = useState('');
    const [opensAtDate, setOpensAtDate] = useState('');
    const [opensAtTime, setOpensAtTime] = useState('');
    const [search, setSearch] = useState(filters.search ?? '');
    const [activeCategory, setActiveCategory] = useState<'UTS' | 'UAS'>(
        category,
    );
    const [yearId, setYearId] = useState(
        filters.academic_year_id?.toString() ?? '',
    );
    const [deleteTarget, setDeleteTarget] = useState<UasLink | null>(null);
    const [monitorTarget, setMonitorTarget] = useState<UasLink | null>(null);
    const [unlockTarget, setUnlockTarget] = useState<MonitoringAttempt | null>(
        null,
    );
    const [unlockReason, setUnlockReason] = useState('');
    const [unlockError, setUnlockError] = useState('');
    const [unlockProcessing, setUnlockProcessing] = useState(false);
    const [monitoringVisible, setMonitoringVisible] = useState(
        new URLSearchParams(window.location.search).has('monitor_uas_link_id'),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const filterParams = (): Record<string, string> => ({
        category: activeCategory,
        academic_year_id: yearId,
        ...(search ? { search } : {}),
    });
    const navigate = (params: Record<string, string | number>) =>
        router.get('/kurikulum/ujian', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    const options = subjects.map((subject) => ({
        value: String(subject.id),
        label: `${subject.code} — ${subject.name}`,
    }));
    const openCreate = () => {
        setEditing(null);
        setSubjectId('');
        setUrl('');
        setOpensAtDate('');
        setOpensAtTime('');
        setErrors({});
        setOpen(true);
    };
    const openEdit = (link: UasLink) => {
        setEditing(link);
        setSubjectId(String(link.subject.id));
        setUrl(link.link);
        setOpensAtDate(link.opens_at?.slice(0, 10) ?? '');
        setOpensAtTime(link.opens_at?.slice(11, 16) ?? '');
        setErrors({});
        setOpen(true);
    };
    const submit = () => {
        setProcessing(true);
        const data = {
            category: activeCategory,
            subject_id: subjectId,
            link: url,
            opens_at_date: opensAtDate,
            opens_at_time: opensAtTime,
        };
        const callbacks = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: setErrors,
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(`/kurikulum/ujian/${editing.id}`, data, callbacks);
        } else {
            router.post('/kurikulum/ujian', data, callbacks);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ujian" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Ujian"
                        description="Kelola data UTS dan UAS berdasarkan Tahun Ajaran."
                    />
                    <Button
                        onClick={openCreate}
                        disabled={!activeContext || subjects.length === 0}
                    >
                        <Plus />
                        Tambah Ujian
                    </Button>
                </div>
                <div className="rounded-xl border p-4">
                    {activeContext ? (
                        <p className="text-sm">
                            <span className="text-muted-foreground">
                                Tahun Ajaran:
                            </span>{' '}
                            {activeContext.academic_year}{' '}
                            <span className="mx-2 text-muted-foreground">
                                ·
                            </span>
                            <span className="text-muted-foreground">
                                Semester:
                            </span>{' '}
                            {activeContext.academic_period}
                        </p>
                    ) : (
                        <p className="text-sm text-destructive">
                            Tahun Ajaran dan semester aktif belum tersedia.
                        </p>
                    )}
                </div>
                <div className="grid gap-4 rounded-xl border p-4">
                    <div className="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        <div className="grid gap-2 md:col-span-6">
                            <Label htmlFor="uas-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="uas-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    onKeyDown={(event) =>
                                        event.key === 'Enter' &&
                                        navigate({
                                            ...filterParams(),
                                            page: 1,
                                            per_page: links.per_page,
                                        })
                                    }
                                    placeholder="Mata pelajaran..."
                                    className="pl-8"
                                />
                            </div>
                        </div>
                        <div className="grid gap-2 md:col-span-3">
                            <Label>Kategori</Label>
                            <SearchableCombobox
                                value={activeCategory}
                                options={(['UTS', 'UAS'] as const).map(
                                    (examCategory) => ({
                                        value: examCategory,
                                        label: examCategory,
                                    }),
                                )}
                                onChange={(value) => {
                                    if (value === 'UTS' || value === 'UAS') {
                                        setActiveCategory(value);
                                    }
                                }}
                                placeholder="Pilih kategori"
                                searchPlaceholder="Cari kategori..."
                                emptyMessage="Kategori tidak ditemukan."
                            />
                        </div>
                        <div className="grid gap-2 md:col-span-3">
                            <Label>Tahun Ajaran</Label>
                            <SearchableCombobox
                                value={yearId}
                                options={[
                                    { value: '', label: 'Semua' },
                                    ...years.map((year) => ({
                                        value: year.id.toString(),
                                        label: year.year,
                                    })),
                                ]}
                                onChange={setYearId}
                                placeholder="Semua"
                                searchPlaceholder="Cari Tahun Ajaran..."
                                emptyMessage="Tahun Ajaran tidak ditemukan."
                            />
                        </div>
                        <div className="flex gap-2 md:col-span-3">
                            <Button
                                onClick={() =>
                                    navigate({
                                        ...filterParams(),
                                        page: 1,
                                        per_page: links.per_page,
                                    })
                                }
                            >
                                Filter
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch('');
                                    setYearId(
                                        activeContext?.academic_year_id.toString() ??
                                            '',
                                    );
                                    setActiveCategory('UAS');
                                    navigate({
                                        category: 'UAS',
                                        page: 1,
                                        per_page: links.per_page,
                                    });
                                }}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </div>
                <DataTableShell>
                    <DataTableToolbar>
                        <div className="text-sm text-muted-foreground">
                            {selected.length} dari {links.total} baris dipilih.
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSelected([])}
                            disabled={!selected.length}
                        >
                            Hapus pilihan
                        </Button>
                    </DataTableToolbar>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12">
                                        <Checkbox
                                            checked={
                                                links.data.length > 0 &&
                                                selected.length ===
                                                    links.data.length
                                            }
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked
                                                        ? links.data.map(
                                                              (item) => item.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            aria-label="Pilih semua link UAS"
                                        />
                                    </TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Mata Pelajaran</TableHead>
                                    <TableHead>Tahun Ajaran</TableHead>
                                    <TableHead>Semester</TableHead>
                                    <TableHead>Google Form</TableHead>
                                    <TableHead>Waktu Buka (WITA)</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {links.data.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.includes(
                                                    item.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked
                                                            ? [
                                                                  ...current,
                                                                  item.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      item.id,
                                                              ),
                                                    )
                                                }
                                                aria-label={`Pilih ${item.category} ${item.subject.name}`}
                                            />
                                        </TableCell>
                                        <TableCell>{item.category}</TableCell>
                                        <TableCell>
                                            <span className="font-medium">
                                                {item.subject.name}
                                            </span>
                                            <span className="ml-2 text-muted-foreground">
                                                {item.subject.code}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {item.academic_year}
                                        </TableCell>
                                        <TableCell>
                                            {item.academic_period}
                                        </TableCell>
                                        <TableCell>
                                            <a
                                                href={item.link}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 text-primary underline"
                                            >
                                                Buka Form{' '}
                                                <ExternalLink className="size-3" />
                                            </a>
                                        </TableCell>
                                        <TableCell>
                                            {item.opens_at ??
                                                'Langsung tersedia'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DataTableRowActions
                                                label={`Aksi ${item.category} ${item.subject.name}`}
                                            >
                                                <DropdownMenuItem
                                                    onClick={() =>
                                                        openEdit(item)
                                                    }
                                                >
                                                    Edit
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() => {
                                                        setMonitorTarget(item);
                                                        setMonitoringVisible(
                                                            true,
                                                        );
                                                        navigate({
                                                            ...filterParams(),
                                                            page: links.current_page,
                                                            per_page:
                                                                links.per_page,
                                                            monitor_uas_link_id:
                                                                item.id,
                                                            monitor_page: 1,
                                                        });
                                                    }}
                                                >
                                                    Monitoring
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    className="text-destructive focus:text-destructive"
                                                    onClick={() =>
                                                        setDeleteTarget(item)
                                                    }
                                                >
                                                    <Trash2 />
                                                    Hapus
                                                </DropdownMenuItem>
                                            </DataTableRowActions>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {links.data.length === 0 && (
                                    <DataTableEmptyState colSpan={8}>
                                        Belum ada data {activeCategory}.
                                    </DataTableEmptyState>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                    <DataTablePagination
                        resource={links}
                        noun={`data ${activeCategory}`}
                        onPageChange={(page) =>
                            navigate({
                                ...filterParams(),
                                page,
                                per_page: links.per_page,
                            })
                        }
                        onPerPageChange={(perPage) =>
                            navigate({
                                ...filterParams(),
                                page: 1,
                                per_page: perPage,
                            })
                        }
                    />
                </DataTableShell>
                <Dialog
                    open={monitoringVisible}
                    onOpenChange={(isOpen) => {
                        if (!isOpen) {
                            setMonitoringVisible(false);
                            setMonitorTarget(null);
                            navigate({
                                ...filterParams(),
                                page: links.current_page,
                                per_page: links.per_page,
                            });
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Monitoring Siswa Terkunci</DialogTitle>
                            <DialogDescription>
                                Siswa dengan percobaan terkunci untuk{' '}
                                {monitoring?.subject.name ??
                                    monitorTarget?.subject.name}
                                .
                            </DialogDescription>
                        </DialogHeader>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama Siswa</TableHead>
                                    <TableHead className="text-right">
                                        Aksi
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {monitoring?.attempts.data.map((attempt) => (
                                    <TableRow key={attempt.id}>
                                        <TableCell>
                                            {attempt.student_name ?? 'Siswa'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    setUnlockTarget(attempt);
                                                    setUnlockReason('');
                                                    setUnlockError('');
                                                }}
                                            >
                                                Buka
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {(!monitoring ||
                                    monitoring.attempts.data.length === 0) && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={2}
                                            className="text-center text-muted-foreground"
                                        >
                                            Tidak ada siswa terkunci.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                        {monitoring && (
                            <DataTablePagination
                                resource={monitoring.attempts}
                                noun="siswa terkunci"
                                onPageChange={(monitorPage) =>
                                    navigate({
                                        ...filterParams(),
                                        page: links.current_page,
                                        per_page: links.per_page,
                                        monitor_uas_link_id:
                                            monitoring.uas_link_id,
                                        monitor_page: monitorPage,
                                        monitor_per_page:
                                            monitoring.attempts.per_page,
                                    })
                                }
                                onPerPageChange={(monitorPerPage) =>
                                    navigate({
                                        ...filterParams(),
                                        page: links.current_page,
                                        per_page: links.per_page,
                                        monitor_uas_link_id:
                                            monitoring.uas_link_id,
                                        monitor_page: 1,
                                        monitor_per_page: monitorPerPage,
                                    })
                                }
                            />
                        )}
                    </DialogContent>
                </Dialog>
                <Dialog
                    open={unlockTarget !== null}
                    onOpenChange={(isOpen) => {
                        if (!isOpen && !unlockProcessing) {
                            setUnlockTarget(null);
                            setUnlockReason('');
                            setUnlockError('');
                        }
                    }}
                >
                    <DialogContent className="max-h-[85vh] w-[calc(100%-2rem)] overflow-y-auto sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Buka Percobaan Ujian</DialogTitle>
                            <DialogDescription>
                                Masukkan alasan wajib untuk membuka percobaan{' '}
                                {unlockTarget?.student_name ?? 'siswa'}.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="unlock-reason">Alasan</Label>
                            <Input
                                id="unlock-reason"
                                value={unlockReason}
                                onChange={(event) => {
                                    setUnlockReason(event.target.value);
                                    setUnlockError('');
                                }}
                                aria-invalid={unlockError !== ''}
                                aria-describedby={
                                    unlockError
                                        ? 'unlock-reason-error'
                                        : undefined
                                }
                                autoFocus
                            />
                            {unlockError && (
                                <p
                                    id="unlock-reason-error"
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {unlockError}
                                </p>
                            )}
                        </div>
                        <DialogFooter className="flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={unlockProcessing}
                                onClick={() => {
                                    setUnlockTarget(null);
                                    setUnlockReason('');
                                    setUnlockError('');
                                }}
                            >
                                Batal
                            </Button>
                            <Button
                                type="button"
                                disabled={
                                    unlockProcessing ||
                                    unlockReason.trim().length < 5
                                }
                                onClick={() => {
                                    if (
                                        !unlockTarget ||
                                        unlockReason.trim().length < 5
                                    ) {
                                        setUnlockError(
                                            'Alasan wajib diisi minimal 5 karakter.',
                                        );

                                        return;
                                    }

                                    setUnlockProcessing(true);
                                    router.post(
                                        `/kurikulum/ujian/attempts/${unlockTarget.id}/unlock`,
                                        { reason: unlockReason.trim() },
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setUnlockTarget(null);
                                                setUnlockReason('');
                                                setMonitoringVisible(false);
                                                setMonitorTarget(null);
                                                navigate({
                                                    ...filterParams(),
                                                    page: links.current_page,
                                                    per_page: links.per_page,
                                                });
                                            },
                                            onError: () => {
                                                setUnlockError(
                                                    'Alasan tidak dapat diproses. Periksa kembali lalu coba lagi.',
                                                );
                                            },
                                            onFinish: () =>
                                                setUnlockProcessing(false),
                                        },
                                    );
                                }}
                            >
                                {unlockProcessing
                                    ? 'Membuka...'
                                    : 'Buka Percobaan'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Dialog
                    open={deleteTarget !== null}
                    onOpenChange={(isOpen) => {
                        if (!isOpen) {
                            setDeleteTarget(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Hapus data {deleteTarget?.category}?
                            </DialogTitle>
                            <DialogDescription>
                                Data {deleteTarget?.category} untuk{' '}
                                {deleteTarget?.subject.name} akan dihapus
                                permanen, termasuk relasi mapel, tahun ajaran,
                                semester, URL, dan waktu buka. Master mapel,
                                tahun ajaran, semester, serta Google Form
                                eksternal tetap dipertahankan.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setDeleteTarget(null)}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (!deleteTarget) {
                                        return;
                                    }

                                    router.delete(
                                        `/kurikulum/ujian/${deleteTarget.id}`,
                                        {
                                            data: {
                                                ...filterParams(),
                                                page: links.current_page.toString(),
                                                per_page:
                                                    links.per_page.toString(),
                                            },
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                setDeleteTarget(null),
                                        },
                                    );
                                }}
                            >
                                Hapus Permanen
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="sm:max-w-lg">
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? `Ubah Data ${activeCategory}`
                                    : 'Tambah Ujian'}
                            </DialogTitle>
                            <DialogDescription>
                                Simpan Google Form untuk mata pelajaran pada
                                konteks akademik aktif.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Tahun Ajaran dan Semester aktif</Label>
                                <div
                                    aria-label="Tahun Ajaran dan Semester aktif"
                                    className="pointer-events-none flex h-8 w-full items-center rounded-lg border border-input bg-muted/50 px-2.5 py-1 text-sm text-muted-foreground opacity-75 select-none"
                                >
                                    {activeContext
                                        ? `${activeContext.academic_year} · ${activeContext.academic_period}`
                                        : 'Belum tersedia'}
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="uas-subject">
                                    Mata Pelajaran
                                </Label>
                                <SearchableCombobox
                                    value={subjectId}
                                    options={options}
                                    onChange={setSubjectId}
                                    placeholder="Pilih mata pelajaran"
                                    searchPlaceholder="Cari mata pelajaran..."
                                    emptyMessage="Mata pelajaran tidak ditemukan."
                                />
                                <InputError message={errors.subject_id} />
                            </div>
                            <div className="grid grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] gap-3">
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="uas-opens-at-date">
                                        Tanggal buka (WITA)
                                    </Label>
                                    <Input
                                        className="min-w-0"
                                        id="uas-opens-at-date"
                                        type="date"
                                        value={opensAtDate}
                                        onChange={(event) =>
                                            setOpensAtDate(event.target.value)
                                        }
                                        aria-invalid={Boolean(
                                            errors.opens_at_date,
                                        )}
                                    />
                                    <InputError
                                        message={errors.opens_at_date}
                                    />
                                </div>
                                <div className="grid min-w-0 gap-2">
                                    <Label htmlFor="uas-opens-at-time">
                                        Jam buka
                                    </Label>
                                    <Input
                                        className="min-w-0"
                                        id="uas-opens-at-time"
                                        type="time"
                                        value={opensAtTime}
                                        onChange={(event) =>
                                            setOpensAtTime(event.target.value)
                                        }
                                        aria-invalid={Boolean(
                                            errors.opens_at_time,
                                        )}
                                    />
                                    <InputError
                                        message={errors.opens_at_time}
                                    />
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Kosongkan tanggal dan jam agar langsung
                                tersedia.
                            </p>
                            <div className="grid gap-2">
                                <Label htmlFor="uas-link">
                                    URL Google Form
                                </Label>
                                <Input
                                    id="uas-link"
                                    type="url"
                                    value={url}
                                    onChange={(event) =>
                                        setUrl(event.target.value)
                                    }
                                    placeholder="https://docs.google.com/forms/d/..."
                                    aria-invalid={Boolean(errors.link)}
                                />
                                <InputError message={errors.link} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                onClick={submit}
                                disabled={processing || !activeContext}
                            >
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
