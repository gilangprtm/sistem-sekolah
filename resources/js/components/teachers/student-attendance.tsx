import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import InputError from '@/components/input-error';
import SearchableCombobox from '@/components/searchable-combobox';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import ModalShell from '@/components/ui/modal-shell';
import { formatWitaDate } from '@/lib/date-time';
import { update as updateAttendance } from '@/routes/teacher/attendance';
import type {
    AttendanceRow,
    AttendanceStatus,
} from '@/types/student-attendance';

type Props = {
    rows: AttendanceRow[];
    source: 'agenda' | 'homeroom';
    date: string;
    blockKey?: string;
    agendaId?: number | null;
    emptyMessage: string;
    canManage: boolean;
    contactActions?: (student: {
        phone: string | null;
        parent_phone: string | null;
    }) => ReactNode;
};

const options: { value: AttendanceStatus; label: string }[] = [
    { value: 'H', label: 'Hadir' },
    { value: 'I', label: 'Izin' },
    { value: 'S', label: 'Sakit' },
    { value: 'D', label: 'Dispensasi' },
    { value: 'A', label: 'Alpa' },
];

const styles: Record<AttendanceStatus, string> = {
    H: 'bg-emerald-600 text-white border-transparent',
    I: 'bg-amber-500 text-white border-transparent',
    S: 'bg-sky-600 text-white border-transparent',
    D: 'bg-violet-600 text-white border-transparent',
    A: 'bg-red-600 text-white border-transparent',
};

const unrecordedStyle = 'bg-muted text-muted-foreground border-border';

export default function StudentAttendance({
    rows,
    source,
    date,
    blockKey,
    agendaId,
    emptyMessage,
    canManage,
    contactActions,
}: Props) {
    const [selected, setSelected] = useState<AttendanceRow | null>(null);
    const [status, setStatus] = useState<AttendanceStatus>('A');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const saveAttendance = (
        attendance: {
            student_id: number;
            status: AttendanceStatus;
            version: number;
        }[],
        closeModal: boolean,
    ) => {
        if (saving || !canManage || attendance.length === 0) {
            return;
        }

        setSaving(true);
        setErrors({});
        router.put(
            updateAttendance.url(),
            {
                source,
                date,
                block_key: blockKey,
                agenda_id: agendaId,
                attendance,
            },
            {
                preserveScroll: true,
                onError: (response) =>
                    setErrors(response as Record<string, string>),
                onSuccess: () => {
                    if (closeModal) {
                        setSelected(null);
                    }
                },
                onHttpException: () =>
                    setErrors({
                        attendance:
                            'Absensi tidak dapat disimpan. Muat ulang dan periksa akses Anda.',
                    }),
                onNetworkError: () =>
                    setErrors({
                        attendance:
                            'Koneksi bermasalah. Periksa hasil terbaru sebelum mencoba kembali.',
                    }),
                onFinish: () => setSaving(false),
            },
        );
    };

    const save = () => {
        if (!selected) {
            return;
        }

        saveAttendance(
            [
                {
                    student_id: selected.student_id,
                    status,
                    version: selected.version,
                },
            ],
            true,
        );
    };

    const columns: DataTableColumn<AttendanceRow>[] = [
        { id: 'number', header: 'No.', cell: (_, index) => index + 1 },
        {
            id: 'student',
            header: 'Siswa',
            cell: (row) => (
                <div className="min-w-36">
                    <p className="font-medium">{row.full_name}</p>
                </div>
            ),
        },
        {
            id: 'status',
            header: 'Keterangan',
            headerClassName: 'text-center',
            cellClassName: 'text-center',
            cell: (row) => {
                const isUnrecorded = row.version === 0;
                const label = isUnrecorded ? '-' : row.status;

                return (
                    <span
                        className={`mx-auto grid size-8 place-items-center rounded-md border text-xs font-semibold ${isUnrecorded ? unrecordedStyle : styles[row.status]}`}
                        aria-label={`Keterangan ${row.full_name}: ${isUnrecorded ? 'Belum dicatat' : row.status}`}
                        title={isUnrecorded ? 'Belum dicatat' : row.status}
                    >
                        {label}
                    </span>
                );
            },
        },
    ];

    return (
        <>
            <div className="mb-3 space-y-1">
                <h2 className="font-semibold">Kehadiran siswa</h2>
                <p className="text-sm text-muted-foreground">
                    {formatWitaDate(`${date}T12:00:00+08:00`)} · {rows.length}{' '}
                    siswa
                </p>
                <p className="text-xs text-muted-foreground">
                    Data harian bersama semua guru dan Wali Kelas. Tanda -
                    berarti absensi belum dicatat.
                </p>
                {canManage && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={saving || rows.length === 0}
                        onClick={() =>
                            saveAttendance(
                                rows.map((row) => ({
                                    student_id: row.student_id,
                                    status: 'H',
                                    version: row.version,
                                })),
                                false,
                            )
                        }
                    >
                        {saving ? 'Menyimpan…' : 'Semua Hadir'}
                    </Button>
                )}
            </div>
            <DataTableShell
                data={rows}
                columns={columns}
                getRowKey={(row) => row.student_id}
                emptyState={emptyMessage}
                rowActions={{
                    label: (row) => `Aksi ${row.full_name}`,
                    render: (row) => (
                        <>
                            {canManage && (
                                <DropdownMenuItem
                                    onSelect={() => {
                                        setErrors({});
                                        setSelected(row);
                                        setStatus(row.status);
                                    }}
                                >
                                    Absen
                                </DropdownMenuItem>
                            )}
                            {contactActions?.({
                                phone: row.phone ?? null,
                                parent_phone: row.parent_phone ?? null,
                            })}
                            {!canManage && !contactActions && (
                                <DropdownMenuItem disabled>
                                    Hanya lihat
                                </DropdownMenuItem>
                            )}
                        </>
                    ),
                }}
            />
            {Object.entries(errors).map(([field, message]) => (
                <InputError key={field} message={message} role="alert" />
            ))}
            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open && !saving) {
                        setSelected(null);
                    }
                }}
            >
                <ModalShell size="small">
                    <DialogHeader>
                        <DialogTitle>Absen Siswa</DialogTitle>
                        <DialogDescription>
                            {selected?.full_name ?? 'Siswa'} · {date}
                        </DialogDescription>
                    </DialogHeader>
                    {selected && (
                        <>
                            <div
                                className="space-y-2"
                                role="group"
                                aria-label="Keterangan absen"
                            >
                                <p className="text-sm font-medium">
                                    Keterangan
                                </p>
                                <fieldset disabled={saving}>
                                    <SearchableCombobox
                                        value={status}
                                        options={options}
                                        placeholder="Pilih keterangan absen"
                                        searchPlaceholder="Cari keterangan absen…"
                                        onChange={(value) => {
                                            if (
                                                [
                                                    'H',
                                                    'I',
                                                    'S',
                                                    'D',
                                                    'A',
                                                ].includes(value)
                                            ) {
                                                setStatus(
                                                    value as AttendanceStatus,
                                                );
                                            }
                                        }}
                                    />
                                </fieldset>
                            </div>
                            <InputError
                                message={errors.attendance}
                                role="alert"
                            />
                            <p className="text-xs text-muted-foreground">
                                Perubahan tersimpan untuk kehadiran siswa hari
                                ini dan dapat dilihat guru lain serta Wali
                                Kelas.
                            </p>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={saving}
                                    onClick={() => setSelected(null)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="button"
                                    disabled={saving}
                                    onClick={save}
                                >
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
                        </>
                    )}
                </ModalShell>
            </Dialog>
        </>
    );
}
