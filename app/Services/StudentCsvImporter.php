<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentCsvImporter
{
    /**
     * @var array<int, string>
     */
    public const HEADERS = [
        'nis',
        'nisn',
        'tahun_angkatan',
        'full_name',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'phone',
        'mother_name',
        'father_name',
        'mother_occupation',
        'father_occupation',
        'parent_phone',
        'status',
    ];

    /**
     * @return array{count: int, created: int, updated: int}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        return DB::transaction(function () use ($rows): array {
            $students = $this->loadExistingStudents($rows);
            $this->validateExistingCollisions($rows, $students);
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $student = $this->findExistingStudent($students, $row);
                $attributes = $this->attributes($row);

                if ($student !== null) {
                    $attributes['nis'] ??= $student->nis;
                    $attributes['nisn'] ??= $student->nisn;
                }

                if ($student === null) {
                    $student = Student::query()->create($attributes);
                    $this->indexStudent($students, $student);
                    $created++;

                    continue;
                }

                $student->update($attributes);
                $updated++;
            }

            return [
                'count' => count($rows),
                'created' => $created,
                'updated' => $updated,
            ];
        });
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readRows(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            return $this->readXlsxRows($file);
        }

        $contents = $file->get();

        if ($contents === false) {
            $this->fail('CSV tidak dapat dibaca.');
        }

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $this->fail('CSV harus menggunakan encoding UTF-8.');
        }

        $handle = $file->openFile('r');
        $header = $handle->fgetcsv();
        $header = is_array($header)
            ? array_map(static fn ($value): string => trim((string) $value, "\xEF\xBB\xBF \t"), $header)
            : null;

        if ($header !== self::HEADERS) {
            $this->fail('Header CSV tidak sesuai template Master Siswa.');
        }

        $rows = [];
        $line = 1;
        $dataRow = 0;

        while (($values = $handle->fgetcsv()) !== false) {
            $line++;
            $dataRow++;

            if ($values === [null]) {
                if ($handle->eof()) {
                    break;
                }

                $this->fail("Baris {$line}: baris CSV tidak boleh kosong.", "rows.{$dataRow}");
            }

            if (count($values) === 1 && trim((string) ($values[0] ?? '')) === '') {
                $this->fail("Baris {$line}: baris CSV tidak boleh kosong.");
            }

            if (count($values) !== count(self::HEADERS)) {
                $this->fail("Baris {$line}: jumlah kolom tidak sesuai template.", "rows.{$dataRow}");
            }

            $row = array_combine(self::HEADERS, array_map(static fn ($value): ?string => self::nullableValue($value), $values));
            $this->validateRow($row, $dataRow);
            $rows[] = $row;

            if (count($rows) > 300) {
                $this->fail('CSV maksimal berisi 300 baris.');
            }
        }

        if ($rows === []) {
            $this->fail('CSV harus berisi minimal satu baris.');
        }

        $this->validateUniqueIdentities($rows);

        return $rows;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        $dataRows = app(XlsxTemplateReader::class)->read($file, self::HEADERS, 'Master Siswa');
        foreach ($dataRows as $rowNumber => $row) {
            $this->validateRow($row, $rowNumber);
        }
        $dataRows = array_values($dataRows);
        $this->validateUniqueIdentities($dataRows);

        return $dataRows;
    }

    public function templateXlsx(): string
    {
        $rows = [
            self::HEADERS,
            ['Nomor Induk Siswa', 'Nomor Induk Siswa Nasional', 'Tahun angkatan', 'Nama lengkap', 'Jenis kelamin (L/P)', 'Tempat lahir', 'Tanggal lahir (YYYY-MM-DD)', 'Alamat', 'Telepon siswa', 'Nama ibu', 'Nama ayah', 'Pekerjaan ibu', 'Pekerjaan ayah', 'Telepon orangtua', 'Status (active/inactive)'],
            ['NIS-001', '1234567890', '2024', 'Siti Aminah', 'P', 'Denpasar', '2012-01-02', 'Jl. Melati', '081234567890', 'Ibu Siti', 'Bapak Amin', 'Guru', 'Wiraswasta', '081298765432', 'active'],
        ];

        $columnWidths = [];

        foreach ($rows[1] as $index => $label) {
            $columnWidths[$index + 1] = (float) mb_strwidth($label) + 4.0;
        }

        return app(XlsxWriter::class)->write($rows, 'Master Siswa', [
            'columnWidths' => $columnWidths,
            'headerRow' => 2,
        ]);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function validateRow(array $row, int $line): void
    {
        if ($row['nis'] === null && $row['nisn'] === null) {
            $this->fail("Baris {$line}: NIS atau NISN wajib diisi.", "rows.{$line}.nis");
        }

        if ($row['nis'] !== null && mb_strlen($row['nis']) > 255) {
            $this->fail("Baris {$line}: nis maksimal 255 karakter.", "rows.{$line}.nis");
        }

        if ($row['nisn'] !== null && mb_strlen($row['nisn']) > 20) {
            $this->fail("Baris {$line}: nisn maksimal 20 karakter.", "rows.{$line}.nisn");
        }

        if ($row['tahun_angkatan'] !== null && (filter_var($row['tahun_angkatan'], FILTER_VALIDATE_INT) === false || (int) $row['tahun_angkatan'] < 1900 || (int) $row['tahun_angkatan'] > now()->year)) {
            $this->fail("Baris {$line}: tahun_angkatan harus berupa tahun yang valid.", "rows.{$line}.tahun_angkatan");
        }

        if ($row['full_name'] === null || mb_strlen($row['full_name']) > 255) {
            $this->fail("Baris {$line}: full_name wajib diisi dan maksimal 255 karakter.", "rows.{$line}.full_name");
        }

        if ($row['gender'] !== null && ! in_array($row['gender'], ['L', 'P'], true)) {
            $this->fail("Baris {$line}: gender harus L atau P.", "rows.{$line}.gender");
        }

        if ($row['birth_place'] !== null && mb_strlen($row['birth_place']) > 255) {
            $this->fail("Baris {$line}: birth_place maksimal 255 karakter.", "rows.{$line}.birth_place");
        }

        if ($row['birth_date'] !== null) {
            $date = date_create_from_format('!Y-m-d', $row['birth_date']);

            if ($date === false || $date->format('Y-m-d') !== $row['birth_date'] || $date > now()) {
                $this->fail("Baris {$line}: birth_date harus berformat YYYY-MM-DD dan tidak boleh di masa depan.", "rows.{$line}.birth_date");
            }
        }

        foreach (['phone', 'parent_phone'] as $field) {
            if ($row[$field] !== null && mb_strlen($row[$field]) > 30) {
                $this->fail("Baris {$line}: {$field} maksimal 30 karakter.", "rows.{$line}.{$field}");
            }
        }

        foreach (['mother_name', 'father_name', 'mother_occupation', 'father_occupation'] as $field) {
            if ($row[$field] !== null && mb_strlen($row[$field]) > 255) {
                $this->fail("Baris {$line}: {$field} maksimal 255 karakter.", "rows.{$line}.{$field}");
            }
        }

        if ($row['status'] === null || ! in_array($row['status'], ['active', 'inactive'], true)) {
            $this->fail("Baris {$line}: status harus active atau inactive.", "rows.{$line}.status");
        }
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     */
    private function validateUniqueIdentities(array $rows): void
    {
        foreach (['nis', 'nisn'] as $field) {
            $seen = [];

            foreach ($rows as $index => $row) {
                $value = $row[$field];

                if ($value !== null && isset($seen[$value])) {
                    $this->fail('Baris '.($index + 2).": {$field} duplikat dengan baris {$seen[$value]}.", 'rows.'.($index + 2).'.'.$field);
                }

                if ($value !== null) {
                    $seen[$value] = $index + 2;
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @param  Collection<string, Student>  $students
     */
    private function validateExistingCollisions(array $rows, Collection $students): void
    {
        foreach ($rows as $index => $row) {
            $byNis = $row['nis'] === null ? null : $students->get('nis:'.$row['nis']);
            $byNisn = $row['nisn'] === null ? null : $students->get('nisn:'.$row['nisn']);

            if ($byNis !== null && $byNisn !== null && $byNis->isNot($byNisn)) {
                $this->fail('Baris '.($index + 2).': NIS dan NISN merujuk ke data Siswa yang berbeda.', 'file');
            }
        }
    }

    /**
     * @param  array<int, array<string, string|null>>  $rows
     * @return Collection<string, Student>
     */
    private function loadExistingStudents(array $rows): Collection
    {
        $nis = array_values(array_filter(array_column($rows, 'nis')));
        $nisn = array_values(array_filter(array_column($rows, 'nisn')));

        if ($nis === [] && $nisn === []) {
            return collect();
        }

        $students = collect();

        Student::query()
            ->where(function ($query) use ($nis, $nisn): void {
                $query->whereIn('nis', $nis)->orWhereIn('nisn', $nisn);
            })
            ->get()
            ->each(fn (Student $student): bool => $this->indexStudent($students, $student));

        return $students;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string|int|null>
     */
    private function attributes(array $row): array
    {
        return [
            'nis' => $row['nis'],
            'nisn' => $row['nisn'],
            'tahun_angkatan' => $row['tahun_angkatan'] === null ? null : (int) $row['tahun_angkatan'],
            'full_name' => $row['full_name'],
            'gender' => $row['gender'],
            'birth_place' => $row['birth_place'],
            'birth_date' => $row['birth_date'],
            'address' => $row['address'],
            'phone' => $row['phone'],
            'mother_name' => $row['mother_name'],
            'father_name' => $row['father_name'],
            'mother_occupation' => $row['mother_occupation'],
            'father_occupation' => $row['father_occupation'],
            'parent_phone' => $row['parent_phone'],
            'status' => $row['status'],
        ];
    }

    /**
     * @param  Collection<string, Student>  $students
     * @param  array<string, string|null>  $row
     */
    private function findExistingStudent(Collection $students, array $row): ?Student
    {
        $byNis = $row['nis'] === null ? null : $students->get('nis:'.$row['nis']);
        $byNisn = $row['nisn'] === null ? null : $students->get('nisn:'.$row['nisn']);

        return $byNis ?? $byNisn;
    }

    /**
     * @param  Collection<string, Student>  $students
     */
    private function indexStudent(Collection $students, Student $student): bool
    {
        if ($student->nis !== null) {
            $students->put('nis:'.$student->nis, $student);
        }

        if ($student->nisn !== null) {
            $students->put('nisn:'.$student->nisn, $student);
        }

        return true;
    }

    private static function nullableValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function fail(string $message, string $key = 'file'): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
