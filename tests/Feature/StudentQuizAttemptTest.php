<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentQuizAttempt;
use App\Models\Subject;
use App\Models\TeacherQuiz;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_all_students_can_access_an_open_quiz_and_attempt_locks_after_three_exits(): void
    {
        [$student, $studentUser] = $this->student();
        [, $anotherStudent] = $this->student();
        $quiz = $this->quiz(true);

        $this->actingAs($studentUser)->get(route('student-app.quizzes'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('student-app/quizzes/index')->has('quizzes.data', 1));
        $this->actingAs($studentUser)->get(route('student-app.quizzes.show', $quiz))->assertOk()
            ->assertInertia(fn ($page) => $page->component('student-app/quizzes/attempt')->where('started', false));
        $start = route('student-app.quizzes.attempt.start', $quiz);
        $this->actingAs($studentUser)->postJson($start, ['expected_count' => 0])->assertOk()->assertJsonPath('interruptionCount', 1);
        $attempt = StudentQuizAttempt::query()->where('student_id', $student->id)->firstOrFail();

        foreach ([1, 2, 3] as $exit) {
            $this->actingAs($studentUser)->postJson(route('student-app.quizzes.attempt.event', $attempt), ['event_type' => 'exam_page_hidden'])->assertOk();
            $response = $this->actingAs($studentUser)->postJson(route('student-app.quizzes.attempt.event', $attempt), ['event_type' => 'exam_page_visible']);
            if ($exit < 3) {
                $response->assertOk()->assertJsonPath('interruptionCount', $exit + 1);
            } else {
                $response->assertStatus(423)->assertJsonPath('interruptionCount', 4);
            }
        }
        $this->actingAs($anotherStudent)->postJson(route('student-app.quizzes.attempt.event', $attempt), ['event_type' => 'exam_page_hidden'])->assertNotFound();

        $this->assertDatabaseHas('tr_teacher_quiz_attempts', ['id' => $attempt->id, 'status' => 'locked', 'interruption_count' => 4]);
    }

    public function test_students_cannot_access_closed_quiz_and_closing_quiz_blocks_active_attempt(): void
    {
        [, $studentUser] = $this->student();
        $quiz = $this->quiz(false);

        $this->actingAs($studentUser)->get(route('student-app.quizzes.show', $quiz))->assertForbidden();
        $this->actingAs($studentUser)->postJson(route('student-app.quizzes.attempt.start', $quiz), ['expected_count' => 0])->assertForbidden();

        $quiz->update(['is_open' => true]);
        $this->actingAs($studentUser)->postJson(route('student-app.quizzes.attempt.start', $quiz), ['expected_count' => 0])->assertOk();
        $quiz->update(['is_open' => false]);
        $attempt = StudentQuizAttempt::query()->firstOrFail();
        $this->actingAs($studentUser)->postJson(route('student-app.quizzes.attempt.complete', $attempt))->assertForbidden();
    }

    public function test_quiz_owner_can_manage_the_global_lock_without_changing_individual_attempt_lock(): void
    {
        [$teacher, $subject] = $this->teacherAndSubject();
        [$student] = $this->student();
        $quiz = $this->quiz(false, $teacher, $subject);
        $attempt = StudentQuizAttempt::query()->create([
            'student_id' => $student->id,
            'teacher_quiz_id' => $quiz->id,
            'status' => 'locked',
            'interruption_count' => 4,
            'started_at' => now(),
            'locked_at' => now(),
        ]);

        $this->actingAs($teacher)->patch(route('teacher.quizzes.availability', $quiz), ['is_open' => true])->assertRedirect();

        $this->assertDatabaseHas('tr_teacher_quizzes', ['id' => $quiz->id, 'is_open' => true]);
        $this->assertDatabaseHas('tr_teacher_quiz_attempts', ['id' => $attempt->id, 'status' => 'locked', 'interruption_count' => 4]);
    }

    public function test_stale_reopen_retry_is_idempotent_and_another_student_cannot_complete_the_attempt(): void
    {
        [, $user] = $this->student();
        [, $other] = $this->student();
        $quiz = $this->quiz(true);
        $route = route('student-app.quizzes.attempt.start', $quiz);
        $this->actingAs($user)->postJson($route, ['expected_count' => 0])->assertOk();
        $this->actingAs($user)->postJson($route, ['reopen' => true, 'expected_count' => 1])->assertOk()->assertJsonPath('interruptionCount', 2);
        $this->actingAs($user)->postJson($route, ['reopen' => true, 'expected_count' => 1])->assertOk()->assertJsonPath('interruptionCount', 2);
        $attempt = StudentQuizAttempt::query()->firstOrFail();
        $this->actingAs($other)->postJson(route('student-app.quizzes.attempt.complete', $attempt))->assertNotFound();
        $this->assertDatabaseCount('tr_teacher_quiz_attempt_events', 2);
        $this->actingAs($user)->postJson(route('student-app.quizzes.attempt.complete', $attempt))->assertOk()->assertJsonPath('status', 'locked');
        $this->actingAs($user)->postJson(route('student-app.quizzes.attempt.complete', $attempt))->assertOk();
        $this->assertDatabaseCount('tr_teacher_quiz_attempt_events', 3);
        $this->actingAs($user)->get(route('student-app.quizzes.show', $quiz))->assertInertia(fn ($page) => $page->where('locked', true)->where('formUrl', null));
    }

    /** @return array{Student, User} */
    private function student(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        return [Student::factory()->create(['user_id' => $user->id]), $user];
    }

    /** @return array{User, Subject} */
    private function teacherAndSubject(): array
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        return [$teacher, Subject::factory()->create()];
    }

    private function quiz(bool $open, ?User $teacher = null, ?Subject $subject = null): TeacherQuiz
    {
        $teacher ??= User::factory()->create();
        $teacher->assignRole('Guru');
        $subject ??= Subject::factory()->create();
        $year = AcademicYear::query()->firstOrCreate(['year' => '2026/2027'], ['status' => 'active']);
        $period = AcademicPeriod::query()->firstOrCreate(['academic_year_id' => $year->id, 'code' => 'S1'], ['name' => 'Semester 1', 'status' => 'active']);

        return TeacherQuiz::query()->create([
            'created_by' => $teacher->id,
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/abcdefgh',
            'is_open' => $open,
        ]);
    }
}
