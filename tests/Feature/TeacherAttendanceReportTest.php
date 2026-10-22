<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class TeacherAttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_download_requires_authentication_permission_role_and_teacher_profile(): void
    {
        $url = route('teacher.attendance.report.download');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $staff = User::factory()->create();
        $staff->assignRole('Staff');
        $staff->givePermissionTo('teacher.homeroom.view');
        $this->actingAs($staff)->getJson($url)->assertForbidden();
        $guru = User::factory()->create();
        $guru->assignRole('Guru');
        $this->actingAs($guru)->getJson($url)->assertForbidden();
    }

    public function test_download_requires_an_active_year_and_own_active_homeroom(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $url = route('teacher.attendance.report.download', ['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->actingAs($user)->getJson($url)->assertNotFound();
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $assignment = HomeroomAssignment::query()->create(['academic_year_id' => $year->id, 'rombel_id' => Rombel::factory()->create()->id,
            'teacher_id' => $teacher->id, 'status' => 'ended']);
        $this->getJson($url)->assertNotFound();
        $otherAssignment = HomeroomAssignment::query()->create(['academic_year_id' => $year->id, 'rombel_id' => Rombel::factory()->create()->id,
            'teacher_id' => Teacher::factory()->create()->id, 'status' => 'active']);
        $this->getJson($url.'&teacher_id='.$otherAssignment->teacher_id.'&rombel_id='.$otherAssignment->rombel_id)->assertNotFound();
        $assignment->update(['status' => 'active']);
        $year->update(['status' => 'inactive']);
        $this->getJson($url)->assertNotFound();
    }

    public function test_excel_totals_daily_attendance_only_for_current_active_roster_year_and_inclusive_dates(): void
    {
        [$user, $year, $rombel] = $this->homeroom();
        $budi = $this->student($year, $rombel, 'Budi');
        $ani = $this->student($year, $rombel, 'Ani');
        $cici = $this->student($year, $rombel, 'Cici');
        $this->student($year, $rombel, 'Ended placement', 'ended');
        $inactive = $this->student($year, $rombel, 'Inactive student');
        $inactive->update(['status' => 'inactive']);
        $otherClass = Rombel::factory()->create();
        $other = $this->student($year, $otherClass, 'Other class');
        $oldYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $this->student($oldYear, $rombel, 'Other year');
        foreach (['2026-10-01' => 'H', '2026-10-02' => 'H', '2026-10-03' => 'I', '2026-10-04' => 'S', '2026-10-05' => 'D', '2026-10-06' => 'A'] as $date => $status) {
            StudentAttendance::factory()->create(['academic_year_id' => $year->id, 'student_id' => $ani->id,
                'attendance_date' => $date, 'status' => $status, 'updated_by' => $user->id]);
        }
        foreach (['2026-09-30', '2026-10-07'] as $date) {
            StudentAttendance::factory()->create(['academic_year_id' => $year->id, 'student_id' => $ani->id,
                'attendance_date' => $date, 'status' => 'H', 'updated_by' => $user->id]);
        }
        StudentAttendance::factory()->create(['academic_year_id' => $oldYear->id, 'student_id' => $ani->id,
            'attendance_date' => '2026-10-01', 'status' => 'H', 'updated_by' => $user->id]);
        StudentAttendance::factory()->create(['academic_year_id' => $year->id, 'student_id' => $budi->id,
            'attendance_date' => '2026-10-06', 'status' => 'S', 'updated_by' => $user->id]);
        StudentAttendance::factory()->create(['academic_year_id' => $year->id, 'student_id' => $other->id,
            'attendance_date' => '2026-10-01', 'status' => 'A', 'updated_by' => $user->id]);

        $response = $this->actingAs($user)->get(route('teacher.attendance.report.download', [
            'from' => '2026-10-01', 'to' => '2026-10-06', 'rombel_id' => $otherClass->id,
            'academic_year_id' => $oldYear->id, 'student_id' => $other->id, 'per_page' => 1,
        ]));

        $response->assertDownload('kehadiran-siswa-2026-10-01-2026-10-06.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $sheet = $this->worksheet($response->streamedContent());
        $this->assertCount(9, $sheet->sheetData->row);
        $this->assertSame(['Laporan Kehadiran Siswa'], $this->rowValues($sheet->sheetData->row[1]));
        $this->assertSame(['Nomor', 'Nama Siswa', 'Keterangan'], $this->rowValues($sheet->sheetData->row[5]));
        $this->assertSame(['1', 'Ani', '1 Ijin, 1 Sakit, 1 Dispensasi, 1 Alpa'], $this->rowValues($sheet->sheetData->row[6]));
        $this->assertSame(['2', 'Budi', '1 Sakit'], $this->rowValues($sheet->sheetData->row[7]));
        $this->assertSame(['3', 'Cici', ''], $this->rowValues($sheet->sheetData->row[8]));
        $this->assertSame(150.0, (float) $sheet->sheetData->row[0]['ht']);
        $this->assertDatabaseCount('tr_student_attendances', 11);
    }

    public function test_student_excel_template_keeps_no_embedded_school_letterhead(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)->get(route('students.import.template'));

        $archive = new ZipArchive;
        $path = tempnam(sys_get_temp_dir(), 'template-no-letterhead-');
        $this->assertIsString($path);
        file_put_contents($path, $response->streamedContent());
        try {
            $this->assertTrue($archive->open($path));
            $this->assertFalse($archive->locateName('xl/media/image1.png') !== false);
            $this->assertFalse($archive->locateName('xl/drawings/drawing1.xml') !== false);
            $archive->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_download_validates_dates_and_handles_empty_roster_and_exactly_366_days(): void
    {
        [$user] = $this->homeroom();
        $this->actingAs($user);
        $this->getJson(route('teacher.attendance.report.download'))->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('teacher.attendance.report.download', ['from' => 'invalid', 'to' => 'invalid']))->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('teacher.attendance.report.download', ['from' => '2026-10-20', 'to' => '2026-10-01']))->assertJsonValidationErrors('to');
        $this->getJson(route('teacher.attendance.report.download', ['from' => '2024-01-01', 'to' => '2025-01-01']))
            ->assertUnprocessable()->assertJsonPath('message', 'Rentang unduh maksimal 366 hari.');
        $response = $this->get(route('teacher.attendance.report.download', ['from' => '2024-01-01', 'to' => '2024-12-31']));
        $response->assertDownload();
        $sheet = $this->worksheet($response->streamedContent());
        $this->assertCount(6, $sheet->sheetData->row);
    }

    public function test_download_accepts_1000_students_and_rejects_larger_rosters(): void
    {
        [$user, $year, $rombel] = $this->homeroom();
        $students = Student::factory()->count(1000)->create(['status' => 'active']);
        StudentPlacement::query()->insert($students->map(fn (Student $student): array => [
            'academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'student_id' => $student->id, 'status' => 'active',
        ])->all());
        $url = route('teacher.attendance.report.download', ['from' => '2026-10-01', 'to' => '2026-10-01']);
        $this->actingAs($user)->get($url)->assertDownload();

        $this->student($year, $rombel, 'Extra Student');

        $this->getJson($url)->assertUnprocessable()->assertJsonPath('message', 'Terlalu banyak siswa dalam kelas. Maksimal 1.000 siswa.');
    }

    public function test_excel_preserves_formula_looking_names_as_literal_text(): void
    {
        [$user, $year, $rombel] = $this->homeroom();
        $this->student($year, $rombel, '=1+1 <&>');

        $response = $this->actingAs($user)->get(route('teacher.attendance.report.download', ['from' => '2026-10-01', 'to' => '2026-10-01']));

        $sheet = $this->worksheet($response->streamedContent());
        $this->assertSame('inlineStr', (string) $sheet->sheetData->row[6]->c[1]['t']);
        $this->assertSame(['1', '=1+1 <&>', ''], $this->rowValues($sheet->sheetData->row[6]));
    }

    public function test_excel_leaves_keterangan_empty_when_student_has_only_present_records(): void
    {
        [$user, $year, $rombel] = $this->homeroom();
        $student = $this->student($year, $rombel, 'Ani');
        StudentAttendance::factory()->create([
            'academic_year_id' => $year->id, 'student_id' => $student->id,
            'attendance_date' => '2026-10-01', 'status' => 'H', 'updated_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('teacher.attendance.report.download', ['from' => '2026-10-01', 'to' => '2026-10-01']));

        $sheet = $this->worksheet($response->streamedContent());
        $this->assertSame(['1', 'Ani', ''], $this->rowValues($sheet->sheetData->row[6]));
        $this->assertDatabaseHas('tr_student_attendances', ['student_id' => $student->id, 'status' => 'H']);
    }

    /** @return array{User, AcademicYear, Rombel} */
    private function homeroom(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $rombel = Rombel::factory()->create();
        HomeroomAssignment::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id, 'status' => 'active']);

        return [$user, $year, $rombel];
    }

    private function student(AcademicYear $year, Rombel $rombel, string $name, string $placementStatus = 'active'): Student
    {
        $student = Student::factory()->create(['full_name' => $name, 'status' => 'active']);
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id,
            'student_id' => $student->id, 'status' => $placementStatus]);

        return $student;
    }

    private function worksheet(string $content): SimpleXMLElement
    {
        $path = tempnam(sys_get_temp_dir(), 'attendance-xlsx-test-');
        $this->assertIsString($path);
        try {
            file_put_contents($path, $content);
            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path));
            foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/worksheets/sheet1.xml', 'xl/styles.xml'] as $part) {
                $xml = $archive->getFromName($part);
                $this->assertIsString($xml);
                $this->assertNotFalse(simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET));
            }
            $sheet = simplexml_load_string($archive->getFromName('xl/worksheets/sheet1.xml'), SimpleXMLElement::class, LIBXML_NONET);
            $this->assertSame(file_get_contents(public_path('images/kop-sekolah.png')), $archive->getFromName('xl/media/image1.png'));
            $drawingXml = $archive->getFromName('xl/drawings/drawing1.xml');
            $this->assertIsString($drawingXml);
            $drawing = simplexml_load_string($drawingXml, SimpleXMLElement::class, LIBXML_NONET);
            $this->assertInstanceOf(SimpleXMLElement::class, $drawing);
            $drawing->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $this->assertSame('0', (string) $drawing->xpath('//xdr:from/xdr:col')[0]);
            $this->assertSame((string) (91 * 9525), (string) $drawing->xpath('//xdr:from/xdr:colOff')[0]);
            $this->assertSame((string) (564 * 9525), (string) $drawing->xpath('//xdr:oneCellAnchor/xdr:ext/@cx')[0]);
            $this->assertSame((string) (183 * 9525), (string) $drawing->xpath('//xdr:oneCellAnchor/xdr:ext/@cy')[0]);
            $this->assertIsString($archive->getFromName('xl/worksheets/_rels/sheet1.xml.rels'));
            $styles = simplexml_load_string($archive->getFromName('xl/styles.xml'), SimpleXMLElement::class, LIBXML_NONET);
            $archive->close();
            $this->assertInstanceOf(SimpleXMLElement::class, $sheet);
            $this->assertInstanceOf(SimpleXMLElement::class, $styles);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame([], $sheet->xpath('//s:f'));
            $this->assertCount(3, $sheet->cols->col);
            $this->assertSame('A2:C2', (string) $sheet->mergeCells->mergeCell[0]['ref']);
            foreach ($sheet->sheetData->row as $row) {
                if ((int) $row['r'] < 6) {
                    continue;
                }
                $this->assertCount(3, $row->c);
                foreach ($row->c as $cell) {
                    $this->assertSame('1', (string) $styles->cellXfs->xf[(int) $cell['s']]['borderId']);
                }
            }

            return $sheet;
        } finally {
            unlink($path);
        }
    }

    /** @return list<string> */
    private function rowValues(SimpleXMLElement $row): array
    {
        $values = [];
        foreach ($row->c as $cell) {
            $values[] = (string) $cell['t'] === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v;
        }

        return $values;
    }
}
