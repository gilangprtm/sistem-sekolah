<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\ScheduleCustomSlot;
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
        ScheduleCustomSlot::query()->create([
            'schedule_plan_id' => $plan->id,
            'academic_period_id' => $period->id,
            'day' => 'friday',
            'lesson_number' => 6,
            'start_time' => '11:30',
            'end_time' => '12:10',
            'label' => 'Kegiatan Jumat',
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
                ->where('days.0.entries', fn ($entries) => count($entries) === 1)
                ->where('days.0.entries.0.teacherName', 'Guru Jadwal')
                ->has('days.0.rows', 11)
                ->where('days.0.rows.6.type', 'lesson')
                ->where('days.0.rows.6.start', '11:10')
                ->where('days.0.rows.7.type', 'break')
                ->where('days.0.rows.7.start', '11:50')
                ->has('days.4.rows', 9)
                ->where('days.4.rows.6.type', 'break')
                ->where('days.4.rows.6.start', '11:10')
                ->where('days.4.rows.7.type', 'custom')
                ->where('days.4.rows.7.label', 'Kegiatan Jumat')
                ->where('days.4.rows.7.start', '11:30')
                ->where('days.4.rows.7.end', '12:10'));

        $source = file_get_contents(resource_path('js/pages/student-app/schedule.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString('Jam Pelajaran', $source);
        $this->assertStringContainsString('Waktu', $source);
        $this->assertStringContainsString('min-w-[520px]', $source);
        $this->assertStringContainsString('selectedDayKey', $source);
        $this->assertStringContainsString('setSelectedDayKey', $source);
        $this->assertStringContainsString('Pilih hari jadwal', $source);
        $this->assertStringContainsString('selectedDay.rows.map', $source);
        $this->assertStringContainsString('selectedDay.entries.filter', $source);
        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tab"', $source);
        $this->assertStringContainsString('aria-selected={isSelected}', $source);
        $this->assertStringContainsString('grid-cols-5', $source);
        $this->assertStringContainsString('h-12 min-h-12 w-full grid-cols-5 items-stretch gap-1', $source);
        $this->assertStringContainsString('overflow-hidden rounded-lg border', $source);
        $this->assertStringContainsString('h-full min-h-10 w-full min-w-0', $source);
        $this->assertStringContainsString('px-1 py-2 text-center', $source);
        $this->assertStringContainsString('w-32 min-w-32 border-r', $source);
        $this->assertStringContainsString('entry.subjectCode !==', $source);
        $this->assertStringContainsString('entry.subjectName', $source);
        $this->assertStringContainsString('focus-visible:ring-2', $source);
        $this->assertStringNotContainsString('timetableRows', $source);
        $this->assertStringNotContainsString('configuredRows', $source);
        $this->assertStringNotContainsString('configuredTimes', $source);
        $this->assertStringContainsString('Jam Pelajaran', $source);
        $this->assertStringContainsString('Waktu', $source);
        $this->assertStringNotContainsString('>Hari<', $source);
        $this->assertStringNotContainsString('>Kelas<', $source);

        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', [
            'rombel_id' => $otherRombel->id,
        ]);
    }

    public function test_duplicate_identical_entries_are_deduplicated_per_day_and_slot(): void
    {
        $entry = new ScheduleEntry([
            'id' => 1,
            'day' => 'monday',
            'lesson_number' => 1,
            'start_time' => '07:30',
            'end_time' => '08:10',
            'subject_code' => 'IPA',
            'subject_name' => 'Ilmu Pengetahuan Alam',
            'teacher_name' => 'Guru IPA',
        ]);
        $duplicate = new ScheduleEntry($entry->getAttributes());
        $distinctSubject = new ScheduleEntry([
            ...$entry->getAttributes(),
            'id' => 2,
            'subject_code' => 'IPS',
            'subject_name' => 'Ilmu Pengetahuan Sosial',
        ]);
        $controller = app(\App\Http\Controllers\StudentApp\ScheduleController::class);
        $method = new \ReflectionMethod($controller, 'groupEntriesByDay');

        $days = $method->invoke($controller, collect([$entry, $duplicate, $distinctSubject]), collect());

        $this->assertCount(2, $days[0]['entries']);
        $this->assertSame(['IPA', 'IPS'], array_column($days[0]['entries'], 'subjectCode'));
        $this->assertSame('break', $days[0]['rows'][3]['type']);
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
