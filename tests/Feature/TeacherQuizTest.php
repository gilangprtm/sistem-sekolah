<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentQuizAttempt;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherQuiz;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_teacher_creates_a_locked_owned_quiz_and_cannot_access_another_teachers_quiz(): void
    {
        [$teacher, $subject] = $this->teacherAndSubject();
        [$otherTeacher] = $this->teacherAndSubject();
        $foreignQuiz = $this->quiz($otherTeacher, $subject);
        $year = AcademicYear::query()->firstOrCreate(['year' => '2026/2027'], ['status' => 'active']);
        $period = AcademicPeriod::query()->firstOrCreate(['academic_year_id' => $year->id, 'code' => 'S1'], ['name' => 'Semester 1', 'status' => 'active']);

        $this->actingAs($teacher)->post(route('teacher.quizzes.store'), [
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/abcdefgh',
            'is_open' => true,
            'created_by' => $otherTeacher->id,
        ])->assertRedirect();

        $created = TeacherQuiz::query()->where('created_by', $teacher->id)->firstOrFail();
        $this->assertDatabaseHas('tr_teacher_quizzes', [
            'id' => $created->id,
            'created_by' => $teacher->id,
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'is_open' => false,
        ]);

        $this->actingAs($teacher)->get(route('teacher.quizzes.index', ['monitor_quiz_id' => $foreignQuiz->id]))->assertNotFound();
        $this->actingAs($teacher)->patch(route('teacher.quizzes.update', $foreignQuiz), [
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/abcdefgh',
        ])->assertNotFound();
        $this->actingAs($teacher)->patch(route('teacher.quizzes.availability', $foreignQuiz), ['is_open' => true])->assertNotFound();
        $this->actingAs($teacher)->delete(route('teacher.quizzes.destroy', $foreignQuiz))->assertNotFound();
    }

    public function test_teacher_quiz_rejects_non_google_form_link(): void
    {
        [$teacher, $subject] = $this->teacherAndSubject();
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'S1', 'name' => 'Semester 1', 'status' => 'active']);

        $this->actingAs($teacher)->from(route('teacher.quizzes.index'))->post(route('teacher.quizzes.store'), [
            'subject_id' => $subject->id,
            'link' => 'https://example.org/form',
        ])->assertSessionHasErrors('link');

        $this->assertDatabaseCount('tr_teacher_quizzes', 0);
    }

    public function test_owner_can_unlock_only_a_locked_attempt_with_a_reason_and_global_lock_is_unchanged(): void
    {
        [$teacher, $subject] = $this->teacherAndSubject();
        [$otherTeacher] = $this->teacherAndSubject();
        [$student] = $this->student();
        $quiz = $this->quiz($teacher, $subject, false);
        $attempt = $this->attempt($quiz, $student);

        $this->actingAs($otherTeacher)->post(route('teacher.quizzes.attempts.unlock', $attempt), ['reason' => 'Permintaan Guru'])->assertNotFound();
        $this->actingAs($teacher)->post(route('teacher.quizzes.attempts.unlock', $attempt), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($teacher)->post(route('teacher.quizzes.attempts.unlock', $attempt), ['reason' => 'Verifikasi identitas siswa'])->assertRedirect();

        $this->assertDatabaseHas('tr_teacher_quiz_attempts', [
            'id' => $attempt->id,
            'status' => 'active',
            'interruption_count' => 0,
            'unlock_reason' => 'Verifikasi identitas siswa',
        ]);
        $this->assertDatabaseHas('tr_teacher_quiz_attempt_events', [
            'quiz_attempt_id' => $attempt->id,
            'event_type' => 'teacher_unlock',
            'actor_user_id' => $teacher->id,
            'reason' => 'Verifikasi identitas siswa',
        ]);
        $this->assertDatabaseHas('tr_teacher_quizzes', ['id' => $quiz->id, 'is_open' => false]);
    }

    public function test_list_contains_only_owned_quizzes_and_super_admin_cannot_bypass_ownership(): void
    {
        [$teacher, $subject] = $this->teacherAndSubject();
        [$other] = $this->teacherAndSubject();
        $own = $this->quiz($teacher, $subject);
        $foreign = $this->quiz($other, $subject);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        [$student] = $this->student();
        $attempt = $this->attempt($foreign, $student);

        $this->actingAs($teacher)->get(route('teacher.quizzes.index'))->assertInertia(fn ($page) => $page
            ->has('quizzes.data', 1)->where('quizzes.data.0.id', $own->id));
        $this->actingAs($admin)->get(route('teacher.quizzes.index'))->assertInertia(fn ($page) => $page->has('quizzes.data', 0));
        $this->actingAs($admin)->get(route('teacher.quizzes.index', ['monitor_quiz_id' => $foreign->id]))->assertNotFound();
        $this->actingAs($admin)->patch(route('teacher.quizzes.availability', $foreign), ['is_open' => false])->assertNotFound();
        $this->actingAs($admin)->post(route('teacher.quizzes.attempts.unlock', $attempt), ['reason' => 'Tidak diizinkan lintas pemilik'])->assertNotFound();
        $this->assertDatabaseHas('tr_teacher_quiz_attempts', ['id' => $attempt->id, 'status' => 'locked']);
    }

    public function test_owner_can_update_and_delete_quiz_and_invalid_subject_or_missing_context_is_rejected(): void
    {
        [$teacher, $subject] = $this->teacherAndSubject();
        $quiz = $this->quiz($teacher, $subject);
        $this->actingAs($teacher)->patch(route('teacher.quizzes.update', $quiz), ['subject_id' => $subject->id, 'link' => 'https://docs.google.com/forms/d/test/viewform'])->assertRedirect();
        $this->assertDatabaseHas('tr_teacher_quizzes', ['id' => $quiz->id, 'link' => 'https://docs.google.com/forms/d/test/viewform']);
        $subject->update(['status' => 'inactive']);
        $this->actingAs($teacher)->patch(route('teacher.quizzes.update', $quiz), ['subject_id' => $subject->id, 'link' => 'https://forms.gle/test'])->assertSessionHasErrors('subject_id');
        $subject->update(['status' => 'active']);
        AcademicPeriod::query()->update(['status' => 'inactive']);
        $this->actingAs($teacher)->post(route('teacher.quizzes.store'), ['subject_id' => $subject->id, 'link' => 'https://forms.gle/test'])->assertSessionHasErrors('subject_id');
        $this->actingAs($teacher)->delete(route('teacher.quizzes.destroy', $quiz))->assertRedirect();
        $this->assertModelMissing($quiz);
    }

    public function test_subject_options_and_server_validation_are_scoped_to_the_teachers_assignments(): void
    {
        [$user, $subject] = $this->teacherAndSubject();
        [, $otherSubject] = $this->teacherAndSubject();
        $unassigned = Subject::factory()->create();
        $inactive = Subject::factory()->create(['status' => 'inactive']);
        TeacherSubject::factory()->create(['teacher_id' => $user->teacher->id, 'subject_id' => $inactive->id]);
        $quiz = $this->quiz($user, $subject);

        $this->actingAs($user)->get(route('teacher.quizzes.index'))->assertInertia(fn ($page) => $page
            ->has('subjects', 1)->where('subjects.0.id', $subject->id));

        foreach ([$otherSubject, $unassigned, $inactive] as $invalid) {
            $payload = ['subject_id' => $invalid->id, 'link' => 'https://forms.gle/test'];
            $this->post(route('teacher.quizzes.store'), $payload)
                ->assertSessionHasErrors(['subject_id' => 'Pilih Mata Pelajaran aktif yang Anda ampu.']);
            $this->patch(route('teacher.quizzes.update', $quiz), $payload)
                ->assertSessionHasErrors('subject_id');
        }

        $this->assertDatabaseCount('tr_teacher_quizzes', 1);
        $this->assertDatabaseHas('tr_teacher_quizzes', ['id' => $quiz->id, 'subject_id' => $subject->id]);
    }

    public function test_removed_assignment_cannot_be_submitted_and_does_not_hide_existing_owned_quizzes(): void
    {
        [$user, $subject] = $this->teacherAndSubject();
        $quiz = $this->quiz($user, $subject);
        $user->teacher->teacherSubjects()->delete();

        $this->actingAs($user)->get(route('teacher.quizzes.index'))->assertInertia(fn ($page) => $page
            ->has('subjects', 0)->has('quizzes.data', 1)->where('quizzes.data.0.id', $quiz->id)
            ->has('filterOptions.subjects', 1)->where('filterOptions.subjects.0.id', $subject->id));
        $this->post(route('teacher.quizzes.store'), ['subject_id' => $subject->id, 'link' => 'https://forms.gle/test'])
            ->assertSessionHasErrors('subject_id');
        $this->assertDatabaseCount('tr_teacher_quizzes', 1);
    }

    public function test_missing_or_ineligible_teacher_profiles_never_receive_all_subjects_even_for_super_admin(): void
    {
        [, $subject] = $this->teacherAndSubject();
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->get(route('teacher.quizzes.index'))->assertInertia(fn ($page) => $page->has('subjects', 0));
        $this->post(route('teacher.quizzes.store'), ['subject_id' => $subject->id, 'link' => 'https://forms.gle/test'])
            ->assertSessionHasErrors('subject_id');

        [$user, $ownSubject] = $this->teacherAndSubject();
        foreach ([['status' => 'inactive', 'staff_type' => 'guru'], ['status' => 'active', 'staff_type' => 'staff']] as $attributes) {
            $user->teacher->update($attributes);
            $this->actingAs($user)->get(route('teacher.quizzes.index'))->assertInertia(fn ($page) => $page->has('subjects', 0));
            $this->post(route('teacher.quizzes.store'), ['subject_id' => $ownSubject->id, 'link' => 'https://forms.gle/test'])
                ->assertSessionHasErrors('subject_id');
        }
        $this->assertDatabaseCount('tr_teacher_quizzes', 0);
    }

    public function test_search_subject_and_availability_filters_remain_owned_and_keep_options_across_pagination(): void
    {
        [$user, $subject] = $this->teacherAndSubject();
        $subject->update(['code' => 'MATH', 'name' => 'Matematika']);
        [$other, $otherSubject] = $this->teacherAndSubject();
        $open = $this->quiz($user, $subject, true);
        $this->quiz($user, $subject, false);
        $this->quiz($user, $otherSubject, true);
        $this->quiz($other, $subject, true);

        $this->actingAs($user)->get(route('teacher.quizzes.index', [
            'search' => ' math ', 'subject_id' => $subject->id, 'availability' => 'open', 'per_page' => 1,
        ]))->assertInertia(fn ($page) => $page
            ->where('filters.search', 'math')->where('filters.subject_id', $subject->id)->where('filters.availability', 'open')
            ->where('quizzes.total', 1)->where('quizzes.data.0.id', $open->id)->has('filterOptions.subjects', 2));
        $this->get(route('teacher.quizzes.index', ['availability' => 'locked']))->assertInertia(fn ($page) => $page->where('quizzes.total', 1));
        $this->get(route('teacher.quizzes.index', ['per_page' => 1]))->assertInertia(fn ($page) => $page
            ->where('quizzes.total', 3)->where('quizzes.last_page', 3)->has('quizzes.data', 1));
        $this->get(route('teacher.quizzes.index', ['search' => "' OR 1=1 --"]))->assertInertia(fn ($page) => $page->has('quizzes.data', 0));
        $this->get(route('teacher.quizzes.index', ['availability' => 'invalid']))->assertSessionHasErrors('availability');
    }

    public function test_quiz_factory_creates_a_consistent_academic_context(): void
    {
        $quiz = TeacherQuiz::factory()->create();
        $this->assertSame($quiz->academic_year_id, $quiz->academicPeriod->academic_year_id);
        $this->assertFalse($quiz->is_open);
    }

    /** @return array{User, Subject} */
    private function teacherAndSubject(): array
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        $profile = Teacher::factory()->create(['user_id' => $teacher->id, 'status' => 'active', 'staff_type' => 'guru']);
        $subject = Subject::factory()->create();
        TeacherSubject::factory()->create(['teacher_id' => $profile->id, 'subject_id' => $subject->id]);

        return [$teacher, $subject];
    }

    /** @return array{Student, User} */
    private function student(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        return [Student::factory()->create(['user_id' => $user->id]), $user];
    }

    private function quiz(User $teacher, Subject $subject, bool $isOpen = true): TeacherQuiz
    {
        $year = AcademicYear::query()->firstOrCreate(['year' => '2026/2027'], ['status' => 'active']);
        $period = AcademicPeriod::query()->firstOrCreate(['academic_year_id' => $year->id, 'code' => 'S1'], ['name' => 'Semester 1', 'status' => 'active']);

        return TeacherQuiz::query()->create([
            'created_by' => $teacher->id,
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/abcdefgh',
            'is_open' => $isOpen,
        ]);
    }

    private function attempt(TeacherQuiz $quiz, Student $student): StudentQuizAttempt
    {
        return StudentQuizAttempt::query()->create([
            'student_id' => $student->id,
            'teacher_quiz_id' => $quiz->id,
            'status' => 'locked',
            'interruption_count' => 4,
            'started_at' => now(),
            'locked_at' => now(),
        ]);
    }
}
