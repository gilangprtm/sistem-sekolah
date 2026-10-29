<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\User;
use App\Services\XlsxWriter;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassStudentImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['curriculum.view', 'curriculum.update']);

        return $user;
    }

    /** @param list<list<string|int>> $rows */
    private function upload(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('kelas.xlsx', app(XlsxWriter::class)->write([
            ['nisn', 'full_name'], ['NISN', 'Nama Siswa'], ['0012345678', 'Siti Aminah'], ...$rows,
        ], 'Kelas'));
    }

    /** @return array{academic_year_id: int, rombel_id: int, file: UploadedFile} */
    private function payload(UploadedFile $file): array
    {
        return [
            'academic_year_id' => AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active'])->id,
            'rombel_id' => Rombel::factory()->create(['status' => 'active'])->id,
            'file' => $file,
        ];
    }

    public function test_import_matches_existing_students_without_writing_until_class_is_saved(): void
    {
        $editor = $this->editor();
        $student = Student::factory()->create(['nis' => 'NIS-1', 'nisn' => '0012345678', 'full_name' => 'Siti Aminah']);
        $payload = $this->payload($this->upload([['0012345678', 'Nama yang berbeda tidak membatalkan NISN']]));
        $before = $student->fresh()->getAttributes();

        $response = $this->actingAs($editor)->postJson(route('management-class.students.import'), $payload)
            ->assertOk()->assertExactJson(['students' => [[
                'id' => $student->id, 'nis' => 'NIS-1', 'nisn' => '0012345678', 'full_name' => 'Siti Aminah',
            ]]]);
        $this->assertSame($before, $student->fresh()->getAttributes());
        $this->assertDatabaseCount('m_students', 1);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 0);
        $this->assertDatabaseCount('tr_curriculum_year_classes', 0);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $this->post(route('management-class.classes.update'), [
            'academic_year_id' => $payload['academic_year_id'], 'rombel_id' => $payload['rombel_id'],
            'teacher_id' => $teacher->id, 'student_ids' => array_column($response->json('students'), 'id'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_curriculum_student_placements', [
            'academic_year_id' => $payload['academic_year_id'], 'rombel_id' => $payload['rombel_id'],
            'student_id' => $student->id, 'status' => 'active',
        ]);
    }

    public function test_unknown_and_inactive_students_reject_entire_file(): void
    {
        Student::factory()->create(['nisn' => '001', 'full_name' => 'Valid Student']);
        Student::factory()->create(['nisn' => '002', 'full_name' => 'Real Name']);
        Student::factory()->create(['nisn' => '003', 'full_name' => 'Inactive', 'status' => 'inactive']);
        $payload = $this->payload($this->upload([
            ['001', 'Valid Student'], ['002', 'Wrong Name'], ['003', 'Inactive'], ['004', 'Valid Student'],
        ]));
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Baris 6: Inactive berstatus tidak aktif.')
            ->assertJsonPath('errors.file.1', 'Baris 7: NISN 004 tidak ditemukan di Master Siswa.')
            ->assertJsonMissingPath('students');
        $this->assertDatabaseCount('m_students', 3);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 0);
    }

    public function test_placement_conflict_is_year_scoped_and_current_class_is_allowed(): void
    {
        $student = Student::factory()->create(['nisn' => '001', 'full_name' => 'Student']);
        $payload = $this->payload($this->upload([['001', 'Student']]));
        $placement = StudentPlacement::query()->create([
            'student_id' => $student->id, 'academic_year_id' => $payload['academic_year_id'],
            'rombel_id' => $payload['rombel_id'], 'status' => 'active', 'started_at' => now(),
        ]);
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $payload)
            ->assertOk()->assertJsonCount(1, 'students');
        $otherClass = Rombel::factory()->create(['code' => 'OTHER-CLASS']);
        $placement->update(['rombel_id' => $otherClass->id]);
        $this->postJson(route('management-class.students.import'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $placement->update(['status' => 'ended']);
        $this->postJson(route('management-class.students.import'), $payload)->assertOk();
        $placement->update(['status' => 'active', 'academic_year_id' => AcademicYear::query()->create(['year' => '2027/2028', 'status' => 'active'])->id]);
        $this->postJson(route('management-class.students.import'), $payload)->assertOk();
        $this->assertDatabaseCount('tr_curriculum_student_placements', 1);
    }

    public function test_save_revalidates_conflicts_added_after_import_preview(): void
    {
        $student = Student::factory()->create(['nisn' => '001', 'full_name' => 'Student']);
        $payload = $this->payload($this->upload([['001', 'Student']]));
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $payload)->assertOk();
        $otherClass = Rombel::factory()->create(['code' => 'OTHER-CLASS']);
        StudentPlacement::query()->create([
            'student_id' => $student->id, 'academic_year_id' => $payload['academic_year_id'],
            'rombel_id' => $otherClass->id, 'status' => 'active', 'started_at' => now(),
        ]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $this->post(route('management-class.classes.update'), [
            'academic_year_id' => $payload['academic_year_id'], 'rombel_id' => $payload['rombel_id'],
            'teacher_id' => $teacher->id, 'student_ids' => [$student->id],
        ])->assertSessionHasErrors('student_ids');
        $this->assertDatabaseCount('tr_curriculum_student_placements', 1);
        $this->assertDatabaseCount('tr_curriculum_year_classes', 0);
    }

    /** @return iterable<string, array{list<list<string|int>>, string}> */
    public static function invalidRows(): iterable
    {
        yield 'empty file' => [[], 'file'];
        yield 'duplicate' => [[['001', 'Student'], ['001', 'Student']], 'file'];
        yield 'empty identity' => [[['', 'Student']], 'rows.4.nisn'];
        yield 'non numeric' => [[['1E+9', 'Student']], 'rows.4.nisn'];
        yield 'overlong identity' => [[[str_repeat('1', 21), 'Student']], 'rows.4.nisn'];
        yield 'empty row' => [[['', '']], 'rows.4'];
        yield 'extra column' => [[['001', 'Student', 'extra']], 'rows.4'];
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_rows_return_422_without_any_placement(array $rows, string $key): void
    {
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $this->payload($this->upload($rows)))
            ->assertUnprocessable()->assertJsonValidationErrors($key);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 0);
    }

    public function test_names_are_not_required_or_used_to_match_students(): void
    {
        $first = Student::factory()->create(['nisn' => '001', 'full_name' => 'Nama Sama']);
        $second = Student::factory()->create(['nisn' => '002', 'full_name' => 'Nama Sama']);
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $this->payload($this->upload([
            ['002', ''], ['001', 'Nama berbeda'],
        ])))->assertOk()->assertExactJson(['students' => [
            ['id' => $second->id, 'nis' => null, 'nisn' => '002', 'full_name' => 'Nama Sama'],
            ['id' => $first->id, 'nis' => null, 'nisn' => '001', 'full_name' => 'Nama Sama'],
        ]]);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 0);
    }

    public function test_import_accepts_300_students_and_rejects_301(): void
    {
        $rows = [];
        for ($index = 1; $index <= 300; $index++) {
            $nisn = sprintf('%010d', $index);
            Student::factory()->create(['nisn' => $nisn, 'full_name' => "Student {$index}"]);
            $rows[] = [$nisn, "Student {$index}"];
        }
        $payload = $this->payload($this->upload($rows));
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $payload)->assertOk()->assertJsonCount(300, 'students');
        $rows[] = ['0000000301', 'Student 301'];
        $payload['file'] = $this->upload($rows);
        $this->postJson(route('management-class.students.import'), $payload)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('tr_curriculum_student_placements', 0);
        $this->assertDatabaseCount('m_students', 300);
    }

    public function test_import_and_template_require_view_and_update_but_not_student_crud(): void
    {
        $import = route('management-class.students.import');
        $template = route('management-class.students.import.template');
        $this->get($template)->assertRedirect(route('login'));
        $this->postJson($import)->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->get($template)->assertForbidden();
        $this->postJson($import)->assertForbidden();
        $user->givePermissionTo('curriculum.view');
        $this->get($template)->assertForbidden();
        $this->postJson($import)->assertForbidden();
        $user->givePermissionTo('curriculum.update');
        $this->assertFalse($user->can('student.create'));
        $this->get($template)->assertOk()->assertDownload('template-import-siswa-kelas.xlsx');
        $this->postJson($import)->assertUnprocessable()->assertJsonValidationErrors(['file', 'academic_year_id', 'rombel_id']);
    }

    public function test_invalid_context_and_uploads_are_rejected(): void
    {
        $payload = $this->payload(UploadedFile::fake()->createWithContent('kelas.xlsx', 'invalid zip'));
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $payload['file'] = UploadedFile::fake()->createWithContent('kelas.csv', 'nisn,full_name');
        $this->postJson(route('management-class.students.import'), $payload)->assertUnprocessable()->assertJsonValidationErrors('file');
        $payload['file'] = UploadedFile::fake()->create('kelas.xlsx', 1025);
        $this->postJson(route('management-class.students.import'), $payload)->assertUnprocessable()->assertJsonValidationErrors('file');
        $payload['file'] = $this->upload([['001', 'Student']]);
        AcademicYear::query()->whereKey($payload['academic_year_id'])->update(['status' => 'inactive']);
        Rombel::query()->whereKey($payload['rombel_id'])->update(['status' => 'inactive']);
        $this->postJson(route('management-class.students.import'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['academic_year_id', 'rombel_id']);
        $payload['academic_year_id'] = 99999;
        $payload['rombel_id'] = 99999;
        $this->postJson(route('management-class.students.import'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['academic_year_id', 'rombel_id']);
    }

    public function test_manage_exposes_nisn_in_lookup_search_and_selected_students(): void
    {
        $student = Student::factory()->create(['nis' => 'LOCAL-1', 'nisn' => '0012345678', 'full_name' => 'Student']);
        $payload = $this->payload($this->upload([['0012345678', 'Student']]));
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $this->actingAs($this->editor())->post(route('management-class.classes.update'), [
            'academic_year_id' => $payload['academic_year_id'], 'rombel_id' => $payload['rombel_id'],
            'teacher_id' => $teacher->id, 'student_ids' => [$student->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('management-class.manage', [
            'academic_year_id' => $payload['academic_year_id'], 'rombel_id' => $payload['rombel_id'], 'lookup_search' => '0012345678',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('studentLookup.total', 1)->where('studentLookup.data.0.nisn', '0012345678')
            ->where('selectedStudents.0.nisn', '0012345678')->where('selectedStudents.0.nis', 'LOCAL-1'));
    }

    public function test_excel_shared_strings_and_numeric_identity_cells_are_supported(): void
    {
        $student = Student::factory()->create(['nisn' => '1234567890', 'full_name' => 'Siti Aminah']);
        $file = $this->upload([['1234567890', 'Siti Aminah']]);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file->getPathname()));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($xml);
        $xml = str_replace('<c r="A4" t="inlineStr"><is><t xml:space="preserve">1234567890</t></is></c>', '<c r="A4"><v>1234567890</v></c>', $xml);
        $xml = str_replace('<c r="B4" t="inlineStr"><is><t xml:space="preserve">Siti Aminah</t></is></c>', '<c r="B4" t="s"><v>0</v></c>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><r><t>Siti </t></r><r><t>Aminah</t></r></si></sst>');
        $zip->close();
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $this->payload($file))
            ->assertOk()->assertJsonPath('students.0.id', $student->id);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidWorkbooks(): iterable
    {
        yield 'wrong header' => ['<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>nis</t></is></c></row><row r="2"/><row r="3"/></sheetData></worksheet>'];
        yield 'missing metadata' => ['<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>'];
        yield 'malformed xml' => ['<worksheet>'];
        yield 'external entities' => ['<!DOCTYPE worksheet [<!ENTITY external SYSTEM "file:///nonexistent">]><worksheet>&external;</worksheet>'];
        yield 'oversized decompressed sheet' => [str_repeat(' ', 16 * 1024 * 1024 + 1)];
    }

    #[DataProvider('invalidWorkbooks')]
    public function test_invalid_workbook_content_returns_422(string $xml): void
    {
        $file = $this->upload([['001', 'Student']]);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file->getPathname()));
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $this->actingAs($this->editor())->postJson(route('management-class.students.import'), $this->payload($file))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('m_students', 0);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 0);
    }

    public function test_downloaded_template_has_only_identity_columns_and_is_accepted_after_data_is_added(): void
    {
        $student = Student::factory()->create(['nisn' => '0012345678', 'full_name' => 'Siti Aminah']);
        $response = $this->actingAs($this->editor())->get(route('management-class.students.import.template'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'class-template-');
        try {
            file_put_contents($path, $response->getContent());
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($xml);
            $this->assertStringContainsString('NISN', $xml);
            $this->assertStringContainsString('Nama Siswa', $xml);
            $this->assertStringNotContainsString('r="C', $xml);
            $xml = str_replace('</sheetData>', '<row r="4"><c r="A4" t="inlineStr"><is><t>0012345678</t></is></c><c r="B4" t="inlineStr"><is><t>Siti Aminah</t></is></c></row></sheetData>', $xml);
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();
            $this->postJson(route('management-class.students.import'), $this->payload(UploadedFile::fake()->createWithContent('kelas.xlsx', file_get_contents($path))))
                ->assertOk()->assertJsonPath('students.0.id', $student->id);
        } finally {
            unlink($path);
        }
    }
}
