<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\RombelPeriodUsage;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    public function test_super_admin_can_view_read_only_schedule_for_all_weekdays(): void
    {
        $period = $this->activePeriod();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/schedule')
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
            ->assertRedirect('/schedule?grade=VIII');

        $this->actingAs($admin)
            ->get('/schedules?grade=VIII')
            ->assertRedirect('/schedule?grade=VIII');
    }

    public function test_legacy_plural_preparation_path_redirects_to_singular_path(): void
    {
        $this->actingAs($this->admin())
            ->get('/schedules/generate?grade=VIII')
            ->assertRedirect('/schedule/generate?grade=VIII');
    }

    public function test_schedule_route_names_use_singular_schedule_prefix(): void
    {
        $this->assertSame('/schedule', route('schedule.index', absolute: false));
        $this->assertSame('/schedule/generate', route('schedule.generate', absolute: false));
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
            ->get('/schedule?grade=VII&rombel_ids[]='.$viiA->id)
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $selectedRombelIds,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => array_slice($selectedRombelIds, 0, 3)]],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get('/schedule?grade=VII')
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiTeacherSubject->id, 'rombel_ids' => [$viiRombel->id]]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/schedule', [
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombels[0]->id],
            'assignments' => [['teacher_subject_id' => $viiTeacherSubject->id, 'rombel_ids' => [$viiRombels[0]->id]]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VIII',
            'selected_rombel_ids' => [$viiiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiiTeacherSubject->id, 'rombel_ids' => [$viiiRombel->id]]],
        ])->assertRedirect();
        $viiiPlanId = SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->latest('id')->value('id');

        $this->actingAs($admin)->post('/schedule', [
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombel->id],
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$viiRombel->id]]],
        ])->assertRedirect();

        $this->actingAs($admin)->get('/schedule?grade=IX')->assertOk()->assertInertia(fn ($page) => $page
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
            $this->actingAs($admin)->post('/schedule', [
                'academic_period_id' => $period->id,
                'grade' => $rombel->grade_level,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post('/schedule/publish', ['schedule_plan_id' => SchedulePlan::query()->firstOrFail()->id])
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
            $this->actingAs($admin)->post('/schedule', [
                'academic_period_id' => $period->id,
                'grade' => $rombel->grade_level,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])->assertRedirect();
            $planIds[$rombel->grade_level] = SchedulePlan::query()->where('academic_period_id', $period->id)
                ->where('grade_level', $rombel->grade_level)->where('status', 'published')->value('id');
        }

        $this->actingAs($admin)->get('/schedule/generate?academic_period_id='.$period->id.'&grade=VIII')
            ->assertInertia(fn ($page) => $page->where('draftPlan.id', $planIds['VIII'])
                ->where('draftPlan.teaching_assignments.0.rombel_id', $rombels[1]->id));
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
            $this->actingAs($admin)->post('/schedule', [
                'academic_period_id' => $period->id,
                'grade' => $rombel->grade_level,
                'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
            ])->assertRedirect();
            $plans[$rombel->grade_level] = SchedulePlan::query()->where('academic_period_id', $period->id)
                ->where('grade_level', $rombel->grade_level)->where('status', 'published')->firstOrFail();
        }

        $this->actingAs($admin)->delete('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertRedirect();
        $this->assertDatabaseMissing('tr_curriculum_schedule_plans', ['id' => $plans['VII']->id]);
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plans['VIII']->id, 'status' => 'published']);
        $this->assertDatabaseMissing('tr_curriculum_schedule_entries', ['schedule_plan_id' => $plans['VII']->id]);

        $this->actingAs($admin)->delete('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertRedirect();
    }

    public function test_schedule_grade_delete_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->delete('/schedule', [
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

        $this->actingAs($admin)->post('/schedule', [
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

        $this->actingAs($admin)->delete('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
        ])->assertSessionHasErrors('schedule');
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VII',
            'selected_rombel_ids' => [$viiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiAnchor->id, 'rombel_ids' => [$viiRombel->id]]],
        ])->assertRedirect();
        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'grade' => 'VIII',
            'selected_rombel_ids' => [$viiiRombel->id],
            'assignments' => [['teacher_subject_id' => $viiiAnchor->id, 'rombel_ids' => [$viiiRombel->id]]],
        ])->assertRedirect();

        $this->assertSame(2, SchedulePlan::query()->where('academic_period_id', $period->id)->where('status', 'published')->count());
        $this->actingAs($admin)->get('/schedule?grade=VII')->assertInertia(fn ($page) => $page
            ->where('selectedRombelIds.0', $viiRombel->id)
            ->where('entries.0.rombel_id', $viiRombel->id));
        $this->actingAs($admin)->get('/schedule?grade=VIII')->assertInertia(fn ($page) => $page
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get('/schedule?grade=VII')
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->firstOrFail();

        $this->actingAs($admin)
            ->post('/schedule/move', [
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

    public function test_viewer_move_preserves_all_selected_rombels_when_assignment_covers_subset(): void
    {
        $period = $this->activePeriod();
        $rombels = Rombel::factory()->count(8)->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $selectedRombelIds = $rombels->pluck('id')->all();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'selected_rombel_ids' => $selectedRombelIds,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => array_slice($selectedRombelIds, 0, 3)]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->firstOrFail();

        $response = $this->actingAs($admin)->post('/schedule/move', [
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => $rombels->pluck('id')->all()]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();
        $entry = ScheduleEntry::query()->where('schedule_plan_id', $plan->id)->where('rombel_id', $rombels[1]->id)->firstOrFail();
        $original = $entry->only(['day', 'lesson_number']);

        $this->actingAs($admin)
            ->post('/schedule/move', [
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
            ->get('/schedule?grade=VII&rombel_ids[]='.$viii->id)
            ->assertSessionHasErrors('rombel_ids.0');
    }

    public function test_schedule_preparation_is_separate_from_read_only_viewer(): void
    {
        $this->activePeriod();
        $this->actingAs($this->admin())
            ->get('/schedule/generate')
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
            ->get('/schedule/generate?academic_period_id='.$period->id.'&grade=VII')
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
            ->post('/schedule', [
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
            ->get('/schedule?academic_period_id='.$period->id.'&grade=VII&rombel_ids[]='.$rombel->id)
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
            ->post('/schedule', [
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
            ->post('/schedule', [
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $published = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($admin)->post('/schedule', [
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($admin)->post('/schedule', [
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
            ->post('/schedule', [
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

    public function test_schedule_mutation_requires_dedicated_permission(): void
    {
        $period = $this->activePeriod();
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)
            ->post('/schedule', ['academic_period_id' => $period->id, 'assignments' => []])
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

        $this->actingAs($admin)->post('/schedule', $firstPayload)->assertRedirect();
        $plan = SchedulePlan::query()->where('status', 'published')->firstOrFail();

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'assignments' => [['teacher_subject_id' => $first->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertRedirect();
        $this->assertSame(1, SchedulePlan::query()->where('status', 'published')->count());
        $this->assertSame($plan->id, SchedulePlan::query()->where('status', 'published')->value('id'));

        $this->actingAs($admin)->post('/schedule', [
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
            ->get('/schedule/generate?academic_period_id='.$period->id)
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

        $this->actingAs($admin)->post('/schedule', [
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

        $this->actingAs($admin)->post('/schedule', [
            'academic_period_id' => $period->id,
            'source_plan_id' => $plan->id,
            'assignments' => [...$assignments, ['teacher_subject_id' => $teacherSubject->id, 'rombel_ids' => [$rombel->id]]],
        ])->assertStatus(422);
        $this->assertDatabaseHas('tr_curriculum_schedule_plans', ['id' => $plan->id, 'status' => 'published']);
        $this->assertDatabaseCount('tr_curriculum_schedule_entries', 30);
    }

    /** @group legacy-publish-disabled */
    public function test_publish_revalidates_configured_slots_and_rolls_back_on_invalid_entry(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        RombelPeriodUsage::factory()->create(['academic_period_id' => $period->id, 'rombel_id' => $rombel->id]);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/schedule', [
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
            ->post('/schedule/publish', ['schedule_plan_id' => $plan->id])
            ->assertStatus(422);
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
            ->post('/schedule', [
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
            ->post('/schedule', [
                'academic_period_id' => $this->activePeriod()->id,
                'assignments' => [],
            ])
            ->assertSessionHasErrors('assignments');
    }

    /** @group legacy-publish-disabled */
    public function test_publish_rejects_empty_draft_plan(): void
    {
        $period = $this->activePeriod();
        $plan = SchedulePlan::factory()->create([
            'academic_period_id' => $period->id,
            'created_by' => $this->admin()->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin())
            ->post('/schedule/publish', ['schedule_plan_id' => $plan->id])
            ->assertStatus(422);
    }

    public function test_schedule_save_accepts_global_active_rombel_without_period_usage(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $subject = Subject::factory()->create(['jp_per_class' => 1]);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $teacherSubject = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        $this->actingAs($this->admin())
            ->post('/schedule', [
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

        $this->actingAs($user)->get('/schedule')->assertForbidden();
        $this->actingAs($user)->get('/schedule/generate')->assertForbidden();
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
            ->post('/schedule', [
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
            ->post('/schedule', [
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
            ->post('/schedule', [
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
            ->get('/schedule/generate?academic_period_id='.$period->id.'&grade=VII&rombel_ids[]='.$rombels[0]->id)
            ->assertInertia(fn ($page) => $page
                ->has('draftPlan.entries', 1)
                ->where('draftPlan.entries.0.day', 'monday')
                ->where('draftPlan.entries.0.lesson_number', 2)
                ->where('draftPlan.custom_slots.0.day', 'monday')
                ->where('draftPlan.custom_slots.0.lesson_number', 1)
                ->where('draftPlan.custom_slots.0.label', 'Upacara Pembukaan'));

        $this->actingAs($admin)
            ->get('/schedule?academic_period_id='.$period->id.'&grade=VII&rombel_ids[]='.$rombels[0]->id.'&rombel_ids[]='.$rombels[1]->id)
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
            ->post('/schedule', [
                ...$payload,
                'custom_slots' => [
                    ['day' => 'monday', 'lesson_number' => 1, 'label' => 'Kegiatan A'],
                    ['day' => 'monday', 'lesson_number' => 1, 'label' => 'Kegiatan B'],
                ],
            ])
            ->assertStatus(422);

        $this->actingAs($this->admin())
            ->post('/schedule', [
                ...$payload,
                'custom_slots' => [['day' => 'friday', 'lesson_number' => 8, 'label' => 'Kegiatan Tidak Valid']],
            ])
            ->assertStatus(422);
    }

    public function test_grade_filter_rejects_values_outside_school_levels(): void
    {
        $this->actingAs($this->admin())
            ->get('/schedule?grade=X')
            ->assertSessionHasErrors('grade');
    }
}
