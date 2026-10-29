<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentExamAttempt;
use App\Models\StudentPlacement;
use App\Models\Subject;
use App\Models\UasLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExamGradeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[TestWith(['UAS', 'VII', 'VIII'])]
    #[TestWith(['UTS', 'VIII', 'VII'])]
    #[TestWith(['UAS', 'IX', 'VIII'])]
    public function test_students_see_and_open_only_exams_for_their_active_grade(string $category, string $ownGrade, string $otherGrade): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $gradeSevenExam = $this->exam($year, $period, $subject, $ownGrade, 'https://forms.gle/own-grade');
        $gradeEightExam = $this->exam($year, $period, $subject, $otherGrade, 'https://forms.gle/other-grade');
        $gradeSevenExam->update(['category' => $category]);
        $gradeEightExam->update(['category' => $category]);
        $studentUser = $this->studentAtGrade($year, $ownGrade);
        $linksProp = $category === 'UTS' ? 'utsLinks' : 'links';

        $this->actingAs($studentUser)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page
                ->where('status', 'ready')
                ->where('gradeLevel', $ownGrade)
                ->has($linksProp, 1)
                ->where("{$linksProp}.0.id", $gradeSevenExam->id)
                ->missing("{$linksProp}.1"));

        $this->actingAs($studentUser)->get(route('student-app.uas.attempt', $gradeSevenExam))->assertOk();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $gradeSevenExam))->assertOk();
        $this->actingAs($studentUser)->get(route('student-app.uas.open', $gradeSevenExam))
            ->assertRedirect($gradeSevenExam->link);
        $attemptCount = StudentExamAttempt::query()->count();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $gradeEightExam))->assertNotFound();
        $this->assertSame($attemptCount, StudentExamAttempt::query()->count());
        $this->actingAs($studentUser)->get(route('student-app.uas.attempt', $gradeEightExam))->assertNotFound();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $gradeEightExam))->assertNotFound();
        $this->actingAs($studentUser)->get(route('student-app.uas.open', $gradeEightExam))->assertNotFound();

        $gradeEightStudent = $this->studentAtGrade($year, $otherGrade);
        $this->actingAs($gradeEightStudent)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page
                ->where('gradeLevel', $otherGrade)
                ->has($linksProp, 1)
                ->where("{$linksProp}.0.id", $gradeEightExam->id));
        $this->actingAs($gradeEightStudent)->get(route('student-app.uas.attempt', $gradeSevenExam))->assertNotFound();
    }

    public function test_students_without_an_active_class_see_no_exams_and_cannot_start_one(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $exam = $this->exam($year, $period, $subject, 'VII', 'https://forms.gle/unassigned-student');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        Student::factory()->create(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page
                ->where('status', 'no_active_class')
                ->where('gradeLevel', null)
                ->has('links', 0)
                ->has('utsLinks', 0));

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $exam))->assertNotFound();
    }

    public function test_admin_can_create_distinct_exams_for_the_same_subject_in_different_grades(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.create']);

        $this->actingAs($operator)->post(route('uas.store'), [
            'category' => 'UAS',
            'grade_level' => 'VII',
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/admin-grade-seven',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($operator)->post(route('uas.store'), [
            'category' => 'UAS',
            'grade_level' => 'VIII',
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/admin-grade-eight',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_uas_links', [
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'grade_level' => 'VII',
        ]);
        $this->assertDatabaseHas('tr_curriculum_uas_links', [
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'grade_level' => 'VIII',
        ]);

        $this->actingAs($operator)->post(route('uas.store'), [
            'category' => 'UAS',
            'grade_level' => 'VII',
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/duplicate-grade-seven',
        ])->assertSessionHasErrors('subject_id');
    }

    #[TestWith([null])]
    #[TestWith(['X'])]
    public function test_exam_creation_rejects_missing_or_invalid_grade_level(?string $gradeLevel): void
    {
        $this->activeContext();
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.create']);
        $subject = Subject::factory()->create();

        $this->actingAs($operator)->from(route('uas.index'))
            ->post(route('uas.store'), [
                'category' => 'UAS',
                'grade_level' => $gradeLevel,
                'subject_id' => $subject->id,
                'link' => 'https://forms.gle/invalid-grade',
            ])->assertSessionHasErrors('grade_level');

        $this->assertDatabaseCount('tr_curriculum_uas_links', 0);
    }

    public function test_legacy_exam_remains_hidden_until_admin_assigns_its_grade_without_losing_attempts(): void
    {
        [$year, $period] = $this->activeContext();
        $exam = $this->exam($year, $period, Subject::factory()->create(), 'VII', 'https://forms.gle/legacy-grade');
        $exam->update(['grade_level' => null]);
        $user = $this->studentAtGrade($year, 'VII');
        $attempt = StudentExamAttempt::query()->create([
            'student_id' => $user->student->id, 'uas_link_id' => $exam->id,
            'status' => 'active', 'interruption_count' => 1, 'started_at' => now(),
        ]);
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.update']);

        $this->actingAs($user)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page->has('links', 0));
        $this->get(route('student-app.uas.attempt', $exam))->assertNotFound();
        $this->postJson(route('student-app.uas.attempt.start', $exam))->assertNotFound();
        $this->get(route('student-app.uas.open', $exam))->assertNotFound();
        $this->actingAs($operator)->get(route('uas.index'))
            ->assertInertia(fn ($page) => $page->where('links.data.0.grade_level', null));
        $this->patch(route('uas.update', $exam), [
            'category' => 'UAS', 'grade_level' => 'VII', 'subject_id' => $exam->subject_id, 'link' => $exam->link,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', ['id' => $attempt->id, 'uas_link_id' => $exam->id]);
        $this->actingAs($user)->get(route('student-app.uas.open', $exam))->assertRedirect($exam->link);
    }

    public function test_grade_change_cannot_bypass_duplicate_validation(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $this->exam($year, $period, $subject, 'VII', 'https://forms.gle/first-grade');
        $exam = $this->exam($year, $period, $subject, 'VIII', 'https://forms.gle/second-grade');
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.update']);

        $this->actingAs($operator)->patch(route('uas.update', $exam), [
            'category' => 'UAS', 'grade_level' => 'VII', 'subject_id' => $subject->id, 'link' => $exam->link,
        ])->assertSessionHasErrors('subject_id');

        $this->assertSame('VIII', $exam->refresh()->grade_level);
        $this->assertDatabaseCount('tr_curriculum_uas_links', 2);
    }

    public function test_ended_placement_revokes_access_even_with_an_existing_attempt(): void
    {
        [$year, $period] = $this->activeContext();
        $exam = $this->exam($year, $period, Subject::factory()->create(), 'VII', 'https://forms.gle/revoked');
        $user = $this->studentAtGrade($year, 'VII');
        $this->actingAs($user)->postJson(route('student-app.uas.attempt.start', $exam))->assertOk();
        $attempt = StudentExamAttempt::query()->firstOrFail();
        StudentPlacement::query()->where('student_id', $user->student->id)->update(['status' => 'inactive']);

        $this->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page->where('status', 'no_active_class')->has('links', 0));
        $this->get(route('student-app.uas.attempt', $exam))->assertNotFound();
        $this->postJson(route('student-app.uas.attempt.start', $exam))->assertNotFound();
        $this->get(route('student-app.uas.open', $exam))->assertNotFound();
        $this->postJson(route('student-app.uas.attempt.event', $attempt), ['event_type' => 'exam_page_hidden'])->assertNotFound();
        $this->postJson(route('student-app.uas.attempt.complete', $attempt))->assertNotFound();

        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 1);
        $this->assertSame('active', $attempt->refresh()->status);
    }

    public function test_historical_placement_does_not_grant_access_to_current_exams(): void
    {
        [$year, $period] = $this->activeContext();
        $oldYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $user = $this->studentAtGrade($oldYear, 'VII');
        $exam = $this->exam($year, $period, Subject::factory()->create(), 'VII', 'https://forms.gle/current');

        $this->actingAs($user)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page->where('status', 'no_active_class')->has('links', 0));
        $this->postJson(route('student-app.uas.attempt.start', $exam))->assertNotFound();
        $this->assertDatabaseCount('tr_curriculum_exam_attempts', 0);
    }

    /** @return array{0: AcademicYear, 1: AcademicPeriod} */
    private function activeContext(): array
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'active',
        ]);

        return [$year, $period];
    }

    private function exam(AcademicYear $year, AcademicPeriod $period, Subject $subject, string $gradeLevel, string $url): UasLink
    {
        return UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'grade_level' => $gradeLevel,
            'link' => $url,
        ]);
    }

    private function studentAtGrade(AcademicYear $year, string $gradeLevel): User
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create(['grade_level' => $gradeLevel]);
        StudentPlacement::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'status' => 'active',
        ]);

        return $user;
    }
}
