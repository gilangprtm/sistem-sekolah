<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\LmsCourse;
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

    public function test_students_save_resume_and_submit_and_teacher_sees_actual_answers_and_statuses(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $questions = $course->approvedRevision->content['materials'][0]['questions'];
        $answers = [$questions[0]['id'] => $questions[0]['options'][0]['id'], $questions[1]['id'] => 'Jawaban esai siswa'];
        $this->actingAs($user)->get(route('student-app.courses'))->assertOk()->assertInertia(fn ($page) => $page->has('courses', 1));
        $data = $this->payload($course, $answers);
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
        $this->post(route('student-app.courses.answers', $course), $data)->assertRedirect(route('student-app.courses.show', $course))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_attempts', ['student_id' => $student->id, 'status' => 'in_progress']);
        $this->assertDatabaseCount('tr_lms_course_answers', 2);
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.answers.'.$questions[1]['id'], 'Jawaban esai siswa')->where('course.status', 'in_progress'));
        $data = $this->payload($course, $answers, true);
        $this->post(route('student-app.courses.answers', $course), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_attempts', ['student_id' => $student->id, 'status' => 'submitted']);
        $submitted = $this->payload($course, [], true);
        $this->post(route('student-app.courses.answers', $course), $submitted)->assertStatus(409);
        $this->assertDatabaseCount('tr_lms_course_answers', 2);
        $this->assertDatabaseHas('tr_lms_course_answers', ['question_key' => $questions[1]['id'], 'score' => null, 'feedback' => null]);
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($page) => $page
            ->where('students.0.status', 'submitted')->where('students.0.answered_count', 2)
            ->where('selectedResponse.answers.'.$questions[1]['id'], 'Jawaban esai siswa')
            ->where('selectedResponse.status', 'submitted')->where('selectedResponse.submitted_at', fn ($value) => $value !== null));
    }

    public function test_blank_answers_are_removed_and_stale_writes_cannot_overwrite_saved_work(): void
    {
        [, $course, $period, $assignment] = $this->context();
        [$user] = $this->student($period, $assignment->rombel_id);
        $id = $course->approvedRevision->content['materials'][0]['questions'][1]['id'];
        $this->actingAs($user);
        $stale = $this->payload($course, [$id => 'Jawaban lama']);
        $this->post(route('student-app.courses.answers', $course), $stale)->assertSessionHasNoErrors();
        $this->post(route('student-app.courses.answers', $course), $stale)->assertSessionHasErrors('answers');
        $fresh = $this->payload($course, [$id => '']);
        $this->post(route('student-app.courses.answers', $course), $fresh)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tr_lms_course_answers', 0);
        $this->assertDatabaseCount('tr_lms_course_runs', 1);
        $this->assertDatabaseCount('tr_lms_course_attempts', 1);
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

    public function test_revision_is_pinned_for_class_after_first_save_and_stale_initial_revision_is_rejected(): void
    {
        [$teacher, $course, $period, $assignment] = $this->context();
        [$user, $student] = $this->student($period, $assignment->rombel_id);
        $this->actingAs($user);
        $first = $this->payload($course, []);
        $revision = $course->revisions()->create([
            'revision_number' => 2, 'title' => 'Versi baru', 'content' => $course->approvedRevision->content,
            'status' => 'approved', 'created_by' => $course->created_by,
        ]);
        $course->forceFill(['approved_revision_id' => $revision->id])->save();
        $this->post(route('student-app.courses.answers', $course), $first)->assertSessionHasErrors('answers');
        $this->post(route('student-app.courses.answers', $course), $this->payload($course, []))->assertSessionHasNoErrors();
        $old = $course->revisions()->where('revision_number', 1)->first();
        $course->forceFill(['approved_revision_id' => $old->id])->save();
        $this->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page->where('course.title', 'Versi baru')->where('course.revision_id', $revision->id));
        [$other] = $this->student($period, $assignment->rombel_id, 'Siswa kedua');
        $this->actingAs($other)->get(route('student-app.courses.show', $course))->assertInertia(fn ($page) => $page->where('course.revision_id', $revision->id));
        $this->actingAs($teacher)->get(route('lms.my-courses.show', [$course, 'student_id' => $student->id]))->assertInertia(fn ($page) => $page
            ->where('course.title', 'Course pengujian')->where('selectedResponse.status', 'in_progress'));
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
    private function payload(LmsCourse $course, array $answers, bool $submit = false): array
    {
        $props = [];
        $this->get(route('student-app.courses.show', $course))->assertOk()->assertInertia(function ($page) use (&$props): void {
            $props = $page->toArray()['props']['course'];
        });

        return ['revision_id' => $props['revision_id'], 'attempt_token' => $props['attempt_token'], 'answers' => $answers, 'submit' => $submit];
    }
}
