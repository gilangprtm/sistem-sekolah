<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentExamAttempt;
use App\Models\Subject;
use App\Models\UasLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_must_acknowledge_before_starting_and_can_start_an_active_exam(): void
    {
        [$link, $studentUser] = $this->examForStudent();

        $this->actingAs($studentUser)->get(route('student-app.uas.attempt', $link))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/exam')
                ->where('started', false)
                ->where('formUrl', null)
                ->where('subjectCode', $link->subject->code));

        $this->actingAs($studentUser)
            ->get(route('student-app.uas.open', $link))
            ->assertForbidden();

        $this->actingAs($studentUser)
            ->postJson(route('student-app.uas.attempt.start', $link))
            ->assertOk()
            ->assertJsonPath('status', 'active');

        $this->actingAs($studentUser)
            ->get(route('student-app.uas.open', $link))
            ->assertRedirect($link->link);

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'interruption_count' => 0,
        ]);
    }

    public function test_page_hidden_and_visible_events_are_retained_with_duration(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link));
        $attempt = StudentExamAttempt::query()->firstOrFail();

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.event', $attempt), [
            'event_type' => 'exam_page_hidden',
            'occurred_at' => now()->utc()->subSeconds(15)->toIso8601String(),
            'duration_seconds' => 999,
        ])->assertOk()->assertJsonPath('interruptionCount', 0);
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.event', $attempt), [
            'event_type' => 'exam_page_visible',
            'occurred_at' => now()->utc()->subDays(10)->toIso8601String(),
            'duration_seconds' => 1,
        ])->assertOk()->assertJsonPath('interruptionCount', 1);

        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'exam_page_hidden',
        ]);
        $visibleEvent = $attempt->events()->where('event_type', 'exam_page_visible')->firstOrFail();
        $this->assertSame(1, $visibleEvent->interruption_count);
        $this->assertGreaterThanOrEqual(0, $visibleEvent->duration_seconds);
        $this->assertLessThan(5, $visibleEvent->duration_seconds);
    }

    public function test_fourth_visible_return_locks_attempt_and_raw_events_are_retained(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link));
        $attempt = StudentExamAttempt::query()->firstOrFail();

        for ($count = 1; $count <= 4; $count++) {
            $this->actingAs($studentUser)
                ->postJson(route('student-app.uas.attempt.event', $attempt), [
                    'event_type' => 'exam_page_hidden',
                    'occurred_at' => now()->utc()->subSeconds(10)->toIso8601String(),
                ])->assertOk();
            $this->actingAs($studentUser)
                ->postJson(route('student-app.uas.attempt.event', $attempt), [
                    'event_type' => 'exam_page_visible',
                    'occurred_at' => now()->utc()->toIso8601String(),
                    'duration_seconds' => 12,
                ])
                ->assertStatus($count === 4 ? 423 : 200)
                ->assertJsonPath('interruptionCount', $count);
        }

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'id' => $attempt->id,
            'status' => 'locked',
            'interruption_count' => 4,
        ]);
        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 8);

        $this->actingAs($studentUser)->get(route('student-app.uas.attempt', $link))
            ->assertInertia(fn ($page) => $page
                ->component('student-app/exam-locked')
                ->where('subjectCode', $link->subject->code)
                ->where('interruptionCount', 4));
        $this->actingAs($studentUser)->get(route('student-app.uas.open', $link))->assertStatus(423);

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.event', $attempt), [
            'event_type' => 'exam_page_visible',
        ])->assertUnprocessable();
    }

    public function test_student_cannot_unlock_an_attempt(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link));
        $attempt = StudentExamAttempt::query()->firstOrFail();
        $attempt->update(['status' => 'locked', 'locked_at' => now(), 'interruption_count' => 4]);

        $this->actingAs($studentUser)
            ->post(route('uas.attempt.unlock', $attempt), ['reason' => 'Tidak diizinkan.'])
            ->assertForbidden();
    }

    public function test_admin_can_unlock_with_reason_and_student_can_resume(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link));
        $attempt = StudentExamAttempt::query()->firstOrFail();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.event', $attempt), [
            'event_type' => 'exam_page_hidden',
            'occurred_at' => now()->utc()->subSeconds(5)->toIso8601String(),
        ]);
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.event', $attempt), [
            'event_type' => 'exam_page_visible',
            'occurred_at' => now()->utc()->toIso8601String(),
            'duration_seconds' => 5,
        ]);
        $attempt->update(['status' => 'locked', 'locked_at' => now(), 'interruption_count' => 4]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.update']);

        $this->actingAs($admin)
            ->post(route('uas.attempt.unlock', $attempt), ['reason' => 'Verifikasi oleh pengawas.'])
            ->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'id' => $attempt->id,
            'status' => 'active',
            'interruption_count' => 0,
            'unlocked_by' => $admin->id,
            'unlock_reason' => 'Verifikasi oleh pengawas.',
        ]);
        $this->assertSame(0, $attempt->fresh()->interruption_count);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'admin_unlock',
            'actor_user_id' => $admin->id,
            'reason' => 'Verifikasi oleh pengawas.',
        ]);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'exam_page_hidden',
        ]);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'exam_page_visible',
        ]);
    }

    public function test_exam_page_uses_contextual_title_and_safe_http_start_error_copy(): void
    {
        $source = file_get_contents(resource_path('js/pages/student-app/exam.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('Server menolak permintaan memulai ujian (HTTP ${response.status})', $source);
        $this->assertStringContainsString('Tidak dapat menghubungi server untuk memulai ujian.', $source);
        $this->assertStringContainsString('startRequested.current = true;', $source);
        $this->assertStringContainsString('void startAttempt();', $source);
        $this->assertStringContainsString("'Membuka formulir ujian...'", $source);
        $this->assertStringNotContainsString('Saya memahami, mulai ujian', $source);
        $this->assertStringNotContainsString('Sebelum memulai ujian', $source);
        $this->assertStringContainsString('`${category} ${subjectCode ?? \'\'}`.trim()', $source);
        $this->assertStringContainsString("'h-[calc(100dvh-3.5rem)] w-full'", $source);
        $this->assertStringContainsString('className="block size-full border-0 bg-white"', $source);
        $this->assertStringNotContainsString('Buka Google Form di tab baru', $source);
        $this->assertStringContainsString("type ActivityStatus = 'HIDDEN' | 'VISIBLE' | 'SENDING' | 'SYNCED' | 'FAILED'", $source);
        $this->assertStringContainsString('const activityLogLimit = 30', $source);
        $this->assertStringContainsString('activityStatuses.includes', $source);
        $this->assertStringContainsString('Date.parse(entry.at)', $source);
        $this->assertStringContainsString('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?Z$/', $source);
        $this->assertStringContainsString('stored.filter(isActivityEntry)', $source);
        $this->assertStringContainsString('new Date().toISOString()', $source);
        $this->assertStringContainsString('relative size-full', $source);
        $this->assertStringContainsString('sessionStorage.setItem(activityLogKey', $source);
        $this->assertStringContainsString('result.interruptionCount', $source);
        $this->assertStringContainsString('Diagnostik sesi', $source);
        $this->assertStringContainsString("import { formatWitaDateTime } from '@/lib/date-time';", $source);
        $this->assertStringContainsString('formatWitaDateTime(entry.at)', $source);
        $this->assertStringContainsString('<time dateTime={entry.at}>', $source);
        $this->assertStringContainsString('Server menolak pencatatan gangguan (HTTP ${response.status})', $source);
        $this->assertStringContainsString('Tidak dapat menghubungi server untuk mencatat gangguan halaman.', $source);
        $this->assertStringContainsString('role="alert"', $source);
        $viewSource = file_get_contents(base_path('resources/views/app.blade.php'));
        $this->assertIsString($viewSource);
        $this->assertStringContainsString('meta name="csrf-token"', $viewSource);
        $shellSource = file_get_contents(resource_path('js/components/student-app/secondary-page-shell.tsx'));
        $this->assertIsString($shellSource);
        $this->assertStringContainsString('window.history.back()', $shellSource);
        $this->assertStringNotContainsString('Periksa koneksi lalu coba kembali.', $source);
    }

    /** @return array{0: UasLink, 1: User} */
    private function examForStudent(): array
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create();
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/example',
        ]);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        Student::factory()->create(['user_id' => $studentUser->id]);

        return [$link, $studentUser];
    }
}
