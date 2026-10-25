<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\LmsCourse;
use App\Models\LmsCourseAttempt;
use App\Models\Rombel;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\LmsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LmsCourseResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LmsPermissionSeeder::class);
    }

    public function test_response_lists_all_active_students_including_students_without_accounts_or_answers(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [, $first] = $this->student($period, $assignment->rombel_id, 'A Belum mulai');
        $second = Student::factory()->create(['full_name' => 'B Tanpa akun', 'user_id' => null, 'status' => 'active']);
        $this->place($second, $period, $assignment->rombel_id);
        [, $inactive] = $this->student($period, $assignment->rombel_id, 'Tidak aktif');
        $inactive->update(['status' => 'inactive']);
        [, $moved] = $this->student($period, $assignment->rombel_id, 'Sudah pindah');
        StudentPlacement::query()->where('student_id', $moved->id)->update(['status' => 'inactive']);
        $other = Rombel::factory()->create();
        $this->student($period, $other->id, 'Kelas lain');

        $this->actingAs($teacher)->get(route('lms.my-courses.show', $course))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->has('classes', 1)->has('students', 2)
                ->where('selectedClassId', $assignment->rombel_id)
                ->where('students.0.id', $first->id)->where('students.0.status', 'not_started')
                ->where('students.1.id', $second->id)->where('students.1.answered_count', 0));
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $first->id]))->assertInertia(fn ($page) => $page
            ->where('selectedResponse.status', 'not_started')->has('selectedResponse.answers', 0)
            ->missing('selectedResponse.content.materials.0.questions.0.options.0.is_correct'));
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
    }

    public function test_gradebook_lists_all_students_and_only_question_pages_with_final_scores(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id, 'A Sudah mengerjakan');
        $withoutAccount = Student::factory()->create(['full_name' => 'B Tanpa akun', 'user_id' => null, 'status' => 'active']);
        $this->place($withoutAccount, $period, $assignment->rombel_id);
        [, $inactive] = $this->student($period, $assignment->rombel_id, 'C Tidak aktif');
        $inactive->update(['status' => 'inactive']);
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $section = $this->questionPage('Bagian 1');
        $section['questions'][0]['points'] = 100;
        $legacy = $this->questionPage('Tanpa bobot');
        $empty = [...$this->questionPage('Tanpa soal'), 'questions' => []];
        $content['materials'][0]['sections'] = [$section, $legacy, $empty];
        $course->approvedRevision->update(['content' => $content]);
        $page = $content['materials'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($props) => $props->missing('gradebook')->missing('course.gradebook'));
        $this->actingAs($teacher)->get(route('lms.my-courses.show', $course))->assertInertia(fn ($props) => $props
            ->has('gradebook.pages', 3)->has('gradebook.students', 2)
            ->where('gradebook.pages.0.id', $page['id'])->where('gradebook.pages.0.title', '1. Materi')
            ->where('gradebook.pages.1.id', $section['id'])->where('gradebook.pages.1.title', '1. Materi · Bagian 1')
            ->where('gradebook.pages.2.id', $legacy['id'])
            ->where('gradebook.students.0.id', $student->id)
            ->where('gradebook.students.0.scores.'.$page['id'].'.score', null)
            ->where('gradebook.students.0.scores.'.$page['id'].'.ungraded_essays', 1)
            ->where('gradebook.students.0.scores.'.$section['id'].'.submitted', false)
            ->where('gradebook.students.0.scores.'.$legacy['id'].'.weights_set', false)
            ->where('gradebook.students.1.id', $withoutAccount->id)
            ->where('gradebook.students.1.scores.'.$page['id'].'.score', null)
            ->where('gradebook.students.1.scores.'.$page['id'].'.submitted', false)
            ->missing('gradebook.students.0.scores.'.$page['id'].'.questions')
            ->missing('gradebook.students.0.scores.'.$page['id'].'.token'));
        $this->post(route('lms.my-courses.grade', $course), [
            'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id,
            'page_id' => $page['id'], 'grading_token' => $this->grading($course, $student->id, $page['id'])['token'],
            'scores' => [$page['questions'][1]['id'] => 0],
        ])->assertSessionHasNoErrors();
        $this->get(route('lms.my-courses.show', $course))->assertInertia(fn ($props) => $props
            ->where('gradebook.students.0.scores.'.$page['id'].'.score', 0)
            ->where('gradebook.students.0.scores.'.$page['id'].'.ungraded_essays', 0));
        $this->assertDatabaseCount('tr_lms_course_attempts', 1);
    }

    public function test_grade_download_matches_class_gradebook_with_zero_decimal_and_unfinished_scores(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$firstUser, $first] = $this->student($period, $assignment->rombel_id, '=1+1 <&>');
        [$secondUser, $second] = $this->student($period, $assignment->rombel_id, 'B Nilai pecahan');
        $withoutAccount = Student::factory()->create(['full_name' => 'C Tanpa akun', 'user_id' => null, 'status' => 'active']);
        $this->place($withoutAccount, $period, $assignment->rombel_id);
        [, $inactive] = $this->student($period, $assignment->rombel_id, 'Tidak aktif');
        $inactive->update(['status' => 'inactive']);
        $this->student($period, Rombel::factory()->create()->id, 'Kelas lain');
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $pending = $this->questionPage('Uraian');
        $pending['questions'][0]['points'] = 100;
        $notSubmitted = $this->questionPage('Belum dikumpulkan');
        $notSubmitted['questions'][0]['points'] = 100;
        $content['materials'][0]['sections'] = [$pending, $notSubmitted, $this->questionPage('Tanpa bobot'), [...$this->questionPage('Tanpa soal'), 'questions' => []]];
        $course->approvedRevision->update(['content' => $content]);
        $page = $content['materials'][0];
        foreach ([[$firstUser, $first, 0], [$secondUser, $second, 12.5]] as [$user, $student, $score]) {
            $this->actingAs($user);
            $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
            $this->post(route('student-app.courses.answers', $course), $this->payload($course, [], $pending['id']))->assertSessionHasNoErrors();
            $this->actingAs($teacher);
            $this->post(route('lms.my-courses.grade', $course), [
                'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id,
                'page_id' => $page['id'], 'grading_token' => $this->grading($course, $student->id, $page['id'])['token'],
                'scores' => [$page['questions'][1]['id'] => $score],
            ])->assertSessionHasNoErrors();
        }

        $response = $this->get(route('lms.my-courses.grades.download', [$course,
            'rombel_id' => $assignment->rombel_id, 'revision_id' => $course->approved_revision_id,
            'student_id' => $second->id, 'per_page' => 1,
        ]));

        $response->assertDownload('nilai-siswa-course-'.$course->id.'-kelas-'.$assignment->rombel_id.'-revisi-1.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $rows = $this->gradeWorksheetRows($response->streamedContent());
        $this->assertSame(['Daftar Nilai Siswa'], $rows[1]);
        $this->assertSame(['Course', 'Course pengujian'], $rows[2]);
        $this->assertSame(['Kelas', $assignment->rombel->name ?: $assignment->rombel->code], $rows[4]);
        $this->assertSame(['Versi Course', 'Revisi 1 · Aktif terbaru'], $rows[5]);
        $this->assertSame(['Nomor', 'Nama', '1. Materi', '1. Materi · Uraian', '1. Materi · Belum dikumpulkan', '1. Materi · Tanpa bobot'], $rows[6]);
        $this->assertSame(['1', '=1+1 <&>', '0/100', 'Belum dinilai (1)', 'Belum dikumpulkan', 'Bobot belum diatur'], $rows[7]);
        $this->assertSame(['2', 'B Nilai pecahan', '12.50/100', 'Belum dinilai (1)', 'Belum dikumpulkan', 'Bobot belum diatur'], $rows[8]);
        $this->assertSame(['3', 'C Tanpa akun', 'Belum dikumpulkan', 'Belum dikumpulkan', 'Belum dikumpulkan', 'Bobot belum diatur'], $rows[9]);
        $this->assertCount(10, $rows);
        $this->assertDatabaseCount('tr_lms_course_attempts', 2);
    }

    public function test_grade_download_preserves_historical_revision_and_does_not_export_draft_content(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $old = $course->approvedRevision;
        $content = $old->content;
        $content['materials'][0]['questions'] = [$content['materials'][0]['questions'][0]];
        $content['materials'][0]['questions'][0]['points'] = 100;
        $old->update(['content' => $content]);
        $question = $content['materials'][0]['questions'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$question['id'] => $question['options'][0]['id']]))->assertSessionHasNoErrors();
        $next = $course->revisions()->create(['revision_number' => 2, 'title' => 'Course baru', 'content' => $content, 'status' => 'approved', 'created_by' => $course->created_by]);
        $course->forceFill(['approved_revision_id' => $next->id])->save();
        $draft = $course->revisions()->create(['revision_number' => 3, 'title' => 'Draft rahasia', 'content' => $content, 'status' => 'draft', 'created_by' => $course->created_by]);
        $this->actingAs($teacher);
        $url = route('lms.my-courses.grades.download', [$course, 'rombel_id' => $assignment->rombel_id]);

        $current = $this->get($url)->assertDownload();
        $history = $this->get($url.'&revision_id='.$old->id)->assertDownload();

        $currentRows = $this->gradeWorksheetRows($current->streamedContent());
        $oldRows = $this->gradeWorksheetRows($history->streamedContent());
        $this->assertSame(['Course', 'Course baru'], $currentRows[2]);
        $this->assertSame(['Versi Course', 'Revisi 2 · Aktif terbaru'], $currentRows[5]);
        $this->assertSame(['1', 'Siswa pengujian', 'Belum dikumpulkan'], $currentRows[7]);
        $this->assertSame(['Course', 'Course pengujian'], $oldRows[2]);
        $this->assertSame(['Versi Course', 'Revisi 1 · Riwayat'], $oldRows[5]);
        $this->assertSame(['1', 'Siswa pengujian', '100/100'], $oldRows[7]);
        $this->get($url.'&revision_id='.$draft->id)->assertNotFound();
        $this->assertDatabaseCount('tr_lms_course_runs', 1);
        $this->assertDatabaseCount('tr_lms_course_attempts', 1);
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $question['id'], 'score' => 100]);
    }

    public function test_grade_download_requires_authentication_grading_permission_and_authorized_class(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        $url = route('lms.my-courses.grades.download', [$course, 'rombel_id' => $assignment->rombel_id]);
        $this->get($url)->assertRedirect(route('login'));
        [$studentUser] = $this->student($period, $assignment->rombel_id);
        foreach ([$studentUser, User::factory()->create()] as $user) {
            $this->actingAs($user)->getJson($url)->assertForbidden();
        }
        $teacher->removeRole('Guru');
        $teacher->givePermissionTo('lms.course.view');
        $this->actingAs($teacher)->getJson($url)->assertForbidden();
        $teacher->assignRole('Guru');
        $this->actingAs($teacher);
        $this->getJson(route('lms.my-courses.grades.download', [$course, 'rombel_id' => Rombel::factory()->create()->id]))->assertNotFound();
        $otherTeacher = User::factory()->create();
        $otherTeacher->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $otherTeacher->id]);
        $this->actingAs($otherTeacher)->getJson($url)->assertNotFound();
        $course->forceFill(['archived_at' => now()])->save();
        $this->actingAs($teacher)->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
    }

    public function test_grade_download_validates_filters_and_supports_empty_students_and_question_pages(): void
    {
        [$teacher, $course, , $assignment] = $this->context();
        $this->actingAs($teacher);
        $url = route('lms.my-courses.grades.download', $course);
        $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('rombel_id');
        $this->getJson($url.'?rombel_id=invalid&revision_id=0')->assertUnprocessable()->assertJsonValidationErrors(['rombel_id', 'revision_id']);
        $this->getJson($url.'?rombel_id=0&revision_id=invalid')->assertUnprocessable()->assertJsonValidationErrors(['rombel_id', 'revision_id']);
        $this->getJson($url.'?rombel_id='.$assignment->rombel_id.'&revision_id=999999')->assertNotFound();
        $course->approvedRevision->update(['content' => ['materials' => []]]);

        $response = $this->get($url.'?rombel_id='.$assignment->rombel_id)->assertDownload();

        $rows = $this->gradeWorksheetRows($response->streamedContent());
        $this->assertCount(7, $rows);
        $this->assertSame(['Nomor', 'Nama'], $rows[6]);
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
    }

    /** @return list<list<string>> */
    private function gradeWorksheetRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'lms-grades-test-');
        $this->assertIsString($path);
        file_put_contents($path, $content);
        $archive = new \ZipArchive;
        try {
            $this->assertTrue($archive->open($path));
            $this->assertSame(file_get_contents(public_path('images/kop-sekolah.png')), $archive->getFromName('xl/media/image1.png'));
            $xml = $archive->getFromName('xl/worksheets/sheet1.xml');
            $this->assertIsString($xml);
            $sheet = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
            $this->assertInstanceOf(\SimpleXMLElement::class, $sheet);
            $this->assertSame('150', (string) $sheet->sheetData->row[0]['ht']);
            $this->assertSame('2', (string) $sheet->sheetData->row[6]->c[0]['s']);
            $this->assertStringNotContainsString('<f>', $xml);
            $rows = [];
            foreach ($sheet->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $cells[] = (string) ($cell->is->t ?? $cell->v);
                }
                $rows[] = $cells;
            }

            return $rows;
        } finally {
            $archive->close();
            @unlink($path);
        }
    }

    public function test_students_collect_answers_and_teacher_sees_actual_answers_and_statuses(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $questions = $course->approvedRevision->content['materials'][0]['questions'];
        $answers = [$questions[0]['id'] => $questions[0]['options'][0]['id'], $questions[1]['id'] => 'Jawaban esai siswa'];
        $this->actingAs($user)->get(route('student-app.courses'))->assertOk()->assertInertia(fn ($page) => $page->has('courses', 1));
        $data = $this->payload($course, $answers);
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
        $this->post(route('student-app.courses.answers', $course), $data)->assertRedirect(route('student-app.courses.show', $course))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tr_lms_course_answers', 2);
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.answers.'.$questions[1]['id'], 'Jawaban esai siswa')->where('course.status', 'submitted')
            ->where('course.submitted_page_ids', [$course->approvedRevision->content['materials'][0]['id']]));
        $this->assertDatabaseHas('tr_lms_course_attempts', ['student_id' => $student->id, 'status' => 'submitted']);
        $submitted = $this->payload($course, []);
        $this->post(route('student-app.courses.answers', $course), $submitted)->assertStatus(409);
        $this->assertDatabaseCount('tr_lms_course_answers', 2);
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $questions[1]['id'], 'score' => null, 'feedback' => null]);
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($page) => $page
            ->where('students.0.status', 'submitted')->where('students.0.answered_count', 2)
            ->where('selectedResponse.answers.'.$questions[1]['id'], 'Jawaban esai siswa')
            ->where('selectedResponse.status', 'submitted')->where('selectedResponse.submitted_at', fn ($value) => $value !== null));
    }

    public function test_blank_collection_changes_token_and_stale_writes_cannot_overwrite_work(): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $section = $this->questionPage('Bagian 1');
        $content['materials'][0]['sections'] = [$section, $this->questionPage('Bagian 2')];
        $course->approvedRevision->update(['content' => $content]);
        $id = $content['materials'][0]['questions'][1]['id'];
        $this->actingAs($user);
        $first = $this->payload($course, [$id => 'Jawaban lama']);
        $this->post(route('student-app.courses.answers', $course), $first)->assertSessionHasNoErrors();
        $stale = $this->payload($course, [$section['questions'][0]['id'] => 'Jawaban basi'], $section['id']);
        $fresh = $this->payload($course, [], $section['id']);
        $this->post(route('student-app.courses.answers', $course), $fresh)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_attempts', ['status' => 'in_progress']);
        $this->post(route('student-app.courses.answers', $course), $stale)->assertSessionHasErrors('answers');
        $this->assertDatabaseCount('tr_lms_course_answers', 1);
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $id, 'answer' => json_encode(['value' => 'Jawaban lama'])]);
        $this->assertDatabaseCount('tr_lms_course_runs', 1);
        $this->assertDatabaseCount('tr_lms_course_attempts', 1);
    }

    public function test_collection_is_scoped_to_material_or_section_and_does_not_delete_other_pages(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $firstSection = $this->questionPage('Bagian 1');
        $secondSection = $this->questionPage('Bagian 2');
        $nextMaterial = [...$this->questionPage('Materi 2'), 'sections' => []];
        $content['materials'][0]['sections'] = [$firstSection, $secondSection];
        $content['materials'][] = $nextMaterial;
        $content['materials'][] = ['id' => (string) Str::uuid(), 'title' => 'Tanpa soal', 'body' => '', 'media' => [], 'questions' => [], 'sections' => []];
        $course->approvedRevision->update(['content' => $content]);
        $material = $content['materials'][0];
        $questionId = $material['questions'][1]['id'];
        $this->actingAs($user);
        $data = $this->payload($course, [$questionId => 'Jawaban materi']);
        $this->post(route('student-app.courses.answers', $course), $data)->assertSessionHasNoErrors();
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.status', 'in_progress')->where('course.submitted_page_ids', [$material['id']])
            ->has('course.answers', 1));
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$questionId => 'Tidak boleh diganti']))->assertStatus(409);
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($page) => $page
            ->where('selectedResponse.status', 'in_progress')->where('selectedResponse.submitted_page_ids', [$material['id']])
            ->where('selectedResponse.submitted_at', null));
        $this->actingAs($user);
        foreach ([$secondSection, $nextMaterial, $firstSection] as $page) {
            $answerId = $page['questions'][0]['id'];
            $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$answerId => $page['title']], $page['id']))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $questionId, 'answer' => json_encode(['value' => 'Jawaban materi'])]);
        }
        $this->assertDatabaseCount('tr_lms_course_answers', 4);
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.status', 'submitted')
            ->where('course.submitted_page_ids', [$material['id'], $firstSection['id'], $secondSection['id'], $nextMaterial['id']]));
    }

    public function test_collection_requires_student_access_and_cannot_write_another_students_attempt(): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$firstUser, $firstStudent] = $this->student($period, $assignment->rombel_id);
        [$secondUser, $secondStudent] = $this->student($period, $assignment->rombel_id, 'Siswa kedua');
        $questionId = $course->approvedRevision->content['materials'][0]['questions'][1]['id'];
        $this->actingAs($firstUser);
        $first = $this->payload($course, [$questionId => 'Jawaban siswa pertama']);
        $this->post(route('student-app.courses.answers', $course), $first)->assertSessionHasNoErrors();
        $this->actingAs($secondUser);
        $second = $this->payload($course, [$questionId => 'Jawaban siswa kedua']);
        $second['student_id'] = $firstStudent->id;
        $second['status'] = 'in_progress';
        $second['progress'] = ['submitted_pages' => []];
        $this->post(route('student-app.courses.answers', $course), $second)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_attempts', ['student_id' => $firstStudent->id, 'status' => 'submitted']);
        $this->assertDatabaseHas('tr_lms_course_attempts', ['student_id' => $secondStudent->id, 'status' => 'submitted']);
        $this->actingAs($firstUser)->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.answers.'.$questionId, 'Jawaban siswa pertama'));
        $this->actingAs(User::factory()->create())->post(route('student-app.courses.answers', $course), $second)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post(route('student-app.courses.answers', $course), $second)->assertRedirect(route('login'));
        $this->assertDatabaseCount('tr_lms_course_attempts', 2);
        $this->assertDatabaseCount('tr_lms_course_answers', 2);
    }

    public function test_answers_from_another_page_and_unknown_or_empty_pages_are_rejected(): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $section = $this->questionPage('Bagian 1');
        $empty = [...$this->questionPage('Tanpa soal'), 'questions' => []];
        $content['materials'][0]['sections'] = [$section, $empty];
        $course->approvedRevision->update(['content' => $content]);
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$section['questions'][0]['id'] => 'Tidak boleh lintas tab']))->assertSessionHasErrors('answers');
        foreach ([(string) Str::uuid(), $empty['id']] as $pageId) {
            $this->post(route('student-app.courses.answers', $course), $this->payload($course, [], $pageId))->assertSessionHasErrors('page_id');
        }
        $data = $this->payload($course, []);
        unset($data['page_id']);
        $this->post(route('student-app.courses.answers', $course), $data)->assertSessionHasErrors('page_id');
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
        $this->assertDatabaseCount('tr_lms_course_answers', 0);
    }

    public function test_legacy_draft_answers_survive_collection_on_other_pages_and_legacy_final_attempts_stay_locked(): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $section = $this->questionPage('Bagian 1');
        $content['materials'][0]['sections'] = [$section];
        $course->approvedRevision->update(['content' => $content]);
        $this->actingAs($user);
        $materialId = $content['materials'][0]['id'];
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [], $section['id']))->assertSessionHasNoErrors();
        $attempt = LmsCourseAttempt::query()->firstOrFail();
        $attempt->update(['progress' => null]);
        $attempt->answers()->create(['question_key' => $section['questions'][0]['id'], 'answer' => ['value' => 'Draf lama']]);
        $questionId = $content['materials'][0]['questions'][1]['id'];
        $attempt->answers()->create(['question_key' => $questionId, 'answer' => ['value' => 'Draf dihapus saat kosong']]);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$questionId => '']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $section['questions'][0]['id'], 'answer' => json_encode(['value' => 'Draf lama'])]);
        $this->assertDatabaseMissing('tr_lms_course_answers', ['question_key' => $questionId]);
        $attempt->update(['status' => 'submitted', 'submitted_at' => now(), 'progress' => null]);
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.submitted_page_ids', [$materialId, $section['id']]));
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [], $section['id']))->assertStatus(409);
        $this->assertDatabaseCount('tr_lms_course_answers', 1);
    }

    #[TestWith(['unknown'])]
    #[TestWith(['option'])]
    #[TestWith(['long'])]
    #[TestWith(['array'])]
    public function test_invalid_answers_are_rejected_without_creating_attempts(string $case): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $question = $course->approvedRevision->content['materials'][0]['questions'][0];
        $answers = match ($case) {
            'unknown' => [(string) Str::uuid() => 'Jawaban'],
            'option' => [$question['id'] => 'opsi-tidak-ada'],
            'long' => [$question['id'] => str_repeat('x', 10001)],
            'array' => [$question['id'] => ['value' => 'invalid']],
        };
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, $answers))->assertSessionHasErrors();
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
    }

    public function test_students_move_to_latest_activated_revision_while_teacher_can_read_old_attempt_history(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $this->actingAs($user);
        $firstPage = $course->approvedRevision->content['materials'][0];
        $first = $this->payload($course, [$firstPage['questions'][1]['id'] => 'Jawaban versi lama']);
        $this->post(route('student-app.courses.answers', $course), $first)->assertSessionHasNoErrors();
        $revision = $course->revisions()->create([
            'revision_number' => 2, 'title' => 'Versi baru', 'content' => $course->approvedRevision->content,
            'status' => 'approved', 'created_by' => $course->created_by,
        ]);
        $course->forceFill(['approved_revision_id' => $revision->id])->save();
        $this->post(route('student-app.courses.answers', $course), $first)->assertSessionHasErrors('answers');
        $old = $course->revisions()->where('revision_number', 1)->first();
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.title', 'Versi baru')->where('course.revision_id', $revision->id)
            ->where('course.status', 'not_started')->has('course.answers', 0));
        [$other] = $this->student($period, $assignment->rombel_id, 'Siswa kedua');
        $this->actingAs($other)->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page->where('course.revision_id', $revision->id));
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($page) => $page
            ->where('course.title', 'Versi baru')->where('selectedResponse.status', 'not_started')
            ->where('selectedRevisionId', $revision->id)->has('selectedResponse.answers', 0)
            ->has('responseRevisions', 2));
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id, 'revision_id' => $old->id]))->assertInertia(fn ($page) => $page
            ->where('selectedRevisionId', $old->id)->where('isHistory', true)
            ->where('selectedResponse.status', 'submitted')->where('selectedResponse.answers.'.$firstPage['questions'][1]['id'], 'Jawaban versi lama')
            ->where('canGrade', false));
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id, 'revision_id' => 999999]))->assertNotFound();
    }

    public function test_new_revision_has_independent_answers_and_scores_and_history_is_read_only(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $old = $course->approvedRevision;
        $content = $old->content;
        $content['materials'][0]['questions'][0]['points'] = 50;
        $content['materials'][0]['questions'][1]['points'] = 50;
        $old->update(['content' => $content]);
        $page = $content['materials'][0];
        [$choice, $essay] = $page['questions'];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [
            $choice['id'] => $choice['options'][0]['id'], $essay['id'] => 'Jawaban lama',
        ]))->assertSessionHasNoErrors();
        $oldAttempt = LmsCourseAttempt::query()->firstOrFail();
        $this->actingAs($teacher);
        $grade = [
            'revision_id' => $old->id, 'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id,
            'page_id' => $page['id'], 'grading_token' => $this->grading($course, $student->id, $page['id'])['token'],
            'scores' => [$essay['id'] => 40],
        ];
        $this->post(route('lms.my-courses.grade', $course), $grade)->assertSessionHasNoErrors();
        $this->assertSame(90, $this->grading($course, $student->id, $page['id'])['score']);
        $content['materials'][0]['questions'][1]['prompt'] = 'Pertanyaan uraian baru';
        $content['materials'][0]['questions'][1]['points'] = 25;
        $added = ['id' => (string) Str::uuid(), 'type' => 'essay', 'prompt' => 'Soal tambahan', 'points' => 25];
        $content['materials'][0]['questions'][] = $added;
        $next = $course->revisions()->create([
            'revision_number' => 2, 'title' => 'Versi terbaru', 'content' => $content,
            'status' => 'draft', 'created_by' => $course->created_by,
        ]);
        $this->actingAs($user)->get(route('student-app.courses.show', $course))->assertInertia(fn ($props) => $props
            ->where('course.revision_id', $old->id)->where('course.status', 'submitted'));
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $course = $course->fresh(['latestRevision']);
        $this->actingAs($admin)->post(route('lms.management.activate', $course), ['revision_id' => $course->latestRevision->editToken($course)])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('student-app.courses.show', [$course, 'revision_id' => $old->id]))->assertInertia(fn ($props) => $props
            ->where('course.revision_id', $next->id)->where('course.status', 'not_started')
            ->has('course.answers', 0)->has('course.submitted_page_ids', 0)
            ->where('course.content.materials.0.questions.1.prompt', 'Pertanyaan uraian baru')->has('course.content.materials.0.questions', 3)
            ->missing('course.grading')->missing('course.content.materials.0.questions.0.points'));
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [
            $choice['id'] => $choice['options'][1]['id'], $essay['id'] => 'Jawaban baru', $added['id'] => 'Tambahan',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tr_lms_course_runs', 2);
        $this->assertDatabaseCount('tr_lms_course_attempts', 2);
        $this->assertDatabaseHas('tr_lms_course_answers', ['course_attempt_id' => $oldAttempt->id, 'question_key' => $essay['id'], 'score' => 40, 'answer' => json_encode(['value' => 'Jawaban lama'])]);
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($props) => $props
            ->where('selectedRevisionId', $next->id)->where('isHistory', false)->where('canGrade', true)
            ->where('selectedResponse.answers.'.$essay['id'], 'Jawaban baru')
            ->where('selectedResponse.grading.'.$page['id'].'.score', null)
            ->where('gradebook.students.0.scores.'.$page['id'].'.score', null)
            ->where('selectedResponse.grading.'.$page['id'].'.questions.'.$choice['id'].'.score', 0));
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id, 'revision_id' => $old->id]))->assertInertia(fn ($props) => $props
            ->where('isHistory', true)->where('canGrade', false)
            ->where('selectedResponse.content.materials.0.questions.1.prompt', 'Jelaskan')
            ->where('selectedResponse.answers.'.$essay['id'], 'Jawaban lama')->where('selectedResponse.grading.'.$page['id'].'.score', 90)
            ->where('gradebook.students.0.scores.'.$page['id'].'.score', 90));
        $this->post(route('lms.my-courses.grade', $course), $grade)->assertSessionHasErrors('grading');
        $this->assertDatabaseHas('tr_lms_course_answers', ['course_attempt_id' => $oldAttempt->id, 'question_key' => $essay['id'], 'score' => 40]);
    }

    public function test_history_media_and_revision_selection_are_scoped_to_authorized_class_and_student(): void
    {
        Storage::fake('local');
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $old = $course->approvedRevision;
        $asset = (string) Str::uuid();
        $content = $old->content;
        $path = 'lms/courses/'.$course->id.'/historical.pdf';
        Storage::disk('local')->put($path, '%PDF-historical');
        $content['materials'][0]['media'] = [['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $asset]];
        $content['assets'] = [$asset => ['name' => 'lama.pdf', 'type' => 'pdf', 'path' => $path, 'mime' => 'application/pdf']];
        $old->update(['content' => $content]);
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
        $content['materials'][0]['media'] = [];
        unset($content['assets']);
        $next = $course->revisions()->create([
            'revision_number' => 2, 'title' => 'Tanpa lampiran lama', 'content' => $content,
            'status' => 'approved', 'created_by' => $course->created_by,
        ]);
        $course->forceFill(['approved_revision_id' => $next->id])->save();
        $draft = $course->revisions()->create([
            'revision_number' => 3, 'title' => 'Rahasia draft', 'content' => $content,
            'status' => 'draft', 'created_by' => $course->created_by,
        ]);
        $mediaParams = [$course, $asset, 'revision_id' => $old->id, 'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id];
        $historyUrl = route('lms.my-courses.media', $mediaParams);
        $this->get(route('student-app.courses.media', $mediaParams))->assertNotFound();
        $this->get($historyUrl)->assertNotFound();
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'revision_id' => $old->id, 'student_id' => $student->id]))->assertInertia(fn ($props) => $props
            ->where('selectedResponse.content.assets.'.$asset.'.url', $historyUrl)
            ->missing('selectedResponse.content.assets.'.$asset.'.path')->has('responseRevisions', 2));
        $this->get($historyUrl)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('lms.my-courses.media', [$course, $asset]))->assertNotFound();
        $this->get(route('lms.my-courses.show', [$course, 'revision_id' => $draft->id, 'student_id' => $student->id]))->assertNotFound();
        $this->get(route('lms.my-courses.media', [$course, $asset, 'revision_id' => $draft->id, 'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id]))->assertNotFound();
        $otherClass = Rombel::factory()->create(['grade_level' => 'VII']);
        [, $outsider] = $this->student($period, $otherClass->id);
        $this->get(route('lms.my-courses.show', [$course, 'revision_id' => $old->id, 'rombel_id' => $otherClass->id]))->assertNotFound();
        $this->get(route('lms.my-courses.media', [$course, $asset, 'revision_id' => $old->id, 'rombel_id' => $otherClass->id, 'student_id' => $outsider->id]))->assertNotFound();
        $this->get(route('lms.my-courses.media', [$course, $asset, 'revision_id' => $old->id, 'rombel_id' => $assignment->rombel_id, 'student_id' => $outsider->id]))->assertNotFound();
        $assignment->update(['status' => 'inactive']);
        $this->get($historyUrl)->assertNotFound();
        $this->get(route('lms.my-courses.show', [$course, 'revision_id' => $old->id, 'student_id' => $student->id]))->assertNotFound();
        $this->assertDatabaseCount('tr_lms_course_attempts', 1);
    }

    public function test_teacher_cannot_select_another_teachers_class_or_student_even_for_same_subject(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [, $student] = $this->student($period, $assignment->rombel_id);
        $otherClass = Rombel::factory()->create(['grade_level' => 'VII']);
        [, $otherStudent] = $this->student($period, $otherClass->id);
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'rombel_id' => $otherClass->id]))->assertNotFound();
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $otherStudent->id]))->assertNotFound();
        $assignment->update(['status' => 'inactive']);
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertNotFound();
    }

    public function test_student_access_and_media_are_private_and_revoked_when_schedule_changes_without_deleting_answers(): void
    {
        Storage::fake('local');
        [, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $asset = (string) Str::uuid();
        $path = 'lms/courses/'.$course->id.'/test.pdf';
        Storage::disk('local')->put($path, '%PDF-test');
        $content = $course->approvedRevision->content;
        $content['materials'][0]['media'] = [['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $asset]];
        $content['assets'] = [$asset => ['name' => 'materi.pdf', 'type' => 'pdf', 'path' => $path, 'mime' => 'application/pdf']];
        $course->approvedRevision->update(['content' => $content]);
        $this->get(route('student-app.courses.show', $course))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('student-app.courses.show', $course))->assertForbidden();
        $this->actingAs($user)->get(route('student-app.courses.show', $course))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->missing('course.content.assets.'.$asset.'.path')
                ->missing('course.content.materials.0.questions.0.options.0.is_correct')
                ->where('course.content.assets.'.$asset.'.url', route('student-app.courses.media', [$course, $asset])));
        $data = $this->payload($course, []);
        $this->post(route('student-app.courses.answers', $course), $data)->assertSessionHasNoErrors();
        $this->get(route('student-app.courses.media', [$course, $asset]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('student-app.courses.media', [$course, Str::uuid()]))->assertNotFound();
        $assignment->update(['status' => 'inactive']);
        $this->get(route('student-app.courses.show', $course))->assertNotFound();
        $this->get(route('student-app.courses.media', [$course, $asset]))->assertNotFound();
        $this->post(route('student-app.courses.answers', $course), $data)->assertNotFound();
        $this->assertDatabaseCount('tr_lms_course_attempts', 1);
        $this->assertDatabaseCount('tr_lms_course_runs', 1);
    }

    public function test_student_without_active_placement_cannot_access_or_save_course(): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $this->actingAs($user);
        $data = $this->payload($course, []);
        StudentPlacement::query()->where('student_id', $student->id)->update(['status' => 'inactive']);
        $this->get(route('student-app.courses'))->assertInertia(fn ($page) => $page->has('courses', 0));
        $this->get(route('student-app.courses.show', $course))->assertNotFound();
        $this->post(route('student-app.courses.answers', $course), $data)->assertNotFound();
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
    }

    public function test_grades_are_independent_per_page_and_zero_is_graded_without_leaking_to_students(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $section = $this->questionPage('Bagian bernilai');
        $section['questions'][0]['points'] = 100;
        $content['materials'][0]['sections'] = [$section];
        $course->approvedRevision->update(['content' => $content]);
        $page = $content['materials'][0];
        [$choice, $essay] = $page['questions'];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$choice['id'] => $choice['options'][0]['id'], $essay['id'] => 'Jawaban']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $choice['id'], 'score' => 40, 'graded_by' => null]);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [], $section['id']))->assertSessionHasNoErrors();
        $this->actingAs($teacher);
        $grading = $this->grading($course, $student->id, $page['id']);
        $this->assertNull($grading['score']);
        $this->assertSame(1, $grading['ungraded_essays']);
        $this->assertSame(40, $grading['questions'][$choice['id']]['score']);
        $data = ['rombel_id' => $assignment->rombel_id, 'student_id' => $student->id, 'page_id' => $page['id'], 'grading_token' => $grading['token'], 'scores' => [$essay['id'] => '0']];
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasNoErrors();
        $result = $this->grading($course, $student->id, $page['id']);
        $this->assertSame(40, $result['score']);
        $this->assertSame(0, $result['ungraded_essays']);
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $essay['id'], 'score' => 0, 'graded_by' => $teacher->id]);
        $sectionResult = $this->grading($course, $student->id, $section['id']);
        $this->assertTrue($sectionResult['submitted']);
        $this->assertSame(1, $sectionResult['ungraded_essays']);
        $this->assertNull($sectionResult['score']);
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasErrors('grading');
        $data['grading_token'] = $result['token'];
        $data['scores'] = [$essay['id'] => '59.25'];
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasNoErrors();
        $this->assertSame(99.25, $this->grading($course, $student->id, $page['id'])['score']);
        $this->actingAs($user)->get(route('student-app.courses.show', $course))->assertInertia(fn ($props) => $props
            ->missing('course.grading')->missing('course.score')->missing('course.content.materials.0.questions.0.points')
            ->missing('course.content.materials.0.sections.0.questions.0.points')
            ->missing('course.content.materials.0.questions.0.options.0.is_correct')
            ->where('course.answers.'.$essay['id'], 'Jawaban'));
        $this->post(route('lms.my-courses.grade', $course), $data)->assertForbidden();
    }

    public function test_partial_essay_grading_holds_total_and_blank_essays_can_be_scored_zero(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'] = [
            ['id' => (string) Str::uuid(), 'type' => 'essay', 'prompt' => 'Pertama', 'points' => 25],
            ['id' => (string) Str::uuid(), 'type' => 'essay', 'prompt' => 'Kedua', 'points' => 75],
        ];
        $course->approvedRevision->update(['content' => $content]);
        $page = $content['materials'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
        $this->actingAs($teacher);
        $data = ['rombel_id' => $assignment->rombel_id, 'student_id' => $student->id, 'page_id' => $page['id']];
        $data['grading_token'] = $this->grading($course, $student->id, $page['id'])['token'];
        $data['scores'] = [$page['questions'][0]['id'] => 0];
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasNoErrors();
        $result = $this->grading($course, $student->id, $page['id']);
        $this->assertNull($result['score']);
        $this->assertSame(1, $result['ungraded_essays']);
        $data['grading_token'] = $result['token'];
        $data['scores'] = [$page['questions'][1]['id'] => '74.5'];
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasNoErrors();
        $this->assertSame(74.5, $this->grading($course, $student->id, $page['id'])['score']);
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($props) => $props
            ->where('students.0.answered_count', 0)->has('selectedResponse.answers', 0));
        $this->actingAs($user)->get(route('student-app.courses.show', $course))->assertInertia(fn ($props) => $props
            ->has('course.answers', 0)->missing('course.grading'));
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $page['questions'][0]['id'], 'answer' => json_encode(['value' => '']), 'score' => 0]);
    }

    #[TestWith(['negative'])]
    #[TestWith(['above_maximum'])]
    #[TestWith(['precision'])]
    #[TestWith(['choice'])]
    #[TestWith(['cross_page'])]
    #[TestWith(['unknown'])]
    public function test_invalid_grades_are_rejected_atomically(string $case): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $section = $this->questionPage('Bagian lain');
        $section['questions'][0]['points'] = 100;
        $content['materials'][0]['sections'] = [$section];
        $course->approvedRevision->update(['content' => $content]);
        $page = $content['materials'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$page['questions'][1]['id'] => 'Jawaban']))->assertSessionHasNoErrors();
        $this->actingAs($teacher);
        $scores = match ($case) {
            'negative' => [$page['questions'][1]['id'] => -1],
            'above_maximum' => [$page['questions'][1]['id'] => 60.01],
            'precision' => [$page['questions'][1]['id'] => '2.555'],
            'choice' => [$page['questions'][0]['id'] => 10],
            'cross_page' => [$section['questions'][0]['id'] => 10],
            default => [(string) Str::uuid() => 10],
        };
        $this->post(route('lms.my-courses.grade', $course), [
            'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id, 'page_id' => $page['id'],
            'grading_token' => $this->grading($course, $student->id, $page['id'])['token'],
            'scores' => array_replace([$page['questions'][1]['id'] => 10], $scores),
        ])->assertSessionHasErrors();
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $page['questions'][1]['id'], 'score' => null, 'graded_by' => null]);
    }

    public function test_teacher_cannot_grade_legacy_weights_missing_or_unsubmitted_page_or_other_classes(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $page = $course->approvedRevision->content['materials'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
        $this->actingAs($teacher);
        $grading = $this->grading($course, $student->id, $page['id']);
        $this->assertFalse($grading['weights_set']);
        $this->assertNull($grading['score']);
        $data = ['rombel_id' => $assignment->rombel_id, 'student_id' => $student->id, 'page_id' => $page['id'], 'grading_token' => $grading['token'], 'scores' => [$page['questions'][1]['id'] => 10]];
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasErrors('grading');
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $section = $this->questionPage('Belum dikumpulkan');
        $section['questions'][0]['points'] = 100;
        $content['materials'][0]['sections'] = [$section];
        $course->approvedRevision->update(['content' => $content]);
        LmsCourseAttempt::query()->update(['status' => 'in_progress', 'progress' => json_encode(['submitted_pages' => [$page['id']]])]);
        $data['page_id'] = $section['id'];
        $data['grading_token'] = $this->grading($course, $student->id, $section['id'])['token'];
        $data['scores'] = [$section['questions'][0]['id'] => 0];
        $this->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasErrors('grading');
        $data['page_id'] = $page['id'];
        $data['scores'] = [$page['questions'][1]['id'] => 1];
        $data['grading_token'] = $this->grading($course, $student->id, $page['id'])['token'];
        $data['rombel_id'] = Rombel::factory()->create()->id;
        $this->post(route('lms.my-courses.grade', $course), $data)->assertNotFound();
        $this->assertDatabaseCount('tr_lms_course_answers', 0);
    }

    #[TestWith(['correct', 100])]
    #[TestWith(['wrong', 0])]
    #[TestWith(['blank', 0])]
    public function test_choice_only_tab_is_scored_automatically_on_collection(string $selection, int $expected): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $question = $content['materials'][0]['questions'][0];
        $question['points'] = 100;
        $content['materials'][0]['questions'] = [$question];
        $course->approvedRevision->update(['content' => $content]);
        $this->actingAs($user);
        $value = match ($selection) {
            'correct' => $question['options'][0]['id'],
            'wrong' => $question['options'][1]['id'],
            default => '',
        };
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$question['id'] => $value]))->assertSessionHasNoErrors();
        $this->actingAs($teacher);
        $result = $this->grading($course, $student->id, $content['materials'][0]['id']);
        $this->assertSame($expected, $result['score']);
        $this->assertSame(0, $result['ungraded_essays']);
        $this->assertSame($expected, $result['questions'][$question['id']]['score']);
    }

    public function test_weighted_revision_starts_fresh_and_keeps_unweighted_answers_in_history(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $oldRevision = $course->approvedRevision;
        $page = $oldRevision->content['materials'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, [$page['questions'][1]['id'] => 'Jawaban historis']))->assertSessionHasNoErrors();
        $content = $oldRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $next = $course->revisions()->create([
            'revision_number' => 2, 'title' => 'Versi dengan bobot', 'status' => 'approved', 'content' => $content, 'created_by' => $course->created_by,
        ]);
        $course->forceFill(['approved_revision_id' => $next->id])->save();
        $this->actingAs($teacher);
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id, 'revision_id' => $oldRevision->id]))->assertInertia(fn ($props) => $props
            ->where('selectedResponse.grading.'.$page['id'].'.weights_set', false)
            ->where('selectedResponse.grading.'.$page['id'].'.score', null));
        $result = $this->grading($course, $student->id, $page['id']);
        $this->assertTrue($result['weights_set']);
        $this->assertFalse($result['submitted']);
        $this->assertNull($result['score']);
        $this->post(route('lms.my-courses.grade', $course), [
            'rombel_id' => $assignment->rombel_id, 'student_id' => $student->id, 'page_id' => $page['id'], 'revision_id' => $oldRevision->id,
            'grading_token' => $result['token'], 'scores' => [$page['questions'][1]['id'] => 60],
        ])->assertSessionHasErrors('grading');
        $this->assertSame($oldRevision->id, LmsCourseAttempt::query()->firstOrFail()->course_revision_id);
        $this->assertSame($page, $oldRevision->fresh()->content['materials'][0]);
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $page['questions'][1]['id'], 'score' => null, 'answer' => json_encode(['value' => 'Jawaban historis'])]);
    }

    public function test_grading_rechecks_permission_teacher_status_roster_and_archived_course(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $content = $course->approvedRevision->content;
        $content['materials'][0]['questions'][0]['points'] = 40;
        $content['materials'][0]['questions'][1]['points'] = 60;
        $course->approvedRevision->update(['content' => $content]);
        $page = $content['materials'][0];
        $this->actingAs($user);
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
        $this->actingAs($teacher);
        $data = ['rombel_id' => $assignment->rombel_id, 'student_id' => $student->id, 'page_id' => $page['id'],
            'grading_token' => $this->grading($course, $student->id, $page['id'])['token'], 'scores' => [$page['questions'][1]['id'] => 5]];
        $this->assertTrue($teacher->can('grade', $course));
        $this->post(route('lms.my-courses.grade', $course), [])->assertSessionHasErrors(['rombel_id', 'student_id', 'page_id', 'grading_token', 'scores']);
        $teacher->removeRole('Guru');
        $teacher->givePermissionTo('lms.course.view');
        $this->assertFalse($teacher->can('grade', $course));
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($props) => $props
            ->missing('selectedResponse.grading')->where('canGrade', false)->where('gradebook', null));
        $this->post(route('lms.my-courses.grade', $course), $data)->assertForbidden();
        $teacher->assignRole('Guru');
        $teacher->teacher->update(['status' => 'inactive']);
        $teacher->unsetRelation('teacher');
        $this->assertFalse($teacher->can('grade', $course));
        $this->post(route('lms.my-courses.grade', $course), $data)->assertForbidden();
        $teacher->teacher->update(['status' => 'active']);
        $teacher->unsetRelation('teacher');
        $student->update(['status' => 'inactive']);
        $this->post(route('lms.my-courses.grade', $course), $data)->assertNotFound();
        $student->update(['status' => 'active']);
        [, $outsider] = $this->student($period, Rombel::factory()->create()->id);
        $this->post(route('lms.my-courses.grade', $course), [...$data, 'student_id' => $outsider->id])->assertNotFound();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->assertTrue($admin->can('grade', $course));
        $this->actingAs($admin)->post(route('lms.my-courses.grade', $course), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $page['questions'][1]['id'], 'score' => 5, 'graded_by' => $admin->id]);
        $data['grading_token'] = $this->grading($course, $student->id, $page['id'])['token'];
        $course->forceFill(['archived_at' => now()])->save();
        $this->actingAs($teacher)->post(route('lms.my-courses.grade', $course), $data)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->post(route('lms.my-courses.grade', $course), $data)->assertRedirect(route('login'));
    }

    /** @return array<string, mixed> */
    private function grading(LmsCourse $course, int $studentId, string $pageId): array
    {
        $props = [];
        $this->get(route('lms.my-courses.show', [$course, 'student_id' => $studentId]))->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props']['selectedResponse']['grading'];
        });

        return $props[$pageId];
    }

    /** @return array<string, mixed> */
    private function questionPage(string $title): array
    {
        return [
            'id' => (string) Str::uuid(), 'title' => $title, 'body' => '', 'media' => [],
            'questions' => [['id' => (string) Str::uuid(), 'type' => 'essay', 'prompt' => 'Jelaskan '.$title]],
        ];
    }

    /** @return array{User, LmsCourse, AcademicPeriod, ScheduleTeachingAssignment} */
    private function context(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'active']);
        $subject = Subject::factory()->create();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $plan = SchedulePlan::factory()->create(['academic_period_id' => $period->id, 'grade_level' => 'VII', 'status' => 'published', 'created_by' => $user->id]);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $assignment = ScheduleTeachingAssignment::query()->create([
            'schedule_plan_id' => $plan->id, 'academic_period_id' => $period->id, 'rombel_id' => $rombel->id,
            'subject_id' => $subject->id, 'teacher_subject_id' => $teacherSubject->id, 'teacher_id' => $teacher->id,
            'weekly_jp' => 2, 'subject_code' => $subject->code, 'subject_name' => $subject->name,
            'teacher_name' => $teacher->full_name, 'rombel_code' => $rombel->code, 'status' => 'active',
        ]);
        ScheduleEntry::query()->create([
            ...$assignment->only(['schedule_plan_id', 'academic_period_id', 'rombel_id', 'subject_id', 'teacher_id', 'subject_code', 'subject_name', 'teacher_name', 'rombel_code']),
            'teaching_assignment_id' => $assignment->id, 'day' => 'monday', 'lesson_number' => 1, 'start_time' => '07:30', 'end_time' => '08:10',
        ]);
        $course = LmsCourse::query()->create(['subject_id' => $subject->id, 'grade_level' => 'VII', 'title' => 'Course pengujian', 'created_by' => $teacher->id]);
        $content = ['materials' => [[
            'id' => (string) Str::uuid(), 'title' => 'Materi', 'body' => '<p>Materi</p>', 'media' => [], 'sections' => [],
            'questions' => [
                ['id' => (string) Str::uuid(), 'type' => 'multiple_choice', 'prompt' => 'Pilihan', 'options' => [['id' => (string) Str::uuid(), 'text' => 'A', 'is_correct' => true], ['id' => (string) Str::uuid(), 'text' => 'B', 'is_correct' => false]]],
                ['id' => (string) Str::uuid(), 'type' => 'essay', 'prompt' => 'Jelaskan'],
            ],
        ]]];
        $revision = $course->revisions()->create(['revision_number' => 1, 'title' => $course->title, 'content' => $content, 'status' => 'approved', 'created_by' => $teacher->id]);
        $course->forceFill(['approved_revision_id' => $revision->id])->save();

        return [$user, $course->fresh(['approvedRevision']), $period, $assignment];
    }

    /** @return array{User, Student} */
    private function student(AcademicPeriod $period, int $rombelId, string $name = 'Siswa pengujian'): array
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id, 'full_name' => $name, 'status' => 'active']);
        $this->place($student, $period, $rombelId);

        return [$user, $student];
    }

    private function place(Student $student, AcademicPeriod $period, int $rombelId): void
    {
        StudentPlacement::query()->create(['student_id' => $student->id, 'academic_year_id' => $period->academic_year_id, 'rombel_id' => $rombelId, 'status' => 'active']);
    }

    /** @param array<string, mixed> $answers
     * @return array<string, mixed>
     */
    private function payload(LmsCourse $course, array $answers, ?string $pageId = null): array
    {
        $props = [];
        $this->get(route('student-app.courses.show', $course))->assertOk()->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props']['course'];
        });

        return ['revision_id' => $props['revision_id'], 'attempt_token' => $props['attempt_token'], 'page_id' => $pageId ?? $props['content']['materials'][0]['id'], 'answers' => $answers];
    }
}
