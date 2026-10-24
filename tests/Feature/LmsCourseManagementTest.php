<?php

namespace Tests\Feature;

use App\Models\LmsCourse;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\LmsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsCourseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LmsPermissionSeeder::class);
    }

    public function test_curriculum_and_super_admin_can_open_management_but_guru_cannot(): void
    {
        $guru = $this->user('Guru');
        $this->actingAs($guru)->get(route('lms.management'))->assertForbidden();
        foreach (['Kurikulum', 'Super Admin'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user)->get(route('lms.management'))->assertOk()->assertInertia(fn ($page) => $page->component('lms/management/index')->has('courses.data', 0));
        }
    }

    public function test_curriculum_activates_complete_draft_directly_without_submission(): void
    {
        [, , $course] = $this->course();
        $course->latestRevision->update(['content' => $this->content()]);
        $manager = $this->user('Kurikulum');
        $revision = $course->fresh()->latestRevision;

        $this->actingAs($manager)->get(route('lms.management.show', $course))->assertInertia(fn ($page) => $page
            ->component('lms/management/show')->where('course.revisions.0.status', 'draft')
            ->missing('course.revisions.0.review_note')->where('actions.can_activate', true));
        $this->get(route('lms.management'))->assertInertia(fn ($page) => $page->where('courses.data.0.can_activate', true));
        $this->from(route('lms.management.show', $course))
            ->post(route('lms.management.activate', $course), ['revision_id' => $revision->editToken($course)])
            ->assertRedirect(route('lms.management'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'approved_revision_id' => $revision->id]);
        $this->assertDatabaseHas('tr_lms_course_revisions', ['id' => $revision->id, 'status' => 'approved', 'submitted_at' => null, 'reviewed_at' => null]);
        $this->get(route('lms.management'))->assertInertia(fn ($page) => $page
            ->where('courses.data.0.status', 'active')->where('courses.data.0.can_activate', false));
    }

    public function test_activation_rejects_empty_course_and_does_not_change_draft(): void
    {
        [, , $course] = $this->course();
        $token = $course->latestRevision->editToken($course);
        $this->actingAs($this->user('Kurikulum'))->from(route('lms.management.show', $course))
            ->post(route('lms.management.activate', $course), ['revision_id' => $token])
            ->assertRedirect(route('lms.management.show', $course))->assertSessionHasErrors('course');
        $this->assertDatabaseHas('tr_lms_course_revisions', ['id' => $course->latestRevision->id, 'status' => 'draft', 'submitted_at' => null]);
    }

    public function test_legacy_review_payload_is_rejected_and_old_endpoints_are_removed(): void
    {
        [, , $course] = $this->course();
        $revision = $course->latestRevision;
        $revision->update(['content' => $this->content()]);
        $this->actingAs($this->user('Kurikulum'))->post(route('lms.management.activate', $course), [
            'revision_id' => $revision->editToken($course), 'decision' => 'rejected', 'review_note' => 'Kembalikan',
        ])->assertSessionHasErrors(['decision', 'review_note']);
        $this->assertSame('draft', $revision->fresh()->status);
        $this->assertFalse(app('router')->has('lms.design.submit'));
        $this->assertFalse(app('router')->has('lms.management.review'));
        $this->post('/lms/perancangan-course/'.$course->id.'/submit')->assertNotFound();
        $this->post('/lms/course-management/'.$course->id.'/review')->assertNotFound();
    }

    public function test_revising_approved_course_creates_new_revision_without_changing_active_version(): void
    {
        [$user, , $course] = $this->course();
        $active = $course->latestRevision;
        $active->update(['status' => 'approved', 'content' => $this->content()]);
        $course->forceFill(['approved_revision_id' => $active->id])->save();
        $token = $active->editToken($course);
        $this->actingAs($user)->post(route('lms.design.revise', $course), ['revision_id' => $token])->assertRedirect(route('lms.design.edit', $course))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tr_lms_course_revisions', ['course_id' => $course->id, 'revision_number' => 2, 'status' => 'draft']);
        $this->assertSame($active->id, $course->fresh()->approved_revision_id);
        $this->assertTrue($user->can('update', $course->fresh()));
    }

    public function test_only_curriculum_or_super_admin_can_activate_and_archive(): void
    {
        [$guru, , $course] = $this->course();
        $revision = $course->latestRevision;
        $revision->update(['content' => $this->content()]);
        $payload = ['revision_id' => $revision->editToken($course)];
        $this->post(route('lms.management.activate', $course), $payload)->assertRedirect(route('login'));
        $this->actingAs($guru)->get(route('lms.management'))->assertForbidden();
        $this->post(route('lms.management.activate', $course), $payload)->assertForbidden();
        $this->post(route('lms.management.archive', $course), $payload)->assertForbidden();
        $this->assertSame('draft', $revision->fresh()->status);
        $admin = $this->user('Super Admin');
        $this->actingAs($admin)->post(route('lms.management.activate', $course), $payload)->assertSessionHasNoErrors();
        $approved = $course->fresh()->latestRevision;
        $this->post(route('lms.management.archive', $course), ['revision_id' => $approved->editToken($course->fresh())])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'archived_by' => $admin->id]);
        $this->assertModelExists($course->fresh()->approvedRevision);
    }

    public function test_stale_missing_and_invalid_tokens_do_not_change_lifecycle(): void
    {
        [, , $course] = $this->course();
        $revision = $course->latestRevision;
        $revision->update(['content' => $this->content()]);
        $stale = $revision->editToken($course);
        $revision->update(['title' => 'Changed']);
        $this->actingAs($this->user('Kurikulum'))->post(route('lms.management.activate', $course), ['revision_id' => $stale])->assertSessionHasErrors('revision_id');
        $this->post(route('lms.management.activate', $course), [])->assertSessionHasErrors('revision_id');
        $this->post(route('lms.management.activate', $course), ['revision_id' => 'bad'])->assertSessionHasErrors('revision_id');
        $this->assertSame('draft', $revision->fresh()->status);
        $this->assertNull($course->fresh()->approved_revision_id);
    }

    public function test_archiving_keeps_revision_readable_in_management(): void
    {
        [, , $course] = $this->course();
        $revision = $course->latestRevision;
        $admin = $this->user('Super Admin');
        $this->actingAs($admin)->from(route('lms.management.show', $course))
            ->post(route('lms.management.archive', $course), ['revision_id' => $revision->editToken($course)])
            ->assertRedirect(route('lms.management'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
        $this->get(route('lms.management.show', $course))->assertOk();
    }

    #[TestWith(['pending_approval'])]
    #[TestWith(['rejected'])]
    public function test_legacy_unpublished_revision_is_editable_draft_and_can_be_activated_directly(string $status): void
    {
        [$owner, , $course] = $this->course();
        $revision = $course->latestRevision;
        $revision->update(['content' => $this->content(), 'status' => $status]);
        $this->actingAs($owner)->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->where('course.status', 'draft')->where('course.editable', true)->missing('course.can_submit')->missing('course.review_note'));
        $this->assertTrue($owner->can('update', $course->fresh()));
        $this->assertFalse($owner->can('revise', $course->fresh()));
        $this->actingAs($this->user('Super Admin'))->post(route('lms.management.activate', $course), ['revision_id' => $revision->editToken($course)])->assertSessionHasNoErrors();
        $this->assertSame($revision->id, $course->fresh()->approved_revision_id);
        $this->assertSame('approved', $revision->fresh()->status);
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
    }

    public function test_draft_content_changes_do_not_overwrite_active_revision_until_direct_activation(): void
    {
        [$owner, , $course] = $this->course();
        $active = $course->latestRevision;
        $active->update(['status' => 'approved', 'content' => $this->content()]);
        $course->forceFill(['approved_revision_id' => $active->id])->save();
        $this->actingAs($owner)->post(route('lms.design.revise', $course), ['revision_id' => $active->editToken($course)])->assertSessionHasNoErrors();
        $course = $course->fresh();
        $content = $this->content();
        $content['materials'][0]['body'] = '<p>Materi baru.</p>';
        $this->patch(route('lms.design.content.update', $course), [
            'revision_id' => $course->latestRevision->editToken($course), 'content_json' => json_encode($content),
            'title' => 'Judul baru', 'subject_id' => $course->subject_id, 'grade_level' => $course->grade_level,
        ])->assertSessionHasNoErrors();
        $this->assertSame($active->content, $active->fresh()->content);
        $this->assertSame('Ekosistem', $active->fresh()->title);
        $this->assertSame('Ekosistem', $course->fresh()->title);
        $course = $course->fresh();
        $manager = $this->user('Kurikulum');
        $this->actingAs($manager)->post(route('lms.management.activate', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertSessionHasNoErrors();
        $this->assertSame($course->latestRevision->id, $course->fresh()->approved_revision_id);
        $this->assertSame('Judul baru', $course->fresh()->title);
        $this->assertSame('Ekosistem', $active->fresh()->title);
        $this->get(route('lms.management.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.revisions.0.status', 'active')->where('course.revisions.0.is_active', true)
            ->where('course.revisions.1.status', 'archived')->where('course.revisions.1.is_active', false));
    }

    public function test_management_filters_history_and_archived_listing_are_consistent(): void
    {
        [, , $course] = $this->course();
        $course->latestRevision->update(['status' => 'pending_approval']);
        [, , $other] = $this->course();
        $other->latestRevision->update(['title' => 'Tidak cocok']);
        $manager = $this->user('Kurikulum');
        $this->actingAs($manager)->get(route('lms.management', ['search' => 'EKOSISTEM', 'status' => 'draft', 'subject_id' => $course->subject_id, 'grade_level' => 'VII', 'per_page' => 500]))->assertInertia(fn ($page) => $page->has('courses.data', 1)->where('courses.data.0.id', $course->id)->where('courses.per_page', 50));
        $this->post(route('lms.management.archive', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertSessionHasNoErrors();
        $this->get(route('lms.management', ['status' => 'draft']))->assertInertia(fn ($page) => $page->has('courses.data', 1)->where('courses.data.0.id', $other->id));
        $this->get(route('lms.management', ['archived' => true]))->assertInertia(fn ($page) => $page->has('courses.data', 1)->where('courses.data.0.id', $course->id)->where('courses.data.0.can_archive', false));
        $this->get(route('lms.management.show', $course))->assertInertia(fn ($page) => $page->where('actions.can_activate', false)->where('actions.can_archive', false));
    }

    public function test_course_design_owner_can_only_view_media_from_its_current_revision(): void
    {
        [$owner, , $course] = $this->course();
        Storage::fake('local');
        $revision = $course->latestRevision;
        $content = $this->content();
        $assetId = (string) Str::uuid();
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $assetId];
        $path = 'lms/courses/'.$course->id.'/lesson.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $content['assets'] = [$assetId => ['path' => $path, 'name' => 'lesson.pdf', 'type' => 'pdf', 'mime' => 'application/pdf']];
        $revision->update(['content' => $content]);
        $url = route('lms.design.media.view', [$course, $assetId]);
        $this->actingAs($owner)->get($url)->assertOk();
        $newRevision = $course->revisions()->create(['revision_number' => 2, 'title' => 'Draft', 'content' => $this->content(), 'created_by' => $course->created_by]);
        $this->get($url)->assertNotFound();
        $course->revisions()->whereKey($newRevision->id)->delete();
        $curriculum = $this->user('Kurikulum');
        $this->actingAs($curriculum)->get($url)->assertOk();
        $other = $this->user('Guru');
        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_management_preview_media_is_private_revision_bound_and_html_sanitized(): void
    {
        Storage::fake('local');
        [, , $course] = $this->course();
        $revision = $course->latestRevision;
        $content = $this->content();
        $assetId = (string) Str::uuid();
        $content['materials'][0]['body'] = '<p onclick="evil()">A</p><script>bad()</script>';
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $assetId];
        $path = 'lms/courses/'.$course->id.'/lesson.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');
        $content['assets'] = [$assetId => ['path' => $path, 'name' => 'lesson.pdf', 'type' => 'pdf', 'mime' => 'application/pdf']];
        $revision->update(['content' => $content]);
        $url = route('lms.management.media', [$course, $revision, $assetId]);
        $manager = $this->user('Kurikulum');
        $this->actingAs($manager)->get(route('lms.management.show', $course))->assertInertia(fn ($page) => $page->where('course.revisions.0.content.materials.0.body', '<p>A</p>')->where('course.revisions.0.content.assets.'.$assetId.'.url', $url)->missing('course.revisions.0.content.assets.'.$assetId.'.path'));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $course->revisions()->create(['revision_number' => 2, 'title' => 'Draf baru tanpa lampiran', 'content' => $this->content(), 'created_by' => $course->created_by]);
        $this->get($url)->assertOk();
        $this->get(route('lms.management.media', [$course, $course->fresh()->latestRevision, $assetId]))->assertNotFound();
        [, , $other] = $this->course();
        $this->get(route('lms.management.media', [$other, $revision, $assetId]))->assertNotFound();
        $this->get(route('lms.management.media', [$course, $revision, (string) Str::uuid()]))->assertNotFound();
        $guru = $this->user('Guru');
        $this->actingAs($guru)->get($url)->assertForbidden();
    }

    #[TestWith(['draft'])]
    #[TestWith(['pending_approval'])]
    #[TestWith(['approved'])]
    #[TestWith(['rejected'])]
    public function test_archived_courses_block_all_lifecycle_changes_even_for_super_admin(string $status): void
    {
        [, , $course] = $this->course();
        $course->latestRevision->update(['status' => $status]);
        $course->forceFill(['archived_at' => now()])->save();
        $admin = $this->user('Super Admin');
        $payload = ['revision_id' => $course->latestRevision->editToken($course)];
        $this->actingAs($admin)->post(route('lms.design.revise', $course), $payload)->assertForbidden();
        $this->post(route('lms.management.activate', $course), $payload)->assertForbidden();
        $this->post(route('lms.management.archive', $course), $payload)->assertForbidden();
        $this->assertSame($status, $course->fresh()->latestRevision->status);
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
    }

    public function test_activation_checks_active_subject_empty_sections_and_missing_private_files(): void
    {
        [$owner, , $course] = $this->course();
        Storage::fake('local');
        $revision = $course->latestRevision;
        $content = $this->content();
        $content['materials'][0]['sections'][] = [
            'id' => (string) Str::uuid(), 'title' => 'Bagian kosong', 'body' => '<p>&nbsp;</p>', 'media' => [], 'questions' => [],
        ];
        $revision->update(['content' => $content]);
        $this->actingAs($this->user('Kurikulum'))->post(route('lms.management.activate', $course), ['revision_id' => $revision->editToken($course)])->assertSessionHasErrors('course');
        $content = $this->content();
        $assetId = (string) Str::uuid();
        $content['materials'][0]['media'][] = ['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $assetId];
        $revision->update(['content' => $content]);
        $this->post(route('lms.management.activate', $course), ['revision_id' => $revision->editToken($course)])->assertSessionHasErrors('course');
        $revision->update(['content' => $this->content()]);
        $course->subject->update(['status' => 'inactive']);
        $this->post(route('lms.management.activate', $course), ['revision_id' => $revision->editToken($course)])->assertSessionHasErrors('course');
        $this->assertSame('draft', $revision->fresh()->status);
    }

    public function test_active_revision_cannot_be_activated_again_or_overwritten_even_by_admin(): void
    {
        [, , $course] = $this->course();
        $course->latestRevision->update(['status' => 'approved', 'content' => $this->content()]);
        $admin = $this->user('Super Admin');
        $token = $course->latestRevision->editToken($course);
        $this->actingAs($admin)->post(route('lms.management.activate', $course), ['revision_id' => $token])->assertForbidden();
        $this->patch(route('lms.design.content.update', $course), ['revision_id' => $token, 'content_json' => '{"materials":[]}'])->assertForbidden();
        $this->assertSame('approved', $course->fresh()->latestRevision->status);
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
    }

    public function test_teacher_cannot_activate_or_revise_another_teachers_course(): void
    {
        [$outsider] = $this->course();
        [, , $course] = $this->course();
        $this->actingAs($outsider)->post(route('lms.management.activate', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertForbidden();
        $course->latestRevision->update(['status' => 'approved']);
        $this->post(route('lms.design.revise', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertForbidden();
    }

    public function test_curriculum_design_and_archive_permissions_are_individually_enforced(): void
    {
        [, , $course] = $this->course();
        $course->latestRevision->update(['status' => 'draft']);
        $manager = $this->user('Kurikulum');
        Role::findByName('Kurikulum')->revokePermissionTo('lms.course.design');
        $this->actingAs($manager)->post(route('lms.management.activate', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertForbidden();
        Role::findByName('Kurikulum')->revokePermissionTo('lms.course.archive');
        $this->get(route('lms.management'))->assertForbidden();
        $this->post(route('lms.management.archive', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertForbidden();
    }

    public function test_super_admin_activates_without_teacher_profile_or_design_permission(): void
    {
        [, , $course] = $this->course();
        $revision = $course->latestRevision;
        $revision->update(['content' => $this->content()]);
        Role::findByName('Super Admin')->revokePermissionTo('lms.course.design');
        $admin = $this->user('Super Admin');
        $this->assertNull($admin->teacher);
        $this->actingAs($admin)->get(route('lms.management'))->assertInertia(fn ($page) => $page->where('courses.data.0.can_activate', true));
        $this->post(route('lms.management.activate', $course), ['revision_id' => $revision->editToken($course)])->assertSessionHasNoErrors();
        $this->assertSame($revision->id, $course->fresh()->approved_revision_id);
    }

    public function test_management_shows_only_draft_active_and_archived_statuses(): void
    {
        [, , $draft] = $this->course();
        [, , $pending] = $this->course();
        $pending->latestRevision->update(['status' => 'pending_approval']);
        [, , $rejected] = $this->course();
        $rejected->latestRevision->update(['status' => 'rejected']);
        [, , $active] = $this->course();
        $activeRevision = $active->latestRevision;
        $activeRevision->update(['status' => 'approved']);
        $active->forceFill(['approved_revision_id' => $activeRevision->id])->save();
        [, , $activeWithDraft] = $this->course();
        $approvedRevision = $activeWithDraft->latestRevision;
        $approvedRevision->update(['status' => 'approved']);
        $activeWithDraft->forceFill(['approved_revision_id' => $approvedRevision->id])->save();
        $activeWithDraft->revisions()->create([
            'revision_number' => 2, 'title' => 'Revisi berjalan', 'content' => $this->content(),
            'created_by' => $activeWithDraft->created_by, 'status' => 'draft',
        ]);
        [, , $archived] = $this->course();
        $archivedRevision = $archived->latestRevision;
        $archivedRevision->update(['status' => 'approved']);
        $archived->forceFill(['approved_revision_id' => $archivedRevision->id, 'archived_at' => now()])->save();
        $manager = $this->user('Kurikulum');

        $this->actingAs($manager)->get(route('lms.management'))
            ->assertInertia(fn ($page) => $page->missing('filters.archived')->where('courses.total', 6)
                ->where('courses.data', fn ($rows): bool => collect($rows)->pluck('status', 'id')->sortKeys()->all() === [
                    $draft->id => 'draft', $pending->id => 'draft', $rejected->id => 'draft',
                    $active->id => 'active', $activeWithDraft->id => 'active', $archived->id => 'archived',
                ]));
        $this->get(route('lms.management', ['status' => 'draft']))
            ->assertInertia(fn ($page) => $page->where('filters.status', 'draft')->where('courses.total', 3));
        $this->get(route('lms.management', ['status' => 'active']))
            ->assertInertia(fn ($page) => $page->where('courses.total', 2)
                ->where('courses.data', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all() === [$active->id, $activeWithDraft->id]));
        $this->get(route('lms.management', ['status' => 'archived']))
            ->assertInertia(fn ($page) => $page->where('courses.total', 1)->where('courses.data.0.id', $archived->id)
                ->where('courses.data.0.can_activate', false)->where('courses.data.0.status', 'archived'));
        $this->get(route('lms.management', ['status' => 'pending_approval']))
            ->assertInertia(fn ($page) => $page->where('filters.status', '')->where('courses.total', 6));
        $this->get(route('lms.management.show', $archived))
            ->assertInertia(fn ($page) => $page->where('course.revisions.0.status', 'archived')
                ->where('course.revisions.0.is_active', false));
    }

    #[TestWith(['Kurikulum'])]
    #[TestWith(['Super Admin'])]
    public function test_restoring_archived_active_course_creates_draft_and_preserves_history(string $role): void
    {
        [$owner, , $course] = $this->course();
        $active = $course->latestRevision;
        $active->update(['status' => 'approved', 'content' => $this->content(), 'description' => 'Deskripsi lama.']);
        $manager = $this->user($role);
        $course->forceFill(['approved_revision_id' => $active->id, 'archived_at' => now(), 'archived_by' => $manager->id])->save();

        $this->actingAs($manager)->from(route('lms.management.show', $course))
            ->post(route('lms.management.restore', $course), ['revision_id' => $active->editToken($course)])
            ->assertRedirect(route('lms.management'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'archived_at' => null, 'archived_by' => null, 'approved_revision_id' => null]);
        $this->assertDatabaseHas('tr_lms_course_revisions', ['id' => $active->id, 'status' => 'approved']);
        $this->assertSame($active->content, $active->fresh()->content);
        $draft = $course->fresh()->latestRevision;
        $this->assertSame(2, $draft->revision_number);
        $this->assertSame('draft', $draft->status);
        $this->assertSame($active->title, $draft->title);
        $this->assertSame($active->description, $draft->description);
        $this->assertSame($active->content, $draft->content);
        $this->assertSame($active->created_by, $draft->created_by);
        $this->assertDatabaseCount('tr_lms_course_revisions', 2);
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
        $this->get(route('lms.management', ['status' => 'draft']))->assertInertia(fn ($page) => $page
            ->where('courses.total', 1)->where('courses.data.0.status', 'draft')
            ->where('courses.data.0.active_title', null)->where('courses.data.0.can_restore', false)
            ->where('courses.data.0.can_activate', true));
        $this->get(route('lms.management', ['status' => 'active']))->assertInertia(fn ($page) => $page->where('courses.total', 0));
        $this->get(route('lms.management', ['status' => 'archived']))->assertInertia(fn ($page) => $page->where('courses.total', 0));
        $this->get(route('lms.management.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.revisions.0.status', 'draft')->where('course.revisions.0.is_active', false)
            ->where('course.revisions.1.status', 'archived')->where('course.revisions.1.is_active', false));
        $this->actingAs($owner)->get(route('lms.design'))->assertInertia(fn ($page) => $page
            ->where('courses.data.0.status', 'draft')->where('courses.data.0.editable', true));
        $this->get(route('lms.design.edit', $course))->assertInertia(fn ($page) => $page
            ->where('course.status', 'draft')->where('course.editable', true)->where('course.has_approved_revision', true));
        $this->patch(route('lms.design.update', $course), [
            'revision_id' => $draft->editToken($course->fresh()), 'title' => $draft->title,
            'subject_id' => $course->subject_id, 'grade_level' => 'VIII',
        ])->assertSessionHasErrors('subject_id');
        $this->assertSame('VII', $course->fresh()->grade_level);
        $this->actingAs($manager)->post(route('lms.management.activate', $course), ['revision_id' => $draft->editToken($course->fresh())])
            ->assertSessionHasNoErrors();
        $this->assertSame($draft->id, $course->fresh()->approved_revision_id);
    }

    #[TestWith(['draft', false])]
    #[TestWith(['draft', true])]
    #[TestWith(['pending_approval', true])]
    #[TestWith(['rejected', true])]
    public function test_restoring_reuses_existing_draft_without_overwriting_its_content(string $status, bool $hasHistory): void
    {
        [$owner, , $course] = $this->course();
        $draft = $course->latestRevision;
        $active = null;
        if ($hasHistory) {
            $active = $draft;
            $active->update(['status' => 'approved']);
            $course->forceFill(['approved_revision_id' => $active->id])->save();
            $draft = $course->revisions()->create([
                'revision_number' => 2, 'title' => 'Revisi belum aktif', 'description' => 'Draft terbaru.',
                'content' => $this->content(), 'created_by' => $course->created_by, 'status' => $status,
            ]);
        }
        $course->forceFill(['archived_at' => now(), 'archived_by' => $owner->id])->save();
        $before = ['title' => $draft->title, 'description' => $draft->description, 'content' => $draft->content, 'created_by' => $draft->created_by, 'status' => $draft->status];

        $this->actingAs($this->user('Kurikulum'))->post(route('lms.management.restore', $course), ['revision_id' => $draft->editToken($course)])
            ->assertRedirect(route('lms.management'))->assertSessionHasNoErrors();

        $freshDraft = $draft->fresh();
        $this->assertSame($before['title'], $freshDraft->title);
        $this->assertSame($before['description'], $freshDraft->description);
        $this->assertSame($before['content'], $freshDraft->content);
        $this->assertSame($before['created_by'], $freshDraft->created_by);
        $this->assertSame($before['status'], $freshDraft->status);
        $this->assertDatabaseCount('tr_lms_course_revisions', $hasHistory ? 2 : 1);
        $this->assertNull($course->fresh()->approved_revision_id);
        $this->assertNull($course->fresh()->archived_at);
        if ($active !== null) {
            $this->assertSame('approved', $active->fresh()->status);
        }
        $this->get(route('lms.management', ['status' => 'draft']))->assertInertia(fn ($page) => $page
            ->where('courses.total', 1)->where('courses.data.0.status', 'draft'));
        $this->get(route('lms.management', ['status' => 'active']))->assertInertia(fn ($page) => $page->where('courses.total', 0));
    }

    public function test_restore_requires_archived_course_and_archive_permission(): void
    {
        [$owner, , $course] = $this->course();
        $token = $course->latestRevision->editToken($course);
        $manager = $this->user('Kurikulum');
        $this->actingAs($manager)->post(route('lms.management.restore', $course), ['revision_id' => $token])->assertForbidden();
        $course->forceFill(['archived_at' => now(), 'archived_by' => $manager->id])->save();
        $token = $course->latestRevision->editToken($course);
        $this->actingAs($owner)->post(route('lms.management.restore', $course), ['revision_id' => $token])->assertForbidden();
        Role::findByName('Kurikulum')->revokePermissionTo('lms.course.archive');
        $manager = $manager->fresh();
        $this->assertFalse($manager->can('restore', $course));
        $this->actingAs($manager)->post(route('lms.management.restore', $course), ['revision_id' => $token])->assertForbidden();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'archived_by' => $manager->id]);
        $this->assertNotNull($course->fresh()->archived_at);
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
    }

    #[TestWith(['Guru', true, false])]
    #[TestWith(['Kurikulum', false, false])]
    #[TestWith(['Kurikulum', true, true])]
    #[TestWith(['Super Admin', false, true])]
    public function test_restore_policy_requires_management_role_and_archive_permission(string $role, bool $archivePermission, bool $allowed): void
    {
        [, , $course] = $this->course();
        $user = $this->user($role);
        $roleModel = Role::findByName($role);
        $roleModel->revokePermissionTo('lms.course.archive');
        if ($archivePermission) {
            $roleModel->givePermissionTo('lms.course.archive');
        }
        $course->forceFill(['archived_at' => now(), 'archived_by' => $user->id])->save();

        $this->assertSame($allowed, $user->fresh()->can('restore', $course));
        $course->forceFill(['archived_at' => null])->save();
        $this->assertFalse($user->fresh()->can('restore', $course));
    }

    public function test_archive_only_manager_can_restore_without_design_permission(): void
    {
        [, , $course] = $this->course();
        $manager = $this->user('Kurikulum');
        Role::findByName('Kurikulum')->revokePermissionTo('lms.course.design');
        $course->forceFill(['archived_at' => now(), 'archived_by' => $manager->id])->save();

        $this->actingAs($manager)->post(route('lms.management.restore', $course), ['revision_id' => $course->latestRevision->editToken($course)])
            ->assertRedirect(route('lms.management'))->assertSessionHasNoErrors();

        $this->assertNull($course->fresh()->archived_at);
        $this->assertNull($course->fresh()->approved_revision_id);
        $this->assertSame('draft', $course->fresh()->latestRevision->status);
        $this->assertFalse($manager->can('activate', $course->fresh()));
    }

    public function test_restore_rejects_guest_missing_invalid_and_stale_tokens_without_changes(): void
    {
        [, , $course] = $this->course();
        $revision = $course->latestRevision;
        $revision->update(['status' => 'approved']);
        $course->forceFill(['archived_at' => now(), 'approved_revision_id' => $revision->id])->save();
        $token = $revision->editToken($course);
        $url = route('lms.management.restore', $course);
        $this->post($url, ['revision_id' => $token])->assertRedirect(route('login'));
        $revision->update(['title' => 'Perubahan terbaru']);
        $manager = $this->user('Kurikulum');
        $this->actingAs($manager)->from(route('lms.management.show', $course))->post($url, ['revision_id' => $token])
            ->assertRedirect(route('lms.management.show', $course))->assertSessionHasErrors('revision_id');
        $this->post($url, [])->assertSessionHasErrors('revision_id');
        $this->post($url, ['revision_id' => 'bad'])->assertSessionHasErrors('revision_id');
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'approved_revision_id' => $revision->id]);
        $this->assertNotNull($course->fresh()->archived_at);
    }

    public function test_restore_actions_are_visible_only_for_authorized_archived_courses_and_cannot_repeat(): void
    {
        [, , $course] = $this->course();
        $manager = $this->user('Kurikulum');
        $course->forceFill(['archived_at' => now(), 'archived_by' => $manager->id])->save();
        $token = $course->latestRevision->editToken($course);
        $this->actingAs($manager)->get(route('lms.management', ['status' => 'archived']))->assertInertia(fn ($page) => $page
            ->where('courses.data.0.can_restore', true)->where('courses.data.0.can_activate', false)->where('courses.data.0.can_archive', false));
        $this->get(route('lms.management.show', $course))->assertInertia(fn ($page) => $page->where('actions.can_restore', true));
        $this->post(route('lms.management.restore', $course), ['revision_id' => $token])->assertSessionHasNoErrors();
        $this->post(route('lms.management.restore', $course), ['revision_id' => $token])->assertForbidden();
        $this->assertDatabaseCount('tr_lms_course_revisions', 1);
    }

    /** @return array{User, Teacher, LmsCourse} */
    private function course(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $course = LmsCourse::query()->create(['title' => 'Ekosistem', 'subject_id' => Subject::factory()->create()->id, 'grade_level' => 'VII', 'created_by' => $teacher->id]);
        $course->revisions()->create(['revision_number' => 1, 'title' => 'Ekosistem', 'content' => ['materials' => []], 'created_by' => $teacher->id]);

        return [$user, $teacher, $course];
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function content(): array
    {
        return ['materials' => [[
            'id' => (string) Str::uuid(), 'title' => 'Materi', 'body' => '<p>Isi materi.</p>', 'media' => [], 'questions' => [], 'sections' => [],
        ]]];
    }
}
