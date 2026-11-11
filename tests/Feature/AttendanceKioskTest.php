<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceKioskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_kiosk_and_scan_active_student_without_exposing_identity(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'qr.student@example.test']);
        $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active', 'full_name' => 'Private Student']);
        $rombel = Rombel::factory()->create();
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'student_id' => $student->id, 'status' => 'active']);

        $this->get('/absensi')->assertOk()->assertInertia(fn ($page) => $page->component('attendance-kiosk/index'));
        $response = $this->postJson('/absensi/check-in', ['qr' => $user->email, 'status' => 'A', 'student_id' => 999, 'updated_by' => 999]);
        $response->assertCreated()->assertExactJson(['message' => 'Kehadiran berhasil dicatat.']);
        $this->assertDatabaseHas('tr_student_attendances', [
            'academic_year_id' => $year->id, 'student_id' => $student->id,
            'status' => 'H', 'version' => 1, 'updated_by' => null,
        ]);
        $this->assertSame(now('Asia/Makassar')->toDateString(), StudentAttendance::query()->sole()->attendance_date->toDateString());
        $response->assertDontSee($user->email)->assertDontSee('Private Student')->assertDontSee((string) $student->id);
        $this->postJson('/absensi/check-in', ['qr' => $user->email])
            ->assertOk()->assertExactJson(['message' => 'Kehadiran hari ini sudah tercatat.']);
        $this->assertDatabaseCount('tr_student_attendances', 1);
    }

    public function test_existing_attendance_is_never_overwritten_and_invalid_identity_fails_generically(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'qr.student@example.test']);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create();
        StudentPlacement::query()->create(['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'student_id' => $student->id, 'status' => 'active']);
        StudentAttendance::query()->create([
            'academic_year_id' => $year->id, 'student_id' => $student->id,
            'attendance_date' => now('Asia/Makassar')->toDateString(), 'status' => 'S', 'version' => 4, 'updated_by' => User::factory()->create()->id,
        ]);

        $this->postJson('/absensi/check-in', ['qr' => $user->email])->assertOk()
            ->assertExactJson(['message' => 'Kehadiran hari ini sudah tercatat.']);
        $this->assertDatabaseHas('tr_student_attendances', ['student_id' => $student->id, 'status' => 'S', 'version' => 4]);
        $this->postJson('/absensi/check-in', ['qr' => 'unknown@example.test'])->assertNotFound()
            ->assertExactJson(['message' => 'Kehadiran tidak dapat dicatat.']);
        $this->postJson('/absensi/check-in', ['qr' => 'not-email'])->assertUnprocessable();
    }

    public function test_check_in_without_active_year_returns_generic_unavailable_response_and_writes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'qr.student@example.test']);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->postJson('/absensi/check-in', ['qr' => $user->email])->assertStatus(503)
            ->assertExactJson(['message' => 'Kehadiran belum dapat dicatat.']);
        $this->assertDatabaseCount('tr_student_attendances', 0);
    }

    public function test_inactive_student_and_students_placed_only_in_another_year_cannot_check_in(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $otherYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $inactiveUser = User::factory()->create(['email' => 'inactive@example.test']);
        $inactiveStudent = Student::factory()->create(['user_id' => $inactiveUser->id, 'status' => 'inactive']);
        $otherUser = User::factory()->create(['email' => 'other-year@example.test']);
        $otherStudent = Student::factory()->create(['user_id' => $otherUser->id, 'status' => 'active']);
        $rombel = Rombel::factory()->create();
        StudentPlacement::query()->create(['academic_year_id' => $otherYear->id, 'rombel_id' => $rombel->id, 'student_id' => $otherStudent->id, 'status' => 'active']);

        foreach ([$inactiveUser, $otherUser] as $user) {
            $this->postJson('/absensi/check-in', ['qr' => $user->email])->assertNotFound()
                ->assertExactJson(['message' => 'Kehadiran tidak dapat dicatat.']);
        }

        $this->assertDatabaseCount('tr_student_attendances', 0);
        $this->assertSame('active', $year->fresh()->status);
    }

    public function test_check_in_does_not_require_placement_when_student_has_no_current_year_placement(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'unplaced@example.test']);
        Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->postJson('/absensi/check-in', ['qr' => $user->email])->assertNotFound()
            ->assertExactJson(['message' => 'Kehadiran tidak dapat dicatat.']);
        $this->assertDatabaseCount('tr_student_attendances', 0);
        $this->assertSame('active', $year->fresh()->status);
    }

    public function test_kiosk_page_captures_keyboard_wedge_without_a_scan_input_or_payload_display(): void
    {
        $source = file_get_contents(resource_path('js/pages/attendance-kiosk/index.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString("window.addEventListener('keydown', handleKeyDown)", $source);
        $this->assertStringContainsString("fetch('/absensi/check-in'", $source);
        $this->assertStringContainsString('JSON.stringify({ qr })', $source);
        $this->assertStringNotContainsString('<input', $source);
        $this->assertStringNotContainsString('autoFocus', $source);
        $this->assertStringContainsString('role="status"', $source);
        $this->assertStringNotContainsString('console.', $source);
    }
}
