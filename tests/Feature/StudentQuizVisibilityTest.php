<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\StudentQuizAttempt;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherQuiz;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentQuizVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_quiz_visibility_is_limited_to_assigned_subject_and_class(): void
    {
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id, 'staff_type' => 'guru', 'status' => 'active']);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'S1', 'name' => 'Semester 1', 'status' => 'active']);
        $plan = SchedulePlan::factory()->create(['academic_period_id' => $period->id, 'created_by' => $teacherUser->id, 'status' => 'published', 'published_at' => now()]);
        $tik = Subject::factory()->create(['code' => 'TIK', 'name' => 'TIK']);
        $ipa = Subject::factory()->create(['code' => 'IPA', 'name' => 'IPA']);
        $tikRelation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $tik->id]);
        $ipaRelation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $ipa->id]);
        $students = [];

        foreach (['A' => [$tik, $tikRelation], 'B' => [$tik, $tikRelation], 'C' => [$tik, $tikRelation], 'D' => [$ipa, $ipaRelation], 'E' => [$ipa, $ipaRelation], 'F' => [$ipa, $ipaRelation]] as $class => [$subject, $relation]) {
            $grade = in_array($class, ['A', 'D'], true) ? 'VII' : (in_array($class, ['B', 'E'], true) ? 'VIII' : 'IX');
            $rombel = Rombel::factory()->create(['code' => $grade.'-'.$class, 'name' => 'Kelas '.$grade.' '.$class, 'grade_level' => $grade, 'parallel_code' => $class]);
            $studentUser = User::factory()->create();
            $studentUser->assignRole('Siswa');
            $student = Student::factory()->create(['user_id' => $studentUser->id, 'status' => 'active']);
            StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'student_id' => $student->id, 'status' => 'active']);
            $assignment = ScheduleTeachingAssignment::query()->create([
                'schedule_plan_id' => $plan->id, 'academic_period_id' => $period->id, 'rombel_id' => $rombel->id,
                'subject_id' => $subject->id, 'teacher_subject_id' => $relation->id, 'teacher_id' => $teacher->id,
                'weekly_jp' => 2, 'subject_code' => $subject->code, 'subject_name' => $subject->name,
                'teacher_name' => $teacher->full_name, 'rombel_code' => $rombel->code, 'status' => 'active',
            ]);
            ScheduleEntry::query()->create([
                ...$assignment->only(['schedule_plan_id', 'academic_period_id', 'rombel_id', 'subject_id', 'teacher_id', 'subject_code', 'subject_name', 'teacher_name', 'rombel_code']),
                'teaching_assignment_id' => $assignment->id, 'day' => 'monday', 'lesson_number' => count($students) + 1,
                'start_time' => '07:30', 'end_time' => '08:10',
            ]);
            $students[$class] = [$student, $studentUser];
        }

        $tikQuiz = TeacherQuiz::query()->create(['created_by' => $teacherUser->id, 'academic_year_id' => $year->id, 'academic_period_id' => $period->id, 'subject_id' => $tik->id, 'link' => 'https://forms.gle/tik', 'is_open' => true]);
        $ipaQuiz = TeacherQuiz::query()->create(['created_by' => $teacherUser->id, 'academic_year_id' => $year->id, 'academic_period_id' => $period->id, 'subject_id' => $ipa->id, 'link' => 'https://forms.gle/ipa', 'is_open' => true]);

        foreach ($students as $class => [, $user]) {
            $response = $this->actingAs($user)->get(route('student-app.quizzes'));
            $response->assertOk()->assertInertia(fn ($page) => $page->has('quizzes.data', 1)->where('quizzes.data.0.subject', in_array($class, ['A', 'B', 'C'], true) ? 'TIK' : 'IPA'));
        }

        [, $tikStudentUser] = $students['A'];
        [, $ipaStudentUser] = $students['D'];
        $this->actingAs($tikStudentUser)->get(route('student-app.quizzes.show', $tikQuiz))->assertOk();
        $this->actingAs($tikStudentUser)->postJson(route('student-app.quizzes.attempt.start', $tikQuiz), ['expected_count' => 0])->assertOk();
        $this->actingAs($ipaStudentUser)->get(route('student-app.quizzes.show', $ipaQuiz))->assertOk();
        $this->actingAs($ipaStudentUser)->postJson(route('student-app.quizzes.attempt.start', $ipaQuiz), ['expected_count' => 0])->assertOk();

        $before = StudentQuizAttempt::query()->count();
        $this->actingAs($ipaStudentUser)->get(route('student-app.quizzes.show', $tikQuiz))->assertNotFound();
        $this->actingAs($ipaStudentUser)->postJson(route('student-app.quizzes.attempt.start', $tikQuiz), ['expected_count' => 0])->assertNotFound();
        $this->actingAs($ipaStudentUser)->get(route('student-app.quizzes.state', $tikQuiz))->assertNotFound();
        $this->assertSame($before, StudentQuizAttempt::query()->count());
    }
}
