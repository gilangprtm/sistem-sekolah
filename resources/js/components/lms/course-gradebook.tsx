import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

export type GradebookPageScore = {
    weights_set: boolean;
    submitted: boolean;
    score: number | null;
    ungraded_essays: number;
};

type Props = {
    pages: { id: string; title: string }[];
    students: {
        id: number;
        name: string;
        scores: Record<string, GradebookPageScore>;
    }[];
};

function scoreLabel(result: GradebookPageScore | undefined): string {
    if (!result?.weights_set) {
        return 'Bobot belum diatur';
    }

    if (!result.submitted) {
        return 'Belum dikumpulkan';
    }

    if (result.score === null) {
        return `Belum dinilai (${result.ungraded_essays})`;
    }

    return `${Number.isInteger(result.score) ? result.score : result.score.toFixed(2)}/100`;
}

export default function CourseGradebook({ pages, students }: Props) {
    if (pages.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Course ini belum memiliki materi atau bagian dengan soal.
            </p>
        );
    }

    return (
        <div className="grid min-w-0 gap-3">
            <p className="text-sm text-muted-foreground">
                Nilai per materi atau bagian hanya terlihat oleh Guru.
            </p>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col">Nomor</TableHead>
                        <TableHead scope="col">Nama</TableHead>
                        {pages.map((page) => (
                            <TableHead
                                key={page.id}
                                scope="col"
                                className="min-w-40 whitespace-normal"
                            >
                                {page.title}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {students.map((student, index) => (
                        <TableRow key={student.id}>
                            <TableCell>{index + 1}</TableCell>
                            <TableCell className="min-w-40 font-medium whitespace-normal">
                                {student.name}
                            </TableCell>
                            {pages.map((page) => (
                                <TableCell
                                    key={page.id}
                                    className="min-w-40 whitespace-normal tabular-nums"
                                >
                                    {scoreLabel(student.scores[page.id])}
                                </TableCell>
                            ))}
                        </TableRow>
                    ))}
                    {students.length === 0 && (
                        <TableRow>
                            <TableCell
                                colSpan={pages.length + 2}
                                className="h-24 text-center text-muted-foreground"
                            >
                                Belum ada siswa berpenempatan aktif di kelas
                                ini.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
