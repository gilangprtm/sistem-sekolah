<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAgenda;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class TeacherAgendaReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_print_requires_authentication_permission_and_teacher_profile(): void
    {
        $url = route('teacher.agenda.report', ['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_report_uses_owned_snapshots_and_current_daily_attendance_without_counting_missing_as_alpa(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'inactive']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'inactive']);
        $students = Student::factory()->count(3)->create();
        $roster = $students->map(fn (Student $student): array => ['student_id' => $student->id, 'full_name' => $student->full_name, 'nis' => $student->nis])->all();
        $agenda = $this->agenda($teacher, $period, $roster);
        $otherAgenda = $this->agenda(Teacher::factory()->create(), $period, $roster);
        $outside = $this->agenda($teacher, $period, $roster);
        $outside->update(['agenda_date' => '2026-11-01']);
        StudentAttendance::query()->create(['academic_year_id' => $year->id, 'student_id' => $students[0]->id, 'attendance_date' => '2026-10-20', 'status' => 'H', 'version' => 1, 'updated_by' => $user->id]);
        StudentAttendance::query()->create(['academic_year_id' => $year->id, 'student_id' => $students[1]->id, 'attendance_date' => '2026-10-20', 'status' => 'S', 'version' => 1, 'updated_by' => $user->id]);
        StudentAttendance::query()->create(['academic_year_id' => $year->id, 'student_id' => $students[2]->id, 'attendance_date' => '2026-10-21', 'status' => 'A', 'version' => 1, 'updated_by' => $user->id]);

        $this->actingAs($user)->get(route('teacher.agenda.report', ['from' => '2026-10-20', 'to' => '2026-10-20']))->assertInertia(fn ($page) => $page
            ->component('teacher/documents/agenda-report')->has('agendas', 1)->where('agendas.0.id', $agenda->id)
            ->where('agendas.0.snapshot.subject_name', 'Snapshot Matematika')
            ->where('agendas.0.material', '<script>materi</script>')
            ->where('agendas.0.counts', ['H' => 1, 'I' => 0, 'S' => 1, 'D' => 0, 'A' => 0])
            ->where('agendas.0.unrecorded', 1));
        $response = $this->get(route('teacher.agenda.download', [
            'from' => '2026-10-20', 'to' => '2026-10-20', 'teacher_id' => $otherAgenda->teacher_id,
        ]));
        $response->assertDownload('agenda-2026-10-20-2026-10-20.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $rows = $this->worksheetRows($response->streamedContent());
        $this->assertCount(6, $rows);
        $this->assertSame(['Laporan Agenda Guru'], $rows[1]);
        $this->assertSame(['Tanggal', 'Tahun Ajaran', 'Semester', 'Kelas', 'Mata Pelajaran', 'Jam Pelajaran', 'Total JP', 'Materi', 'Keterangan'], $rows[4]);
        $this->assertSame('Snapshot Matematika', $rows[5][4]);
        $this->assertSame('07:30–09:30', $rows[5][5]);
        $this->assertSame('3', $rows[5][6]);
        $this->assertSame('<script>materi</script>', $rows[5][7]);
        $this->assertCount(9, $rows[5]);
        $this->assertSame('1 Hadir, 1 Sakit', $rows[5][8]);
        $this->assertSame($roster, $agenda->fresh()->attendance);
        $this->assertDatabaseCount('tr_student_attendances', 3);
    }

    public function test_excel_combines_only_nonzero_attendance_counts_in_keterangan(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'inactive']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'inactive']);
        $students = Student::factory()->count(35)->create();
        $roster = $students->map(fn (Student $student): array => ['student_id' => $student->id, 'full_name' => $student->full_name, 'nis' => $student->nis])->all();
        $this->agenda($teacher, $period, $roster);
        foreach ($students as $index => $student) {
            StudentAttendance::query()->create(['academic_year_id' => $year->id, 'student_id' => $student->id, 'attendance_date' => '2026-10-20',
                'status' => $index < 34 ? 'H' : 'S', 'updated_by' => $user->id]);
        }
        $url = route('teacher.agenda.download', ['from' => '2026-10-20', 'to' => '2026-10-20']);

        $response = $this->actingAs($user)->get($url);

        $rows = $this->worksheetRows($response->streamedContent());
        $this->assertSame('34 Hadir, 1 Sakit', $rows[5][8]);

        foreach (['I', 'D', 'A'] as $index => $status) {
            StudentAttendance::query()->where('student_id', $students[$index]->id)->update(['status' => $status]);
        }
        $rows = $this->worksheetRows($this->get($url)->streamedContent());
        $this->assertSame('31 Hadir, 1 Ijin, 1 Sakit, 1 Dispensasi, 1 Alpa', $rows[5][8]);

    }

    public function test_report_accepts_exactly_366_days_and_rejects_a_longer_range(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->get(route('teacher.agenda.report', ['from' => '2024-01-01', 'to' => '2024-12-31']))->assertInertia(fn ($page) => $page->has('agendas', 0));
        $this->get(route('teacher.agenda.report', ['from' => '2024-01-01', 'to' => '2025-01-01']))->assertUnprocessable();
    }

    public function test_report_validates_dates_range_and_empty_history(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->get(route('teacher.agenda.report', ['from' => 'invalid', 'to' => 'invalid']))->assertSessionHasErrors(['from', 'to']);
        $this->get(route('teacher.agenda.report', ['from' => '2026-10-20', 'to' => '2026-10-01']))->assertSessionHasErrors('to');
        $this->get(route('teacher.agenda.report', ['from' => '2026-01-01', 'to' => '2028-01-01']))->assertUnprocessable();
        $this->get(route('teacher.agenda.report', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertInertia(fn ($page) => $page->has('agendas', 0));
    }

    public function test_download_requires_authentication_permission_and_teacher_profile(): void
    {
        $url = route('teacher.agenda.download', ['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)->getJson($url)->assertForbidden();
    }

    public function test_download_validates_dates_and_supports_empty_history_and_366_days(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);
        $this->getJson(route('teacher.agenda.download'))->assertUnprocessable()->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('teacher.agenda.download', ['from' => 'invalid', 'to' => 'invalid']))->assertJsonValidationErrors(['from', 'to']);
        $this->getJson(route('teacher.agenda.download', ['from' => '2026-10-20', 'to' => '2026-10-01']))->assertJsonValidationErrors('to');
        $this->getJson(route('teacher.agenda.download', ['from' => '2024-01-01', 'to' => '2025-01-01']))->assertUnprocessable();
        $response = $this->get(route('teacher.agenda.download', ['from' => '2024-01-01', 'to' => '2024-12-31']));
        $response->assertDownload('agenda-2024-01-01-2024-12-31.xlsx');
        $rows = $this->worksheetRows($response->streamedContent());
        $this->assertCount(5, $rows);
    }

    public function test_excel_keeps_formulas_and_xml_as_literal_text_and_rejects_over_1000_agendas(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id, 'full_name' => '=1+1']);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'inactive']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'inactive']);
        $agenda = $this->agenda($teacher, $period, []);
        $agenda->update(['material' => "=HYPERLINK(\"https://invalid.example\",\"x\")\n<&>\x01"]);
        $url = route('teacher.agenda.download', ['from' => '2026-10-20', 'to' => '2026-10-20']);

        $response = $this->actingAs($user)->get($url);
        $response->assertDownload();
        $content = $response->streamedContent();
        $rows = $this->worksheetRows($content);
        $this->assertSame('=1+1', $rows[2][1]);
        $this->assertSame("=HYPERLINK(\"https://invalid.example\",\"x\")\n<&>", $rows[5][7]);
        $this->assertSame('', $rows[5][8]);

        $rows = [];
        for ($index = 0; $index < 999; $index++) {
            $attributes = $agenda->getAttributes();
            unset($attributes['id']);
            $attributes['block_key'] = hash('sha256', 'extra-'.$index);
            $rows[] = $attributes;
        }
        TeacherAgenda::query()->insert($rows);
        $this->get($url)->assertDownload();
        $extra = $rows[0];
        $extra['block_key'] = hash('sha256', 'extra-over-limit');
        TeacherAgenda::query()->insert($extra);
        $this->getJson($url)->assertUnprocessable()->assertJsonPath('message', 'Terlalu banyak agenda. Persempit rentang tanggal.');
    }

    /**
     * @return list<array<int, string>>
     */
    private function worksheetRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'agenda-xlsx-test-');
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
            $stylesXml = $archive->getFromName('xl/styles.xml');
            $this->assertIsString($stylesXml);
            $styles = simplexml_load_string($stylesXml, SimpleXMLElement::class, LIBXML_NONET);
            $this->assertInstanceOf(SimpleXMLElement::class, $styles);
            $xml = $archive->getFromName('xl/worksheets/sheet1.xml');
            $this->assertSame(file_get_contents(public_path('images/kop-sekolah.png')), $archive->getFromName('xl/media/image1.png'));
            $drawingXml = $archive->getFromName('xl/drawings/drawing1.xml');
            $this->assertIsString($drawingXml);
            $drawing = simplexml_load_string($drawingXml, SimpleXMLElement::class, LIBXML_NONET);
            $this->assertInstanceOf(SimpleXMLElement::class, $drawing);
            $drawing->registerXPathNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
            $this->assertSame('4', (string) $drawing->xpath('//xdr:from/xdr:col')[0]);
            $this->assertSame((string) (50 * 9525), (string) $drawing->xpath('//xdr:from/xdr:colOff')[0]);
            $this->assertSame((string) (564 * 9525), (string) $drawing->xpath('//xdr:oneCellAnchor/xdr:ext/@cx')[0]);
            $this->assertSame((string) (183 * 9525), (string) $drawing->xpath('//xdr:oneCellAnchor/xdr:ext/@cy')[0]);
            $this->assertIsString($archive->getFromName('xl/worksheets/_rels/sheet1.xml.rels'));
            $archive->close();
            $this->assertIsString($xml);
            $sheet = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            $this->assertInstanceOf(SimpleXMLElement::class, $sheet);
            $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame([], $sheet->xpath('//s:f'));
            $this->assertSame('60', (string) $sheet->cols->col[7]['width']);
            $this->assertSame('12.5', (string) $sheet->cols->col[6]['width']);
            $this->assertSame('30', (string) $sheet->cols->col[8]['width']);
            $this->assertCount(9, $sheet->cols->col);
            $this->assertSame('A2:I2', (string) $sheet->mergeCells->mergeCell[0]['ref']);
            $this->assertCount(9, $sheet->sheetData->row[4]->c);
            $this->assertSame('Keterangan', (string) $sheet->sheetData->row[4]->c[8]->is->t);
            $this->assertSame(150.0, (float) $sheet->sheetData->row[0]['ht']);
            $titleStyle = (int) $sheet->sheetData->row[1]->c[0]['s'];
            $titleFill = (int) $styles->cellXfs->xf[$titleStyle]['fillId'];
            $this->assertSame('FF1E3A5F', (string) $styles->fills->fill[$titleFill]->patternFill->fgColor['rgb']);
            foreach ($sheet->sheetData->row[4]->c as $cell) {
                $style = $styles->cellXfs->xf[(int) $cell['s']];
                $this->assertSame('FF2563EB', (string) $styles->fills->fill[(int) $style['fillId']]->patternFill->fgColor['rgb']);
                $this->assertSame('1', (string) $style['borderId']);
            }
            $this->assertSame('thin', (string) $styles->borders->border[1]->left['style']);
            $this->assertSame('thin', (string) $styles->borders->border[1]->right['style']);
            $this->assertSame('thin', (string) $styles->borders->border[1]->top['style']);
            $this->assertSame('thin', (string) $styles->borders->border[1]->bottom['style']);
            foreach ($sheet->sheetData->row as $row) {
                if ((int) $row['r'] < 6) {
                    continue;
                }
                foreach ($row->c as $cell) {
                    $this->assertSame('1', (string) $styles->cellXfs->xf[(int) $cell['s']]['borderId']);
                }
                $materialStyle = (int) $row->c[7]['s'];
                $this->assertSame('1', (string) $styles->cellXfs->xf[$materialStyle]->alignment['wrapText']);
            }
            $rows = [];

            foreach ($sheet->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    preg_match('/^([A-Z]+)\\d+$/', (string) $cell['r'], $matches);
                    $column = 0;
                    foreach (str_split($matches[1] ?? '') as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 'inlineStr') {
                        $value = (string) $cell->is->t;
                    }
                    $values[$column - 1] = $value;
                }
                ksort($values);
                $rows[] = array_values($values);
            }

            return $rows;
        } finally {
            @unlink($path);
        }
    }

    /** @param array<int, array<string, mixed>> $roster */
    private function agenda(Teacher $teacher, AcademicPeriod $period, array $roster): TeacherAgenda
    {
        return TeacherAgenda::query()->create([
            'teacher_id' => $teacher->id, 'academic_period_id' => $period->id, 'agenda_date' => '2026-10-20',
            'block_key' => hash('sha256', (string) Str::uuid()),
            'schedule_snapshot' => ['academic_year' => '2026/2027', 'academic_period' => 'Ganjil', 'rombel_code' => 'VII-A',
                'subject_name' => 'Snapshot Matematika', 'teacher_name' => $teacher->full_name, 'lesson_start' => 1, 'lesson_end' => 3,
                'start_time' => '07:30', 'end_time' => '09:30'],
            'material' => '<script>materi</script>', 'attendance' => $roster,
        ]);
    }
}
