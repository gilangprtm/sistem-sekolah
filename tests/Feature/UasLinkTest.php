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
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UasLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_uas_admin_page_exposes_delete_action_and_academic_year_filter(): void
    {
        $source = file_get_contents(resource_path('js/pages/uas/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("import DataTableShell from '@/components/data-table/data-table-shell';", $source);
        $this->assertStringContainsString('selection={{', $source);
        $this->assertStringContainsString('rowActions={{', $source);
        $this->assertStringContainsString('pagination={{', $source);
        $this->assertStringContainsString("import SearchableCombobox from '@/components/searchable-combobox';", $source);
        $this->assertStringContainsString('Hapus data {deleteTarget?.category}?', $source);
        $this->assertStringContainsString('Data {deleteTarget?.category} untuk', $source);
        $this->assertStringContainsString('Hapus Permanen', $source);
        $this->assertStringContainsString('Jenjang Kelas', $source);
        $this->assertStringContainsString('grade_level', $source);
        $this->assertStringContainsString('grade_level', file_get_contents(app_path('Http/Controllers/UasLinkController.php')));
        $this->assertStringContainsString("router.get('/kurikulum/ujian'", $source);
        $this->assertStringContainsString("['UTS', 'UAS']", $source);
        $this->assertStringContainsString('<Label>Kategori</Label>', $source);
        $this->assertStringContainsString('Kategori Ujian', $source);
        $this->assertStringContainsString('value={examCategory}', $source);
        $this->assertStringContainsString('category: examCategory', $source);
        $this->assertStringNotContainsString('role="tablist"', $source);
        $this->assertStringContainsString('Tambah Ujian', $source);

        $studentUasSource = file_get_contents(resource_path('js/pages/student-app/uas.tsx'));
        $this->assertIsString($studentUasSource);
        $this->assertStringContainsString('SecondaryPageShell title="Ujian" fallbackHref="/student"', $studentUasSource);
        $this->assertStringNotContainsString('id="student-uas-heading"', $studentUasSource);
        $this->assertStringContainsString('role="tablist"', $studentUasSource);
        $this->assertStringContainsString('shrink-0 grid-cols-2 rounded-2xl bg-muted p-1', $studentUasSource);
        $this->assertStringContainsString('min-h-0 flex-1 overflow-y-auto overscroll-contain', $studentUasSource);
        $this->assertStringContainsString("import ExamConsentDialog from '@/components/student-app/exam-consent-dialog';", $studentUasSource);
        $this->assertStringContainsString('title="Mulai ujian"', $studentUasSource);
        $consentSource = file_get_contents(resource_path('js/components/student-app/exam-consent-dialog.tsx'));
        $this->assertStringContainsString('<Link href={href}>Mulai</Link>', $consentSource);
        $this->assertStringContainsString('Aktivitas halaman ujian dicatat untuk audit.', $studentUasSource);
        $this->assertStringContainsString('setSelectedExam(null)', $studentUasSource);
        $this->assertStringContainsString('`/student/uas/${selectedExam.id}/attempt`', $studentUasSource);

        $studentLayoutSource = file_get_contents(resource_path('js/layouts/student-app-layout.tsx'));
        $this->assertIsString($studentLayoutSource);
        $this->assertStringContainsString('isKantin || isUas || isExam', $studentLayoutSource);
        $this->assertStringContainsString('isKantin || isUas', $studentLayoutSource);
        $this->assertStringContainsString("category: 'UAS'", $source);
        $this->assertStringContainsString('academic_year_id', $source);
        $this->assertStringContainsString('DataTableShell', $source);
        $this->assertStringNotContainsString("from '@/components/ui/table'", $source);
        $this->assertStringNotContainsString("from '@/components/ui/checkbox'", $source);
        $this->assertStringContainsString('DropdownMenuItem', $source);
        $this->assertStringContainsString('Monitoring', $source);
        $this->assertStringContainsString('monitor_uas_link_id', $source);
        $this->assertStringContainsString('Nama Siswa', $source);
        $this->assertStringNotContainsString('<TableHead>Status Percobaan</TableHead>', $source);
        $this->assertStringNotContainsString('window.prompt', $source);
        $this->assertStringContainsString('unlock-reason', $source);
        $this->assertStringContainsString('Alasan wajib diisi minimal 5 karakter.', $source);
        $this->assertStringContainsString('<DialogTitle>Buka ujian siswa</DialogTitle>', $source);
    }

    public function test_admin_monitoring_exposes_only_locked_three_or_four_attempts_with_pagination(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/monitoring',
        ]);
        $lockedStudent = User::factory()->create();
        $lockedStudent->assignRole('Siswa');
        $locked = Student::factory()->create(['user_id' => $lockedStudent->id, 'full_name' => 'Siswa Terkunci']);
        StudentExamAttempt::query()->create([
            'student_id' => $locked->id,
            'uas_link_id' => $link->id,
            'status' => 'locked',
            'interruption_count' => 3,
            'started_at' => now(),
            'locked_at' => now(),
        ]);
        $legacyStudent = User::factory()->create();
        $legacyStudent->assignRole('Siswa');
        $legacy = Student::factory()->create(['user_id' => $legacyStudent->id, 'full_name' => 'Siswa Legacy']);
        StudentExamAttempt::query()->create([
            'student_id' => $legacy->id,
            'uas_link_id' => $link->id,
            'status' => 'locked',
            'interruption_count' => 4,
            'started_at' => now(),
            'locked_at' => now(),
        ]);
        $activeStudent = User::factory()->create();
        $activeStudent->assignRole('Siswa');
        $active = Student::factory()->create(['user_id' => $activeStudent->id, 'full_name' => 'Siswa Aktif']);
        StudentExamAttempt::query()->create([
            'student_id' => $active->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'interruption_count' => 2,
            'started_at' => now(),
        ]);
        $lockedWrongCountStudent = User::factory()->create();
        $lockedWrongCount = Student::factory()->create(['user_id' => $lockedWrongCountStudent->id]);
        StudentExamAttempt::query()->create([
            'student_id' => $lockedWrongCount->id,
            'uas_link_id' => $link->id,
            'status' => 'locked',
            'interruption_count' => 2,
            'started_at' => now(),
            'locked_at' => now(),
        ]);
        $activeThreeCountStudent = User::factory()->create();
        $activeThreeCount = Student::factory()->create(['user_id' => $activeThreeCountStudent->id]);
        StudentExamAttempt::query()->create([
            'student_id' => $activeThreeCount->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'interruption_count' => 3,
            'started_at' => now(),
        ]);
        $operator = User::factory()->create();
        $operator->givePermissionTo('curriculum.uas.view');

        $this->actingAs($operator)->get(route('uas.index', [
            'monitor_uas_link_id' => $link->id,
            'monitor_per_page' => 10,
        ]))->assertInertia(fn ($page) => $page
            ->where('monitoring.uas_link_id', $link->id)
            ->where('monitoring.attempts.total', 2)
            ->where('monitoring.attempts.data.0.student_name', 'Siswa Legacy')
            ->where('monitoring.attempts.data.0.interruption_count', 4)
            ->where('monitoring.attempts.data.1.student_name', 'Siswa Terkunci')
            ->where('monitoring.attempts.data.1.interruption_count', 3)
            ->where('monitoring.attempts.per_page', 10));

        $historicalYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $historicalPeriod = AcademicPeriod::query()->create([
            'academic_year_id' => $historicalYear->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);
        $historicalSubject = Subject::factory()->create();
        $historicalLink = UasLink::query()->create([
            'academic_year_id' => $historicalYear->id,
            'academic_period_id' => $historicalPeriod->id,
            'subject_id' => $historicalSubject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/historical-monitoring',
        ]);

        $this->actingAs($operator)->get(route('uas.index', [
            'academic_year_id' => $historicalYear->id,
            'monitor_uas_link_id' => $historicalLink->id,
        ]))->assertInertia(fn ($page) => $page->where('monitoring', null));

        $otherCategoryLink = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UTS',
            'link' => 'https://forms.gle/other-category',
        ]);

        $this->actingAs($operator)->get(route('uas.index', [
            'category' => 'UAS',
            'monitor_uas_link_id' => $otherCategoryLink->id,
        ]))->assertInertia(fn ($page) => $page->where('monitoring', null));
    }

    public function test_deleting_uas_link_cascades_exam_attempts_and_events(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/cascade',
        ]);
        $studentUser = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $attempt = StudentExamAttempt::query()->create([
            'student_id' => $student->id,
            'uas_link_id' => $link->id,
            'status' => 'locked',
            'interruption_count' => 3,
            'started_at' => now(),
            'locked_at' => now(),
        ]);
        $event = $attempt->events()->create([
            'event_type' => 'exam_page_hidden',
            'occurred_at' => now(),
            'interruption_count' => 3,
        ]);
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.delete']);

        $this->actingAs($operator)->delete(route('uas.destroy', $link))->assertRedirect();

        $this->assertDatabaseMissing('tr_curriculum_uas_links', ['id' => $link->id]);
        $this->assertDatabaseMissing('tr_curriculum_exam_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('tr_curriculum_exam_attempt_events', ['id' => $event->id]);
    }

    public function test_admin_year_filter_returns_only_selected_year_and_preserves_query_state(): void
    {
        [$year, $period] = $this->activeContext();
        $historicalYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $historicalPeriod = AcademicPeriod::query()->create([
            'academic_year_id' => $historicalYear->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);
        $activeSubject = Subject::factory()->create(['name' => 'Aktif']);
        $oldSubject = Subject::factory()->create(['name' => 'Historis']);
        UasLink::query()->create(['academic_year_id' => $year->id, 'academic_period_id' => $period->id, 'subject_id' => $activeSubject->id, 'category' => 'UAS', 'link' => 'https://forms.gle/active']);
        UasLink::query()->create(['academic_year_id' => $historicalYear->id, 'academic_period_id' => $historicalPeriod->id, 'subject_id' => $oldSubject->id, 'category' => 'UAS', 'link' => 'https://forms.gle/historical']);
        $operator = User::factory()->create();
        $operator->givePermissionTo('curriculum.uas.view');

        $this->actingAs($operator)->get(route('uas.index', [
            'academic_year_id' => $historicalYear->id,
            'search' => 'Historis',
            'page' => 1,
            'per_page' => 1,
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.academic_year_id', $historicalYear->id)
            ->where('filters.search', 'Historis')
            ->where('filters.per_page', '1')
            ->where('links.total', 1)
            ->where('links.data.0.academic_year', '2025/2026'));

        $this->actingAs($operator)->get(route('uas.index', ['academic_year_id' => '']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.academic_year_id', null)
                ->where('links.total', 2));
    }

    public function test_admin_index_defaults_to_active_year_and_active_period_and_filters_category_server_side(): void
    {
        [$year, $period] = $this->activeContext();
        $inactivePeriod = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'genap',
            'name' => 'Semester Genap',
            'status' => 'inactive',
        ]);
        $subject = Subject::factory()->create(['name' => 'Aktif']);
        $otherSubject = Subject::factory()->create(['name' => 'Lainnya']);
        UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/active-uas',
        ]);
        UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $otherSubject->id,
            'category' => 'UTS',
            'link' => 'https://forms.gle/active-uts',
        ]);
        UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $inactivePeriod->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/inactive-period',
        ]);
        $operator = User::factory()->create();
        $operator->givePermissionTo('curriculum.uas.view');

        $this->actingAs($operator)->get(route('uas.index'))
            ->assertInertia(fn ($page) => $page
                ->where('links.total', 1)
                ->where('links.data.0.link', 'https://forms.gle/active-uas')
                ->where('activeContext.academic_period_id', $period->id));

        $this->actingAs($operator)->get(route('uas.index', ['category' => 'UTS']))
            ->assertInertia(fn ($page) => $page
                ->where('links.total', 1)
                ->where('links.data.0.link', 'https://forms.gle/active-uts')
                ->where('filters.category', 'UTS'));
    }

    public function test_authorized_user_can_delete_selected_historical_link_without_deleting_related_records(): void
    {
        [$year, $period] = $this->activeContext();
        $historicalYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $historicalPeriod = AcademicPeriod::query()->create([
            'academic_year_id' => $historicalYear->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);
        $subject = Subject::factory()->create();
        $link = UasLink::query()->create([
            'academic_year_id' => $historicalYear->id,
            'academic_period_id' => $historicalPeriod->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/historical',
        ]);
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.delete']);

        $this->actingAs($operator)
            ->delete(route('uas.destroy', $link), [
                'academic_year_id' => $historicalYear->id,
                'search' => 'Historis',
                'page' => 2,
                'per_page' => 25,
                'category' => 'UAS',
            ])
            ->assertRedirect(route('uas.index', [
                'academic_year_id' => $historicalYear->id,
                'search' => 'Historis',
                'per_page' => 25,
                'page' => 2,
                'category' => 'UAS',
            ]));

        $this->assertDatabaseMissing('tr_curriculum_uas_links', ['id' => $link->id]);
        $this->assertDatabaseHas('m_subjects', ['id' => $subject->id]);
        $this->assertDatabaseHas('m_academic_years', ['id' => $historicalYear->id]);
        $this->assertDatabaseHas('m_academic_periods', ['id' => $historicalPeriod->id]);
        $this->assertSame('2026/2027', $year->year);
        $this->assertDatabaseCount('tr_curriculum_uas_links', 0);
        $this->assertSame('https://forms.gle/historical', $link->link);
        $this->assertNull($link->opens_at);
    }

    public function test_super_admin_seeded_permission_allows_deleting_a_uas_record(): void
    {
        $this->seed(RolePermissionSeeder::class);

        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/super-admin-delete',
        ]);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');

        $this->assertTrue($superAdmin->can('curriculum.uas.delete'));

        $this->actingAs($superAdmin)
            ->delete(route('uas.destroy', $link), ['category' => 'UAS'])
            ->assertRedirect(route('uas.index', ['category' => 'UAS']));

        $this->assertDatabaseMissing('tr_curriculum_uas_links', ['id' => $link->id]);
    }

    public function test_delete_requires_permission_and_missing_route_model_returns_not_found(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('curriculum.uas.view');

        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/no-delete',
        ]);
        $this->actingAs($user)->delete(route('uas.destroy', $link))->assertForbidden();
        $this->assertDatabaseHas('tr_curriculum_uas_links', ['id' => $link->id]);

        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.delete']);
        $this->actingAs($operator)->delete(route('uas.destroy', 999999))->assertNotFound();
    }

    public function test_authorized_curriculum_user_can_create_and_update_one_link_per_subject_in_active_context(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create(['code' => 'MAT', 'name' => 'Matematika']);
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.create', 'curriculum.uas.update']);

        $this->actingAs($operator)
            ->post(route('uas.store'), [
                'category' => 'UAS',
                'subject_id' => $subject->id,
                'link' => 'https://forms.gle/matematika-uas',
                'grade_level' => 'VII',
            ])
            ->assertRedirect(route('uas.index', ['category' => 'UAS']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_uas_links', [
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/matematika-uas',
        ]);

        $this->actingAs($operator)
            ->post(route('uas.store'), [
                'category' => 'UTS',
                'grade_level' => 'VII',
                'subject_id' => $subject->id,
                'link' => 'https://forms.gle/matematika-uts',
            ])
            ->assertRedirect(route('uas.index', ['category' => 'UTS']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_uas_links', [
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UTS',
        ]);

        $link = UasLink::query()->where('category', 'UAS')->firstOrFail();
        $this->actingAs($operator)
            ->patch(route('uas.update', $link), [
                'category' => 'UAS',
                'subject_id' => $subject->id,
                'link' => 'https://docs.google.com/forms/d/matematika-uas/edit',
                'grade_level' => 'VII',
            ])
            ->assertRedirect(route('uas.index', ['category' => 'UAS']))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://docs.google.com/forms/d/matematika-uas/edit', $link->refresh()->link);
        $this->assertSame(2, UasLink::query()->count());
    }

    public function test_opening_date_is_stored_as_utc_from_wita_admin_input_and_validated_as_a_pair(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.create']);

        $this->actingAs($operator)->post(route('uas.store'), [
            'category' => 'UAS',
            'grade_level' => 'VII',
            'subject_id' => $subject->id,
            'link' => 'https://forms.gle/scheduled',
            'opens_at_date' => '2026-11-01',
            'opens_at_time' => '09:30',
        ])->assertRedirect(route('uas.index', ['category' => 'UAS']))->assertSessionHasNoErrors();

        $link = UasLink::query()->firstOrFail();
        $this->assertSame('2026-11-01 01:30:00', Carbon::parse((string) $link->opens_at)->utc()->format('Y-m-d H:i:s'));
        $this->assertSame($year->id, $link->academic_year_id);
        $this->assertSame($period->id, $link->academic_period_id);

        $this->from(route('uas.index'))->post(route('uas.store'), [
            'category' => 'UAS',
            'grade_level' => 'VII',
            'subject_id' => Subject::factory()->create()->id,
            'link' => 'https://forms.gle/unpaired',
            'opens_at_date' => '2026-11-01',
            'opens_at_time' => '',
        ])->assertSessionHasErrors('opens_at_time');

        $this->from(route('uas.index'))->post(route('uas.store'), [
            'category' => 'UAS',
            'grade_level' => 'VII',
            'subject_id' => Subject::factory()->create()->id,
            'link' => 'https://forms.gle/invalid',
            'opens_at_date' => '2026-02-30',
            'opens_at_time' => '09:30',
        ])->assertSessionHasErrors('opens_at_date');
    }

    public function test_student_uas_hides_scheduled_urls_until_open_time_and_open_gate_is_inclusive(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create(['name' => 'Bahasa Indonesia']);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->placeStudent($studentUser, $year);
        $opensAt = Carbon::parse('2026-11-01 01:30:00', 'UTC');
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/secret-form-url',
            'grade_level' => 'VII',
            'opens_at' => $opensAt,
        ]);

        Carbon::setTestNow($opensAt->copy()->subSecond());
        $this->actingAs($studentUser)->get(route('student-app.uas'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('links.0.available', false)
                ->where('links.0.category', 'UAS')
                ->has('utsLinks', 0)
                ->where('links.0.opens_at', $opensAt->toIso8601String())
                ->missing('links.0.link'));
        $this->get(route('student-app.uas.open', $link))->assertForbidden();

        Carbon::setTestNow($opensAt);
        StudentExamAttempt::query()->create([
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $this->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page
                ->where('links.0.available', true)
                ->missing('links.0.link'));
        $this->get(route('student-app.uas.open', $link))
            ->assertRedirect('https://forms.gle/secret-form-url');

        Carbon::setTestNow($opensAt->copy()->addSecond());
        $this->get(route('student-app.uas.open', $link))
            ->assertRedirect('https://forms.gle/secret-form-url');
        Carbon::setTestNow();
    }

    public function test_legacy_null_opening_time_is_immediately_available_and_open_route_revalidates_context(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->placeStudent($studentUser, $year);
        $link = UasLink::query()->create([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $subject->id,
            'category' => 'UAS',
            'link' => 'https://forms.gle/legacy',
            'grade_level' => 'VII',
            'opens_at' => null,
        ]);

        $this->actingAs($studentUser)->get(route('student-app.uas'))
            ->assertInertia(fn ($page) => $page
                ->where('links.0.available', true)
                ->missing('links.0.link'));
        StudentExamAttempt::query()->create([
            'student_id' => $studentUser->student->id,
            'uas_link_id' => $link->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $this->get(route('student-app.uas.open', $link))->assertRedirect($link->link);

        $period->update(['status' => 'inactive']);
        $this->get(route('student-app.uas.open', $link))->assertNotFound();
    }

    public function test_unauthenticated_and_non_student_users_cannot_access_student_uas_page(): void
    {
        $this->get(route('student-app.uas'))->assertRedirect(route('login'));

        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        $this->actingAs($teacher)->get(route('student-app.uas'))->assertForbidden();
    }

    public function test_unauthorized_user_cannot_manage_uas_links(): void
    {
        $user = User::factory()->create();
        $subject = Subject::factory()->create();

        $this->actingAs($user)->get(route('uas.index'))->assertForbidden();
        $this->actingAs($user)->post(route('uas.store'), ['category' => 'UAS', 'subject_id' => $subject->id, 'link' => 'https://forms.gle/test'])->assertForbidden();
        $this->assertDatabaseCount('tr_curriculum_uas_links', 0);
    }

    public function test_invalid_google_form_url_and_inactive_context_are_rejected(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create();
        $operator = User::factory()->create();
        $operator->givePermissionTo(['curriculum.uas.view', 'curriculum.uas.create']);

        $this->actingAs($operator)
            ->from(route('uas.index'))
            ->post(route('uas.store'), ['subject_id' => $subject->id, 'link' => 'https://example.com/not-a-form'])
            ->assertSessionHasErrors('link');

        $period->update(['status' => 'inactive']);
        $this->actingAs($operator)
            ->from(route('uas.index'))
            ->post(route('uas.store'), ['subject_id' => $subject->id, 'grade_level' => 'VII', 'link' => 'https://forms.gle/test'])
            ->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('tr_curriculum_uas_links', 0);
        $this->assertSame('2026/2027', $year->year);
    }

    public function test_student_pwa_sees_only_active_context_links_without_student_identity_data(): void
    {
        [$year, $period] = $this->activeContext();
        $subject = Subject::factory()->create(['code' => 'IPA', 'name' => 'IPA']);
        $otherSubject = Subject::factory()->create(['code' => 'IPS', 'name' => 'IPS']);
        UasLink::query()->create(['academic_year_id' => $year->id, 'academic_period_id' => $period->id, 'subject_id' => $subject->id, 'grade_level' => 'VII', 'link' => 'https://forms.gle/ipa']);
        $oldYear = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $oldPeriod = AcademicPeriod::query()->create(['academic_year_id' => $oldYear->id, 'code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'active']);
        UasLink::query()->create(['academic_year_id' => $oldYear->id, 'academic_period_id' => $oldPeriod->id, 'subject_id' => $otherSubject->id, 'link' => 'https://forms.gle/old']);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->placeStudent($studentUser, $year);

        $this->actingAs($studentUser)
            ->get(route('student-app.uas'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/uas')
                ->where('status', 'ready')
                ->where('links.0.subject.name', 'IPA')
                ->missing('links.0.link')
                ->missing('links.0.student_id')
                ->missing('links.0.student')
                ->missing('links.1'));
    }

    private function placeStudent(User $user, AcademicYear $year): void
    {
        $student = Student::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        StudentPlacement::query()->create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'status' => 'active',
        ]);
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
}
