<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\TeacherAgenda;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_homeroom_saves_one_shared_daily_record_visible_in_teacher_agenda(): void
    {
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $this->actingAs($context['user'])->put('/guru/kehadiran', [
            'source' => 'homeroom', 'date' => $date,
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'H', 'version' => 0]],
        ])->assertRedirect();

        $agenda = TeacherAgenda::query()->create([
            'teacher_id' => $context['teacher']->id,
            'academic_period_id' => $context['period']->id,
            'agenda_date' => $date,
            'block_key' => str_repeat('a', 64),
            'schedule_snapshot' => ['rombel_id' => $context['rombel']->id, 'rombel_code' => 'VII-A'],
            'material' => 'Materi',
            'attendance' => [['student_id' => $context['student']->id, 'full_name' => 'Ani', 'nis' => '001']],
        ]);

        $this->get(route('teacher.agenda.edit', $agenda))->assertInertia(fn ($page) => $page
            ->where('agenda.attendance.0.status', 'H')->where('agenda.attendance.0.version', 1));
        $this->assertDatabaseHas('tr_student_attendances', [
            'academic_year_id' => $context['year']->id,
            'student_id' => $context['student']->id,
            'status' => 'H',
            'updated_by' => $context['user']->id,
        ]);
        $this->assertSame($date, StudentAttendance::query()->sole()->attendance_date->toDateString());
    }

    public function test_agenda_saves_shared_daily_status_and_homeroom_reads_the_same_record(): void
    {
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $agenda = TeacherAgenda::query()->create([
            'teacher_id' => $context['teacher']->id,
            'academic_period_id' => $context['period']->id,
            'agenda_date' => $date,
            'block_key' => str_repeat('b', 64),
            'schedule_snapshot' => ['rombel_id' => $context['rombel']->id],
            'material' => 'Materi',
            'attendance' => [['student_id' => $context['student']->id, 'full_name' => 'Ani', 'nis' => '001']],
        ]);
        $this->actingAs($context['user'])->put('/guru/kehadiran', [
            'source' => 'agenda', 'agenda_id' => $agenda->id, 'date' => $date,
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'I', 'version' => 0]],
        ])->assertRedirect();

        $this->get('/guru/wali-kelas')->assertInertia(fn ($page) => $page
            ->where('attendanceStudents.0.status', 'I')->where('attendanceStudents.0.version', 1));
        $this->assertDatabaseCount('tr_student_attendances', 1);
    }

    public function test_stale_edit_foreign_roster_and_non_homeroom_are_rejected(): void
    {
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $payload = ['source' => 'homeroom', 'date' => $date,
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'H', 'version' => 0]]];
        $this->actingAs($context['user'])->put('/guru/kehadiran', $payload)->assertRedirect();
        $this->put('/guru/kehadiran', ['source' => 'homeroom', 'date' => $date,
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'S', 'version' => 0]]])
            ->assertSessionHasErrors('attendance');
        $other = Student::factory()->create();
        $this->put('/guru/kehadiran', ['source' => 'homeroom', 'date' => $date,
            'attendance' => [['student_id' => $other->id, 'status' => 'A', 'version' => 0]]])
            ->assertSessionHasErrors('attendance');
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $otherUser->id]);
        $this->actingAs($otherUser)->put('/guru/kehadiran', $payload)->assertNotFound();
        $this->assertDatabaseCount('tr_student_attendances', 1);
        $this->assertSame('H', StudentAttendance::query()->sole()->status);
    }

    public function test_all_present_saves_existing_and_unsaved_students_together(): void
    {
        $this->freezeTime();
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $second = Student::factory()->create();
        StudentPlacement::query()->create([
            'academic_year_id' => $context['year']->id, 'rombel_id' => $context['rombel']->id,
            'student_id' => $second->id, 'status' => 'active',
        ]);
        StudentAttendance::query()->create([
            'academic_year_id' => $context['year']->id, 'student_id' => $context['student']->id,
            'attendance_date' => $date, 'status' => 'S', 'version' => 2, 'updated_by' => $context['user']->id,
        ]);

        $this->actingAs($context['user'])->put('/guru/kehadiran', [
            'source' => 'homeroom', 'date' => $date,
            'attendance' => [
                ['student_id' => $context['student']->id, 'status' => 'H', 'version' => 2],
                ['student_id' => $second->id, 'status' => 'H', 'version' => 0],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tr_student_attendances', 2);
        $this->assertDatabaseHas('tr_student_attendances', [
            'academic_year_id' => $context['year']->id, 'student_id' => $context['student']->id,
            'status' => 'H', 'version' => 3, 'updated_by' => $context['user']->id,
        ]);
        $this->assertDatabaseHas('tr_student_attendances', [
            'academic_year_id' => $context['year']->id, 'student_id' => $second->id,
            'status' => 'H', 'version' => 1, 'updated_by' => $context['user']->id,
        ]);
    }

    public function test_all_present_rejects_the_entire_batch_when_one_student_version_is_stale(): void
    {
        $this->freezeTime();
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $second = Student::factory()->create();
        StudentPlacement::query()->create([
            'academic_year_id' => $context['year']->id, 'rombel_id' => $context['rombel']->id,
            'student_id' => $second->id, 'status' => 'active',
        ]);
        StudentAttendance::query()->create([
            'academic_year_id' => $context['year']->id, 'student_id' => $second->id,
            'attendance_date' => $date, 'status' => 'I', 'version' => 1, 'updated_by' => $context['user']->id,
        ]);

        $this->actingAs($context['user'])->put('/guru/kehadiran', [
            'source' => 'homeroom', 'date' => $date,
            'attendance' => [
                ['student_id' => $context['student']->id, 'status' => 'H', 'version' => 0],
                ['student_id' => $second->id, 'status' => 'H', 'version' => 0],
            ],
        ])->assertSessionHasErrors([
            'attendance' => 'Absensi sudah diubah guru lain. Muat ulang sebelum menyimpan kembali.',
        ]);

        $this->assertDatabaseCount('tr_student_attendances', 1);
        $this->assertDatabaseMissing('tr_student_attendances', ['student_id' => $context['student']->id]);
        $this->assertDatabaseHas('tr_student_attendances', [
            'student_id' => $second->id, 'status' => 'I', 'version' => 1,
        ]);
    }

    public function test_user_without_attendance_permission_cannot_write_shared_records(): void
    {
        $context = $this->classroom();
        Role::findByName('Guru', 'web')->revokePermissionTo('teacher.attendance.manage');
        $context['user']->unsetRelation('roles');
        $this->actingAs($context['user'])->put('/guru/kehadiran', [
            'source' => 'homeroom', 'date' => now('Asia/Makassar')->toDateString(),
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'H', 'version' => 0]],
        ])->assertForbidden();
        $this->assertDatabaseCount('tr_student_attendances', 0);
    }

    public function test_next_teacher_sees_and_edits_the_same_status_without_changing_journal_or_roster(): void
    {
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $other = User::factory()->create();
        $other->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $other->id]);
        $agenda = $this->agenda($context, $teacher, $date);
        $payload = ['source' => 'homeroom', 'date' => $date, 'attendance' => [
            ['student_id' => $context['student']->id, 'status' => 'H', 'version' => 0],
        ]];
        $this->actingAs($context['user'])->put('/guru/kehadiran', $payload)->assertRedirect();
        $this->actingAs($other)->get(route('teacher.agenda.edit', $agenda))->assertInertia(fn ($page) => $page
            ->where('agenda.attendance.0.status', 'H')->where('agenda.attendance.0.version', 1));
        StudentPlacement::query()->update(['status' => 'ended']);
        $context['period']->update(['status' => 'inactive']);
        $context['student']->update(['full_name' => 'Nama baru']);
        $this->put('/guru/kehadiran', ['source' => 'agenda', 'agenda_id' => $agenda->id, 'date' => $date,
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'D', 'version' => 1]],
        ])->assertRedirect();
        $this->get(route('teacher.agenda.edit', $agenda))->assertInertia(fn ($page) => $page
            ->where('agenda.attendance.0.status', 'D')->where('agenda.attendance.0.full_name', 'Ani'));
        $this->assertDatabaseCount('tr_student_attendances', 1);
        $this->assertSame($other->id, StudentAttendance::query()->sole()->updated_by);
        $this->assertSame('Materi', $agenda->fresh()->material);
        $this->assertArrayNotHasKey('status', $agenda->fresh()->attendance[0]);
        $this->actingAs($context['user'])->put('/guru/kehadiran', ['source' => 'agenda', 'agenda_id' => $agenda->id, 'date' => $date,
            'attendance' => [['student_id' => $context['student']->id, 'status' => 'A', 'version' => 2]],
        ])->assertNotFound();
    }

    public function test_guest_permissions_and_invalid_payloads_cannot_write(): void
    {
        $this->put('/guru/kehadiran', [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->put('/guru/kehadiran', [])->assertForbidden();
        $context = $this->classroom();
        $this->actingAs($context['user'])->put('/guru/kehadiran', [])->assertSessionHasErrors(['source', 'date', 'attendance']);
        $row = ['student_id' => $context['student']->id, 'status' => 'X', 'version' => -1];
        $this->put('/guru/kehadiran', ['source' => 'homeroom', 'date' => '2026-99-99', 'attendance' => [$row]])
            ->assertSessionHasErrors(['date', 'attendance.0.status' => 'Status absensi harus H, I, S, D, atau A.', 'attendance.0.version']);
        $row = ['student_id' => $context['student']->id, 'status' => 'H', 'version' => 0];
        $this->put('/guru/kehadiran', ['source' => 'homeroom', 'date' => now('Asia/Makassar')->toDateString(), 'attendance' => [$row, $row]])
            ->assertSessionHasErrors('attendance.0.student_id');
        $row['updated_by'] = 999;
        $this->put('/guru/kehadiran', ['source' => 'homeroom', 'date' => now('Asia/Makassar')->toDateString(), 'attendance' => [$row]])
            ->assertSessionHasErrors('attendance.0');
        $this->assertDatabaseCount('tr_student_attendances', 0);
    }

    public function test_wita_date_changes_create_separate_daily_records_and_reject_previous_day_form(): void
    {
        $context = $this->classroom();
        $this->travelTo(new \DateTimeImmutable('2026-10-19T15:59:00+00:00'));
        $this->actingAs($context['user'])->get('/guru/wali-kelas')->assertInertia(fn ($page) => $page->where('attendanceDate', '2026-10-19'));
        $payload = ['source' => 'homeroom', 'date' => '2026-10-19', 'attendance' => [
            ['student_id' => $context['student']->id, 'status' => 'S', 'version' => 0],
        ]];
        $this->put('/guru/kehadiran', $payload)->assertRedirect();
        $this->travelTo(new \DateTimeImmutable('2026-10-19T16:00:00+00:00'));
        $this->put('/guru/kehadiran', $payload)->assertSessionHasErrors('date');
        $this->get('/guru/wali-kelas')->assertInertia(fn ($page) => $page
            ->where('attendanceDate', '2026-10-20')->where('attendanceStudents.0.status', 'A')->where('attendanceStudents.0.version', 0));
        $payload['date'] = '2026-10-20';
        $this->put('/guru/kehadiran', $payload)->assertRedirect();
        $this->assertDatabaseCount('tr_student_attendances', 2);
    }

    public function test_legacy_backfill_checks_conflicts_then_imports_idempotently_without_removing_history(): void
    {
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $agenda = $this->agenda($context, $context['teacher'], $date);
        $agenda->update(['attendance' => [[
            'student_id' => $context['student']->id, 'full_name' => 'Ani', 'nis' => '001', 'status' => 'I',
        ]]]);
        $this->actingAs($context['user'])->get(route('teacher.agenda.edit', $agenda))->assertStatus(409);
        $this->artisan('attendance:backfill-agendas')->assertSuccessful();
        $this->assertDatabaseCount('tr_student_attendances', 0);
        $this->artisan('attendance:backfill-agendas', ['--apply' => true])->assertSuccessful();
        $this->artisan('attendance:backfill-agendas', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('tr_student_attendances', 1);
        $this->assertSame('I', StudentAttendance::query()->sole()->status);
        $this->assertSame('I', $agenda->fresh()->attendance[0]['status']);
        $this->get(route('teacher.agenda.edit', $agenda))->assertInertia(fn ($page) => $page->where('agenda.attendance.0.status', 'I'));
        $agenda->update(['attendance' => [[
            'student_id' => $context['student']->id, 'full_name' => 'Ani', 'nis' => '001', 'status' => 'S',
        ]]]);
        $this->artisan('attendance:backfill-agendas', ['--apply' => true])->assertFailed();
        $this->assertSame('I', StudentAttendance::query()->sole()->status);
    }

    public function test_conflicting_legacy_agendas_are_not_arbitrarily_merged(): void
    {
        $context = $this->classroom();
        $date = now('Asia/Makassar')->toDateString();
        $first = $this->agenda($context, $context['teacher'], $date);
        $first->update(['attendance' => [[
            'student_id' => $context['student']->id, 'full_name' => 'Ani', 'nis' => '001', 'status' => 'H',
        ]]]);
        $second = $first->replicate();
        $second->block_key = str_repeat('c', 64);
        $second->attendance = [['student_id' => $context['student']->id, 'status' => 'A']];
        $second->save();
        $this->artisan('attendance:backfill-agendas', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('tr_student_attendances', 0);
        $this->assertSame('H', $first->fresh()->attendance[0]['status']);
        $this->assertSame('A', $second->fresh()->attendance[0]['status']);
    }

    /** @param array<string, mixed> $context */
    private function agenda(array $context, Teacher $teacher, string $date): TeacherAgenda
    {
        return TeacherAgenda::query()->create([
            'teacher_id' => $teacher->id, 'academic_period_id' => $context['period']->id,
            'agenda_date' => $date, 'block_key' => str_repeat('b', 64),
            'schedule_snapshot' => ['rombel_id' => $context['rombel']->id, 'rombel_code' => 'VII-A'],
            'material' => 'Materi',
            'attendance' => [['student_id' => $context['student']->id, 'full_name' => 'Ani', 'nis' => '001']],
        ]);
    }

    /** @return array{year: AcademicYear, period: AcademicPeriod, user: User, teacher: Teacher, rombel: Rombel, student: Student} */
    private function classroom(): array
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create(['code' => 'VII-A']);
        HomeroomAssignment::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'teacher_id' => $teacher->id, 'status' => 'active']);
        $student = Student::factory()->create(['full_name' => 'Ani', 'nis' => '001']);
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'student_id' => $student->id, 'status' => 'active']);

        return compact('year', 'period', 'user', 'teacher', 'rombel', 'student');
    }
}
