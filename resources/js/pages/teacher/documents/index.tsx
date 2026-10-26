import { Head, Link, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowLeft,
    CalendarIcon,
    Clock3,
    Download,
    EllipsisVertical,
    File as FileIcon,
    FileChartColumn,
    FileImage,
    FileSpreadsheet,
    FileText,
    Folder,
    FolderPlus,
    Grid2X2,
    List,
    LockKeyhole,
    Printer,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Calendar } from '@/components/ui/calendar';
import { useConfirmation } from '@/components/ui/confirmation-provider';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ModalShell from '@/components/ui/modal-shell';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import AppLayout from '@/layouts/app-layout';
import { formatWitaDate, witaDateKey } from '@/lib/date-time';
import { cn } from '@/lib/utils';
import { download as downloadAgenda } from '@/routes/teacher/agenda';
import { download as downloadAttendance } from '@/routes/teacher/attendance/report';

type FolderRow = {
    id: number;
    name: string;
    visibility: 'private' | 'public';
    owner: string;
    url: string;
    deleteUrl: string;
    fileCount: number;
    size: number;
    createdAt: string;
    canDelete: boolean;
};
type FileRow = {
    id: number;
    name: string;
    originalName: string;
    mimeType: string;
    size: number;
    visibility: 'private' | 'public';
    owner: string;
    canDelete: boolean;
    viewUrl: string;
    downloadUrl: string;
    deleteUrl: string;
    createdAt: string;
};
type Props = {
    module: { key: string; label: string };
    documentRoutes: { index: string; store: string; storeFolder: string };
    folder: null | {
        id: number;
        name: string;
        visibility: 'private' | 'public';
        owner: string;
        breadcrumbs: { id: number; name: string; url: string }[];
        canDelete: boolean;
        deleteUrl: string;
    };
    folders: {
        data: FolderRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    documents: {
        data: FileRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    search: string;
    sort: 'name' | 'newest' | 'oldest';
    visibility: 'all' | 'private' | 'public';
    canManage: boolean;
    printOptions: ('agenda' | 'student-attendance')[];
};

function DocumentPagination({
    label,
    resource,
}: {
    label: string;
    resource: {
        data: unknown[];
        links: Props['folders']['links'];
        total: number;
    };
}) {
    if (resource.total <= resource.data.length) {
        return null;
    }

    return (
        <nav
            aria-label={`Halaman ${label.toLowerCase()}`}
            className="flex flex-wrap items-center justify-center gap-2"
        >
            {resource.links.map((link, position) => (
                <Button
                    key={position}
                    asChild={link.url !== null}
                    disabled={link.url === null}
                    size="sm"
                    variant={link.active ? 'default' : 'outline'}
                >
                    {link.url ? (
                        <Link
                            href={link.url}
                            preserveScroll
                            aria-current={link.active ? 'page' : undefined}
                        >
                            {link.label
                                .replace('&laquo;', '‹')
                                .replace('&raquo;', '›')}
                        </Link>
                    ) : (
                        link.label
                            .replace('&laquo;', '‹')
                            .replace('&raquo;', '›')
                    )}
                </Button>
            ))}
        </nav>
    );
}

const formatSize = (size: number) =>
    size === 0
        ? '0 KB'
        : size < 1024 * 1024
          ? `${Math.max(1, Math.round(size / 1024))} KB`
          : size < 1024 * 1024 * 1024
            ? `${(size / (1024 * 1024)).toFixed(1)} MB`
            : `${(size / (1024 * 1024 * 1024)).toFixed(1)} GB`;
const fileIcon = (mime: string) =>
    mime === 'application/pdf'
        ? FileIcon
        : mime.includes('image')
          ? FileImage
          : mime.includes('sheet') || mime.includes('excel')
            ? FileSpreadsheet
            : mime.includes('presentation') || mime.includes('powerpoint')
              ? FileChartColumn
              : FileText;
const fileType = (file: FileRow): string =>
    file.originalName.split('.').pop()?.toUpperCase() ?? 'File';
const visibilityLabel = (visibility: 'private' | 'public') =>
    visibility === 'private' ? 'Pribadi' : 'Publik · pengguna modul';

export default function TeacherDocuments(props: Props) {
    return (
        <TeacherDocumentsContent
            key={`${props.module.key}-${props.folder?.id ?? 'root'}`}
            {...props}
        />
    );
}

function TeacherDocumentsContent({
    module,
    documentRoutes,
    folder,
    folders,
    documents,
    search,
    sort,
    visibility,
    canManage,
    printOptions,
}: Props) {
    const confirm = useConfirmation();
    const [layout, setLayout] = useState<'grid' | 'list'>('grid');
    const [query, setQuery] = useState(search);
    const [selectedSort, setSelectedSort] = useState(sort);
    const [selectedVisibility, setSelectedVisibility] = useState(visibility);
    const [folderOpen, setFolderOpen] = useState(false);
    const [uploadOpen, setUploadOpen] = useState(false);
    const [downloadOpen, setDownloadOpen] = useState(false);
    const [reportType, setReportType] = useState('');
    const availableReport = printOptions.find(
        (option) => option === reportType,
    );
    const [downloading, setDownloading] = useState(false);
    const [downloadError, setDownloadError] = useState('');
    const [reportFromOpen, setReportFromOpen] = useState(false);
    const [reportToOpen, setReportToOpen] = useState(false);
    const reportForm = useForm({
        from: witaDateKey(new Date()),
        to: witaDateKey(new Date()),
    });
    const fileInput = useRef<HTMLInputElement>(null);
    const reportFromDate = reportForm.data.from
        ? parseISO(reportForm.data.from)
        : undefined;
    const reportToDate = reportForm.data.to
        ? parseISO(reportForm.data.to)
        : undefined;
    const folderForm = useForm({
        name: '',
        visibility: 'private' as 'private' | 'public',
        parent_id: folder?.id ?? (null as number | null),
    });
    const uploadForm = useForm({
        name: '',
        folder_id: folder?.id ?? (null as number | null),
        visibility: 'private' as 'private' | 'public',
        file: null as File | null,
    });
    const downloadReport = async (event: React.FormEvent) => {
        event.preventDefault();

        if (
            downloading ||
            !availableReport ||
            !reportForm.data.from ||
            !reportForm.data.to
        ) {
            return;
        }

        const downloadRoute =
            availableReport === 'agenda' ? downloadAgenda : downloadAttendance;
        const filenamePrefix =
            availableReport === 'agenda' ? 'agenda' : 'kehadiran-siswa';
        setDownloading(true);
        setDownloadError('');
        reportForm.clearErrors();

        try {
            const response = await fetch(
                downloadRoute.url({ query: reportForm.data }),
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: AbortSignal.timeout(60000),
                },
            );

            if (!response.ok) {
                const error = await response.json().catch(() => null);
                const errors = error?.errors as
                    Record<string, string[]> | undefined;

                if (errors) {
                    for (const field of ['from', 'to'] as const) {
                        if (errors[field]?.[0]) {
                            reportForm.setError(field, errors[field][0]);
                        }
                    }
                }

                throw new Error(
                    response.status === 401 || response.status === 403
                        ? 'Anda tidak memiliki akses atau akun belum terhubung ke profil guru.'
                        : response.status >= 500
                          ? 'Laporan gagal disiapkan. Silakan coba lagi.'
                          : (error?.message ??
                            'Unduhan gagal. Silakan coba lagi.'),
                );
            }

            if (
                !response.headers
                    .get('Content-Type')
                    ?.includes(
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    )
            ) {
                throw new Error(
                    'Respons unduhan tidak valid. Silakan masuk ulang dan coba lagi.',
                );
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${filenamePrefix}-${reportForm.data.from}-${reportForm.data.to}.xlsx`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch (error) {
            setDownloadError(
                error instanceof Error
                    ? error.message
                    : 'Unduhan gagal. Silakan coba lagi.',
            );
        } finally {
            setDownloading(false);
        }
    };
    const searchNow = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            documentRoutes.index,
            {
                folder: folder?.id,
                search: query || undefined,
                sort: selectedSort,
                visibility: selectedVisibility,
            },
            { preserveState: true, replace: true },
        );
    };
    const resetFilters = () => {
        setQuery('');
        setSelectedSort('name');
        setSelectedVisibility('all');
        router.get(
            documentRoutes.index,
            { folder: folder?.id, sort: 'name', visibility: 'all' },
            { preserveState: true, replace: true },
        );
    };
    const removeFile = async (file: FileRow) => {
        if (
            await confirm({
                title: 'Hapus dokumen?',
                description: `Dokumen “${file.name}” akan dihapus permanen.`,
            })
        ) {
            router.delete(file.deleteUrl);
        }
    };
    const removeFolder = async () => {
        if (
            folder &&
            (await confirm({
                title: 'Hapus folder?',
                description:
                    'Super Admin dapat menghapus folder beserta seluruh isi dan kontribusi pengguna lain.',
            }))
        ) {
            router.delete(folder.deleteUrl);
        }
    };
    const removeListedFolder = async (item: FolderRow) => {
        if (
            await confirm({
                title: 'Hapus folder?',
                description: `Folder “${item.name}” akan dihapus permanen. Pengguna biasa hanya dapat menghapus folder kosong; Super Admin dapat menghapus seluruh isinya.`,
            })
        ) {
            router.delete(item.deleteUrl);
        }
    };
    const addFolder = (event: React.FormEvent) => {
        event.preventDefault();
        folderForm.post(documentRoutes.storeFolder, {
            onSuccess: () => {
                folderForm.reset();
                setFolderOpen(false);
            },
        });
    };
    const upload = (event: React.FormEvent) => {
        event.preventDefault();

        if (!uploadForm.data.file) {
            return;
        }

        uploadForm.post(documentRoutes.store, {
            forceFormData: true,
            onSuccess: () => {
                uploadForm.reset();
                setUploadOpen(false);

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    };

    return (
        <AppLayout>
            <Head title={`Dokumen ${module.label}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="flex min-w-0 items-start gap-3">
                        {folder && (
                            <Button
                                asChild
                                variant="outline"
                                size="icon"
                                aria-label="Kembali ke folder"
                            >
                                <Link
                                    href={
                                        folder.breadcrumbs.length > 1
                                            ? folder.breadcrumbs.at(-2)!.url
                                            : documentRoutes.index
                                    }
                                >
                                    <ArrowLeft />
                                </Link>
                            </Button>
                        )}
                        <Heading
                            variant="small"
                            title={folder?.name ?? `Dokumen ${module.label}`}
                            description={`Dokumen khusus modul ${module.label}. Publik hanya untuk pengguna yang memiliki akses modul ini.`}
                        />
                    </div>
                    <div className="flex w-full flex-col gap-2 md:w-auto md:flex-row md:flex-wrap md:justify-end">
                        <Dialog
                            open={downloadOpen}
                            onOpenChange={(open) => {
                                if (!downloading) {
                                    setDownloadOpen(open);
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="w-full md:w-auto"
                                >
                                    <Download />
                                    Unduh
                                </Button>
                            </DialogTrigger>
                            <ModalShell size="small">
                                <DialogHeader>
                                    <DialogTitle>Unduh</DialogTitle>
                                    <DialogDescription>
                                        Pilih laporan yang ingin diunduh.
                                    </DialogDescription>
                                </DialogHeader>
                                <form
                                    className="grid min-w-0 gap-4"
                                    onSubmit={downloadReport}
                                >
                                    <div className="grid gap-2">
                                        <p
                                            id="print-type-label"
                                            className="text-sm font-medium"
                                        >
                                            Mau unduh apa?
                                        </p>
                                        <div
                                            role="group"
                                            aria-labelledby="print-type-label"
                                        >
                                            <SearchableCombobox
                                                value={reportType}
                                                options={printOptions.map(
                                                    (value) => ({
                                                        value,
                                                        label:
                                                            value === 'agenda'
                                                                ? 'Agenda'
                                                                : 'Kehadiran Siswa',
                                                    }),
                                                )}
                                                onChange={(value) => {
                                                    setReportType(value);
                                                    setDownloadError('');
                                                    reportForm.clearErrors();
                                                }}
                                                placeholder="Pilih laporan"
                                                searchPlaceholder="Cari laporan..."
                                                emptyMessage="Tidak ada pilihan laporan yang tersedia."
                                            />
                                        </div>
                                    </div>
                                    {printOptions.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Belum ada pilihan laporan untuk akun
                                            Anda.
                                        </p>
                                    )}
                                    {availableReport && (
                                        <>
                                            <p className="text-sm text-muted-foreground">
                                                Unduh{' '}
                                                {availableReport === 'agenda'
                                                    ? 'Agenda Anda'
                                                    : 'rekap kehadiran siswa kelas wali Anda'}{' '}
                                                dalam format Excel (.xlsx)
                                                berdasarkan rentang tanggal,
                                                maksimal 366 hari.
                                            </p>
                                            <fieldset className="grid gap-3 text-sm">
                                                <legend className="font-medium">
                                                    Format
                                                </legend>
                                                <RadioGroup
                                                    name="report-format"
                                                    aria-label="Format unduhan"
                                                    value="excel"
                                                    disabled={downloading}
                                                >
                                                    <div className="flex min-h-11 items-center gap-2">
                                                        <RadioGroupItem
                                                            id="report-format-excel"
                                                            value="excel"
                                                        />
                                                        <Label htmlFor="report-format-excel">
                                                            Excel
                                                        </Label>
                                                    </div>
                                                </RadioGroup>
                                            </fieldset>
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="grid min-w-0 gap-2">
                                                    <Label htmlFor="report-from">
                                                        Dari
                                                    </Label>
                                                    <Popover
                                                        open={reportFromOpen}
                                                        onOpenChange={
                                                            setReportFromOpen
                                                        }
                                                    >
                                                        <PopoverTrigger asChild>
                                                            <Button
                                                                id="report-from"
                                                                type="button"
                                                                variant="outline"
                                                                className={cn(
                                                                    'w-full justify-start text-left font-normal',
                                                                    !reportFromDate &&
                                                                        'text-muted-foreground',
                                                                )}
                                                                aria-label="Pilih tanggal awal"
                                                            >
                                                                <CalendarIcon className="mr-2 size-4" />
                                                                {reportFromDate
                                                                    ? format(
                                                                          reportFromDate,
                                                                          'd MMMM yyyy',
                                                                          {
                                                                              locale: indonesianLocale,
                                                                          },
                                                                      )
                                                                    : 'Pilih tanggal'}
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            className="w-auto p-0"
                                                            align="start"
                                                        >
                                                            <Calendar
                                                                mode="single"
                                                                captionLayout="dropdown"
                                                                selected={
                                                                    reportFromDate
                                                                }
                                                                defaultMonth={
                                                                    reportFromDate
                                                                }
                                                                disabled={
                                                                    reportToDate
                                                                        ? {
                                                                              after: reportToDate,
                                                                          }
                                                                        : undefined
                                                                }
                                                                onSelect={(
                                                                    date,
                                                                ) => {
                                                                    reportForm.setData(
                                                                        'from',
                                                                        date
                                                                            ? format(
                                                                                  date,
                                                                                  'yyyy-MM-dd',
                                                                              )
                                                                            : '',
                                                                    );
                                                                    setReportFromOpen(
                                                                        false,
                                                                    );
                                                                }}
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                                <div className="grid min-w-0 gap-2">
                                                    <Label htmlFor="report-to">
                                                        Sampai
                                                    </Label>
                                                    <Popover
                                                        open={reportToOpen}
                                                        onOpenChange={
                                                            setReportToOpen
                                                        }
                                                    >
                                                        <PopoverTrigger asChild>
                                                            <Button
                                                                id="report-to"
                                                                type="button"
                                                                variant="outline"
                                                                className={cn(
                                                                    'w-full justify-start text-left font-normal',
                                                                    !reportToDate &&
                                                                        'text-muted-foreground',
                                                                )}
                                                                aria-label="Pilih tanggal akhir"
                                                            >
                                                                <CalendarIcon className="mr-2 size-4" />
                                                                {reportToDate
                                                                    ? format(
                                                                          reportToDate,
                                                                          'd MMMM yyyy',
                                                                          {
                                                                              locale: indonesianLocale,
                                                                          },
                                                                      )
                                                                    : 'Pilih tanggal'}
                                                            </Button>
                                                        </PopoverTrigger>
                                                        <PopoverContent
                                                            className="w-auto p-0"
                                                            align="start"
                                                        >
                                                            <Calendar
                                                                mode="single"
                                                                captionLayout="dropdown"
                                                                selected={
                                                                    reportToDate
                                                                }
                                                                defaultMonth={
                                                                    reportToDate
                                                                }
                                                                disabled={
                                                                    reportFromDate
                                                                        ? {
                                                                              before: reportFromDate,
                                                                          }
                                                                        : undefined
                                                                }
                                                                onSelect={(
                                                                    date,
                                                                ) => {
                                                                    reportForm.setData(
                                                                        'to',
                                                                        date
                                                                            ? format(
                                                                                  date,
                                                                                  'yyyy-MM-dd',
                                                                              )
                                                                            : '',
                                                                    );
                                                                    setReportToOpen(
                                                                        false,
                                                                    );
                                                                }}
                                                            />
                                                        </PopoverContent>
                                                    </Popover>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                    {downloadError && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {downloadError}
                                        </p>
                                    )}
                                    {Object.entries(reportForm.errors).map(
                                        ([field, message]) => (
                                            <p
                                                key={field}
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {message}
                                            </p>
                                        ),
                                    )}
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={downloading}
                                            onClick={() =>
                                                setDownloadOpen(false)
                                            }
                                        >
                                            Batal
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={
                                                !availableReport ||
                                                !reportForm.data.from ||
                                                !reportForm.data.to ||
                                                downloading
                                            }
                                        >
                                            <Download />
                                            {downloading
                                                ? 'Menyiapkan...'
                                                : 'Unduh'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </ModalShell>
                        </Dialog>
                    </div>
                </div>
                {folder && (
                    <p className="text-sm text-muted-foreground">
                        {visibilityLabel(folder.visibility)} · {folder.owner}
                    </p>
                )}
                <form
                    onSubmit={searchNow}
                    className="grid gap-4 rounded-xl border p-4"
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-0 flex-[2_1_18rem] gap-2">
                            <Label htmlFor="document-search">Search</Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="document-search"
                                    className="pl-8"
                                    placeholder="Cari dokumen atau folder"
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div
                            role="group"
                            aria-labelledby="document-access-label"
                            className="grid min-w-0 flex-[1_1_12rem] gap-2"
                        >
                            <Label id="document-access-label">Akses</Label>
                            <SearchableCombobox
                                value={selectedVisibility}
                                options={[
                                    { value: 'all', label: 'Semua akses' },
                                    { value: 'private', label: 'Pribadi' },
                                    { value: 'public', label: 'Publik' },
                                ]}
                                onChange={(value) =>
                                    setSelectedVisibility(
                                        value as Props['visibility'],
                                    )
                                }
                                searchPlaceholder="Cari akses..."
                            />
                        </div>
                        <div
                            role="group"
                            aria-labelledby="document-sort-label"
                            className="grid min-w-0 flex-[1_1_12rem] gap-2"
                        >
                            <Label id="document-sort-label">Urutan</Label>
                            <SearchableCombobox
                                value={selectedSort}
                                options={[
                                    { value: 'name', label: 'Nama A–Z' },
                                    { value: 'newest', label: 'Terbaru' },
                                    { value: 'oldest', label: 'Terlama' },
                                ]}
                                onChange={(value) =>
                                    setSelectedSort(value as Props['sort'])
                                }
                                searchPlaceholder="Cari urutan..."
                            />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit">Filter</Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetFilters}
                            >
                                Reset
                            </Button>
                        </div>
                    </div>
                </form>
                {canManage && (
                    <div className="flex flex-wrap justify-end gap-2">
                        <Dialog
                            open={folderOpen}
                            onOpenChange={(open) => {
                                if (!folderForm.processing) {
                                    setFolderOpen(open);

                                    if (!open) {
                                        folderForm.reset();
                                        folderForm.clearErrors();
                                    }
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <FolderPlus />
                                    Buat folder
                                </Button>
                            </DialogTrigger>
                            <ModalShell
                                size="small"
                                showCloseButton={!folderForm.processing}
                            >
                                <DialogHeader>
                                    <DialogTitle>Buat folder</DialogTitle>
                                    <DialogDescription>
                                        {folder
                                            ? `Buat subfolder di ${folder.name}.`
                                            : 'Beri nama folder dan pilih aksesnya.'}
                                    </DialogDescription>
                                </DialogHeader>
                                <form
                                    onSubmit={addFolder}
                                    className="grid min-w-0 gap-4"
                                >
                                    <Label htmlFor="document-folder-name">
                                        Nama folder
                                    </Label>
                                    <Input
                                        id="document-folder-name"
                                        aria-label="Nama folder"
                                        placeholder="Nama folder"
                                        maxLength={160}
                                        value={folderForm.data.name}
                                        onChange={(event) =>
                                            folderForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {!folder && (
                                        <fieldset className="grid gap-3 text-sm">
                                            <legend className="mb-2 font-medium">
                                                Akses
                                            </legend>
                                            <RadioGroup
                                                name="folder-visibility"
                                                aria-label="Akses folder"
                                                value={
                                                    folderForm.data.visibility
                                                }
                                                onValueChange={(value) => {
                                                    if (
                                                        value === 'private' ||
                                                        value === 'public'
                                                    ) {
                                                        folderForm.setData(
                                                            'visibility',
                                                            value,
                                                        );
                                                    }
                                                }}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <RadioGroupItem
                                                        id="folder-visibility-private"
                                                        value="private"
                                                    />
                                                    <Label htmlFor="folder-visibility-private">
                                                        Pribadi
                                                    </Label>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <RadioGroupItem
                                                        id="folder-visibility-public"
                                                        value="public"
                                                    />
                                                    <Label htmlFor="folder-visibility-public">
                                                        Publik untuk pengguna
                                                        aplikasi
                                                    </Label>
                                                </div>
                                            </RadioGroup>
                                        </fieldset>
                                    )}
                                    {folder && (
                                        <p className="text-xs text-muted-foreground">
                                            Akses diwariskan dari folder induk
                                            dan tidak dapat diubah.
                                        </p>
                                    )}
                                    {Object.entries(folderForm.errors).map(
                                        ([field, message]) => (
                                            <p
                                                key={field}
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {message}
                                            </p>
                                        ),
                                    )}
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={folderForm.processing}
                                            onClick={() => {
                                                folderForm.reset();
                                                folderForm.clearErrors();
                                                setFolderOpen(false);
                                            }}
                                        >
                                            Batal
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={folderForm.processing}
                                        >
                                            <FolderPlus />
                                            {folderForm.processing
                                                ? 'Menyimpan…'
                                                : 'Buat folder'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </ModalShell>
                        </Dialog>
                        <Dialog
                            open={uploadOpen}
                            onOpenChange={(open) => {
                                if (!uploadForm.processing) {
                                    setUploadOpen(open);

                                    if (!open) {
                                        uploadForm.reset();
                                        uploadForm.clearErrors();
                                    }
                                }
                            }}
                        >
                            <DialogTrigger asChild>
                                <Button>
                                    <Upload />
                                    Unggah file
                                </Button>
                            </DialogTrigger>
                            <ModalShell
                                size="small"
                                showCloseButton={!uploadForm.processing}
                            >
                                <DialogHeader>
                                    <DialogTitle>Unggah file</DialogTitle>
                                    <DialogDescription>
                                        PDF, Word, spreadsheet, presentasi, atau
                                        gambar. Maksimal 20 MB.
                                    </DialogDescription>
                                </DialogHeader>
                                <form
                                    onSubmit={upload}
                                    className="grid min-w-0 gap-4"
                                >
                                    <Label htmlFor="document-name">
                                        Nama dokumen
                                    </Label>
                                    <Input
                                        id="document-name"
                                        aria-label="Nama dokumen"
                                        placeholder="Nama dokumen"
                                        maxLength={180}
                                        value={uploadForm.data.name}
                                        onChange={(event) =>
                                            uploadForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <Label htmlFor="document-upload">
                                        Berkas
                                    </Label>
                                    <Input
                                        id="document-upload"
                                        ref={fileInput}
                                        aria-label="Pilih berkas"
                                        type="file"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.gif"
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0] ?? null;
                                            uploadForm.setData('file', file);

                                            if (file && !uploadForm.data.name) {
                                                uploadForm.setData(
                                                    'name',
                                                    file.name.replace(
                                                        /\.[^.]+$/,
                                                        '',
                                                    ),
                                                );
                                            }
                                        }}
                                        required
                                    />
                                    {!folder && (
                                        <fieldset className="grid gap-3 text-sm">
                                            <legend className="mb-2 font-medium">
                                                Akses
                                            </legend>
                                            <RadioGroup
                                                name="file-visibility"
                                                aria-label="Akses file"
                                                value={
                                                    uploadForm.data.visibility
                                                }
                                                onValueChange={(value) => {
                                                    if (
                                                        value === 'private' ||
                                                        value === 'public'
                                                    ) {
                                                        uploadForm.setData(
                                                            'visibility',
                                                            value,
                                                        );
                                                    }
                                                }}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <RadioGroupItem
                                                        id="file-visibility-private"
                                                        value="private"
                                                    />
                                                    <Label htmlFor="file-visibility-private">
                                                        Pribadi
                                                    </Label>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <RadioGroupItem
                                                        id="file-visibility-public"
                                                        value="public"
                                                    />
                                                    <Label htmlFor="file-visibility-public">
                                                        Publik untuk pengguna
                                                        aplikasi
                                                    </Label>
                                                </div>
                                            </RadioGroup>
                                        </fieldset>
                                    )}
                                    {folder && (
                                        <p className="text-xs text-muted-foreground">
                                            Akses file mengikuti folder dan
                                            tidak dapat diubah.
                                        </p>
                                    )}
                                    {Object.entries(uploadForm.errors).map(
                                        ([field, message]) => (
                                            <p
                                                key={field}
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {message}
                                            </p>
                                        ),
                                    )}
                                    {uploadForm.progress && (
                                        <progress
                                            className="w-full"
                                            value={
                                                uploadForm.progress.percentage
                                            }
                                            max={100}
                                        >
                                            {uploadForm.progress.percentage}%
                                        </progress>
                                    )}
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={uploadForm.processing}
                                            onClick={() => {
                                                uploadForm.reset();
                                                uploadForm.clearErrors();
                                                setUploadOpen(false);
                                            }}
                                        >
                                            Batal
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={
                                                uploadForm.processing ||
                                                !uploadForm.data.file
                                            }
                                        >
                                            <Upload />
                                            {uploadForm.processing
                                                ? 'Mengunggah…'
                                                : 'Unggah'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </ModalShell>
                        </Dialog>
                    </div>
                )}
                <section
                    aria-labelledby="document-folders-heading"
                    className="@container grid gap-2"
                >
                    <div className="flex items-center justify-between gap-3">
                        <h2
                            id="document-folders-heading"
                            className="text-base font-medium"
                        >
                            Folder
                        </h2>
                        <span className="text-sm text-muted-foreground">
                            {folders.total} folder
                        </span>
                    </div>
                    <div className="grid gap-2.5 @min-[36rem]:grid-cols-2 @min-[60rem]:grid-cols-4">
                        {folders.data.map((item) => (
                            <article
                                key={item.id}
                                className="min-w-0 rounded-2xl border bg-card p-3 text-card-foreground"
                            >
                                <div className="flex items-start gap-2">
                                    <Link
                                        href={item.url}
                                        className="flex min-w-0 flex-1 items-center gap-2 rounded-lg focus-visible:outline-ring"
                                        title={`${item.name} · ${visibilityLabel(item.visibility)} · ${item.owner}`}
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                            <Folder className="size-4" />
                                        </span>
                                        <div className="min-w-0">
                                            <h3 className="truncate text-sm font-semibold">
                                                {item.name}
                                            </h3>
                                            <p className="text-xs text-muted-foreground">
                                                {item.fileCount} file
                                            </p>
                                        </div>
                                    </Link>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={`Aksi folder ${item.name}`}
                                            >
                                                <EllipsisVertical className="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            className="w-56"
                                        >
                                            <DropdownMenuItem asChild>
                                                <Link href={item.url}>
                                                    <Folder />
                                                    Buka folder
                                                </Link>
                                            </DropdownMenuItem>
                                            <div className="grid gap-1 px-2 py-1.5 text-xs text-muted-foreground">
                                                <span>
                                                    {visibilityLabel(
                                                        item.visibility,
                                                    )}
                                                </span>
                                                <span className="break-words">
                                                    Pemilik: {item.owner}
                                                </span>
                                            </div>
                                            {item.canDelete && (
                                                <>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onSelect={() =>
                                                            void removeListedFolder(
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        Hapus folder
                                                    </DropdownMenuItem>
                                                </>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                                <div className="mt-3 flex min-w-0 items-center justify-between gap-2 text-xs text-muted-foreground">
                                    <span className="flex min-w-0 items-center gap-1">
                                        <Clock3 className="size-3.5 shrink-0" />
                                        <span className="truncate">
                                            Dibuat{' '}
                                            {formatWitaDate(item.createdAt)}
                                        </span>
                                    </span>
                                    <span
                                        className="shrink-0"
                                        title="Ukuran file langsung di dalam folder"
                                    >
                                        {formatSize(item.size)}
                                    </span>
                                </div>
                            </article>
                        ))}
                        {folders.data.length === 0 && (
                            <p className="col-span-full rounded-2xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                Tidak ada folder.
                            </p>
                        )}
                    </div>
                    <DocumentPagination label="Folder" resource={folders} />
                </section>
                <section
                    aria-labelledby="document-files-heading"
                    className="@container grid gap-2"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2
                            id="document-files-heading"
                            className="text-base font-medium"
                        >
                            File{' '}
                            <span className="ml-1 text-sm font-normal text-muted-foreground">
                                ({documents.total})
                            </span>
                        </h2>
                        <ButtonGroup
                            aria-label="Tampilan file"
                            className="rounded-lg border"
                        >
                            <Button
                                type="button"
                                variant={
                                    layout === 'grid' ? 'secondary' : 'ghost'
                                }
                                size="sm"
                                aria-pressed={layout === 'grid'}
                                onClick={() => setLayout('grid')}
                            >
                                <Grid2X2 className="size-3.5" />
                                Grid View
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    layout === 'list' ? 'secondary' : 'ghost'
                                }
                                size="sm"
                                aria-pressed={layout === 'list'}
                                onClick={() => setLayout('list')}
                            >
                                <List className="size-3.5" />
                                List View
                            </Button>
                        </ButtonGroup>
                    </div>
                    <div
                        className={
                            layout === 'grid'
                                ? 'grid gap-3 @min-[36rem]:grid-cols-2 @min-[60rem]:grid-cols-4'
                                : 'grid gap-2'
                        }
                    >
                        {documents.data.map((item) => {
                            const Icon = fileIcon(item.mimeType);
                            const canPreview =
                                item.mimeType === 'application/pdf' ||
                                item.mimeType.startsWith('image/');

                            return (
                                <article
                                    key={item.id}
                                    className={cn(
                                        'min-w-0 rounded-2xl border bg-card p-3 text-card-foreground',
                                        layout === 'list' &&
                                            'flex items-center gap-3',
                                    )}
                                >
                                    <a
                                        href={
                                            canPreview
                                                ? item.viewUrl
                                                : item.downloadUrl
                                        }
                                        target={
                                            canPreview ? '_blank' : undefined
                                        }
                                        rel={
                                            canPreview
                                                ? 'noreferrer'
                                                : undefined
                                        }
                                        aria-label={`${canPreview ? 'Lihat' : 'Unduh'} ${item.name}`}
                                        className={cn(
                                            'relative flex items-center justify-center rounded-xl bg-muted/60 text-muted-foreground focus-visible:outline-ring',
                                            layout === 'grid'
                                                ? 'h-36 w-full'
                                                : 'size-14 shrink-0',
                                        )}
                                    >
                                        <Icon
                                            className={
                                                layout === 'grid'
                                                    ? 'size-12'
                                                    : 'size-7'
                                            }
                                            strokeWidth={1.7}
                                        />
                                        {layout === 'grid' && (
                                            <div className="absolute inset-x-3 bottom-3 flex justify-between gap-2 text-xs">
                                                <span>{fileType(item)}</span>
                                                <span>
                                                    {formatSize(item.size)}
                                                </span>
                                            </div>
                                        )}
                                    </a>
                                    <div
                                        className={cn(
                                            'flex min-w-0 items-start gap-2',
                                            layout === 'grid'
                                                ? 'mt-3'
                                                : 'flex-1',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <h3
                                                className="truncate text-sm font-semibold"
                                                title={item.originalName}
                                            >
                                                <a
                                                    href={
                                                        canPreview
                                                            ? item.viewUrl
                                                            : item.downloadUrl
                                                    }
                                                    target={
                                                        canPreview
                                                            ? '_blank'
                                                            : undefined
                                                    }
                                                    rel={
                                                        canPreview
                                                            ? 'noreferrer'
                                                            : undefined
                                                    }
                                                    className="hover:underline"
                                                >
                                                    {item.name}
                                                </a>
                                            </h3>
                                            <p
                                                className="mt-1 truncate text-sm text-muted-foreground"
                                                title={`Dibuat ${formatWitaDate(item.createdAt)} oleh ${item.owner}`}
                                            >
                                                Dibuat{' '}
                                                {formatWitaDate(item.createdAt)}{' '}
                                                oleh {item.owner}
                                            </p>
                                            {layout === 'list' && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {fileType(item)} ·{' '}
                                                    {formatSize(item.size)}
                                                </p>
                                            )}
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={`Aksi file ${item.name}`}
                                                >
                                                    <EllipsisVertical className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                align="end"
                                                className="w-56"
                                            >
                                                <DropdownMenuItem asChild>
                                                    {canPreview ? (
                                                        <a
                                                            href={item.viewUrl}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <Printer />
                                                            Lihat / Cetak
                                                        </a>
                                                    ) : (
                                                        <a
                                                            href={
                                                                item.downloadUrl
                                                            }
                                                        >
                                                            <Download />
                                                            Unduh untuk mencetak
                                                        </a>
                                                    )}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <a href={item.downloadUrl}>
                                                        <Download />
                                                        Unduh
                                                    </a>
                                                </DropdownMenuItem>
                                                <div className="px-2 py-1.5 text-xs text-muted-foreground">
                                                    {visibilityLabel(
                                                        item.visibility,
                                                    )}
                                                </div>
                                                {item.canDelete && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={() =>
                                                                void removeFile(
                                                                    item,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Hapus file
                                                        </DropdownMenuItem>
                                                    </>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </article>
                            );
                        })}
                        {documents.data.length === 0 && (
                            <p className="col-span-full rounded-2xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                                Tidak ada file.
                            </p>
                        )}
                    </div>
                    <DocumentPagination label="File" resource={documents} />
                </section>
                {folder?.canDelete && (
                    <div className="flex justify-end">
                        <Button
                            variant="destructive"
                            onClick={() => void removeFolder()}
                        >
                            <Trash2 />
                            Hapus folder
                        </Button>
                    </div>
                )}
                <footer className="flex items-center gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                    <LockKeyhole className="size-4 shrink-0" />
                    <span>
                        Publik berarti tersedia bagi pengguna dalam aplikasi,
                        bukan dapat diakses melalui internet. File privat tidak
                        memiliki URL publik.
                    </span>
                </footer>
            </div>
        </AppLayout>
    );
}
