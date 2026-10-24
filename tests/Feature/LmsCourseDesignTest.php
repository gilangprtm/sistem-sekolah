<?php

namespace Tests\Feature;

use App\Models\LmsCourse;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\LmsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsCourseDesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LmsPermissionSeeder::class);
    }

    public function test_guest_cannot_open_or_mutate_course_design(): void
    {
        $course = $this->course(Teacher::factory()->create());

        $this->get(route('lms.design'))->assertRedirect(route('login'));
        $this->get(route('lms.design.edit', $course))->assertRedirect(route('login'));
        $this->post(route('lms.design.store'), [])->assertRedirect(route('login'));
        $this->patch(route('lms.design.update', $course), [])->assertRedirect(route('login'));
        $this->assertDatabaseCount('m_lms_courses', 1);
    }

    #[TestWith(['Siswa'])]
    #[TestWith(['Staff'])]
    public function test_non_design_roles_cannot_open_or_create_drafts(string $roleName): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));
        Teacher::factory()->create(['user_id' => $user->id]);
        $course = $this->course($user->teacher);

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', LmsCourse::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', LmsCourse::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $course));
        $this->actingAs($user)->get(route('lms.design'))->assertForbidden();
        $this->get(route('lms.design.edit', $course))->assertForbidden();
        $this->post(route('lms.design.store'), $this->payload())->assertForbidden();
        $this->patch(route('lms.design.update', $course), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('m_lms_courses', 1);
    }

    #[TestWith(['inactive', 'guru'])]
    #[TestWith(['active', 'staff'])]
    public function test_design_permission_requires_an_active_teacher_profile(string $status, string $staffType): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id, 'status' => $status, 'staff_type' => $staffType]);

        $this->assertFalse(Gate::forUser($user)->allows('create', LmsCourse::class));
        $this->actingAs($user)->post(route('lms.design.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('m_lms_courses', 0);
    }

    public function test_guru_without_profile_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get(route('lms.design'))->assertForbidden();
        $this->post(route('lms.design.store'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('m_lms_courses', 0);
    }

    public function test_teacher_creates_draft_without_falsifying_author_status_or_content(): void
    {
        [$user, $teacher] = $this->teacher();
        $other = Teacher::factory()->create();
        $data = $this->payload();

        $response = $this->actingAs($user)->post(route('lms.design.store'), [
            ...$data, 'designer_id' => $other->id, 'created_by' => $other->id,
            'status' => 'approved', 'reviewed_by' => $user->id, 'archived_at' => now(),
            'content' => ['materials' => [['title' => 'Injected']]],
        ]);

        $course = LmsCourse::query()->sole();
        $response->assertRedirect(route('lms.design.edit', $course))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_lms_courses', [
            'id' => $course->id, 'title' => 'Ekosistem', 'subject_id' => $data['subject_id'],
            'grade_level' => 'VII', 'created_by' => $teacher->id, 'archived_at' => null,
        ]);
        $this->assertDatabaseHas('tr_lms_course_revisions', [
            'course_id' => $course->id, 'revision_number' => 1, 'status' => 'draft',
            'title' => 'Ekosistem', 'description' => 'Interaksi makhluk hidup.',
            'created_by' => $teacher->id, 'submitted_at' => null, 'reviewed_by' => null,
        ]);
        $this->assertSame(['materials' => []], $course->latestRevision->content);
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
    }

    #[TestWith(['Super Admin'])]
    #[TestWith(['Kurikulum'])]
    public function test_design_managers_can_create_draft_for_an_active_teacher(string $roleName): void
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        $teacher = Teacher::factory()->create();

        $this->actingAs($user)->post(route('lms.design.store'), [
            ...$this->payload(), 'designer_id' => $teacher->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course = LmsCourse::query()->sole();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'created_by' => $teacher->id]);
        $this->assertDatabaseHas('tr_lms_course_revisions', ['course_id' => $course->id, 'created_by' => $teacher->id, 'status' => 'draft']);
        $this->get(route('lms.design'))->assertInertia(fn ($page) => $page
            ->component('lms/design/index')->has('courses.data', 1)->where('canChooseDesigner', true));
        $this->patch(route('lms.design.update', $course), [
            ...$this->payload(), 'title' => 'Oleh admin', 'revision_id' => $course->latestRevision->editToken($course),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Oleh admin', 'created_by' => $teacher->id]);
    }

    #[TestWith(['Super Admin'])]
    #[TestWith(['Kurikulum'])]
    public function test_design_managers_must_select_valid_active_teacher(string $roleName): void
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        $data = $this->payload();
        $this->actingAs($user)->post(route('lms.design.store'), $data)
            ->assertSessionHasErrors(['designer_id' => 'Guru perancang wajib dipilih.']);
        $inactive = Teacher::factory()->create(['status' => 'inactive']);
        $staff = Teacher::factory()->create(['staff_type' => 'staff']);

        foreach ([$inactive->id, $staff->id, 99999] as $designerId) {
            $this->post(route('lms.design.store'), [...$data, 'designer_id' => $designerId])
                ->assertSessionHasErrors(['designer_id' => 'Guru perancang tidak tersedia atau tidak aktif.']);
        }
        $this->assertDatabaseCount('m_lms_courses', 0);
    }

    public function test_validates_required_fields_and_rejects_archived_subject_and_invalid_grade(): void
    {
        [$user] = $this->teacher();
        $this->actingAs($user)->post(route('lms.design.store'), [])->assertSessionHasErrors([
            'title' => 'Judul course wajib diisi.',
            'subject_id' => 'Mata pelajaran wajib dipilih.',
            'grade_level' => 'Jenjang wajib dipilih.',
        ]);
        $subject = Subject::factory()->create(['status' => 'inactive']);
        $this->post(route('lms.design.store'), [
            'title' => str_repeat('x', 181), 'subject_id' => $subject->id,
            'grade_level' => 'X', 'description' => str_repeat('x', 10001),
        ])->assertSessionHasErrors([
            'title' => 'Judul course maksimal 180 karakter.',
            'subject_id' => 'Mata pelajaran tidak tersedia atau sudah diarsipkan.',
            'grade_level' => 'Jenjang yang dipilih tidak tersedia.',
            'description' => 'Deskripsi maksimal 10.000 karakter.',
        ]);
        $this->assertDatabaseCount('m_lms_courses', 0);
        $this->assertDatabaseCount('tr_lms_course_revisions', 0);
    }

    public function test_listing_filters_latest_revision_without_leaking_other_teachers_or_archived_courses(): void
    {
        [$user, $teacher] = $this->teacher();
        $subject = Subject::factory()->create(['name' => 'IPA']);
        $course = $this->course($teacher, ['subject_id' => $subject->id]);
        $course->latestRevision->update(['title' => 'EKOSISTEM', 'description' => 'Sistem kehidupan']);
        $this->course(Teacher::factory()->create(), ['subject_id' => $subject->id]);
        $archived = $this->course($teacher, ['subject_id' => $subject->id]);
        DB::table('m_lms_courses')->where('id', $archived->id)->update(['archived_at' => now()]);
        $oldMatch = $this->course($teacher, ['subject_id' => $subject->id]);
        $oldMatch->latestRevision->update(['description' => 'ekosistem']);
        $oldMatch->revisions()->create(['revision_number' => 2, 'title' => 'Tidak cocok', 'description' => 'Tentang atom', 'content' => ['materials' => []], 'created_by' => $teacher->id]);

        $this->actingAs($user)->get(route('lms.design', [
            'search' => 'ekosistem', 'subject_id' => $subject->id, 'grade_level' => 'VII', 'per_page' => 1,
        ]))->assertInertia(fn ($page) => $page->component('lms/design/index')
            ->has('courses.data', 1)->where('courses.total', 1)->where('courses.data.0.id', $course->id)
            ->where('courses.data.0.editable', true)->where('courses.data.0.subject', 'IPA')
            ->where('canChooseDesigner', false)->has('designers', 0)->where('canDesignLmsCourses', true));
        $this->get(route('lms.design', ['grade_level' => 'IX']))->assertInertia(fn ($page) => $page->has('courses.data', 0));
        $this->get(route('lms.design', ['subject_id' => Subject::factory()->create()->id]))->assertInertia(fn ($page) => $page->has('courses.data', 0));
        $this->get(route('lms.design', ['search' => "' OR 1=1 --"]))->assertInertia(fn ($page) => $page->has('courses.data', 0));
    }

    public function test_curriculum_can_read_and_modify_another_teachers_draft(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Kurikulum');
        $teacher = Teacher::factory()->create();
        $course = $this->course($teacher);
        $this->course(Teacher::factory()->create());
        $archived = $this->course($teacher);
        $archived->forceFill(['archived_at' => now()])->save();

        $this->actingAs($user)->get(route('lms.design'))->assertInertia(fn ($page) => $page
            ->has('courses.data', 2)->where('canChooseDesigner', true)->has('designers', 2)
            ->where('canDesignLmsCourses', true)->where('canCreate', true));
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->component('lms/design/edit')->where('course.editable', true));
        $this->assertTrue(Gate::forUser($user)->allows('view', $course));
        $this->assertTrue(Gate::forUser($user)->allows('update', $course));
        $this->patch(route('lms.design.update', $course), [
            ...$this->payload(), 'title' => 'Draf Kurikulum',
            'revision_id' => $course->latestRevision->editToken($course),
        ])->assertSessionHasNoErrors()->assertRedirect(route('lms.design.edit', $course));

        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Draf Kurikulum', 'created_by' => $teacher->id]);
        $this->assertDatabaseHas('tr_lms_course_revisions', ['course_id' => $course->id, 'title' => 'Draf Kurikulum', 'created_by' => $teacher->id]);
    }

    public function test_curriculum_without_design_permission_cannot_manage_drafts(): void
    {
        Role::findByName('Kurikulum', 'web')->revokePermissionTo('lms.course.design');
        $user = User::factory()->create();
        $user->assignRole('Kurikulum');
        $course = $this->course(Teacher::factory()->create());

        $this->assertFalse(Gate::forUser($user)->allows('manageDesigns', LmsCourse::class));
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', LmsCourse::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $course));
        $this->actingAs($user)->get(route('lms.design'))->assertForbidden();
        $this->patch(route('lms.design.update', $course), $this->payload())->assertForbidden();

        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Awal']);
    }

    #[TestWith(['pending_approval', false, false])]
    #[TestWith(['approved', false, false])]
    #[TestWith(['rejected', false, false])]
    #[TestWith(['draft', true, false])]
    #[TestWith(['draft', false, true])]
    public function test_curriculum_cannot_bypass_course_lifecycle_locks(string $status, bool $archived, bool $previouslyApproved): void
    {
        $user = User::factory()->create();
        $user->assignRole('Kurikulum');
        $teacher = Teacher::factory()->create();
        $course = $this->course($teacher);
        $course->latestRevision->update(['status' => $status]);

        if ($archived) {
            $course->forceFill(['archived_at' => now()])->save();
        }

        if ($previouslyApproved) {
            $course->latestRevision->update(['status' => 'approved']);
            $course->revisions()->create([
                'revision_number' => 2, 'title' => 'Draf berikutnya',
                'content' => ['materials' => []], 'created_by' => $teacher->id,
            ]);
        }

        $course = $course->fresh();
        $this->assertFalse(Gate::forUser($user)->allows('update', $course));
        $this->actingAs($user)->patch(route('lms.design.update', $course), [
            ...$this->payload(), 'revision_id' => $course->latestRevision->editToken($course),
        ])->assertForbidden();

        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Awal']);
    }

    public function test_teacher_cannot_read_or_modify_another_teachers_draft(): void
    {
        [$user] = $this->teacher();
        $course = $this->course(Teacher::factory()->create());

        $this->assertFalse(Gate::forUser($user)->allows('manageDesigns', LmsCourse::class));
        $this->assertFalse(Gate::forUser($user)->allows('view', $course));
        $this->assertFalse(Gate::forUser($user)->allows('update', $course));
        $this->actingAs($user)->get(route('lms.design.edit', $course))->assertNotFound();
        $this->patch(route('lms.design.update', $course), [
            ...$this->payload(), 'revision_id' => $course->latestRevision->editToken($course),
        ])->assertForbidden();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Awal']);
    }

    public function test_teacher_updates_identity_without_erasing_content_or_changing_author_or_status(): void
    {
        [$user, $teacher] = $this->teacher();
        $course = $this->course($teacher);
        $content = ['materials' => [['title' => 'Materi lama']]];
        $course->latestRevision->update(['content' => $content]);
        $this->actingAs($user)->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->component('lms/design/edit')->where('course.editable', true)->where('course.title', 'Awal'));
        $payload = $this->payload();

        $this->patch(route('lms.design.update', $course), [
            ...$payload, 'revision_id' => $course->latestRevision->editToken($course),
            'status' => 'approved', 'created_by' => Teacher::factory()->create()->id,
            'content' => ['materials' => []],
        ])->assertSessionHasNoErrors()->assertRedirect(route('lms.design.edit', $course));

        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Ekosistem', 'subject_id' => $payload['subject_id'], 'created_by' => $teacher->id]);
        $this->assertDatabaseHas('tr_lms_course_revisions', ['course_id' => $course->id, 'title' => 'Ekosistem', 'status' => 'draft', 'description' => 'Interaksi makhluk hidup.']);
        $this->assertSame($content, $course->fresh()->latestRevision->content);
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
    }

    #[TestWith(['pending_approval'])]
    #[TestWith(['approved'])]
    #[TestWith(['rejected'])]
    public function test_non_draft_revision_cannot_be_overwritten(string $status): void
    {
        [$user, $teacher] = $this->teacher();
        $course = $this->course($teacher);
        $course->latestRevision->update(['status' => $status]);

        $this->assertFalse(Gate::forUser($user)->allows('update', $course));
        $this->actingAs($user)->patch(route('lms.design.update', $course), [
            ...$this->payload(), 'revision_id' => $course->latestRevision->editToken($course),
        ])->assertForbidden();
        $this->assertDatabaseHas('tr_lms_course_revisions', ['course_id' => $course->id, 'status' => $status, 'title' => 'Awal']);
    }

    public function test_archived_and_previously_approved_courses_cannot_be_changed(): void
    {
        [$user, $teacher] = $this->teacher();
        $course = $this->course($teacher);
        DB::table('m_lms_courses')->where('id', $course->id)->update(['archived_at' => now()]);
        $this->actingAs($user)->get(route('lms.design.edit', $course))->assertNotFound();
        $this->patch(route('lms.design.update', $course), $this->payload())->assertForbidden();

        DB::table('m_lms_courses')->where('id', $course->id)->update(['archived_at' => null]);
        $course->latestRevision->update(['status' => 'approved']);
        $course->revisions()->create(['revision_number' => 2, 'title' => 'Draf berikutnya', 'content' => ['materials' => []], 'created_by' => $teacher->id]);
        $this->patch(route('lms.design.update', $course), $this->payload())->assertForbidden();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Awal']);
    }

    public function test_stale_edit_is_rejected_even_for_changes_within_same_second(): void
    {
        $this->freezeTime();
        [$user, $teacher] = $this->teacher();
        $course = $this->course($teacher);
        $payload = [...$this->payload(), 'revision_id' => $course->latestRevision->editToken($course)];
        $this->actingAs($user)->patch(route('lms.design.update', $course), [...$payload, 'title' => 'Perubahan pertama'])
            ->assertSessionHasNoErrors();

        $this->patch(route('lms.design.update', $course), [...$payload, 'title' => 'Halaman lama'])
            ->assertSessionHasErrors(['revision_id' => 'Draf course telah berubah. Muat ulang halaman sebelum menyimpan.']);
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'title' => 'Perubahan pertama']);
        $this->assertDatabaseHas('tr_lms_course_revisions', ['course_id' => $course->id, 'title' => 'Perubahan pertama']);
    }

    public function test_stale_subject_only_change_and_missing_revision_token_are_rejected(): void
    {
        $this->freezeTime();
        [$user, $teacher] = $this->teacher();
        $course = $this->course($teacher);
        $payload = [
            'title' => 'Awal', 'subject_id' => $course->subject_id, 'grade_level' => 'VII',
            'description' => null, 'revision_id' => $course->latestRevision->editToken($course),
        ];
        $newSubject = Subject::factory()->create();
        $this->actingAs($user)->patch(route('lms.design.update', $course), [...$payload, 'subject_id' => $newSubject->id])->assertSessionHasNoErrors();
        $this->patch(route('lms.design.update', $course), $payload)->assertSessionHasErrors('revision_id');
        unset($payload['revision_id']);
        $this->patch(route('lms.design.update', $course), $payload)->assertSessionHasErrors('revision_id');
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'subject_id' => $newSubject->id]);
    }

    /** @return array{User, Teacher} */
    private function teacher(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        return [$user, Teacher::factory()->create(['user_id' => $user->id])];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['title' => 'Ekosistem', 'subject_id' => Subject::factory()->create()->id, 'grade_level' => 'VII', 'description' => 'Interaksi makhluk hidup.'];
    }

    /** @param array<string, mixed> $attributes */
    private function course(Teacher $teacher, array $attributes = []): LmsCourse
    {
        $course = LmsCourse::query()->create([
            'subject_id' => $attributes['subject_id'] ?? Subject::factory()->create()->id,
            'grade_level' => 'VII', 'title' => 'Awal', 'created_by' => $teacher->id,
        ]);
        $course->revisions()->create(['revision_number' => 1, 'title' => 'Awal', 'content' => ['materials' => []], 'created_by' => $teacher->id]);

        return $course;
    }
}
