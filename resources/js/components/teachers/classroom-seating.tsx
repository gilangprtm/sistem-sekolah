import { useDraggable, useDroppable } from '@dnd-kit/core';
import { Armchair, Grip } from 'lucide-react';
import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

export type SeatingLayout = {
    version: string;
    seats: Array<{ seat_number: number; student_id: number }>;
    students: Array<{
        id: number;
        full_name: string | null;
        photo_url: string | null;
    }>;
};

type SeatStudent = SeatingLayout['students'][number];
type SeatProps = {
    number: number;
    student?: SeatStudent;
    disabled: boolean;
    onSelect: () => void;
};

function SeatOccupant({ student }: { student?: SeatStudent }) {
    return student ? (
        <Avatar
            className={
                student.photo_url
                    ? 'aspect-[2/3] h-auto w-full max-w-20 overflow-hidden rounded-md after:rounded-md'
                    : 'size-12'
            }
        >
            <AvatarImage
                src={student.photo_url ?? undefined}
                alt={student.full_name ?? 'Siswa'}
                draggable={false}
                className="aspect-auto rounded-md object-top"
            />
            <AvatarFallback
                className={student.photo_url ? 'rounded-md' : undefined}
            >
                {(student.full_name ?? 'S')
                    .split(' ')
                    .map((word) => word[0])
                    .slice(0, 2)
                    .join('')}
            </AvatarFallback>
        </Avatar>
    ) : (
        <Armchair className="size-8 text-muted-foreground" aria-hidden="true" />
    );
}

function SeatNumber({ number }: { number: number }) {
    return (
        <span
            className="absolute top-1 left-1 z-20 rounded bg-background/80 px-1 text-[10px] text-muted-foreground"
            aria-hidden="true"
        >
            {number}
        </span>
    );
}

function ReadOnlySeat({
    number,
    student,
    onStudentSelect,
}: Omit<SeatProps, 'disabled' | 'onSelect'> & {
    onStudentSelect?: (student: SeatStudent) => void;
}) {
    return (
        <div
            className="relative min-w-0 rounded-xl border bg-card p-2"
            aria-label={`Meja ${number}: ${student?.full_name ?? 'Kosong'}`}
        >
            <div className="grid h-44 w-full grid-rows-[minmax(0,1fr)_2rem_1rem] items-center justify-items-center gap-1 rounded-lg">
                {student && onStudentSelect ? (
                    <button
                        type="button"
                        onClick={() => onStudentSelect(student)}
                        className="flex min-h-12 w-full max-w-20 items-center justify-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label={`Buka Catatan Siswa ${student.full_name ?? ''}`}
                    >
                        <SeatOccupant student={student} />
                    </button>
                ) : (
                    <SeatOccupant student={student} />
                )}
                <span className="line-clamp-2 w-full text-center text-xs font-medium">
                    {student?.full_name ?? `Meja ${number}`}
                </span>
            </div>
            <SeatNumber number={number} />
        </div>
    );
}

function EditableSeat({ number, student, disabled, onSelect }: SeatProps) {
    const { setNodeRef: setDropRef, isOver } = useDroppable({
        id: number,
        disabled,
    });
    const {
        setNodeRef: setDragRef,
        attributes,
        listeners,
        transform,
        isDragging,
    } = useDraggable({
        id: number,
        disabled: disabled || !student,
    });

    return (
        <div
            ref={setDropRef}
            className={`relative min-w-0 rounded-xl border bg-card p-2 ${isOver ? 'border-primary bg-primary/10 ring-2 ring-primary' : ''}`}
        >
            <button
                ref={setDragRef}
                type="button"
                disabled={disabled}
                onClick={onSelect}
                {...attributes}
                {...listeners}
                style={{
                    transform: transform
                        ? `translate3d(${transform.x}px, ${transform.y}px, 0)`
                        : undefined,
                    opacity: isDragging ? 0.4 : 1,
                }}
                className="relative z-10 grid h-44 w-full touch-manipulation grid-rows-[minmax(0,1fr)_2rem_1rem] items-center justify-items-center gap-1 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
                aria-label={`Meja ${number}: ${student?.full_name ?? 'Kosong'}. Pilih atau ganti siswa.`}
            >
                <SeatOccupant student={student} />
                <span className="line-clamp-2 w-full text-center text-xs font-medium">
                    {student?.full_name ?? `Meja ${number}`}
                </span>
                {student && (
                    <Grip
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                )}
            </button>
            <SeatNumber number={number} />
        </div>
    );
}

type Props = {
    seating: SeatingLayout;
    notice?: ReactNode;
    saving?: boolean;
} & (
    | {
          readOnly: true;
          disabled?: never;
          onSelectSeat?: never;
          onStudentSelect?: (student: SeatStudent) => void;
      }
    | {
          readOnly?: false;
          disabled: boolean;
          onSelectSeat: (number: number) => void;
      }
);

export default function ClassroomSeating(props: Props) {
    const { seating, notice, readOnly = false } = props;
    const assignments = Object.fromEntries(
        seating.seats.map(({ seat_number, student_id }) => [
            seat_number,
            student_id,
        ]),
    );
    const seatedIds = new Set(Object.values(assignments));
    const unseatedCount = seating.students.filter(
        ({ id }) => !seatedIds.has(id),
    ).length;

    return (
        <section className="space-y-4" aria-labelledby="seating-heading">
            <div>
                <h2 id="seating-heading" className="font-semibold">
                    Denah Tempat Duduk
                </h2>
                <p className="text-sm text-muted-foreground">
                    4 pasang di depan · 5 pasang ke belakang per kelompok · 40
                    meja siswa
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {readOnly
                        ? 'Denah terbaru untuk kelas dan tahun ajaran agenda. Hanya dapat dilihat; pengaturan dilakukan melalui Wali Kelas.'
                        : 'Sentuh meja untuk memilih, mengganti, atau mengosongkan siswa. Geser foto untuk memindahkan atau menukar tempat; pada layar sentuh, tahan foto sebentar lalu geser. Perubahan tersimpan otomatis.'}
                </p>
            </div>
            <p className="text-sm" aria-live="polite">
                {props.saving
                    ? 'Menyimpan denah…'
                    : `${seatedIds.size} siswa sudah duduk · ${unseatedCount} belum mendapat tempat`}
            </p>
            {notice}
            <div
                className="overflow-x-auto rounded-xl border bg-muted/20 p-4"
                tabIndex={0}
                role="region"
                aria-label="Denah 40 meja siswa; geser mendatar pada layar kecil"
            >
                <div className="min-w-[860px] space-y-6">
                    <div className="grid grid-cols-4 gap-10">
                        <div className="col-start-4 rounded-lg border bg-muted px-6 py-4 text-center text-sm font-semibold">
                            Meja Guru · Depan kelas
                        </div>
                    </div>
                    <div className="grid grid-cols-4 gap-10">
                        {Array.from({ length: 4 }, (_, group) => (
                            <div
                                key={group}
                                className="grid grid-cols-2 gap-x-2 gap-y-5"
                                aria-label={`Kelompok meja ${group + 1}`}
                            >
                                {Array.from({ length: 5 }, (_, depth) =>
                                    [0, 1].map((side) => {
                                        const number =
                                            depth * 8 + group * 2 + side + 1;
                                        const student = seating.students.find(
                                            ({ id }) =>
                                                id === assignments[number],
                                        );

                                        return props.readOnly ? (
                                            <ReadOnlySeat
                                                key={number}
                                                number={number}
                                                student={student}
                                                onStudentSelect={
                                                    props.onStudentSelect
                                                }
                                            />
                                        ) : (
                                            <EditableSeat
                                                key={number}
                                                number={number}
                                                student={student}
                                                disabled={props.disabled}
                                                onSelect={() =>
                                                    props.onSelectSeat(number)
                                                }
                                            />
                                        );
                                    }),
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
