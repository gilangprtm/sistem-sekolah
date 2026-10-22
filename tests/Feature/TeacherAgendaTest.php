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
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAgenda;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TeacherAgendaPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_and_users_without_permission_cannot_access_agenda(): void
    {
        $this->get('/guru/agenda')->assertRedirect(route('login'));
        $this->post('/guru/agenda', [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/guru/agenda')->assertForbidden();
        $this->post('/guru/agenda', [])->assertForbidden();
    }

    public function test_permission_seed_is_additive_and_idempotent(): void
    {
        $role = Role::findByName('Guru', 'web');
        $role->givePermissionTo(Permission::findOrCreate('teacher.custom.test', 'web'));
        $role->revokePermissionTo('teacher.agenda.manage');
        $this->seed(TeacherAgendaPermissionSeeder::class);
        $this->seed(TeacherAgendaPermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.agenda.manage'));
        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.custom.test'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('teacher.agenda.manage'));
    }

    public function test_three_consecutive_lessons_form_one_agenda_and_other_teachers_are_excluded(): void
    {
        $context = $this->schedule();
        $otherTeacher = Teacher::factory()->create();
        $otherAssignment = $this->assignment($context['plan'], $context['period'], Rombel::factory()->create(), $otherTeacher);
        $this->entry($otherAssignment, 5, '10:30', '11:10');

        $this->actingAs($context['user'])->get('/guru/agenda?date=2026-10-19')->assertInertia(fn ($page) => $page
            ->component('teacher/agenda/index')->has('blocks', 1)
            ->where('blocks.0.snapshot.rombel_code', 'VII-A')
            ->where('blocks.0.snapshot.subject_name', 'Matematika')
            ->where('blocks.0.snapshot.lesson_start', 1)
            ->where('blocks.0.snapshot.lesson_end', 3)
            ->where('blocks.0.snapshot.start_time', '07:30')
            ->where('blocks.0.snapshot.end_time', '09:30')
            ->where('blocks.0.agenda_id', null));
    }

    public function test_a_break_gap_or_different_class_starts_another_block(): void
    {
        $context = $this->schedule();
        $this->entry($context['assignment'], 4, '09:50', '10:30');
        $this->entry($context['assignment'], 6, '11:10', '11:50');
        $otherAssignment = $this->assignment($context['plan'], $context['period'], Rombel::factory()->create(), $context['teacher']);
        $this->entry($otherAssignment, 7, '11:50', '12:30');

        $this->actingAs($context['user'])->get('/guru/agenda?date=2026-10-19')->assertInertia(fn ($page) => $page
            ->has('blocks', 4)->where('blocks.0.snapshot.lesson_end', 3)
            ->where('blocks.1.snapshot.lesson_start', 4)->where('blocks.1.snapshot.lesson_end', 4)
            ->where('blocks.2.snapshot.lesson_start', 6)->where('blocks.3.snapshot.lesson_start', 7));
    }

    public function test_overlapping_entries_from_another_grade_do_not_split_a_teaching_block(): void
    {
        $context = $this->schedule();
        $plan = SchedulePlan::factory()->create(['academic_period_id' => $context['period']->id, 'grade_level' => 'VIII', 'status' => 'published', 'created_by' => $context['user']->id]);
        $assignment = $this->assignment($plan, $context['period'], Rombel::factory()->create(['code' => 'VIII-A', 'grade_level' => 'VIII']), $context['teacher']);
        $this->entry($assignment, 1, '07:30', '08:10');
        $this->entry($assignment, 2, '08:10', '08:50');
        $this->actingAs($context['user'])->get('/guru/agenda?date=2026-10-19')->assertInertia(fn ($page) => $page
            ->has('blocks', 2)
            ->where('blocks.0.snapshot.rombel_code', 'VII-A')
            ->where('blocks.0.snapshot.lesson_end', 3)
            ->where('blocks.1.snapshot.rombel_code', 'VIII-A'));
    }

    public function test_default_date_uses_wita_and_weekend_has_no_blocks(): void
    {
        $context = $this->schedule();
        $this->travelTo(new \DateTimeImmutable('2026-10-18T17:00:00+00:00'));

        $this->actingAs($context['user'])->get('/guru/agenda')->assertInertia(fn ($page) => $page
            ->where('date', '2026-10-19')->has('blocks', 1));
        $this->get('/guru/agenda?date=2026-10-18')->assertInertia(fn ($page) => $page->has('blocks', 0));
        $this->get('/guru/agenda?date=invalid')->assertSessionHasErrors('date');
    }

    public function test_missing_profile_and_active_period_have_safe_empty_states(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)->get('/guru/agenda')->assertInertia(fn ($page) => $page
            ->where('hasTeacherProfile', false)->where('hasActivePeriod', false)->has('blocks', 0)->where('history', null));
        $this->get('/guru/agenda/create?date=2026-10-19&block_key='.str_repeat('a', 64))->assertForbidden();
        Teacher::factory()->create(['user_id' => $user->id]);
        $user->unsetRelation('teacher');
        $this->get('/guru/agenda')->assertInertia(fn ($page) => $page->where('hasTeacherProfile', true)->where('hasActivePeriod', false));
    }

    public function test_form_loads_full_active_roster_and_saves_one_agenda_with_server_snapshots(): void
    {
        $context = $this->schedule();
        $other = Student::factory()->create();
        $this->place($context, $other, Rombel::factory()->create()->id);
        $ended = Student::factory()->create();
        $this->place($context, $ended, null, 'ended');
        $inactive = Student::factory()->create(['status' => 'inactive']);
        $this->place($context, $inactive);
        $form = $this->newForm($context);
        $this->assertCount(2, $form['attendance']);
        $this->assertSame('Ani', $form['attendance'][0]['full_name']);
        $this->assertSame('A', $form['attendance'][0]['status']);
        $this->assertArrayNotHasKey('address', $form['attendance'][0]);
        $payload = $this->payload($form);
        $payload['teacher_id'] = 999;
        $payload['schedule_snapshot'] = ['rombel_code' => 'Palsu'];
        $payload['attendance'] = [['student_id' => 99999, 'status' => 'H']];

        $this->post('/guru/agenda', $payload)->assertRedirect();

        $agenda = TeacherAgenda::query()->sole();
        $this->assertSame($context['teacher']->id, $agenda->teacher_id);
        $this->assertSame('VII-A', $agenda->schedule_snapshot['rombel_code']);
        $this->assertSame(3, $agenda->schedule_snapshot['lesson_end']);
        $this->assertArrayNotHasKey('status', $agenda->attendance[0]);
        $this->assertDatabaseCount('tr_student_attendances', 0);
        $this->assertSame('001', $agenda->attendance[0]['nis']);
        $this->get('/guru/agenda?date=2026-10-19')->assertInertia(fn ($page) => $page
            ->where('blocks.0.agenda_id', $agenda->id)->where('history.total', 1));
    }

    public function test_same_block_cannot_be_saved_twice_but_next_week_is_a_new_agenda(): void
    {
        $context = $this->schedule();
        $form = $this->newForm($context);
        $this->post('/guru/agenda', $this->payload($form))->assertRedirect();
        $this->post('/guru/agenda', $this->payload($form))->assertSessionHasErrors('block_key');
        $this->get('/guru/agenda/create?date=2026-10-19&block_key='.$form['block_key'])
            ->assertRedirect(route('teacher.agenda.edit', TeacherAgenda::query()->sole()));
        $next = $this->newForm($context, '2026-10-26');
        $this->post('/guru/agenda', $this->payload($next))->assertRedirect();

        $this->assertDatabaseCount('tr_teacher_agendas', 2);
    }

    public function test_history_survives_schedule_deletion_and_remains_editable_after_period_and_roster_change(): void
    {
        $context = $this->schedule();
        $this->post('/guru/agenda', $this->payload($this->newForm($context)))->assertRedirect();
        $agenda = TeacherAgenda::query()->sole();
        $originalSnapshot = $agenda->schedule_snapshot;
        $context['plan']->delete();
        $context['period']->update(['status' => 'inactive']);
        StudentPlacement::query()->update(['status' => 'ended']);
        $context['students'][0]->update(['full_name' => 'Nama Baru']);
        $this->travelTo(new \DateTimeImmutable('2027-02-01T10:00:00+08:00'));
        $response = $this->get(route('teacher.agenda.edit', $agenda))->assertInertia(fn ($page) => $page
            ->where('agenda.snapshot.lesson_end', 3)->where('agenda.attendance.0.full_name', 'Ani'));
        $form = $response->viewData('page')['props']['agenda'];
        $payload = $this->payload($form);
        $payload['material'] = 'Materi dikoreksi';
        $payload['attendance'] = [['student_id' => 99999, 'status' => 'D']];

        $this->put(route('teacher.agenda.update', $agenda), $payload)->assertRedirect(route('teacher.agenda.edit', $agenda));

        $agenda->refresh();
        $this->assertSame($originalSnapshot, $agenda->schedule_snapshot);
        $this->assertSame('2026-10-19', $agenda->agenda_date->format('Y-m-d'));
        $this->assertSame('Materi dikoreksi', $agenda->material);
        $this->assertArrayNotHasKey('status', $agenda->attendance[0]);
        $this->assertSame('Ani', $agenda->attendance[0]['full_name']);
        $this->get('/guru/agenda')->assertInertia(fn ($page) => $page->has('blocks', 0)->where('history.total', 1));
    }

    public function test_other_teacher_cannot_read_update_or_use_another_teachers_block(): void
    {
        $context = $this->schedule();
        $form = $this->newForm($context);
        $this->post('/guru/agenda', $this->payload($form));
        $agenda = TeacherAgenda::query()->sole();
        $other = User::factory()->create();
        $other->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $other->id]);

        $this->actingAs($other)->get(route('teacher.agenda.edit', $agenda))->assertNotFound();
        $this->put(route('teacher.agenda.update', $agenda), $this->payload($form))->assertNotFound();
        $this->post('/guru/agenda', $this->payload($form))->assertSessionHasErrors('block_key');
        $this->get('/guru/agenda')->assertInertia(fn ($page) => $page->where('history.total', 0));
        $this->assertDatabaseCount('tr_teacher_agendas', 1);
    }

    public function test_validation_rejects_missing_material_and_attendance_is_not_written_by_journal(): void
    {
        $context = $this->schedule();
        $form = $this->newForm($context);
        $payload = $this->payload($form);
        $payload['material'] = '';
        $this->post('/guru/agenda', $payload)->assertSessionHasErrors([
            'material' => 'Materi yang diajarkan wajib diisi.',
        ]);
        $this->assertDatabaseCount('tr_teacher_agendas', 0);
        $this->assertDatabaseCount('tr_student_attendances', 0);
    }

    public function test_stale_roster_or_schedule_is_rejected_instead_of_silently_changing_history(): void
    {
        $context = $this->schedule();
        $form = $this->newForm($context);
        $this->place($context, Student::factory()->create());
        $this->post('/guru/agenda', $this->payload($form))->assertSessionHasErrors('context_token');
        $form = $this->newForm($context);
        $context['plan']->entries()->where('lesson_number', 3)->delete();
        $this->post('/guru/agenda', $this->payload($form))->assertSessionHasErrors('block_key');
        $this->assertDatabaseCount('tr_teacher_agendas', 0);
    }

    public function test_saved_context_cannot_be_moved_or_overwritten_by_stale_edit(): void
    {
        $context = $this->schedule();
        $this->post('/guru/agenda', $this->payload($this->newForm($context)));
        $agenda = TeacherAgenda::query()->sole();
        $form = $this->get(route('teacher.agenda.edit', $agenda))->viewData('page')['props']['agenda'];
        $payload = $this->payload($form);
        $payload['agenda_date'] = '2026-10-20';
        $this->put(route('teacher.agenda.update', $agenda), $payload)->assertSessionHasErrors('agenda_date');
        $payload = $this->payload($form);
        $payload['material'] = 'Perubahan terbaru';
        $this->put(route('teacher.agenda.update', $agenda), $payload)->assertRedirect();
        $this->put(route('teacher.agenda.update', $agenda), $this->payload($form))->assertSessionHasErrors('context_token');
        $this->assertSame('Perubahan terbaru', $agenda->fresh()->material);
    }

    public function test_empty_roster_and_unpublished_schedule_cannot_create_an_agenda(): void
    {
        $context = $this->schedule();
        StudentPlacement::query()->delete();
        $form = $this->newForm($context);
        $this->assertSame([], $form['attendance']);
        $this->post('/guru/agenda', $this->payload($form))->assertSessionHasErrors('attendance');
        $context['plan']->update(['status' => 'draft']);
        $this->get('/guru/agenda?date=2026-10-19')->assertInertia(fn ($page) => $page->has('blocks', 0));
        $this->assertDatabaseCount('tr_teacher_agendas', 0);
    }

    public function test_history_pagination_is_bounded_and_survives_schedule_replacement(): void
    {
        $context = $this->schedule();
        $this->post('/guru/agenda', $this->payload($this->newForm($context)))->assertRedirect();
        $original = TeacherAgenda::query()->sole();
        for ($week = 1; $week <= 10; $week++) {
            $copy = $original->replicate();
            $copy->agenda_date = now()->parse('2026-10-19')->addWeeks($week)->toDateString();
            $copy->save();
        }
        $context['plan']->entries()->update(['subject_name' => 'Nama mapel diperbarui']);
        $this->get('/guru/agenda?date=2026-10-19&page=2')->assertInertia(fn ($page) => $page
            ->where('blocks.0.snapshot.subject_name', 'Nama mapel diperbarui')
            ->where('blocks.0.agenda_id', $original->id)
            ->where('history.total', 11)->where('history.current_page', 2)
            ->has('history.data', 1)->where('history.data.0.snapshot.subject_name', 'Matematika'));
        $this->get('/guru/agenda?per_page=20')->assertInertia(fn ($page) => $page
            ->where('history.per_page', 20)->has('history.data', 11));
        $this->get('/guru/agenda?per_page=1000')->assertSessionHasErrors('per_page');
        $this->get(route('teacher.agenda.edit', $original))->assertInertia(fn ($page) => $page
            ->where('agenda.snapshot.subject_name', 'Matematika'));
    }

    /** @return array<string, mixed> */
    private function schedule(): array
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $period = AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id, 'full_name' => 'Pak A']);
        $rombel = Rombel::factory()->create(['code' => 'VII-A', 'name' => 'Kelas VII A', 'grade_level' => 'VII', 'parallel_code' => 'A']);
        $plan = SchedulePlan::factory()->create(['academic_period_id' => $period->id, 'grade_level' => 'VII', 'created_by' => $user->id, 'status' => 'published']);
        $assignment = $this->assignment($plan, $period, $rombel, $teacher);
        $this->entry($assignment, 1, '07:30', '08:10');
        $this->entry($assignment, 2, '08:10', '08:50');
        $this->entry($assignment, 3, '08:50', '09:30');
        $students = [Student::factory()->create(['full_name' => 'Ani', 'nis' => '001']), Student::factory()->create(['full_name' => 'Budi', 'nis' => '002'])];
        $context = compact('year', 'period', 'user', 'teacher', 'rombel', 'plan', 'assignment', 'students');
        foreach ($students as $student) {
            $this->place($context, $student);
        }

        return $context;
    }

    private function assignment(SchedulePlan $plan, AcademicPeriod $period, Rombel $rombel, Teacher $teacher): ScheduleTeachingAssignment
    {
        $subject = Subject::query()->firstOrCreate(['name' => 'Matematika'], ['code' => 'MAT', 'jp_per_class' => 3, 'color' => '#F3F4F6', 'status' => 'active']);
        $relation = TeacherSubject::query()->where('teacher_id', $teacher->id)->where('subject_id', $subject->id)->first()
            ?? TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);

        return ScheduleTeachingAssignment::query()->create([
            'schedule_plan_id' => $plan->id, 'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id, 'subject_id' => $subject->id,
            'teacher_subject_id' => $relation->id, 'teacher_id' => $teacher->id,
            'weekly_jp' => 3, 'subject_code' => $subject->code, 'subject_name' => $subject->name,
            'teacher_name' => $teacher->full_name, 'rombel_code' => $rombel->code, 'status' => 'active',
        ]);
    }

    private function entry(ScheduleTeachingAssignment $assignment, int $number, string $start, string $end): void
    {
        ScheduleEntry::query()->create([
            ...$assignment->only(['schedule_plan_id', 'academic_period_id', 'rombel_id', 'subject_id', 'teacher_id', 'subject_code', 'subject_name', 'teacher_name', 'rombel_code']),
            'teaching_assignment_id' => $assignment->id, 'day' => 'monday',
            'lesson_number' => $number, 'start_time' => $start, 'end_time' => $end,
        ]);
    }

    /** @param array<string, mixed> $context */
    private function place(array $context, Student $student, ?int $rombelId = null, string $status = 'active'): void
    {
        StudentPlacement::query()->create(['academic_year_id' => $context['year']->id, 'rombel_id' => $rombelId ?? $context['rombel']->id, 'student_id' => $student->id, 'status' => $status]);
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function newForm(array $context, string $date = '2026-10-19'): array
    {
        $this->actingAs($context['user']);
        $blocks = $this->get('/guru/agenda?date='.$date)->viewData('page')['props']['blocks'];
        $response = $this->get('/guru/agenda/create?date='.$date.'&block_key='.$blocks[0]['block_key'])->assertOk();

        return $response->viewData('page')['props']['agenda'];
    }

    /** @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function payload(array $form): array
    {
        return [
            'agenda_date' => $form['date'], 'block_key' => $form['block_key'], 'context_token' => $form['context_token'],
            'material' => 'Persamaan linear',
        ];
    }
}
