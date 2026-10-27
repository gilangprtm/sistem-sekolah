import { Head, Link, router } from '@inertiajs/react';
import { addDays, format, isSameDay, startOfWeek } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Plus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import DataTableShell from '@/components/data-table/data-table-shell';
import type { DataTableColumn } from '@/components/data-table/data-table-shell';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { witaDateKey } from '@/lib/date-time';
import { cn } from '@/lib/utils';
import { create, edit, index as agendaIndex } from '@/routes/teacher/agenda';
import type { BreadcrumbItem } from '@/types';
import type { AgendaSnapshot } from '@/types/teacher-agenda';

type Block = {
    block_key: string;
    snapshot: AgendaSnapshot;
    agenda_id: number | null;
};
type AgendaHistory = {
    id: number;
    date: string;
    snapshot: AgendaSnapshot;
    material: string;
};
type Props = {
    date: string;
    hasTeacherProfile: boolean;
    hasActivePeriod: boolean;
    canManage: boolean;
    blocks: Block[];
    history: {
        data: AgendaHistory[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    } | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Agenda Guru', href: agendaIndex.url() },
];

const dateLabel = (date: string): string =>
    new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Asia/Makassar',
    }).format(new Date(`${date}T12:00:00+08:00`));

const blockLabel = (snapshot: AgendaSnapshot): string =>
    snapshot.lesson_start === snapshot.lesson_end
        ? `Jam ke-${snapshot.lesson_start}`
        : `Jam ke-${snapshot.lesson_start}–${snapshot.lesson_end}`;

export default function AgendaIndex({
    date,
    hasTeacherProfile,
    hasActivePeriod,
    canManage,
    blocks,
    history,
}: Props) {
    const selectedDate = new Date(`${date}T12:00:00`);
    const [todayKey, setTodayKey] = useState(() => witaDateKey(new Date()));
    const [selectedAgendas, setSelectedAgendas] = useState<number[]>([]);
    const today = new Date(`${todayKey}T12:00:00`);
    const weekStart = startOfWeek(selectedDate, { weekStartsOn: 1 });
    const weekDays = Array.from({ length: 7 }, (_, index) =>
        addDays(weekStart, index),
    );

    useEffect(() => {
        const timer = window.setInterval(() => {
            setTodayKey(witaDateKey(new Date()));
        }, 30_000);

        return () => window.clearInterval(timer);
    }, []);
    const columns: DataTableColumn<AgendaHistory>[] = [
        {
            id: 'date',
            header: 'Tanggal',
            cell: (agenda) => dateLabel(agenda.date),
        },
        {
            id: 'class',
            header: 'Kelas · Mata Pelajaran',
            headerClassName: 'w-72 max-w-72',
            cellClassName: 'w-72 max-w-72',
            cell: (agenda) => (
                <div className="max-w-64 min-w-0">
                    <p className="font-medium wrap-anywhere whitespace-normal">
                        {agenda.snapshot.rombel_code} ·{' '}
                        {agenda.snapshot.subject_name}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {blockLabel(agenda.snapshot)} ·{' '}
                        {agenda.snapshot.start_time}–{agenda.snapshot.end_time}
                    </p>
                </div>
            ),
        },
        {
            id: 'material',
            header: 'Materi',
            headerClassName: 'max-w-xs',
            cellClassName: 'max-w-xs',
            cell: (agenda) => (
                <span className="line-clamp-2 max-w-xs wrap-anywhere whitespace-normal">
                    {agenda.material}
                </span>
            ),
        },
    ];
    const selectDate = (selected: Date | undefined) => {
        if (selected) {
            setSelectedAgendas([]);
            router.get(
                agendaIndex.url(),
                { date: format(selected, 'yyyy-MM-dd') },
                { preserveState: true, preserveScroll: true },
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Agenda Guru" />
            <div className="flex h-full min-w-0 flex-1 flex-col gap-4 rounded-xl p-4">
                <Heading
                    variant="small"
                    title="Agenda Guru"
                    description="Catat materi dan kehadiran per blok jadwal mengajar."
                />
                <section
                    className="grid min-w-0 gap-4 rounded-xl border p-4"
                    aria-label="Kalender agenda mingguan"
                >
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p
                                className="text-sm font-medium"
                                aria-live="polite"
                            >
                                {dateLabel(date)}
                            </p>
                            <div className="flex items-center justify-end gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="min-h-11"
                                    onClick={() => selectDate(today)}
                                    disabled={isSameDay(selectedDate, today)}
                                >
                                    <CalendarDays /> Hari ini
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label="Minggu sebelumnya"
                                    onClick={() =>
                                        selectDate(addDays(selectedDate, -7))
                                    }
                                >
                                    <ChevronLeft />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label="Minggu berikutnya"
                                    onClick={() =>
                                        selectDate(addDays(selectedDate, 7))
                                    }
                                >
                                    <ChevronRight />
                                </Button>
                            </div>
                        </div>
                        <div
                            className="grid grid-cols-7 gap-1 sm:gap-2"
                            role="group"
                            aria-label="Pilih tanggal agenda"
                        >
                            {weekDays.map((day) => {
                                const dayKey = format(day, 'yyyy-MM-dd');
                                const isSelected = isSameDay(day, selectedDate);
                                const isToday = isSameDay(day, today);

                                return (
                                    <Button
                                        key={dayKey}
                                        type="button"
                                        variant={
                                            isSelected ? 'default' : 'ghost'
                                        }
                                        className={cn(
                                            'h-auto min-h-20 min-w-0 flex-col gap-1 px-0 py-2 sm:gap-2 sm:py-3',
                                            isToday &&
                                                !isSelected &&
                                                'bg-muted ring-1 ring-primary/40 ring-inset',
                                        )}
                                        aria-current={
                                            isToday ? 'date' : undefined
                                        }
                                        aria-label={`${format(day, 'EEEE d MMMM yyyy', { locale: indonesianLocale })}${isToday ? ', hari ini' : ''}`}
                                        aria-pressed={isSelected}
                                        onClick={() => selectDate(day)}
                                    >
                                        <span
                                            className={cn(
                                                'text-[0.7rem] font-normal capitalize sm:text-xs',
                                                !isSelected &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            {format(day, 'EEE', {
                                                locale: indonesianLocale,
                                            })}
                                        </span>
                                        <span className="text-base font-semibold sm:text-lg">
                                            {format(day, 'd')}
                                        </span>
                                        {isToday && (
                                            <span className="text-[0.65rem] leading-none sm:text-xs">
                                                Hari ini
                                            </span>
                                        )}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                </section>
                <section className="grid min-w-0 gap-4 rounded-xl border p-4">
                    <Heading
                        variant="small"
                        title="Agenda mengajar"
                        description="Jadwal mengajar untuk tanggal yang dipilih."
                    />
                    {!hasTeacherProfile ? (
                        <p
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm"
                        >
                            Akun ini belum terhubung dengan profil guru.
                        </p>
                    ) : !hasActivePeriod ? (
                        <p
                            role="status"
                            className="rounded-lg border p-4 text-sm text-muted-foreground"
                        >
                            Tahun ajaran atau semester aktif belum tersedia.
                        </p>
                    ) : (
                        <div className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {blocks.length === 0 ? (
                                <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground sm:col-span-2 xl:col-span-3">
                                    <ClipboardList className="mb-2 size-5" />
                                    Tidak ada jadwal mengajar untuk{' '}
                                    {dateLabel(date)}.
                                </div>
                            ) : (
                                blocks.map(
                                    ({ block_key, snapshot, agenda_id }) => (
                                        <article
                                            key={block_key}
                                            className="grid min-w-0 content-start gap-4 rounded-xl border p-4 wrap-anywhere"
                                        >
                                            <div>
                                                <p className="text-sm text-muted-foreground">
                                                    {agenda_id
                                                        ? 'Agenda tersimpan'
                                                        : 'Selamat mengajar di'}
                                                </p>
                                                <h2 className="text-lg font-semibold">
                                                    {snapshot.rombel_code}
                                                </h2>
                                                <p className="text-sm">
                                                    {snapshot.subject_name}
                                                </p>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {blockLabel(snapshot)} ·{' '}
                                                    {snapshot.start_time}–
                                                    {snapshot.end_time}
                                                </p>
                                            </div>
                                            {canManage ? (
                                                <Button
                                                    asChild
                                                    className="w-full"
                                                >
                                                    <Link
                                                        href={
                                                            agenda_id
                                                                ? edit.url(
                                                                      agenda_id,
                                                                  )
                                                                : create.url({
                                                                      query: {
                                                                          date,
                                                                          block_key,
                                                                      },
                                                                  })
                                                        }
                                                    >
                                                        {agenda_id ? (
                                                            'Lihat Agenda'
                                                        ) : (
                                                            <>
                                                                <Plus /> Isi
                                                                Agenda
                                                            </>
                                                        )}
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">
                                                    Akun ini tidak dapat mengisi
                                                    agenda.
                                                </p>
                                            )}
                                        </article>
                                    ),
                                )
                            )}
                        </div>
                    )}
                </section>
                <section className="grid min-w-0 gap-4">
                    <Heading
                        variant="small"
                        title="Riwayat Agenda"
                        description="Riwayat menyimpan konteks jadwal saat jurnal dibuat, meskipun jadwal kemudian berubah."
                    />
                    {history && (
                        <DataTableShell
                            data={history.data}
                            columns={columns}
                            getRowKey={(agenda) => agenda.id}
                            selection={{
                                selectedKeys: selectedAgendas,
                                onSelectionChange: setSelectedAgendas,
                                selectAllLabel:
                                    'Pilih semua agenda di halaman ini',
                                getRowLabel: (agenda) =>
                                    `Pilih agenda ${agenda.snapshot.rombel_code} ${agenda.snapshot.subject_name} tanggal ${dateLabel(agenda.date)}`,
                                selectedCountLabel: (count) =>
                                    `${count} agenda dipilih.`,
                                clearSelectionLabel: 'Batal pilih',
                            }}
                            rowActions={{
                                variant: 'custom',
                                render: (agenda) =>
                                    canManage ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={edit.url(agenda.id)}>
                                                Lihat
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span className="text-sm text-muted-foreground">
                                            Lihat saja
                                        </span>
                                    ),
                            }}
                            emptyState={
                                <p className="p-6 text-sm text-muted-foreground">
                                    Belum ada agenda yang tersimpan.
                                </p>
                            }
                            pagination={{
                                resource: history,
                                onPageChange: (target) => {
                                    setSelectedAgendas([]);
                                    router.get(
                                        agendaIndex.url(),
                                        {
                                            date,
                                            page: target,
                                            per_page: history.per_page,
                                        },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                },
                                onPerPageChange: (perPage) => {
                                    setSelectedAgendas([]);
                                    router.get(
                                        agendaIndex.url(),
                                        { date, per_page: perPage },
                                        {
                                            preserveState: true,
                                            preserveScroll: true,
                                        },
                                    );
                                },
                                noun: 'agenda',
                            }}
                        />
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
