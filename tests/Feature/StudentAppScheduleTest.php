<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAppScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_and_non_student_cannot_view_schedule(): void
    {
        $this->get(route('student-app.schedule'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get(route('student-app.schedule'))->assertForbidden();
    }

    public function test_student_sees_clear_state_when_academic_setup_is_missing(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('student-app.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/schedule')
                ->where('status', 'no_active_year')
                ->where('message', 'Tahun ajaran aktif belum tersedia.')
                ->has('days', 5));
    }

    public function test_student_sees_only_published_schedule_for_own_active_class(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'active',
        ]);
        $rombel = Rombel::factory()->create(['code' => 'VII-A', 'name' => 'Kelas VII A', 'grade_level' => 'VII']);
        $otherRombel = Rombel::factory()->create(['code' => 'VII-B', 'name' => 'Kelas VII B', 'grade_level' => 'VII']);
        StudentPlacement::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
        $teacher = Teacher::factory()->create(['full_name' => 'Guru Jadwal']);
        $subject = Subject::factory()->create(['code' => 'MAT', 'name' => 'Matematika']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'code' => 'MAT01',
        ]);
        $plan = SchedulePlan::query()->create([
            'academic_period_id' => $period->id,
            'grade_level' => 'VII',
            'created_by' => $user->id,
            'status' => 'published',
            'revision' => 1,
            'payload_hash' => hash('sha256', 'student-schedule'),
            'published_at' => now(),
        ]);
        $entry = ScheduleEntry::query()->create([
            'schedule_plan_id' => $plan->id,
            'teaching_assignment_id' => $this->createTeachingAssignment($plan, $period, $rombel, $subject, $teacher, $teacherSubject)->id,
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day' => 'monday',
            'lesson_number' => 1,
            'start_time' => '07:00',
            'end_time' => '07:40',
            'subject_code' => 'MAT',
            'subject_name' => 'Matematika',
            'teacher_name' => 'Guru Jadwal',
            'rombel_code' => 'VII-A',
        ]);

        $this->actingAs($user)
            ->get(route('student-app.schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/schedule')
                ->where('status', 'ready')
                ->where('rombel.code', 'VII-A')
                ->where('days.0.key', 'monday')
                ->where('days.0.entries.0.id', $entry->id)
                ->where('days.0.entries.0.subjectName', 'Matematika')
                ->where('days.0.entries.0.teacherName', 'Guru Jadwal'));

        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', [
            'rombel_id' => $otherRombel->id,
        ]);
    }

    private function createTeachingAssignment(
        SchedulePlan $plan,
        AcademicPeriod $period,
        Rombel $rombel,
        Subject $subject,
        Teacher $teacher,
        TeacherSubject $teacherSubject,
    ): \App\Models\ScheduleTeachingAssignment {
        return \App\Models\ScheduleTeachingAssignment::query()->create([
            'schedule_plan_id' => $plan->id,
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
            'subject_id' => $subject->id,
            'teacher_subject_id' => $teacherSubject->id,
            'teacher_id' => $teacher->id,
            'weekly_jp' => 1,
            'subject_code' => 'MAT',
            'subject_name' => 'Matematika',
            'teacher_name' => 'Guru Jadwal',
            'rombel_code' => 'VII-A',
            'status' => 'active',
        ]);
    }
}
