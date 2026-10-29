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
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('interruptionCount', 1)
            ->assertJsonPath('warning', false)
            ->assertJsonPath('locked', false);

        $this->actingAs($studentUser)
            ->get(route('student-app.uas.open', $link))
            ->assertRedirect($link->link);

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'interruption_count' => 1,
        ]);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => StudentExamAttempt::query()->firstOrFail()->id,
            'event_type' => 'exam_attempt_started',
            'interruption_count' => 1,
        ]);
    }

    public function test_reopening_active_attempt_increments_server_count_and_warns_then_locks_at_four(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link))
            ->assertOk()
            ->assertJsonPath('interruptionCount', 1)
            ->assertJsonPath('warning', false);

        $this->actingAs($studentUser)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page
                ->component('student-app/uas')
                ->where('links.0.attempt.interruption_count', 1));

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link).'?reopen=1')
            ->assertOk()
            ->assertJsonPath('interruptionCount', 2)
            ->assertJsonPath('warning', true)
            ->assertJsonPath('locked', false);

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link).'?reopen=1')
            ->assertOk()
            ->assertJsonPath('interruptionCount', 3)
            ->assertJsonPath('warning', true);

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link).'?reopen=1')
            ->assertStatus(423)
            ->assertJsonPath('interruptionCount', 4)
            ->assertJsonPath('warning', false)
            ->assertJsonPath('locked', true);

        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'event_type' => 'exam_attempt_reopened',
            'interruption_count' => 4,
        ]);
        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'status' => 'locked',
            'interruption_count' => 4,
        ]);
    }

    public function test_stale_reopen_retry_returns_serialized_current_state_without_duplicate_event(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)
            ->postJson(route('student-app.uas.attempt.start', $link))
            ->assertOk();

        $this->actingAs($studentUser)
            ->postJson(route('student-app.uas.attempt.start', $link).'?reopen=1', [
                'expected_count' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('interruptionCount', 2)
            ->assertJsonPath('warning', true)
            ->assertJsonPath('locked', false);

        $this->actingAs($studentUser)
            ->postJson(route('student-app.uas.attempt.start', $link).'?reopen=1', [
                'expected_count' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('interruptionCount', 2)
            ->assertJsonPath('warning', true)
            ->assertJsonPath('locked', false);

        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 2);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'event_type' => 'exam_attempt_reopened',
            'interruption_count' => 2,
        ]);
    }

    public function test_stale_retry_after_count_four_returns_locked_current_state_without_duplicate_event(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $startRoute = route('student-app.uas.attempt.start', $link);

        $this->actingAs($studentUser)->postJson($startRoute)->assertOk();

        foreach ([1, 2] as $expectedCount) {
            $this->actingAs($studentUser)
                ->postJson($startRoute.'?reopen=1', ['expected_count' => $expectedCount])
                ->assertOk();
        }

        $this->actingAs($studentUser)
            ->postJson($startRoute.'?reopen=1', ['expected_count' => 3])
            ->assertStatus(423)
            ->assertJsonPath('status', 'locked')
            ->assertJsonPath('interruptionCount', 4)
            ->assertJsonPath('warning', false)
            ->assertJsonPath('locked', true);

        $this->actingAs($studentUser)
            ->postJson($startRoute.'?reopen=1', ['expected_count' => 3])
            ->assertStatus(423)
            ->assertJsonPath('status', 'locked')
            ->assertJsonPath('interruptionCount', 4)
            ->assertJsonPath('warning', false)
            ->assertJsonPath('locked', true);

        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 4);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'event_type' => 'exam_attempt_reopened',
            'interruption_count' => 4,
        ]);
    }

    public function test_student_can_complete_active_attempt_and_retry_without_duplicate_completion_event(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $startResponse = $this->actingAs($studentUser)
            ->postJson(route('student-app.uas.attempt.start', $link));
        $attemptId = $startResponse->json('attemptId');
        $completeRoute = route('student-app.uas.attempt.complete', $attemptId);

        $this->actingAs($studentUser)
            ->postJson($completeRoute)
            ->assertOk()
            ->assertJsonPath('status', 'locked')
            ->assertJsonPath('interruptionCount', 4);

        $this->actingAs($studentUser)
            ->postJson($completeRoute)
            ->assertOk()
            ->assertJsonPath('status', 'locked')
            ->assertJsonPath('interruptionCount', 4);

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'id' => $attemptId,
            'status' => 'locked',
            'interruption_count' => 4,
        ]);
        $this->actingAs($studentUser)
            ->get(route('student-app.uas.attempt', $link))
            ->assertInertia(fn ($page) => $page->component('student-app/exam-locked'));
        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 2);
        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attemptId,
            'event_type' => 'exam_attempt_completed',
            'interruption_count' => 4,
        ]);
    }

    public function test_student_cannot_complete_another_students_attempt(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $attempt = StudentExamAttempt::query()->create([
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'interruption_count' => 1,
            'started_at' => now(),
        ]);
        $otherUser = User::factory()->create();
        Student::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->assignRole('Siswa');

        $this->actingAs($otherUser)
            ->postJson(route('student-app.uas.attempt.complete', $attempt))
            ->assertNotFound();

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'id' => $attempt->id,
            'status' => 'active',
            'interruption_count' => 1,
        ]);
        $this->assertDatabaseMissing('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'exam_attempt_completed',
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
        ])->assertOk()->assertJsonPath('interruptionCount', 1);
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.event', $attempt), [
            'event_type' => 'exam_page_visible',
            'occurred_at' => now()->utc()->subDays(10)->toIso8601String(),
            'duration_seconds' => 1,
        ])->assertOk()->assertJsonPath('interruptionCount', 2);

        $this->assertDatabaseHas('tr_curriculum_exam_attempt_events', [
            'exam_attempt_id' => $attempt->id,
            'event_type' => 'exam_page_hidden',
        ]);
        $visibleEvent = $attempt->events()->where('event_type', 'exam_page_visible')->firstOrFail();
        $this->assertSame(2, $visibleEvent->interruption_count);
        $this->assertIsInt($visibleEvent->duration_seconds);
        $this->assertGreaterThanOrEqual(0, $visibleEvent->duration_seconds);
        $this->assertLessThan(5, $visibleEvent->duration_seconds);
    }

    public function test_third_visible_return_locks_attempt_and_raw_events_are_retained(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link));
        $attempt = StudentExamAttempt::query()->firstOrFail();

        for ($count = 1; $count <= 3; $count++) {
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
                ->assertStatus($count === 3 ? 423 : 200)
                ->assertJsonPath('interruptionCount', $count + 1)
                ->assertJsonPath('warning', $count < 3);
        }

        $this->assertDatabaseHas('tr_curriculum_exam_attempts', [
            'id' => $attempt->id,
            'status' => 'locked',
            'interruption_count' => 4,
        ]);
        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 7);

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

    public function test_legacy_zero_count_attempt_is_initialized_once_and_non_reopen_retry_is_idempotent(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $attempt = StudentExamAttempt::query()->create([
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'started_at' => now(),
            'interruption_count' => 0,
        ]);

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link))
            ->assertOk()
            ->assertJsonPath('interruptionCount', 1);
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link))
            ->assertOk()
            ->assertJsonPath('interruptionCount', 1);

        $this->assertSame(1, $attempt->fresh()->interruption_count);
        $this->assertDatabaseCount('tr_curriculum_exam_attempt_events', 1);
    }

    public function test_student_cannot_unlock_an_attempt(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link));
        $attempt = StudentExamAttempt::query()->firstOrFail();
        $attempt->update(['status' => 'locked', 'locked_at' => now(), 'interruption_count' => 3]);

        $this->actingAs($studentUser)
            ->post(route('uas.attempt.unlock', $attempt), ['reason' => 'Tidak diizinkan.'])
            ->assertForbidden();
    }

    public function test_admin_unlock_resets_attempt_for_fresh_first_start(): void
    {
        [$link, $studentUser] = $this->examForStudent();
        $attempt = StudentExamAttempt::query()->create([
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'locked',
            'started_at' => now(),
            'locked_at' => now(),
            'interruption_count' => 4,
        ]);
        $admin = User::factory()->create();
        $admin->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.update']);

        $this->actingAs($admin)
            ->post(route('uas.attempt.unlock', $attempt), ['reason' => 'Verifikasi oleh pengawas.'])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Percobaan ujian berhasil dibuka kembali.');

        $this->actingAs($studentUser)->postJson(route('student-app.uas.attempt.start', $link))
            ->assertOk()
            ->assertJsonPath('interruptionCount', 1)
            ->assertJsonPath('warning', false)
            ->assertJsonPath('locked', false);
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
        $attempt->update(['status' => 'locked', 'locked_at' => now(), 'interruption_count' => 3]);
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
        $this->assertStringContainsString('if (!response.ok && response.status !== 423)', $source);
        $this->assertStringContainsString('setWarning(\'\')', $source);
        $this->assertStringContainsString('interruption_count >', file_get_contents(resource_path('js/pages/student-app/uas.tsx')));
        $this->assertStringContainsString("'?reopen=1'", $source);
        $this->assertStringContainsString('window.location.replace(`/student/uas/${linkId}/attempt`)', $source);
        $this->assertSame(2, substr_count($source, 'window.location.replace(`/student/uas/${linkId}/attempt`)'));
        $this->assertStringNotContainsString('window.location.assign(`/student/uas/${linkId}/attempt`)', $source);
        $this->assertStringContainsString('startRequested.current = true;', $source);
        $this->assertStringContainsString('void startAttempt();', $source);
        $this->assertStringContainsString("'Membuka formulir ujian...'", $source);
        $this->assertStringNotContainsString('Saya memahami, mulai ujian', $source);
        $this->assertStringNotContainsString('Sebelum memulai ujian', $source);
        $this->assertStringContainsString('`${category} ${subjectCode ?? \'\'}`.trim()', $source);
        $this->assertStringContainsString("'h-[calc(100dvh-3.5rem)] w-full'", $source);
        $this->assertStringContainsString('className="block size-full border-0 bg-white"', $source);
        $this->assertStringNotContainsString('Buka Google Form di tab baru', $source);
        $this->assertStringContainsString('window.location.replace(\'/student/uas\')', $source);
        $this->assertStringContainsString('Selesaikan ujian?', $source);
        $this->assertStringContainsString('Batal', $source);
        $this->assertStringContainsString('Selesai', $source);
        $this->assertStringContainsString('/complete', $source);
        $this->assertStringContainsString('const [completionError, setCompletionError] = useState(\'\');', $source);
        $this->assertStringContainsString('{completionError && (', $source);
        $this->assertStringContainsString('setCompletionError(', $source);
        $this->assertStringNotContainsString("setError(\n                'Tidak dapat menghubungi server untuk menyelesaikan ujian.", $source);
        $this->assertStringNotContainsString('Diagnostik sesi', $source);
        $this->assertStringNotContainsString('sessionStorage', $source);
        $this->assertStringNotContainsString('count/${INTERRUPTION_LIMIT}', $source);
        $this->assertStringContainsString('exam_page_hidden', file_get_contents(app_path('Services/StudentExamAttemptService.php')));
        $this->assertStringContainsString('Kamu telah keluar aplikasi, kamu tidak akan bisa mengikuti ujian jika tetap keluar dari aplikasi', $source);
        $this->assertStringContainsString('Jika kamu keluar aplikasi sekali lagi, ujian akan langsung terkunci.', $source);
        $this->assertStringContainsString('result.interruptionCount === 2', $source);
        $this->assertStringContainsString('result.interruptionCount === 3', $source);
        $this->assertStringContainsString('Server menolak pencatatan gangguan (HTTP ${response.status})', $source);
        $this->assertStringContainsString('Tidak dapat menghubungi server untuk mencatat gangguan halaman.', $source);
        $this->assertStringContainsString('role="alert"', $source);
        $viewSource = file_get_contents(base_path('resources/views/app.blade.php'));
        $this->assertIsString($viewSource);
        $this->assertStringContainsString('meta name="csrf-token"', $viewSource);
        $shellSource = file_get_contents(resource_path('js/components/student-app/secondary-page-shell.tsx'));
        $this->assertIsString($shellSource);
        $this->assertStringContainsString('window.history.back()', $shellSource);
        $uasSource = file_get_contents(resource_path('js/pages/student-app/uas.tsx'));
        $this->assertIsString($uasSource);
        $this->assertStringContainsString("import { Head, Link, router } from '@inertiajs/react';", $uasSource);
        $this->assertStringContainsString("window.addEventListener('pageshow', refreshAttempts);", $uasSource);
        $this->assertStringContainsString("window.addEventListener('focus', refreshAttempts);", $uasSource);
        $this->assertStringContainsString("router.on('navigate'", $uasSource);
        $this->assertStringContainsString("detail.page.component === 'student-app/uas'", $uasSource);
        $this->assertStringContainsString('refreshInFlight.current', $uasSource);
        $this->assertStringContainsString('onFinish: () => {', $uasSource);
        $this->assertStringContainsString('router.reload({', $uasSource);
        $this->assertStringContainsString('only: [', $uasSource);
        $this->assertStringContainsString("'links',", $uasSource);
        $this->assertStringContainsString('interruption_count >', $uasSource);
        $this->assertStringContainsString('href={`/student/uas/${item.id}/attempt?reopen=1`}', $uasSource);
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
            'grade_level' => 'VII',
        ]);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        StudentPlacement::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'status' => 'active',
        ]);

        return [$link, $studentUser];
    }
}
