<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\LmsCourse;
use App\Models\LmsCourseRun;
use App\Models\Rombel;
use App\Models\RombelPeriodUsage;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use App\Services\ScheduleReadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $permission = Permission::findOrCreate('curriculum.schedule.manage', 'web');
        $user->givePermissionTo($permission);

        return $user;
    }

    private function activePeriod(): AcademicPeriod
    {
        $year = AcademicYear::query()->create([
            'year' => '2026/2027',
            'status' => 'active',
        ]);

        return $year->periods()->create([
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'active',
        ]);
    }

    public function test_schedule_viewer_shows_only_active_periods_and_uses_full_width_line_tabs(): void
    {
        $activePeriod = $this->activePeriod();
        $inactiveYear = AcademicYear::query()->create([
            'year' => '2025/2026',
            'status' => 'inactive',
        ]);
        $inactiveYear->periods()->create([
            'code' => 'genap',
            'name' => 'Semester Genap',
            'status' => 'active',
        ]);
        $inactivePeriodYear = AcademicYear::query()->create([
            'year' => '2024/2025',
            'status' => 'active',
        ]);
        $inactivePeriodYear->periods()->create([
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);

        $this->actingAs($this->admin())
            ->get('/kurikulum/schedule')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schedules/index')
                ->has('periods', 1)
                ->where('periods.0.id', $activePeriod->id));

        $source = file_get_contents(resource_path('js/pages/schedules/index.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString('variant="line"', $source);
        $this->assertStringContainsString(
            'className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"',
            $source,
        );
        $this->assertStringNotContainsString(
            'grid gap-2 rounded-xl border p-4 sm:max-w-xl',
            $source,
        );
        $this->assertLessThan(
            strpos($source, '<TabsList'),
            strpos($source, '<SearchableCombobox'),
        );
    }

    public function test_super_admin_can_view_read_only_schedule_for_all_weekdays(): void
    {
        $period = $this->activePeriod();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/kurikulum/schedule')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schedules/index')
                ->where('selectedPeriod.id', $period->id)
                ->where('days.0.label', 'Senin')
                ->where('days.4.label', 'Jumat')
                ->where('days.0.rows.0.number', 1)
                ->where('days.0.rows.3.type', 'break'));
    }

    public function test_legacy_schedule_paths_redirect_to_singular_viewer(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/jadwal-pelajaran?grade=VIII')
            ->assertRedirect('/kurikulum/schedule?grade=VIII');

        $this->actingAs($admin)
            ->get('/schedules?grade=VIII')
            ->assertRedirect('/kurikulum/schedule?grade=VIII');
    }

    public function test_legacy_plural_preparation_path_redirects_to_singular_path(): void
    {
        $this->actingAs($this->admin())
            ->get('/schedules/generate?grade=VIII')
            ->assertRedirect('/kurikulum/schedule/generate?grade=VIII');
    }

    public function test_schedule_route_names_use_singular_schedule_prefix(): void
    {
        $this->assertSame('/kurikulum/schedule', route('schedule.index', absolute: false));
        $this->assertSame('/kurikulum/schedule/generate', route('schedule.generate', absolute: false));
    }

    public function test_selected_active_rombels_are_schedule_columns(): void
    {
        $this->activePeriod();
        $admin = $this->admin();
        $viiA = Rombel::factory()->create([
            'code' => 'VII-A',
            'name' => 'Kelas VII A',
            'grade_level' => 'VII',
            'parallel_code' => 'A',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/kurikulum/schedule?grade=VII&rombel_ids[]='.$viiA->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('selectedRombels', 1)
                ->where('selectedRombels.0.id', $viiA->id)
                ->where('selectedRombelIds.0', $viiA->id));
    }

    public function test_schedule_hydrates_persisted_selected_rombel_context_without_assignments(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(8)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $selectedRombelIds = $rombels->pluck('id')->all();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $selectedRombelIds,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => array_slice($selectedRombelIds, 0, 3)]],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get('/kurikulum/schedule?grade=VII')
            ->assertInertia(fn ($page) => $page
                ->has('selectedRombels', 8)
                ->where('selectedRombelIds', $selectedRombelIds)
                ->has('entries', 3));
    }

    public function test_saving_different_grades_keeps_each_published_plan_in_the_same_period(): void
    {
        $period = $this->activePeriod();
        $viiRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $viiiRombel = Rombel::factory()->create(['grade_level' => 'VIII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $viiTeacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[0]->id, 'subject_id' => $subject->id]);
        $viiiTeacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[1]->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiTeacherSubject->id, 'rombel_ids' => [$viiRombel->id]]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VIII',
            'selected_rombel_ids' => [$viiiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiiTeacherSubject->id, 'rombel_ids' => [$viiiRombel->id]]],
        ])->assertRedirect();

        $this->assertSame(2, SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->count());
    }

    public function test_saving_same_grade_updates_only_its_published_plan(): void
    {
        $period = $this->activePeriod();
        $viiRombels = Rombel::factory()->count(2)->create(['grade_level' => 'VII', 'status' => 'active']);
        $viiiRombel = Rombel::factory()->create(['grade_level' => 'VIII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teachers = Teacher::factory()->count(3)->create(['status' => 'active', 'staff_type' => 'guru']);
        $viiTeacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[0]->id, 'subject_id' => $subject->id]);
        $viiReplacementTeacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[1]->id, 'subject_id' => $subject->id]);
        $viiiTeacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[2]->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombels[0]->id],
            'assignments' => [['teacher_subject_id' => $viiTeacherSubject->id, 'rombel_ids' => [$viiRombels[0]->id]]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VIII',
            'selected_rombel_ids' => [$viiiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiiTeacherSubject->id, 'rombel_ids' => [$viiiRombel->id]]],
        ])->assertRedirect();
        $viiiPlanId = SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->latest('id')->value('id');

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombels[1]->id],
            'assignments' => [['teacher_subject_id' => $viiReplacementTeacherSubject->id, 'rombel_ids' => [$viiRombels[1]->id]]],
        ])->assertRedirect();

        $this->assertSame(2, SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->count());
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $viiiPlanId, 'status' => 'published']);
        $this->assertSame(0, SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'archived')->count());
    }

    public function test_missing_grade_tab_is_empty_until_that_grade_is_saved(): void
    {
        $period = $this->activePeriod();
        $viiRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombel->id],
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$viiRombel->id]]],
        ])->assertRedirect();

        $this->actingAs($admin)->get('/kurikulum/schedule?grade=IX')->assertOk()->assertInertia(fn ($page) => $page
            ->has('selectedRombels', 0)
            ->has('entries', 0));
    }

    public function test_legacy_publish_endpoint_is_disabled_without_archiving_sibling_grades(): void
    {
        $period = $this->activePeriod();
        $viiRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $viiiRombel = Rombel::factory()->create(['grade_level' => 'VIII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();

        foreach ([[$viiRombel, $teachers[0]], [$viiiRombel, $teachers[1]]] as [$rombel, $teacher]) {
            $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
            $this->actingAs($admin)->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'grade' => $rombel->grade_level,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post('/kurikulum/schedule/publish', ['schedule_plan_id' => SchedulePlan::query()->firstOrFail()->id])
            ->assertNotFound();
        $this->assertSame(2, SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->count());
    }

    public function test_generate_hydrates_only_the_requested_grade_plan(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->createMany([
            ['grade_level' => 'VII', 'status' => 'active'],
            ['grade_level' => 'VIII', 'status' => 'active'],
        ]);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();
        $planIds = [];

        foreach ($rombels as $index => $rombel) {
            $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[$index]->id, 'subject_id' => $subject->id]);
            $this->actingAs($admin)->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'grade' => $rombel->grade_level,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])->assertRedirect();
            $planIds[$rombel->grade_level] = SchedulePlan::query()->where('academic_period_id', $period->id)
                ->where('grade_level', $rombel->grade_level)->where('status', 'published')->value('id');
        }

        $this->actingAs($admin)->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VIII')
            ->assertInertia(fn ($page) => $page->where('draftPlan.id', $planIds['VIII'])
                ->where('draftPlan.teaching_assignments.0.rombel_id', $rombels[1]->id));
    }

    public function test_generate_restores_all_saved_rombels_when_returning_to_a_grade_without_rombel_query(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(7)->create(['grade_level' => 'VII', 'status' => 'active']);
        $viiiRombel = Rombel::factory()->create(['grade_level' => 'VIII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $selectedIds = $rombels->pluck('id')->all();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => $selectedIds,
            'custom_slots' => [['day' => 'friday', 'lesson_number' => 1, 'label' => 'Kegiatan VII']],
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombels[0]->id]]],
        ])->assertRedirect();

        $this->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII')
            ->assertInertia(fn ($page) => $page
                ->where('selectedRombelIds', $selectedIds)
                ->has('selectedRombels', 7)
                ->has('draftPlan.teaching_assignments', 1)
                ->where('draftPlan.custom_slots.0.label', 'Kegiatan VII'));
        $this->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VIII')
            ->assertInertia(fn ($page) => $page
                ->where('grade', 'VIII')
                ->where('selectedRombelIds', [])
                ->where('draftPlan', null)
                ->where('availableRombels.0.id', $viiiRombel->id));
        $this->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII')
            ->assertInertia(fn ($page) => $page
                ->where('grade', 'VII')
                ->where('selectedRombelIds', $selectedIds)
                ->has('selectedRombels', 7)
                ->where('draftPlan.teaching_assignments.0.rombel_id', $rombels[0]->id)
                ->has('draftPlan.manual_placements', 1)
                ->where('draftPlan.custom_slots.0.label', 'Kegiatan VII'));
    }

    public function test_generate_restores_saved_selection_independently_of_search_and_excludes_inactive_rombels(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(3)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        $this->actingAs($this->admin())->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => $rombels->pluck('id')->all(),
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombels[0]->id]]],
        ])->assertRedirect();
        $rombels[2]->update(['status' => 'inactive']);

        $this->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII&search=not-found')
            ->assertInertia(fn ($page) => $page
                ->has('availableRombels', 0)
                ->where('selectedRombelIds', [$rombels[0]->id, $rombels[1]->id])
                ->has('selectedRombels', 2));
    }

    public function test_schedule_grade_delete_removes_only_target_plan_and_is_idempotent(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->createMany([
            ['grade_level' => 'VII', 'status' => 'active'],
            ['grade_level' => 'VIII', 'status' => 'active'],
        ]);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();
        $plans = [];

        foreach ($rombels as $index => $rombel) {
            $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teachers[$index]->id, 'subject_id' => $subject->id]);
            $this->actingAs($admin)->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'grade' => $rombel->grade_level,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])->assertRedirect();
            $plans[$rombel->grade_level] = SchedulePlan::query()->where('academic_period_id', $period->id)
                ->where('grade_level', $rombel->grade_level)->where('status', 'published')->firstOrFail();
        }

        $this->actingAs($admin)->delete('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertRedirect();
        $this->assertDatabaseMissing('tr_curriculum_schedule_plans', ['id' => $plans['VII']->id]);
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plans['VIII']->id, 'status' => 'published']);
        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', ['schedule_plan_id' => $plans['VII']->id]);

        $this->actingAs($admin)->delete('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertRedirect();
    }

    public function test_schedule_grade_delete_targets_published_plan_not_older_archived_plan(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();
        $archivedPlan = SchedulePlan::factory()->create([
            'academic_period_id' => $period->id,
            'grade_level' => 'VII',
            'status' => 'archived',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('academic_period_id', $period->id)
            ->where('grade_level', 'VII')->where('status', 'published')->firstOrFail();
        $plan->update(['source_plan_id' => $archivedPlan->id]);

        $this->actingAs($admin)->delete('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertRedirect();

        $this->assertModelMissing($plan);
        $this->assertModelExists($archivedPlan);
    }

    public function test_schedule_grade_delete_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->delete('/kurikulum/schedule', [
            'academic_period_id' => $this->activePeriod()->id,
            'grade' => 'VII',
        ])->assertForbidden();
    }

    public function test_schedule_grade_delete_rolls_back_when_plan_has_dependents(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('grade_level', 'VII')->firstOrFail();
        $dependent = SchedulePlan::factory()->create([
            'academic_period_id' => $period->id,
            'grade_level' => 'VIII',
            'created_by' => $admin->id,
            'source_plan_id' => $plan->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertSessionHasErrors([
            'schedule' => 'Jadwal memiliki versi turunan dan tidak dapat dihapus.',
        ]);
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'published']);
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $dependent->id, 'status' => 'draft']);
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 1);
    }

    public function test_schedule_view_reads_requested_grade_from_matching_published_period_data(): void
    {
        $period = $this->activePeriod();
        $viiRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $viiiRombel = Rombel::factory()->create(['grade_level' => 'VIII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $viiAnchor = TeacherSubject::factory()->create(['teacher_id' => $teachers[0]->id, 'subject_id' => $subject->id]);
        $viiiAnchor = TeacherSubject::factory()->create(['teacher_id' => $teachers[1]->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiAnchor->id, 'rombel_ids' => [$viiRombel->id]]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VIII',
            'selected_rombel_ids' => [$viiiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiiAnchor->id, 'rombel_ids' => [$viiiRombel->id]]],
        ])->assertRedirect();

        $this->assertSame(2, SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->count());
        $this->actingAs($admin)->get('/kurikulum/schedule?grade=VII')->assertInertia(fn ($page) => $page
            ->where('selectedRombelIds.0', $viiRombel->id)
            ->where('entries.0.rombel_id', $viiRombel->id));
        $this->actingAs($admin)->get('/kurikulum/schedule?grade=VIII')->assertInertia(fn ($page) => $page
            ->where('selectedRombelIds.0', $viiiRombel->id)
            ->where('entries.0.rombel_id', $viiiRombel->id));
    }

    public function test_schedule_defaults_to_published_plan_rombels_when_no_selection_is_given(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get('/kurikulum/schedule?grade=VII')
            ->assertInertia(fn ($page) => $page
                ->where('selectedRombelIds.0', $rombel->id)
                ->where('selectedRombels.0.id', $rombel->id)
                ->has('entries', 1));
    }

    public function test_published_schedule_entry_can_be_moved_from_viewer(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->firstOrFail();

        $this->actingAs($admin)
            ->post('/kurikulum/schedule/move', [
                'schedule_plan_id' => $plan->id,
                'schedule_entry_id' => $entry->id,
                'day' => 'tuesday',
                'lesson_number' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $entry->id,
            'schedule_plan_id' => $plan->id,
            'day' => 'tuesday',
            'lesson_number' => 4,
            'start_time' => '09:50',
            'end_time' => '10:30',
        ]);
    }

    /** @return array{admin: User, period: AcademicPeriod, rombel: Rombel, plan: SchedulePlan, source: ScheduleEntry, target: ScheduleEntry} */
    private function swapContext(bool $sameTeacher = false): array
    {
        $admin = $this->admin();
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $subjects = Subject::factory()->count(2)->create(['jp_per_class' => 1]);
        $relations = [];
        foreach ($subjects as $index => $subject) {
            $relations[] = TeacherSubject::factory()->create([
                'subject_id' => $subject->id,
                'teacher_id' => $index === 0 || $sameTeacher
                    ? $teacher->id
                    : Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru'])->id,
            ]);
        }
        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$rombel->id],
            'assignments' => array_map(fn (TeacherSubject $relation): array => [
                'teacher_subject_id' => $relation->id, 'rombel_ids' => [$rombel->id],
            ], $relations),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $source = $plan->entries()->where('subject_id', $subjects[0]->id)->firstOrFail();
        $target = $plan->entries()->where('subject_id', $subjects[1]->id)->firstOrFail();
        $source->update(['day' => 'monday', 'lesson_number' => 3, 'start_time' => '08:50', 'end_time' => '09:30']);
        $target->update(['day' => 'wednesday', 'lesson_number' => 5, 'start_time' => '10:30', 'end_time' => '11:10']);

        return compact('admin', 'period', 'rombel', 'plan', 'source', 'target');
    }

    #[DataProvider('swapTeacherCases')]
    public function test_schedule_swap_exchanges_two_entries_only_in_same_class(bool $sameTeacher): void
    {
        ['rombel' => $rombel, 'plan' => $plan, 'source' => $source, 'target' => $target] = $this->swapContext($sameTeacher);
        $beforeAssignments = $plan->teachingAssignments()->orderBy('id')->get()->toArray();

        $this->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
            'selected_rombel_ids' => [$rombel->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $source->id, 'teaching_assignment_id' => $source->teaching_assignment_id,
            'subject_id' => $source->subject_id, 'teacher_id' => $source->teacher_id, 'rombel_id' => $rombel->id,
            'day' => 'wednesday', 'lesson_number' => 5, 'start_time' => '10:30', 'end_time' => '11:10',
        ]);
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $target->id, 'teaching_assignment_id' => $target->teaching_assignment_id,
            'subject_id' => $target->subject_id, 'teacher_id' => $target->teacher_id, 'rombel_id' => $rombel->id,
            'day' => 'monday', 'lesson_number' => 3, 'start_time' => '08:50', 'end_time' => '09:30',
        ]);
        $this->assertSame(2, $plan->entries()->count());
        $this->assertSame($beforeAssignments, $plan->teachingAssignments()->orderBy('id')->get()->toArray());
    }

    /** @return array<string, array{bool}> */
    public static function swapTeacherCases(): array
    {
        return ['different teachers' => [false], 'same teacher' => [true]];
    }

    #[DataProvider('swapConflictCases')]
    public function test_schedule_swap_checks_teacher_conflicts_in_both_directions_and_rolls_back(bool $reverse, bool $otherPlan): void
    {
        ['period' => $period, 'rombel' => $rombel, 'plan' => $plan, 'source' => $source, 'target' => $target] = $this->swapContext();
        $otherRombel = Rombel::factory()->create(['grade_level' => $otherPlan ? 'VIII' : 'VII']);
        $conflictPlan = $otherPlan ? SchedulePlan::factory()->create([
            'academic_period_id' => $period->id, 'grade_level' => 'VIII', 'status' => 'published', 'created_by' => $plan->created_by,
        ]) : $plan;
        $conflict = $reverse ? $target : $source;
        $conflictAssignment = $conflictPlan->teachingAssignments()->create([
            ...$conflict->teachingAssignment->only(['academic_period_id', 'subject_id', 'teacher_subject_id', 'teacher_id', 'weekly_jp', 'subject_code', 'subject_name', 'teacher_name', 'status']),
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
        ]);
        $conflictPlan->entries()->create([
            ...$conflict->only(['academic_period_id', 'subject_id', 'teacher_id', 'subject_code', 'subject_name', 'teacher_name']),
            'teaching_assignment_id' => $conflictAssignment->id,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
            'day' => $reverse ? 'monday' : 'wednesday', 'lesson_number' => $reverse ? 3 : 5,
            'start_time' => $reverse ? '08:50' : '10:30', 'end_time' => $reverse ? '09:30' : '11:10',
        ]);
        $before = ScheduleEntry::query()->orderBy('id')->get()->toArray();
        $beforePlan = $plan->fresh()->toArray();

        $this->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
            'selected_rombel_ids' => [$otherRombel->id],
        ])->assertRedirect()->assertSessionHasErrors(['move' => 'Slot tujuan bertabrakan dengan jadwal lain.']);

        $this->assertSame($before, ScheduleEntry::query()->orderBy('id')->get()->toArray());
        $this->assertSame($beforePlan, $plan->fresh()->toArray());
    }

    /** @return array<string, array{bool, bool}> */
    public static function swapConflictCases(): array
    {
        return [
            'source teacher other class' => [false, false], 'target teacher other class' => [true, false],
            'source teacher other grade' => [false, true], 'target teacher other grade' => [true, true],
        ];
    }

    #[DataProvider('swapConflictCases')]
    public function test_schedule_swap_accepts_combined_subject_for_either_teacher_and_grade(bool $reverse, bool $otherPlan): void
    {
        ['period' => $period, 'rombel' => $rombel, 'plan' => $plan, 'source' => $source, 'target' => $target] = $this->swapContext();
        $combined = $reverse ? $target : $source;
        $combined->subject->update(['allow_combined' => true]);
        $otherRombel = Rombel::factory()->create(['grade_level' => $otherPlan ? 'VIII' : 'VII']);
        $conflictPlan = $otherPlan ? SchedulePlan::factory()->create([
            'academic_period_id' => $period->id, 'grade_level' => 'VIII', 'status' => 'published', 'created_by' => $plan->created_by,
        ]) : $plan;
        $assignment = $conflictPlan->teachingAssignments()->create([
            ...$combined->teachingAssignment->only(['academic_period_id', 'subject_id', 'teacher_subject_id', 'teacher_id', 'weekly_jp', 'subject_code', 'subject_name', 'teacher_name', 'status']),
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
        ]);
        $conflictPlan->entries()->create([
            ...$combined->only(['academic_period_id', 'subject_id', 'teacher_id', 'subject_code', 'subject_name', 'teacher_name']),
            'teaching_assignment_id' => $assignment->id,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
            'day' => $reverse ? 'monday' : 'wednesday', 'lesson_number' => $reverse ? 3 : 5,
            'start_time' => $reverse ? '08:50' : '10:30', 'end_time' => $reverse ? '09:30' : '11:10',
        ]);
        $beforeOtherClass = $conflictPlan->entries()->where('rombel_id', $otherRombel->id)->get()->toArray();

        $this->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_schedule_entries', ['id' => $source->id, 'day' => 'wednesday', 'lesson_number' => 5]);
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', ['id' => $target->id, 'day' => 'monday', 'lesson_number' => 3]);
        $this->assertSame($beforeOtherClass, $conflictPlan->entries()->where('rombel_id', $otherRombel->id)->get()->toArray());
    }

    public function test_schedule_swap_rejects_another_class_and_preserves_both_entries(): void
    {
        ['plan' => $plan, 'source' => $source] = $this->swapContext();
        $otherRombel = Rombel::factory()->create();
        $before = $plan->entries()->orderBy('id')->get()->toArray();

        $this->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $otherRombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ])->assertRedirect()->assertSessionHasErrors([
            'move' => 'Jadwal hanya dapat dipindahkan atau ditukar dalam rombel yang sama.',
        ]);

        $this->assertSame($before, $plan->entries()->orderBy('id')->get()->toArray());
    }

    public function test_schedule_swap_allows_same_subject_with_different_teacher_in_another_class(): void
    {
        ['period' => $period, 'rombel' => $rombel, 'plan' => $plan, 'source' => $source] = $this->swapContext();
        $otherRombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $otherTeacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $relation = TeacherSubject::factory()->create(['subject_id' => $source->subject_id, 'teacher_id' => $otherTeacher->id]);
        $assignment = $plan->teachingAssignments()->create([
            ...$source->teachingAssignment->only(['academic_period_id', 'subject_id', 'weekly_jp', 'subject_code', 'subject_name', 'status']),
            'teacher_subject_id' => $relation->id, 'teacher_id' => $otherTeacher->id, 'teacher_name' => $otherTeacher->full_name,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
        ]);
        $otherEntry = $plan->entries()->create([
            ...$source->only(['academic_period_id', 'subject_id', 'subject_code', 'subject_name']),
            'teaching_assignment_id' => $assignment->id, 'teacher_id' => $otherTeacher->id, 'teacher_name' => $otherTeacher->full_name,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
            'day' => 'wednesday', 'lesson_number' => 5, 'start_time' => '10:30', 'end_time' => '11:10',
        ]);
        $before = $otherEntry->fresh()->toArray();

        $this->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tr_curriculum_schedule_entries', ['id' => $source->id, 'day' => 'wednesday', 'lesson_number' => 5]);
        $this->assertSame($before, $otherEntry->fresh()->toArray());
    }

    public function test_schedule_swap_allows_combined_subject_but_rejects_different_subject(): void
    {
        ['period' => $period, 'rombel' => $rombel, 'plan' => $plan, 'source' => $source] = $this->swapContext();
        $source->subject->update(['allow_combined' => true]);
        $otherRombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $otherTeacher = $source->teachingAssignment->teacherSubject->teacher;
        $relation = $source->teachingAssignment->teacherSubject;
        $assignment = $plan->teachingAssignments()->create([
            ...$source->teachingAssignment->only(['academic_period_id', 'subject_id', 'weekly_jp', 'subject_code', 'subject_name', 'status']),
            'teacher_subject_id' => $relation->id, 'teacher_id' => $otherTeacher->id, 'teacher_name' => $otherTeacher->full_name,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
        ]);
        $combinedEntry = $plan->entries()->create([
            ...$source->only(['academic_period_id', 'subject_id', 'subject_code', 'subject_name']),
            'teaching_assignment_id' => $assignment->id, 'teacher_id' => $otherTeacher->id, 'teacher_name' => $otherTeacher->full_name,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
            'day' => 'wednesday', 'lesson_number' => 5, 'start_time' => '10:30', 'end_time' => '11:10',
        ]);

        $this->actingAs($this->admin())->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $source->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ]);
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $combinedEntry->id, 'rombel_id' => $otherRombel->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ]);

        $differentSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $differentTeacher = $otherTeacher;
        $differentRelation = TeacherSubject::factory()->create([
            'subject_id' => $differentSubject->id, 'teacher_id' => $differentTeacher->id,
        ]);
        $differentAssignment = $plan->teachingAssignments()->create([
            ...$source->teachingAssignment->only(['academic_period_id', 'weekly_jp', 'status']),
            'subject_id' => $differentSubject->id, 'teacher_subject_id' => $differentRelation->id,
            'teacher_id' => $differentTeacher->id, 'teacher_name' => $differentTeacher->full_name,
            'subject_code' => $differentSubject->code, 'subject_name' => $differentSubject->name,
            'rombel_id' => $otherRombel->id, 'rombel_code' => $otherRombel->code,
        ]);
        $plan->entries()->create([
            'academic_period_id' => $period->id, 'teaching_assignment_id' => $differentAssignment->id,
            'subject_id' => $differentSubject->id, 'teacher_id' => $differentTeacher->id,
            'rombel_id' => $otherRombel->id, 'day' => 'monday', 'lesson_number' => 3,
            'start_time' => '08:50', 'end_time' => '09:30', 'subject_code' => $differentSubject->code,
            'subject_name' => $differentSubject->name, 'teacher_name' => $differentTeacher->full_name,
            'rombel_code' => $otherRombel->code,
        ]);

        $this->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'monday', 'lesson_number' => 3,
        ])->assertRedirect()->assertSessionHasErrors('move');
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $source->id, 'day' => 'wednesday', 'lesson_number' => 5,
        ]);
    }

    public function test_schedule_swap_rejects_special_activity_and_forbidden_user(): void
    {
        ['rombel' => $rombel, 'plan' => $plan, 'source' => $source] = $this->swapContext();
        $source->subject->update(['allow_combined' => true]);
        $plan->customSlots()->create(['academic_period_id' => $plan->academic_period_id, 'day' => 'wednesday', 'lesson_number' => 5, 'start_time' => '10:30', 'end_time' => '11:10', 'label' => 'Literasi']);
        $before = $plan->entries()->orderBy('id')->get()->toArray();
        $payload = ['schedule_plan_id' => $plan->id, 'schedule_entry_id' => $source->id,
            'target_rombel_id' => $rombel->id, 'day' => 'wednesday', 'lesson_number' => 5];

        $this->post('/kurikulum/schedule/move', $payload)->assertRedirect()
            ->assertSessionHasErrors(['move' => 'Slot tujuan merupakan kegiatan khusus.']);
        $this->actingAs(User::factory()->create())->post('/kurikulum/schedule/move', $payload)->assertForbidden();

        $this->assertSame($before, $plan->entries()->orderBy('id')->get()->toArray());
    }

    public function test_empty_schedule_slot_places_all_remaining_weekly_jp_from_selected_day(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['code' => 'IPA', 'name' => 'IPA', 'jp_per_class' => 5]);
        $teacher = Teacher::factory()->create(['full_name' => 'Pak Budi', 'status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => [$rombel->id],
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $assignment = ScheduleTeachingAssignment::query()->where('schedule_plan_id', $plan->id)->firstOrFail();
        ScheduleEntry::query()->where('teaching_assignment_id', $assignment->id)->delete();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id,
            'rombel_id' => $rombel->id,
            'teacher_subject_id' => $teacherSubject->id,
            'day' => 'monday',
            'lesson_number' => 4,
            'selected_rombel_ids' => [$rombel->id],
        ])->assertRedirect();

        $entries = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->where('rombel_id', $rombel->id)->get();
        $this->assertCount(5, $entries);
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'schedule_plan_id' => $plan->id,
            'rombel_id' => $rombel->id,
            'day' => 'monday',
            'lesson_number' => 4,
            'subject_code' => 'IPA',
            'teacher_name' => 'Pak Budi',
        ]);
        $this->assertSame(5, ScheduleEntry::query()->where('teaching_assignment_id', $assignment->id)->count());
        $this->assertSame([4, 5, 6], $entries->where('day', 'monday')->pluck('lesson_number')->sort()->values()->all());
        $this->assertLessThanOrEqual(3, $entries->groupBy('day')->map->count()->max());
    }

    public function test_empty_schedule_slot_preserves_schedule_when_selected_slot_conflicts(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(2)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['code' => 'IPA', 'name' => 'IPA', 'jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $rombels->pluck('id')->all(),
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombels[0]->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $existingEntry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->firstOrFail();
        $existingEntry->update(['day' => 'monday', 'lesson_number' => 4]);

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id,
            'rombel_id' => $rombels[1]->id,
            'teacher_subject_id' => $teacherSubject->id,
            'day' => 'monday',
            'lesson_number' => 4,
            'selected_rombel_ids' => $rombels->pluck('id')->all(),
        ])->assertRedirect()->assertSessionHasErrors([
            'schedule' => 'IPA — '.$teacher->full_name.' — '.$rombels[1]->code.': Slot yang dipilih tidak tersedia, bertabrakan dengan jadwal lain, atau merupakan kegiatan khusus.',
        ]);

        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 1);
        $this->assertDatabaseCount('tr_curriculum_teaching_assignments', 1);
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $existingEntry->id,
            'day' => 'monday',
            'lesson_number' => 4,
        ]);
    }

    public function test_schedule_activity_applies_to_all_classes_in_the_published_plan(): void
    {
        $context = $this->placementContext();
        $hiddenRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $context['plan']->update(['selected_rombel_ids' => [$context['rombel']->id, $hiddenRombel->id]]);
        $originalHash = $context['plan']->payload_hash;

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'wednesday',
            'lesson_number' => 5,
            'label' => '  Literasi  ',
            'selected_rombel_ids' => [$context['rombel']->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_schedule_custom_slots', [
            'schedule_plan_id' => $context['plan']->id,
            'academic_period_id' => $context['period']->id,
            'day' => 'wednesday',
            'lesson_number' => 5,
            'start_time' => '10:30',
            'end_time' => '11:10',
            'label' => 'Literasi',
        ]);
        $this->assertNotSame($originalHash, $context['plan']->fresh()->payload_hash);
        $this->assertSame(2, $context['plan']->fresh()->revision);
        $this->assertSame([$context['rombel']->id, $hiddenRombel->id], $context['plan']->fresh()->selected_rombel_ids);

        $this->actingAs($context['admin'])
            ->get('/kurikulum/schedule?academic_period_id='.$context['period']->id.'&grade=VII&rombel_ids[]='.$context['rombel']->id)
            ->assertInertia(fn ($page) => $page
                ->where('days.2.rows.5.type', 'custom')
                ->where('days.2.rows.5.label', 'Literasi'));

        $this->actingAs($context['admin'])
            ->get('/kurikulum/schedule?academic_period_id='.$context['period']->id.'&grade=VII&rombel_ids[]='.$hiddenRombel->id)
            ->assertInertia(fn ($page) => $page
                ->where('days.2.rows.5.type', 'custom')
                ->where('days.2.rows.5.label', 'Literasi'));

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $context['plan']->id,
            'rombel_id' => $context['rombel']->id,
            'teacher_subject_id' => $context['relation']->id,
            'day' => 'wednesday',
            'lesson_number' => 5,
        ])->assertRedirect()->assertSessionHasErrors(['schedule']);

        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 0);
    }

    public function test_schedule_activity_rejects_populated_row_without_changing_entries_or_activity(): void
    {
        $context = $this->placementContext(1);
        $hiddenRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $context['plan']->update(['selected_rombel_ids' => [$context['rombel']->id, $hiddenRombel->id]]);
        $this->actingAs($context['admin'])->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $context['plan']->id,
            'rombel_id' => $hiddenRombel->id,
            'teacher_subject_id' => $context['relation']->id,
            'day' => 'monday',
            'lesson_number' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $entry = $context['plan']->entries()->firstOrFail();
        $originalHash = $context['plan']->fresh()->payload_hash;
        $originalRevision = $context['plan']->fresh()->revision;

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'monday',
            'lesson_number' => 1,
            'label' => 'Upacara',
            'selected_rombel_ids' => [$context['rombel']->id],
        ])->assertRedirect()->assertSessionHasErrors([
            'activity' => 'Masih ada mata pelajaran pada baris ini. Hapus atau pindahkan jadwal pelajaran sebelum menambahkan kegiatan.',
        ]);

        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $entry->id,
            'day' => 'monday',
            'lesson_number' => 1,
        ]);
        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
        $this->assertSame($originalHash, $context['plan']->fresh()->payload_hash);
        $this->assertSame($originalRevision, $context['plan']->fresh()->revision);
    }

    public function test_schedule_activity_updates_existing_special_slot_and_forbids_user_without_manage_permission(): void
    {
        $context = $this->placementContext();
        $slot = $context['plan']->customSlots()->create([
            'academic_period_id' => $context['period']->id,
            'day' => 'friday',
            'lesson_number' => 1,
            'start_time' => '07:30',
            'end_time' => '08:10',
            'label' => 'Kegiatan lama',
        ]);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('curriculum.view', 'web'));

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'friday',
            'lesson_number' => 1,
            'label' => 'Kegiatan baru',
        ])->assertRedirect();

        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 1);
        $this->assertDatabaseHas('tr_curriculum_schedule_custom_slots', [
            'id' => $slot->id,
            'label' => 'Kegiatan baru',
        ]);
        $this->assertSame(2, $context['plan']->fresh()->revision);

        $this->actingAs($viewer)->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'thursday',
            'lesson_number' => 1,
            'label' => 'Privileged activity',
        ])->assertForbidden();

        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 1);
    }

    public function test_schedule_activity_requires_a_valid_label_and_configured_slot(): void
    {
        $context = $this->placementContext();

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'monday',
            'lesson_number' => 15,
            'label' => '   ',
        ])->assertRedirect()->assertSessionHasErrors(['label']);

        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
        $this->assertSame(1, $context['plan']->fresh()->revision);

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'friday',
            'lesson_number' => 8,
            'label' => 'Literasi',
        ])->assertRedirect()->assertSessionHasErrors([
            'activity' => 'Slot kegiatan tidak sesuai konfigurasi jadwal.',
        ]);

        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
        $this->assertSame(1, $context['plan']->fresh()->revision);
    }

    #[DataProvider('inactiveActivityContexts')]
    public function test_schedule_activity_rejects_inactive_or_unpublished_plan(string $state, string $error): void
    {
        $context = $this->placementContext();
        match ($state) {
            'period' => $context['period']->update(['status' => 'inactive']),
            'year' => $context['period']->academicYear->update(['status' => 'inactive']),
            default => $context['plan']->update(['status' => $state]),
        };
        $originalHash = $context['plan']->payload_hash;

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'monday',
            'lesson_number' => 1,
            'label' => 'Literasi',
        ])->assertRedirect()->assertSessionHasErrors([$error]);

        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
        $this->assertSame(1, $context['plan']->fresh()->revision);
        $this->assertSame($originalHash, $context['plan']->fresh()->payload_hash);
    }

    /** @return array<string, array{string, string}> */
    public static function inactiveActivityContexts(): array
    {
        return [
            'inactive period' => ['period', 'activity'],
            'inactive year' => ['year', 'activity'],
            'archived plan' => ['archived', 'schedule_plan_id'],
            'draft plan' => ['draft', 'schedule_plan_id'],
        ];
    }

    public function test_schedule_activity_does_not_change_another_grade_and_requires_authentication(): void
    {
        $context = $this->placementContext();
        $otherPlan = SchedulePlan::factory()->create([
            'created_by' => $context['admin']->id,
            'academic_period_id' => $context['period']->id,
            'grade_level' => 'VIII',
            'status' => 'published',
        ]);
        $otherHash = $otherPlan->payload_hash;

        $this->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'monday',
            'lesson_number' => 1,
            'label' => 'Literasi',
        ])->assertRedirect('/login');

        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);

        $this->actingAs($context['admin'])->post('/kurikulum/schedule/activity', [
            'schedule_plan_id' => $context['plan']->id,
            'day' => 'monday',
            'lesson_number' => 1,
            'label' => 'Literasi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, $otherPlan->customSlots()->count());
        $this->assertSame(1, $otherPlan->fresh()->revision);
        $this->assertSame($otherHash, $otherPlan->fresh()->payload_hash);
    }

    public function test_schedule_placement_is_forbidden_without_manage_permission(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create();
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $plan = SchedulePlan::factory()->create([
            'created_by' => $this->admin()->id,
            'academic_period_id' => $period->id,
            'grade_level' => 'VII',
            'status' => 'published',
            'selected_rombel_ids' => [$rombel->id],
            'published_at' => now(),
        ]);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('curriculum.view', 'web'));

        $this->actingAs($viewer)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id,
            'rombel_id' => $rombel->id,
            'teacher_subject_id' => $teacherSubject->id,
            'day' => 'monday',
            'lesson_number' => 1,
        ])->assertForbidden();

        $this->assertDatabaseCount('tr_curriculum_teaching_assignments', 0);
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 0);
    }

    /** @return array{admin: User, period: AcademicPeriod, rombel: Rombel, relation: TeacherSubject, plan: SchedulePlan} */
    private function placementContext(int $weeklyJp = 5): array
    {
        $admin = $this->admin();
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['code' => 'VII-A', 'grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['name' => 'IPA', 'jp_per_class' => $weeklyJp, 'status' => 'active']);
        $teacher = Teacher::factory()->create(['full_name' => 'Pak Budi', 'status' => 'active', 'staff_type' => 'guru']);
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'IPA01']);
        $plan = SchedulePlan::factory()->create([
            'created_by' => $admin->id, 'academic_period_id' => $period->id, 'grade_level' => 'VII',
            'status' => 'published', 'published_at' => now(), 'selected_rombel_ids' => [$rombel->id],
        ]);

        return compact('admin', 'period', 'rombel', 'relation', 'plan');
    }

    public function test_empty_schedule_slot_adds_new_subject_without_changing_other_class_or_existing_entries(): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $otherRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $plan->update(['selected_rombel_ids' => [$rombel->id, $otherRombel->id]]);

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $otherRombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'tuesday', 'lesson_number' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $before = $plan->entries()->orderBy('id')->get()->toArray();

        $response = $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 4, 'selected_rombel_ids' => [$rombel->id, $otherRombel->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entries = $plan->entries()->where('rombel_id', $rombel->id)->get();
        $this->assertCount(5, $entries);
        $this->assertSame(3, $entries->where('day', 'monday')->count());
        $this->assertSame(2, $entries->where('day', '!=', 'monday')->count());
        $this->assertSame($before, $plan->entries()->where('rombel_id', $otherRombel->id)->orderBy('id')->get()->toArray());
        $this->assertSame(1, $plan->teachingAssignments()->where('rombel_id', $rombel->id)->count());
        $this->assertSame(10, $plan->entries()->get()->unique(fn (ScheduleEntry $entry): string => $entry->teacher_id.':'.$entry->day.':'.$entry->lesson_number)->count());
        $this->assertSame(3, $plan->fresh()->revision);
        $this->assertNotSame($plan->payload_hash, $plan->fresh()->payload_hash);
        $this->actingAs($admin)->get($response->headers->get('Location'))->assertInertia(fn ($page) => $page
            ->where('selectedPeriod.id', $period->id)->where('grade', 'VII')
            ->has('selectedRombels', 2)->has('entries', 10)->has('placementOptions', 0));
    }

    #[DataProvider('combinedPlacementCases')]
    public function test_empty_schedule_slot_combines_only_enabled_same_subject_in_other_classes(bool $enabled, bool $sameSubject, bool $otherGrade, bool $allowed): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext(3);
        $relation->subject->update(['allow_combined' => $enabled]);
        $otherRombel = Rombel::factory()->create(['grade_level' => $otherGrade ? 'VIII' : 'VII', 'status' => 'active']);
        $otherPlan = $otherGrade ? SchedulePlan::factory()->create([
            'created_by' => $admin->id, 'academic_period_id' => $period->id, 'grade_level' => 'VIII',
            'status' => 'published', 'selected_rombel_ids' => [$otherRombel->id],
        ]) : $plan;
        if (! $otherGrade) {
            $plan->update(['selected_rombel_ids' => [$rombel->id, $otherRombel->id]]);
        }
        $otherRelation = $sameSubject ? $relation : TeacherSubject::factory()->create([
            'teacher_id' => $relation->teacher_id,
            'subject_id' => Subject::factory()->create(['jp_per_class' => 3, 'allow_combined' => true])->id,
        ]);
        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $otherPlan->id, 'rombel_id' => $otherRombel->id,
            'teacher_subject_id' => $otherRelation->id, 'day' => 'monday', 'lesson_number' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $before = ScheduleEntry::query()->orderBy('id')->get()->toArray();

        $response = $this->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id,
            'teacher_subject_id' => $relation->id, 'day' => 'monday', 'lesson_number' => 1,
        ])->assertRedirect();

        if ($allowed) {
            $response->assertSessionHasNoErrors();
            $this->assertSame(3, $plan->entries()->where('rombel_id', $rombel->id)->where('day', 'monday')->count());
            $this->assertSame(6, ScheduleEntry::query()->count());
        } else {
            $response->assertSessionHasErrors('schedule');
            $this->assertSame($before, ScheduleEntry::query()->orderBy('id')->get()->toArray());
            $this->assertSame(0, $plan->teachingAssignments()->where('rombel_id', $rombel->id)->count());
        }
    }

    /** @return array<string, array{bool, bool, bool, bool}> */
    public static function combinedPlacementCases(): array
    {
        return [
            'enabled same plan' => [true, true, false, true],
            'enabled other grade' => [true, true, true, true],
            'disabled same plan' => [false, true, false, false],
            'disabled other grade' => [false, true, true, false],
            'different subject same plan' => [true, false, false, false],
            'different subject other grade' => [true, false, true, false],
        ];
    }

    public function test_combined_flag_does_not_allow_two_subjects_in_the_same_rombel_slot(): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext(1);
        $relation->subject->update(['allow_combined' => true]);
        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id,
            'teacher_subject_id' => $relation->id, 'day' => 'monday', 'lesson_number' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $otherSubject = Subject::factory()->create(['allow_combined' => true, 'jp_per_class' => 1]);
        $otherTeacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $otherRelation = TeacherSubject::factory()->create(['subject_id' => $otherSubject->id, 'teacher_id' => $otherTeacher->id]);
        $before = $plan->entries()->get()->toArray();

        $this->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id,
            'teacher_subject_id' => $otherRelation->id, 'day' => 'monday', 'lesson_number' => 1,
        ])->assertRedirect()->assertSessionHasErrors('schedule');

        $this->assertSame($before, $plan->entries()->get()->toArray());
        $this->assertSame(1, $plan->teachingAssignments()->count());
    }

    public function test_empty_schedule_slot_fills_only_missing_jp_and_counts_existing_daily_jp(): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan->entries()->where('day', '<>', 'monday')->delete();
        $plan->entries()->where('day', 'monday')->where('lesson_number', '>', 1)->delete();
        $original = $plan->entries()->firstOrFail()->toArray();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [...$payload, 'lesson_number' => 4])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(5, $plan->entries()->count());
        $this->assertSame(3, $plan->entries()->where('day', 'monday')->count());
        $this->assertSame($original, $plan->entries()->whereKey($original['id'])->firstOrFail()->toArray());
        $this->assertSame(1, $plan->teachingAssignments()->count());
    }

    public function test_empty_schedule_slot_skips_special_activities_and_cross_grade_teacher_conflicts(): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $otherRombel = Rombel::factory()->create(['grade_level' => 'VIII', 'status' => 'active']);
        $otherPlan = SchedulePlan::factory()->create([
            'created_by' => $admin->id, 'academic_period_id' => $period->id, 'grade_level' => 'VIII',
            'status' => 'published', 'published_at' => now(), 'selected_rombel_ids' => [$otherRombel->id],
        ]);
        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $otherPlan->id, 'rombel_id' => $otherRombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $plan->customSlots()->create([
            'academic_period_id' => $period->id, 'day' => 'monday', 'lesson_number' => 5,
            'start_time' => '10:30', 'end_time' => '11:10', 'label' => 'Kegiatan',
        ]);

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(5, $plan->entries()->count());
        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', ['schedule_plan_id' => $plan->id, 'day' => 'monday', 'lesson_number' => 5]);
        $allEntries = ScheduleEntry::query()->get();
        $this->assertSame(10, $allEntries->unique(fn (ScheduleEntry $entry): string => $entry->teacher_id.':'.$entry->day.':'.$entry->lesson_number)->count());
        $this->assertLessThanOrEqual(3, $plan->entries()->get()->countBy('day')->max());
    }

    public function test_empty_schedule_slot_rolls_back_all_jp_when_week_has_insufficient_capacity(): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        foreach (app(ScheduleReadService::class)->lessonSlotsByDay() as $day => $slots) {
            foreach ($slots as $slot) {
                if ($day === 'monday' && $slot['number'] === 4) {
                    continue;
                }
                $plan->customSlots()->create([
                    'academic_period_id' => $period->id, 'day' => $day, 'lesson_number' => $slot['number'],
                    'start_time' => $slot['start'], 'end_time' => $slot['end'], 'label' => 'Kegiatan',
                ]);
            }
        }
        $before = $plan->fresh()->toArray();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [
            'schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 4,
        ])->assertRedirect()->assertSessionHasErrors('schedule');

        $this->assertSame(0, $plan->entries()->count());
        $this->assertSame(0, $plan->teachingAssignments()->count());
        $this->assertSame($before, $plan->fresh()->toArray());
        $this->assertSame(42, $plan->customSlots()->count());
    }

    #[DataProvider('invalidPlacementContexts')]
    public function test_empty_schedule_slot_rejects_invalid_context_without_changes(string $case, string $errorKey): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'friday', 'lesson_number' => 4];
        match ($case) {
            'inactive-period' => $period->update(['status' => 'inactive']),
            'inactive-year' => $period->academicYear->update(['status' => 'inactive']),
            'inactive-subject' => $relation->subject->update(['status' => 'inactive']),
            'inactive-teacher' => $relation->teacher->update(['status' => 'inactive']),
            'non-teacher' => $relation->teacher->update(['staff_type' => 'tendik']),
            'archived-plan' => $plan->update(['status' => 'archived']),
            'wrong-grade' => $rombel->update(['grade_level' => 'VIII']),
            'unselected-class' => $plan->update(['selected_rombel_ids' => []]),
            'invalid-friday-slot' => $payload['lesson_number'] = 9,
            'custom-slot' => $plan->customSlots()->create([
                'academic_period_id' => $period->id, 'day' => 'friday', 'lesson_number' => 4,
                'start_time' => '10:00', 'end_time' => '10:40', 'label' => 'Kegiatan',
            ]),
        };
        $before = $plan->fresh()->toArray();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)
            ->assertRedirect()->assertSessionHasErrors($errorKey);

        $this->assertSame(0, $plan->entries()->count());
        $this->assertSame(0, $plan->teachingAssignments()->count());
        $this->assertSame($before, $plan->fresh()->toArray());
    }

    /** @return array<string, array{string, string}> */
    public static function invalidPlacementContexts(): array
    {
        return [
            'inactive period' => ['inactive-period', 'schedule'],
            'inactive year' => ['inactive-year', 'schedule'],
            'inactive subject' => ['inactive-subject', 'teacher_subject_id'],
            'inactive teacher' => ['inactive-teacher', 'teacher_subject_id'],
            'non teacher' => ['non-teacher', 'teacher_subject_id'],
            'archived plan' => ['archived-plan', 'schedule_plan_id'],
            'wrong grade' => ['wrong-grade', 'schedule'],
            'unselected class' => ['unselected-class', 'schedule'],
            'invalid Friday lesson' => ['invalid-friday-slot', 'schedule'],
            'special activity' => ['custom-slot', 'schedule'],
        ];
    }

    public function test_empty_schedule_slot_rejects_completed_subject_and_unauthenticated_requests(): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext(1);
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->post('/kurikulum/schedule/place', $payload)->assertRedirect(route('login'));
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [...$payload, 'lesson_number' => 4])
            ->assertRedirect()->assertSessionHasErrors(['schedule' => 'IPA — Pak Budi — VII-A: Seluruh JP mingguan sudah terjadwal.']);

        $this->assertSame(1, $plan->entries()->count());
        $this->assertSame(1, $plan->teachingAssignments()->count());
    }

    public function test_empty_schedule_slot_rejects_fourth_daily_jp_and_preserves_existing_schedule(): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan->entries()->where('day', '<>', 'monday')->delete();
        $before = $plan->entries()->get()->toArray();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [...$payload, 'lesson_number' => 4])
            ->assertRedirect()->assertSessionHasErrors(['schedule' => 'IPA — Pak Budi — VII-A: Batas maksimal 3 JP pada hari yang dipilih sudah terpenuhi.']);

        $this->assertSame($before, $plan->entries()->get()->toArray());
    }

    public function test_empty_schedule_slot_cannot_replace_existing_subject_teacher(): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $otherRelation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $relation->subject_id]);
        $before = $plan->entries()->get()->toArray();

        $this->actingAs($admin)->post('/kurikulum/schedule/place', [...$payload, 'teacher_subject_id' => $otherRelation->id, 'lesson_number' => 4])
            ->assertRedirect()->assertSessionHasErrors(['teacher_subject_id' => 'Mata pelajaran ini sudah memiliki guru lain pada rombel tersebut.']);

        $this->assertSame($before, $plan->entries()->get()->toArray());
        $this->assertSame($relation->id, $plan->teachingAssignments()->firstOrFail()->teacher_subject_id);
    }

    public function test_empty_schedule_slot_uses_current_master_weekly_jp_for_existing_assignments(): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan->entries()->where('day', '<>', 'monday')->delete();
        $plan->entries()->where('lesson_number', '>', 1)->delete();
        $relation->subject->update(['jp_per_class' => 3]);

        $this->actingAs($admin)->get(route('schedule.index', ['grade' => 'VII']))
            ->assertInertia(fn ($page) => $page->has('placementOptions', 1)
                ->where('placementOptions.0.weekly_jp', 3)->where('placementOptions.0.remaining_jp', 2));
        $this->actingAs($admin)->post('/kurikulum/schedule/place', [...$payload, 'day' => 'tuesday'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, $plan->entries()->where('rombel_id', $rombel->id)->count());
        $this->assertSame(1, $plan->entries()->where('rombel_id', $rombel->id)->where('day', 'monday')->count());
        $this->assertSame(2, $plan->entries()->where('rombel_id', $rombel->id)->where('day', 'tuesday')->count());
        $this->assertSame(3, $plan->teachingAssignments()->where('rombel_id', $rombel->id)->firstOrFail()->weekly_jp);
    }

    #[DataProvider('changedMasterWeeklyQuotas')]
    public function test_empty_schedule_slot_rejects_additions_when_current_master_quota_is_met(int $masterWeeklyJp, string $reason): void
    {
        ['admin' => $admin, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $relation->subject->update(['jp_per_class' => $masterWeeklyJp]);
        $plan->teachingAssignments()->update(['weekly_jp' => 13]);
        $beforeEntries = $plan->entries()->orderBy('id')->get()->toArray();
        $beforePlan = $plan->fresh()->toArray();

        $this->actingAs($admin)->get(route('schedule.index', ['grade' => 'VII']))
            ->assertInertia(fn ($page) => $page->has('placementOptions', 0));
        $this->actingAs($admin)->post('/kurikulum/schedule/place', [...$payload, 'day' => 'wednesday'])
            ->assertRedirect()->assertSessionHasErrors(['schedule' => 'IPA — Pak Budi — VII-A: '.$reason]);

        $this->assertSame($beforeEntries, $plan->entries()->orderBy('id')->get()->toArray());
        $this->assertSame($beforePlan, $plan->fresh()->toArray());
        $this->assertSame(13, $plan->teachingAssignments()->firstOrFail()->weekly_jp);
    }

    /** @return array<string, array{int, string}> */
    public static function changedMasterWeeklyQuotas(): array
    {
        return [
            'quota already met' => [5, 'Seluruh JP mingguan sudah terjadwal.'],
            'existing entries exceed reduced master quota' => [3, 'Jadwal yang sudah ada melebihi 3 JP per minggu dari Master Mata Pelajaran. Periksa jadwal sebelum menambah JP.'],
        ];
    }

    public function test_empty_schedule_slot_options_expose_only_remaining_jp_and_honor_manage_permission(): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext();
        $payload = ['schedule_plan_id' => $plan->id, 'rombel_id' => $rombel->id, 'teacher_subject_id' => $relation->id,
            'day' => 'monday', 'lesson_number' => 1];
        $this->actingAs($admin)->post('/kurikulum/schedule/place', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan->entries()->where('day', '<>', 'monday')->delete();
        $viewerUrl = route('schedule.index', ['academic_period_id' => $period->id, 'grade' => 'VII']);

        $this->actingAs($admin)->get($viewerUrl)->assertInertia(fn ($page) => $page
            ->where('editablePlanId', $plan->id)->where('canManageSchedule', true)
            ->has('placementOptions', 1)->where('placementOptions.0.remaining_jp', 2)
            ->where('placementOptions.0.weekly_jp', 5)
            ->where('placementOptions.0.subject_id', $relation->subject_id)
            ->where('placementOptions.0.teacher_subject_id', $relation->id)
            ->where('placementOptions.0.subject_name', 'IPA')
            ->where('placementOptions.0.label', 'IPA01 - Pak Budi'));
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::findOrCreate('curriculum.view', 'web'));
        $this->actingAs($viewer)->get($viewerUrl)->assertInertia(fn ($page) => $page
            ->where('canManageSchedule', false)->has('placementOptions', 0));
    }

    public function test_empty_schedule_slot_missing_required_fields_returns_validation_errors(): void
    {
        $this->actingAs($this->admin())->post('/kurikulum/schedule/place', [])
            ->assertRedirect()->assertSessionHasErrors(['schedule_plan_id', 'rombel_id', 'teacher_subject_id', 'day', 'lesson_number']);

        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 0);
        $this->assertDatabaseCount('tr_curriculum_teaching_assignments', 0);
    }

    /** @return array<string, array{string}> */
    public static function scheduleGrades(): array
    {
        return [
            'grade VII' => ['VII'],
            'grade VIII' => ['VIII'],
            'grade IX' => ['IX'],
        ];
    }

    #[DataProvider('scheduleGrades')]
    public function test_viewer_move_redirect_preserves_plan_grade_and_selected_rombels(string $grade): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => $grade, 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'grade' => $grade,
            'selected_rombel_ids' => [$rombel->id],
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = $plan->entries()->firstOrFail();

        $response = $this->actingAs($admin)->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id,
            'schedule_entry_id' => $entry->id,
            'selected_rombel_ids' => [$rombel->id],
            'day' => 'tuesday',
            'lesson_number' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $location = $response->headers->get('Location');
        $this->assertStringContainsString('grade='.$grade, $location);
        $this->assertStringContainsString('rombel_ids%5B0%5D='.$rombel->id, $location);
        $this->actingAs($admin)
            ->get(parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY))
            ->assertSessionHasNoErrors()
            ->assertInertia(fn ($page) => $page
                ->where('grade', $grade)
                ->where('selectedRombelIds.0', $rombel->id)
                ->has('entries', 1));
        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $entry->id,
            'day' => 'tuesday',
            'lesson_number' => 4,
        ]);
    }

    public function test_viewer_move_preserves_all_selected_rombels_when_assignment_covers_subset(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(8)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $selectedRombelIds = $rombels->pluck('id')->all();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $selectedRombelIds,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => array_slice($selectedRombelIds, 0, 3)]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->firstOrFail();

        $response = $this->actingAs($admin)->post('/kurikulum/schedule/move', [
            'schedule_plan_id' => $plan->id,
            'schedule_entry_id' => $entry->id,
            'selected_rombel_ids' => $selectedRombelIds,
            'day' => 'tuesday',
            'lesson_number' => 4,
        ])->assertRedirect();

        $location = $response->headers->get('Location');
        foreach ($selectedRombelIds as $rombelId) {
            $this->assertStringContainsString('rombel_ids%5B', $location);
            $this->assertStringContainsString('='.$rombelId, $location);
        }

        $this->actingAs($admin)
            ->get(parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY))
            ->assertInertia(fn ($page) => $page
                ->has('selectedRombels', 8)
                ->where('selectedRombelIds', $selectedRombelIds));
    }

    public function test_viewer_move_rejects_teacher_or_rombel_conflicts_and_preserves_original_entry(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(2)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => $rombels->pluck('id')->all()]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->where('rombel_id', $rombels[1]->id)->firstOrFail();
        $original = $entry->only(['day', 'lesson_number']);

        $this->actingAs($admin)
            ->post('/kurikulum/schedule/move', [
                'schedule_plan_id' => $plan->id,
                'schedule_entry_id' => $entry->id,
                'day' => 'monday',
                'lesson_number' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'move' => 'Slot tujuan bertabrakan dengan jadwal lain.',
            ]);

        $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
            'id' => $entry->id,
            'day' => $original['day'],
            'lesson_number' => $original['lesson_number'],
        ]);
    }

    public function test_selection_must_contain_active_rombel_from_selected_grade(): void
    {
        $this->activePeriod();
        $viii = Rombel::factory()->create(['grade_level' => 'VIII']);

        $this->actingAs($this->admin())
            ->get('/kurikulum/schedule?grade=VII&rombel_ids[]='.$viii->id)
            ->assertSessionHasErrors('rombel_ids.0');
    }

    public function test_schedule_preparation_is_separate_from_read_only_viewer(): void
    {
        $this->activePeriod();
        $this->actingAs($this->admin())
            ->get('/kurikulum/schedule/generate')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('schedules/generate')
                ->has('availableRombels')
                ->has('subjects')
                ->has('teacherSubjects')
                ->where('grade', 'VII'));
    }

    public function test_schedule_preparation_loads_global_active_rombels_and_teacher_subject_anchors(): void
    {
        $period = $this->activePeriod();
        $otherPeriod = AcademicYear::query()->create(['year' => '2027/2028', 'status' => 'inactive'])
            ->periods()->create(['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'inactive']);
        $activeRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $notUsedRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $inactiveRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'inactive']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $activeRombel->id]);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $otherPeriod->id, 'rombel_id' => $notUsedRombel->id]);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $inactiveRombel->id]);

        $subject = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        $teacher = Teacher::factory()->create(['full_name' => 'Guru Aktif', 'status' => 'active', 'staff_type' => 'guru']);
        $inactiveTeacher = Teacher::factory()->create(['full_name' => 'Guru Nonaktif', 'status' => 'inactive', 'staff_type' => 'guru']);
        TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'MTK01']);
        TeacherSubject::factory()->create(['teacher_id' => $inactiveTeacher->id, 'subject_id' => $subject->id, 'code' => 'MTK02']);

        $this->actingAs($this->admin())
            ->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('availableRombels', 2)
                ->where('availableRombels', function (Collection $rombels) use ($activeRombel, $notUsedRombel): bool {
                    return collect($rombels)->pluck('id')->sort()->values()->all() === collect([$activeRombel->id, $notUsedRombel->id])->sort()->values()->all();
                })
                ->has('subjects', 1)
                ->where('subjects.0.id', $subject->id)
                ->has('teacherSubjects', 1)
                ->where('teacherSubjects.0.teacher_name', 'Guru Aktif'));
    }

    public function test_schedule_draft_save_snapshots_jp_and_publish_exposes_entries_to_viewer(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $subject = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika', 'jp_per_class' => 5, 'color' => '#DCEBFF']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru', 'full_name' => 'Guru Jadwal']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'MTK01']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => [$rombel->id],
                ]],
            ])
            ->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertDatabaseHas('tr_curriculum_teaching_assignments', [
            'schedule_plan_id' => $plan->id,
            'weekly_jp' => 5,
            'subject_name' => 'Matematika',
            'teacher_name' => 'Guru Jadwal',
        ]);
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 5);

        $this->actingAs($admin)
            ->get('/kurikulum/schedule?academic_period_id='.$period->id.'&grade=VII&rombel_ids[]='.$rombel->id)
            ->assertInertia(fn ($page) => $page
                ->has('entries', 5)
                ->where('entries.0.assignment_code', 'MTK01')
                ->where('entries.0.subject_color', '#DCEBFF')
                ->where('entries.0.teacher_name', 'Guru Jadwal'));
    }

    public function test_same_teacher_can_cover_seven_rombels_without_false_capacity_failure(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(7)->create([
            'grade_level' => 'VII',
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create(['jp_per_class' => 5]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);
        $rombelIds = $rombels->pluck('id')->all();

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'selected_rombel_ids' => $rombelIds,
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => $rombelIds,
                ]],
            ])
            ->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 35);
        $this->assertSame(35, ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->count());
    }

    public function test_combined_subject_can_be_scheduled_for_multiple_rombels_in_the_same_slots(): void
    {
        $period = $this->activePeriod();
        $customSlots = [];
        foreach (app(ScheduleReadService::class)->lessonSlotsByDay() as $day => $slots) {
            foreach ($slots as $slot) {
                if ($day !== 'monday' || $slot['number'] > 3) {
                    $customSlots[] = ['day' => $day, 'lesson_number' => $slot['number'], 'label' => 'Kegiatan'];
                }
            }
        }
        $rombels = Rombel::factory()->count(2)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 3, 'allow_combined' => true]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $this->actingAs($this->admin())->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'custom_slots' => $customSlots,
            'selected_rombel_ids' => $rombels->pluck('id')->all(),
            'assignments' => [[
                'teacher_subject_id' => $teacherSubject->id,
                'rombel_ids' => $rombels->pluck('id')->all(),
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entries = $plan->entries()->orderBy('rombel_id')->orderBy('day')->orderBy('lesson_number')->get();
        $this->assertCount(6, $entries);
        $this->assertSame(
            $entries->where('rombel_id', $rombels[0]->id)->map(fn (ScheduleEntry $entry): string => $entry->day.':'.$entry->lesson_number)->values()->all(),
            $entries->where('rombel_id', $rombels[1]->id)->map(fn (ScheduleEntry $entry): string => $entry->day.':'.$entry->lesson_number)->values()->all(),
        );
        $this->assertSame(6, $entries->where('day', 'monday')->count());
    }

    public function test_resave_revalidates_manual_combined_slots_when_master_flag_is_disabled(): void
    {
        ['admin' => $admin, 'period' => $period, 'rombel' => $rombel, 'relation' => $relation, 'plan' => $plan] = $this->placementContext(1);
        $relation->subject->update(['allow_combined' => true]);
        $otherRombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $rombelIds = [$rombel->id, $otherRombel->id];
        $payload = [
            'academic_period_id' => $period->id, 'selected_rombel_ids' => $rombelIds,
            'assignments' => [['teacher_subject_id' => $relation->id, 'rombel_ids' => $rombelIds]],
            'manual_placements' => array_map(fn (int $rombelId): array => [
                'teacher_subject_id' => $relation->id, 'rombel_id' => $rombelId,
                'slots' => [['day' => 'monday', 'lesson_number' => 1]],
            ], $rombelIds),
        ];
        $this->actingAs($admin)->post('/kurikulum/schedule', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $before = $plan->entries()->orderBy('id')->get()->toArray();
        $beforePlan = $plan->fresh()->toArray();
        $relation->subject->update(['allow_combined' => false]);

        $this->post('/kurikulum/schedule', $payload)->assertRedirect()->assertSessionHasErrors('schedule');

        $this->assertSame($before, $plan->entries()->orderBy('id')->get()->toArray());
        $this->assertSame($beforePlan, $plan->fresh()->toArray());
    }

    public function test_manual_combined_subject_placements_share_slots(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(2)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1, 'allow_combined' => true]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        $rombelIds = $rombels->pluck('id')->all();

        $this->actingAs($this->admin())->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $rombelIds,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => $rombelIds]],
            'manual_placements' => array_map(fn (int $rombelId): array => [
                'teacher_subject_id' => $teacherSubject->id,
                'rombel_id' => $rombelId,
                'slots' => [['day' => 'monday', 'lesson_number' => 1]],
            ], $rombelIds),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entries = ScheduleEntry::query()->orderBy('rombel_id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame([$rombels[0]->id, $rombels[1]->id], $entries->pluck('rombel_id')->all());
        $this->assertSame(['monday:1', 'monday:1'], $entries->map(fn (ScheduleEntry $entry): string => $entry->day.':'.$entry->lesson_number)->all());
    }

    public function test_save_preserves_page_rombel_context_when_assignment_covers_subset(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(7)->create([
            'grade_level' => 'VII',
            'status' => 'active',
        ]);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);
        $selectedRombelIds = $rombels->pluck('id')->all();
        $assignmentRombelIds = array_slice($selectedRombelIds, 0, 3);

        $response = $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'selected_rombel_ids' => $selectedRombelIds,
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => $assignmentRombelIds,
                ]],
            ])
            ->assertRedirect();

        $location = $response->headers->get('Location');
        foreach ($selectedRombelIds as $rombelId) {
            $this->assertStringContainsString('rombel_ids%5B', $location);
            $this->assertStringContainsString('='.$rombelId, $location);
        }
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 3);
    }

    public function test_second_save_regenerates_previous_published_version_in_place(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $firstSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $secondSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $first = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $firstSubject->id]);
        $second = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $secondSubject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $published = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'source_plan_id' => $published->id,
            'assignments' => [['teacher_subject_id' => $second->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();

        $regenerated = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertSame($published->id, $regenerated->id);
        $this->assertSame(2, $regenerated->revision);
        $this->assertDatabaseMissing('tr_curriculum_schedule_plans', ['id' => $published->id, 'status' => 'archived']);
        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 1);
    }

    public function test_changed_save_preserves_course_run_assignment_in_archived_schedule_revision(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $firstSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $secondSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $first = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $firstSubject->id]);
        $second = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $secondSubject->id]);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $assignment = $plan->teachingAssignments()->firstOrFail();
        $courseTeacher = Teacher::factory()->create();
        $course = LmsCourse::query()->create([
            'subject_id' => $firstSubject->id, 'grade_level' => 'VII', 'title' => 'Course tersimpan',
            'created_by' => $courseTeacher->id,
        ]);
        $revision = $course->revisions()->create([
            'revision_number' => 1, 'title' => $course->title, 'content' => ['materials' => []],
            'status' => 'approved', 'created_by' => $courseTeacher->id,
        ]);
        $course->forceFill(['approved_revision_id' => $revision->id])->save();
        $run = LmsCourseRun::query()->create([
            'course_id' => $course->id, 'course_revision_id' => $revision->id,
            'academic_period_id' => $period->id, 'rombel_id' => $rombel->id,
            'schedule_teaching_assignment_id' => $assignment->id, 'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'source_plan_id' => $plan->id,
            'assignments' => [['teacher_subject_id' => $second->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'archived']);
        $this->assertDatabaseHas('tr_curriculum_teaching_assignments', ['id' => $assignment->id, 'status' => 'active']);
        $this->assertDatabaseHas('tr_lms_course_runs', [
            'id' => $run->id, 'schedule_teaching_assignment_id' => $assignment->id,
        ]);
        $newPlan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertNotSame($plan->id, $newPlan->id);
        $this->assertDatabaseHas('tr_curriculum_teaching_assignments', [
            'schedule_plan_id' => $newPlan->id, 'teacher_subject_id' => $second->id, 'status' => 'active',
        ]);

        $this->actingAs($admin)->delete('/kurikulum/schedule', [
            'academic_period_id' => $period->id, 'grade' => 'VII',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($newPlan);
        $this->assertModelExists($plan);
        $this->assertModelExists($run->fresh());

        $this->actingAs($admin)->delete('/kurikulum/schedule', [
            'academic_period_id' => $period->id, 'grade' => 'VII',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelExists($plan);
        $this->assertModelExists($run->fresh());
    }

    public function test_changed_save_regenerates_existing_published_plan_in_place(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $firstSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $secondSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $first = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $firstSubject->id]);
        $second = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $secondSubject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'source_plan_id' => $plan->id,
            'assignments' => [['teacher_subject_id' => $second->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_schedule_plans', [
            'id' => $plan->id,
            'status' => 'published',
            'revision' => 2,
        ]);
        $this->assertDatabaseMissing('tr_curriculum_schedule_plans', [
            'id' => $plan->id,
            'status' => 'archived',
        ]);
        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 1);
        $this->assertDatabaseMissing('tr_curriculum_teaching_assignments', [
            'schedule_plan_id' => $plan->id,
            'teacher_subject_id' => $first->id,
        ]);
        $this->assertDatabaseHas('tr_curriculum_teaching_assignments', [
            'schedule_plan_id' => $plan->id,
            'teacher_subject_id' => $second->id,
        ]);
    }

    public function test_schedule_subject_can_be_removed_and_added_again_without_deleting_master_data(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $subjects = Subject::factory()->count(2)->create(['jp_per_class' => 1]);
        $first = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subjects[0]->id]);
        $second = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subjects[1]->id]);
        $payload = [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$rombel->id],
            'assignments' => [
                ['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]],
                ['teacher_subject_id' => $second->id, 'rombel_ids' => [$rombel->id]],
            ],
        ];
        $this->actingAs($this->admin())->post('/kurikulum/schedule', $payload)
            ->assertSessionHasNoErrors()->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->post('/kurikulum/schedule', [
            ...$payload,
            'source_plan_id' => $plan->id,
            'assignments' => [$payload['assignments'][0]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseMissing('tr_curriculum_teaching_assignments', [
            'schedule_plan_id' => $plan->id,
            'subject_id' => $subjects[1]->id,
        ]);
        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', [
            'schedule_plan_id' => $plan->id,
            'subject_id' => $subjects[1]->id,
        ]);
        $this->assertModelExists($subjects[1]);
        $this->assertModelExists($second);
        $this->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII')
            ->assertInertia(fn ($page) => $page
                ->has('subjects', 2)
                ->has('draftPlan.teaching_assignments', 1)
                ->where('draftPlan.teaching_assignments.0.teacher_subject_id', $first->id));

        $this->post('/kurikulum/schedule', [...$payload, 'source_plan_id' => $plan->id])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_teaching_assignments', [
            'schedule_plan_id' => $plan->id,
            'subject_id' => $subjects[1]->id,
            'teacher_subject_id' => $second->id,
        ]);
        $this->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII')
            ->assertInertia(fn ($page) => $page->has('draftPlan.teaching_assignments', 2));
    }

    public function test_save_persists_manual_schedule_placements_from_editor(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $subject = Subject::factory()->create(['jp_per_class' => 3]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => [$rombel->id],
                ]],
                'manual_placements' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_id' => $rombel->id,
                    'slots' => [
                        ['day' => 'tuesday', 'lesson_number' => 4],
                        ['day' => 'tuesday', 'lesson_number' => 5],
                        ['day' => 'tuesday', 'lesson_number' => 6],
                    ],
                ]],
            ])
            ->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertSame(['tuesday', 'tuesday', 'tuesday'], ScheduleEntry::query()
            ->where('schedule_plan_id', $plan->id)
            ->orderBy('lesson_number')
            ->pluck('day')
            ->all());
        $this->assertSame([4, 5, 6], ScheduleEntry::query()
            ->where('schedule_plan_id', $plan->id)
            ->orderBy('lesson_number')
            ->pluck('lesson_number')
            ->all());
    }

    public function test_save_places_all_manual_slots_before_auto_assignments_when_custom_slots_are_present(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(8)->create(['grade_level' => 'VII', 'status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $automaticSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $manualSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $automaticTeacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $automaticSubject->id,
        ]);
        $manualTeacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $manualSubject->id,
        ]);
        $rombelIds = $rombels->pluck('id')->all();

        $response = $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'selected_rombel_ids' => $rombelIds,
                'custom_slots' => [[
                    'day' => 'tuesday',
                    'lesson_number' => 4,
                    'label' => 'Kegiatan Khusus',
                ]],
                'assignments' => [
                    ['teacher_subject_id' => $automaticTeacherSubject->id, 'rombel_ids' => $rombelIds],
                    ['teacher_subject_id' => $manualTeacherSubject->id, 'rombel_ids' => $rombelIds],
                ],
                'manual_placements' => array_map(
                    fn (int $rombelId): array => [
                        'teacher_subject_id' => $manualTeacherSubject->id,
                        'rombel_id' => $rombelId,
                        'slots' => [['day' => 'monday', 'lesson_number' => array_search($rombelId, $rombelIds, true) + 1]],
                    ],
                    $rombelIds,
                ),
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertSame(16, ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->count());
        $this->assertSame(8, ScheduleEntry::query()
            ->where('schedule_plan_id', $plan->id)
            ->where('subject_id', $automaticSubject->id)
            ->count());
        foreach ($rombelIds as $index => $rombelId) {
            $this->assertDatabaseHas('tr_curriculum_schedule_entries', [
                'schedule_plan_id' => $plan->id,
                'subject_id' => $manualSubject->id,
                'rombel_id' => $rombelId,
                'day' => 'monday',
                'lesson_number' => $index + 1,
            ]);
        }
        $this->assertCount(16, $plan->entries()->get()->unique(
            fn (ScheduleEntry $entry): string => $entry->teacher_id.':'.$entry->day.':'.$entry->lesson_number,
        ));
        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', [
            'schedule_plan_id' => $plan->id,
            'day' => 'tuesday',
            'lesson_number' => 4,
        ]);
    }

    public function test_activity_conflicting_with_manual_slot_returns_actionable_error_without_saving(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['name' => 'Matematika', 'jp_per_class' => 1, 'allow_combined' => true]);
        $teacher = Teacher::factory()->create(['full_name' => 'Guru Matematika', 'status' => 'active', 'staff_type' => 'guru']);
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        $this->actingAs($this->admin())->from('/kurikulum/schedule/generate')
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'custom_slots' => [['day' => 'wednesday', 'lesson_number' => 5, 'label' => 'Literasi']],
                'assignments' => [['teacher_subject_id' => $relation->id, 'rombel_ids' => [$rombel->id]]],
                'manual_placements' => [[
                    'teacher_subject_id' => $relation->id,
                    'rombel_id' => $rombel->id,
                    'slots' => [['day' => 'wednesday', 'lesson_number' => 5]],
                ]],
            ])
            ->assertRedirect('/kurikulum/schedule/generate')
            ->assertSessionHasErrors([
                'schedule' => "Matematika — Guru Matematika — {$rombel->code}: Slot manual bertabrakan dengan kegiatan khusus. Pindahkan posisi pelajaran atau ubah slot kegiatan.",
            ]);

        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 0);
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 0);
        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
    }

    public function test_unavailable_slots_return_form_error_and_preserve_previous_published_schedule(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $payload = [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $relation->id, 'rombel_ids' => [$rombel->id]]],
        ];
        $this->actingAs($this->admin())->post('/kurikulum/schedule', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $originalPlan = $plan->getAttributes();
        $originalEntries = $plan->entries()->get()->toArray();
        $originalAssignments = $plan->teachingAssignments()->get()->toArray();
        $customSlots = [];
        foreach (['monday' => 9, 'tuesday' => 9, 'wednesday' => 9, 'thursday' => 9, 'friday' => 7] as $day => $lessonCount) {
            for ($lesson = 1; $lesson <= $lessonCount; $lesson++) {
                $customSlots[] = ['day' => $day, 'lesson_number' => $lesson, 'label' => 'Kegiatan'];
            }
        }

        $this->from('/kurikulum/schedule/generate')->post('/kurikulum/schedule', [
            ...$payload,
            'source_plan_id' => $plan->id,
            'custom_slots' => $customSlots,
        ])->assertRedirect('/kurikulum/schedule/generate')->assertSessionHasErrors([
            'schedule' => "{$subject->name} — {$teacher->full_name} — {$rombel->code}: Penempatan otomatis belum menemukan slot untuk 1 JP tersisa. Periksa beban Guru/Rombel, posisi manual, dan kegiatan khusus.",
        ]);

        $this->assertSame($originalPlan, $plan->fresh()->getAttributes());
        $this->assertSame($originalEntries, $plan->entries()->get()->toArray());
        $this->assertSame($originalAssignments, $plan->teachingAssignments()->get()->toArray());
        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 1);
    }

    public function test_schedule_mutation_requires_dedicated_permission(): void
    {
        $period = $this->activePeriod();
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)
            ->post('/kurikulum/schedule', ['academic_period_id' => $period->id, 'assignments' => []])
            ->assertForbidden();
    }

    public function test_save_canonicalizes_order_and_regenerates_published_version_in_place(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $firstSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $secondSubject = Subject::factory()->create(['jp_per_class' => 1]);
        $first = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $firstSubject->id]);
        $second = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $secondSubject->id]);
        $admin = $this->admin();
        $firstPayload = [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ];

        $this->actingAs($admin)->post('/kurikulum/schedule', $firstPayload)->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $this->assertSame(1, SchedulePlan::query()->where('status', 'published')->count());
        $this->assertSame($plan->id, SchedulePlan::query()->where('status', 'published')->value('id'));

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'source_plan_id' => $plan->id,
            'assignments' => [
                ['teacher_subject_id' => $second->id, 'rombel_ids' => [$rombel->id]],
                ['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]],
            ],
        ])->assertRedirect();

        $regenerated = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertSame($plan->id, $regenerated->id);
        $this->assertNull($regenerated->source_plan_id);
        $this->assertSame(2, $regenerated->revision);
        $this->assertDatabaseMissing('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'archived']);
        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 1);

        $this->actingAs($this->admin())
            ->get('/kurikulum/schedule/generate?academic_period_id='.$period->id)
            ->assertInertia(fn ($page) => $page
                ->where('subjects.0.color', $firstSubject->color)
                ->where('draftPlan.id', $regenerated->id)
                ->has('draftPlan.teaching_assignments', 2)
                ->where('selectedRombelIds.0', $rombel->id));
    }

    public function test_friday_uses_seven_lesson_slots_and_teacher_capacity_rolls_back(): void
    {
        $period = $this->activePeriod();
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $assignments = [];
        for ($index = 1; $index <= 2; $index++) {
            $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
            RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
            $subject = Subject::factory()->create(['jp_per_class' => 15]);
            $teacherSubject = TeacherSubject::factory()->create([
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'code' => 'CAP'.$index,
            ]);
            $assignments[] = ['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]];
        }
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => $assignments,
        ])->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $fridayEntries = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->where('day', 'friday')->get();
        $this->assertCount(6, $fridayEntries);
        $this->assertLessThanOrEqual(7, $fridayEntries->max('lesson_number'));

        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $subject = Subject::factory()->create(['jp_per_class' => 15]);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'code' => 'CAP3',
        ]);

        $this->actingAs($admin)->postJson('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'source_plan_id' => $plan->id,
            'assignments' => [...$assignments, ['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertUnprocessable()->assertJsonValidationErrors('schedule');
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'published']);
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 30);
    }

    /** @group legacy-publish-disabled */
    public function test_manual_publish_endpoint_is_unavailable_for_invalid_entry_scenario(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $plan->update(['status' => 'draft', 'published_at' => null]);
        ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->update([
            'day' => 'friday',
            'lesson_number' => 8,
        ]);

        $this->actingAs($admin)
            ->post('/kurikulum/schedule/publish', ['schedule_plan_id' => $plan->id])
            ->assertNotFound();
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'draft']);
        $this->assertDatabaseMissing('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'published']);
    }

    public function test_save_rejects_duplicate_subject_for_same_rombel(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $firstTeacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $secondTeacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $first = TeacherSubject::factory()->create(['teacher_id' => $firstTeacher->id, 'subject_id' => $subject->id]);
        $second = TeacherSubject::factory()->create(['teacher_id' => $secondTeacher->id, 'subject_id' => $subject->id]);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'assignments' => [
                    ['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]],
                    ['teacher_subject_id' => $second->id, 'rombel_ids' => [$rombel->id]],
                ],
            ])
            ->assertStatus(422);
        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 0);
    }

    public function test_save_rejects_empty_assignments(): void
    {
        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $this->activePeriod()->id,
                'assignments' => [],
            ])
            ->assertSessionHasErrors('assignments');
    }

    /** @group legacy-publish-disabled */
    public function test_manual_publish_endpoint_is_unavailable_for_empty_draft_scenario(): void
    {
        $period = $this->activePeriod();
        $plan = SchedulePlan::factory()->create([
            'academic_period_id' => $period->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule/publish', ['schedule_plan_id' => $plan->id])
            ->assertNotFound();
    }

    public function test_schedule_save_accepts_global_active_rombel_without_period_usage(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_schedule_plans', [
            'academic_period_id' => $period->id,
            'status' => 'published',
        ]);
    }

    public function test_non_privileged_user_cannot_access_schedule_surfaces(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get('/kurikulum/schedule')->assertForbidden();
        $this->actingAs($user)->get('/kurikulum/schedule/generate')->assertForbidden();
    }

    /**
     * @return array<string, array{array<int, array{day: string, lesson_number: int, label: string}>}>
     */
    public static function activitySlotCases(): array
    {
        return [
            'without activities' => [[]],
            'monday opening' => [[['day' => 'monday', 'lesson_number' => 1, 'label' => 'Upacara']]],
            'tuesday middle' => [[['day' => 'tuesday', 'lesson_number' => 5, 'label' => 'Literasi']]],
            'wednesday ending' => [[['day' => 'wednesday', 'lesson_number' => 9, 'label' => 'Kegiatan']]],
            'thursday middle' => [[['day' => 'thursday', 'lesson_number' => 2, 'label' => 'Kegiatan']]],
            'friday opening' => [[['day' => 'friday', 'lesson_number' => 1, 'label' => 'Melali']]],
            'friday ending' => [[['day' => 'friday', 'lesson_number' => 7, 'label' => 'Kegiatan']]],
            'multiple activities' => [[
                ['day' => 'monday', 'lesson_number' => 1, 'label' => 'Upacara'],
                ['day' => 'wednesday', 'lesson_number' => 5, 'label' => 'Literasi'],
                ['day' => 'friday', 'lesson_number' => 1, 'label' => 'Melali'],
            ]],
        ];
    }

    /**
     * @param  array<int, array{day: string, lesson_number: int, label: string}>  $customSlots
     */
    #[DataProvider('activitySlotCases')]
    public function test_eight_rombels_can_be_scheduled_with_or_without_activities(array $customSlots): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(8)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 5]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $rombelIds = $rombels->pluck('id')->all();

        $this->actingAs($this->admin())->post('/kurikulum/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $rombelIds,
            'custom_slots' => $customSlots,
            'assignments' => [['teacher_subject_id' => $relation->id, 'rombel_ids' => $rombelIds]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entries = $plan->entries()->get();
        $this->assertCount(40, $entries);
        foreach ($rombels as $rombel) {
            $rombelEntries = $entries->where('rombel_id', $rombel->id);
            $this->assertCount(5, $rombelEntries);
            foreach ($rombelEntries->groupBy('day') as $dailyEntries) {
                $this->assertLessThanOrEqual(3, $dailyEntries->count());
            }
        }
        $this->assertCount(40, $entries->unique(fn (ScheduleEntry $entry): string => $entry->day.':'.$entry->lesson_number));
        foreach ($customSlots as $customSlot) {
            $this->assertDatabaseHas('tr_curriculum_schedule_custom_slots', ['schedule_plan_id' => $plan->id, ...$customSlot]);
            $this->assertDatabaseMissing('tr_curriculum_schedule_entries', [
                'schedule_plan_id' => $plan->id,
                'day' => $customSlot['day'],
                'lesson_number' => $customSlot['lesson_number'],
            ]);
        }
    }

    public function test_allocator_prefers_contiguous_three_then_two_slot_blocks_without_crossing_breaks(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 5]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => [$rombel->id],
                ]],
            ])
            ->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entries = ScheduleEntry::query()
            ->where('schedule_plan_id', $plan->id)
            ->orderBy('day')
            ->orderBy('lesson_number')
            ->get();

        $this->assertSame(5, $entries->count());
        $this->assertSame(['monday', 'monday', 'monday', 'tuesday', 'tuesday'], $entries->pluck('day')->all());
        $this->assertSame([1, 2, 3, 1, 2], $entries->pluck('lesson_number')->all());
    }

    public function test_allocator_does_not_cross_breaks_or_custom_slots_when_finding_blocks(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 3]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'custom_slots' => [[
                    'day' => 'monday',
                    'lesson_number' => 2,
                    'label' => 'Kegiatan Khusus',
                ]],
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => [$rombel->id],
                ]],
            ])
            ->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entries = ScheduleEntry::query()
            ->where('schedule_plan_id', $plan->id)
            ->orderBy('day')
            ->orderBy('lesson_number')
            ->get();

        $this->assertSame(3, $entries->count());
        $this->assertSame(['monday', 'monday', 'monday'], $entries->pluck('day')->all());
        $this->assertSame([4, 5, 6], $entries->pluck('lesson_number')->all());
    }

    public function test_custom_slot_is_persisted_blocks_allocator_and_is_rendered_across_rombels(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(2)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'code' => 'IPA01',
        ]);
        $rombelIds = $rombels->pluck('id')->all();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/kurikulum/schedule', [
                'academic_period_id' => $period->id,
                'selected_rombel_ids' => $rombelIds,
                'custom_slots' => [[
                    'day' => 'monday',
                    'lesson_number' => 1,
                    'label' => 'Upacara Pembukaan',
                ]],
                'assignments' => [[
                    'teacher_subject_id' => $teacherSubject->id,
                    'rombel_ids' => [$rombels[0]->id],
                ]],
            ])
            ->assertRedirect();

        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $this->assertDatabaseHas('tr_curriculum_schedule_custom_slots', [
            'schedule_plan_id' => $plan->id,
            'day' => 'monday',
            'lesson_number' => 1,
            'label' => 'Upacara Pembukaan',
            'start_time' => '07:30',
            'end_time' => '08:10',
        ]);
        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', [
            'schedule_plan_id' => $plan->id,
            'day' => 'monday',
            'lesson_number' => 1,
        ]);

        $this->actingAs($admin)
            ->get('/kurikulum/schedule/generate?academic_period_id='.$period->id.'&grade=VII&rombel_ids[]='.$rombels[0]->id)
            ->assertInertia(fn ($page) => $page
                ->has('draftPlan.entries', 1)
                ->where('draftPlan.entries.0.day', 'monday')
                ->where('draftPlan.entries.0.lesson_number', 2)
                ->where('draftPlan.custom_slots.0.day', 'monday')
                ->where('draftPlan.custom_slots.0.lesson_number', 1)
                ->where('draftPlan.custom_slots.0.label', 'Upacara Pembukaan'));

        $this->actingAs($admin)
            ->get('/kurikulum/schedule?academic_period_id='.$period->id.'&grade=VII&rombel_ids[]='.$rombels[0]->id.'&rombel_ids[]='.$rombels[1]->id)
            ->assertInertia(fn ($page) => $page
                ->where('days.0.rows.0.type', 'custom')
                ->where('days.0.rows.0.number', 1)
                ->where('days.0.rows.0.label', 'Upacara Pembukaan')
                ->where('entries.0.assignment_code', 'IPA01'));
    }

    public function test_custom_slot_rejects_duplicate_and_unconfigured_lesson(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $payload = [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ];

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                ...$payload,
                'custom_slots' => [
                    ['day' => 'monday', 'lesson_number' => 1, 'label' => 'Kegiatan A'],
                    ['day' => 'monday', 'lesson_number' => 1, 'label' => 'Kegiatan B'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['custom_slots' => 'Satu slot hanya dapat memiliki satu kegiatan khusus.']);

        $this->actingAs($this->admin())
            ->post('/kurikulum/schedule', [
                ...$payload,
                'custom_slots' => [['day' => 'friday', 'lesson_number' => 8, 'label' => 'Kegiatan Tidak Valid']],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['custom_slots' => 'Slot kegiatan khusus tidak sesuai konfigurasi jadwal.']);

        $this->assertDatabaseCount('tr_curriculum_schedule_plans', 0);
        $this->assertDatabaseCount('tr_curriculum_schedule_custom_slots', 0);
    }

    public function test_grade_filter_rejects_values_outside_school_levels(): void
    {
        $this->actingAs($this->admin())
            ->get('/kurikulum/schedule?grade=X')
            ->assertSessionHasErrors('grade');
    }
}
