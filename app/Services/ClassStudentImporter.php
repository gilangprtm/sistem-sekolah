<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentPlacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class ClassStudentImporter
{
    /** @var list<string> */
    private const HEADERS = ['nisn', 'full_name'];

    public function __construct(private readonly XlsxTemplateReader $reader) {}

    /** @return array{students: list<array{id: int, nis: string|null, nisn: string|null, full_name: string}>} */
    public function preview(UploadedFile $file, int $academicYearId, int $rombelId): array
    {
        $rows = $this->reader->read($file, self::HEADERS, 'Manajemen Kelas');
        $seen = [];
        foreach ($rows as $line => $row) {
            $nisn = $row['nisn'] ?? '';
            if (! preg_match('/^[0-9]+$/D', $nisn) || mb_strlen($nisn) > 20) {
                throw ValidationException::withMessages(["rows.{$line}.nisn" => "Baris {$line}: NISN wajib berupa angka dan maksimal 20 digit, sesuai Master Siswa."]);
            }
            if (isset($seen[$nisn])) {
                throw ValidationException::withMessages(['file' => "Baris {$line}: NISN duplikat dengan baris {$seen[$nisn]}."]);
            }
            $seen[$nisn] = $line;
        }

        $students = Student::query()->whereIn('nisn', array_column($rows, 'nisn'))
            ->get(['id', 'nis', 'nisn', 'full_name', 'status'])->keyBy('nisn');
        $conflictingStudentIds = StudentPlacement::query()
            ->where('academic_year_id', $academicYearId)
            ->whereIn('student_id', $students->modelKeys())
            ->where('status', 'active')
            ->where('rombel_id', '!=', $rombelId)
            ->pluck('student_id')->all();
        $errors = [];
        $matched = [];
        foreach ($rows as $line => $row) {
            /** @var Student|null $student */
            $student = $students->get($row['nisn']);
            if ($student === null) {
                $errors[] = "Baris {$line}: NISN {$row['nisn']} tidak ditemukan di Master Siswa.";
            } elseif ($student->status !== 'active') {
                $errors[] = "Baris {$line}: {$student->full_name} berstatus tidak aktif.";
            } elseif (in_array($student->id, $conflictingStudentIds, true)) {
                $errors[] = "Baris {$line}: {$student->full_name} sudah terdaftar pada kelas lain di Tahun Ajaran ini.";
            } else {
                $matched[] = ['id' => $student->id, 'nis' => $student->nis, 'nisn' => $student->nisn, 'full_name' => $student->full_name];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        return ['students' => $matched];
    }

    public function template(): string
    {
        return app(XlsxWriter::class)->write([
            self::HEADERS,
            ['NISN', 'Nama Siswa'],
            ['0012345678', 'Siti Aminah'],
        ], 'Import Siswa Kelas', [
            'columnWidths' => [1 => 24.0, 2 => 38.0],
            'headerRow' => 2,
        ]);
    }

}
