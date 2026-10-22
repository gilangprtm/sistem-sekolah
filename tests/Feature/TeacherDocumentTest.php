<?php

namespace Tests\Feature;

use App\DocumentModule;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Rombel;
use App\Models\Teacher;
use App\Models\TeacherDocument;
use App\Models\TeacherDocumentFolder;
use App\Models\User;
use App\Policies\TeacherDocumentPolicy;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TeacherDocumentPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_cannot_list_upload_download_or_delete_documents(): void
    {
        $owner = $this->user();
        $file = $this->document($owner);
        $this->get(route('teacher.documents.index'))->assertRedirect(route('login'));
        $this->post(route('teacher.documents.store'))->assertRedirect(route('login'));
        $this->get(route('teacher.documents.download', $file))->assertRedirect(route('login'));
        $this->delete(route('teacher.documents.destroy', $file))->assertRedirect(route('login'));
        $this->assertModelExists($file);
    }

    public function test_permission_seeder_is_additive_idempotent_and_scoped_to_the_teacher_role(): void
    {
        $role = Role::findByName('Guru', 'web');
        $role->givePermissionTo('student.card.view');
        $role->revokePermissionTo('teacher.document.manage');

        $this->seed(TeacherDocumentPermissionSeeder::class);
        $this->seed(TeacherDocumentPermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.document.manage'));
        $this->assertTrue($role->fresh()->hasPermissionTo('student.card.view'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('teacher.document.manage'));
    }

    public function test_listing_and_search_never_expose_another_users_private_items(): void
    {
        $owner = $this->user();
        $viewer = $this->user('Siswa');
        $this->document($owner, null, 'private', 'Rahasia');
        $public = $this->document($owner, null, 'public', 'Umum');
        $this->folder($owner, null, 'private', 'Rahasia');
        $folder = $this->folder($owner, null, 'public', 'Umum');

        $this->actingAs($viewer)->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->component('teacher/documents/index')->has('documents.data', 1)->has('folders.data', 1)
            ->where('documents.data.0.id', $public->id)->where('folders.data.0.id', $folder->id)
            ->where('documents.data.0.canDelete', false));
        $this->get(route('teacher.documents.index', ['search' => 'Rahasia']))->assertInertia(fn ($page) => $page
            ->has('documents.data', 0)->has('folders.data', 0));
    }

    public function test_private_root_controls_nested_files_even_if_descendant_visibility_is_forged_public(): void
    {
        $owner = $this->user();
        $root = $this->folder($owner);
        $child = $this->folder($owner, $root, 'public');
        $file = $this->document($owner, $child, 'public');
        $other = $this->user('Staff');
        $other->givePermissionTo('teacher.document.manage');

        $this->actingAs($other)->get(route('teacher.documents.index', ['folder' => $child->id]))->assertNotFound();
        $this->get(route('teacher.documents.download', $file))->assertNotFound();
        $this->get(route('teacher.documents.view', $file))->assertNotFound();
        $this->delete(route('teacher.documents.destroy', $file))->assertNotFound();
        $this->post(route('teacher.documents.folders.store'), ['name' => 'Bocor', 'parent_id' => $child->id])->assertNotFound();
        $this->post(route('teacher.documents.store'), [
            'name' => 'Bocor', 'folder_id' => $child->id, 'file' => $this->imageUpload(),
        ])->assertNotFound();
        $this->assertDatabaseCount('tr_teacher_documents', 1);
        $this->assertModelExists($file);
    }

    public function test_public_folders_accept_nested_contributions_but_keep_each_creator_and_parent_access(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $contributor = $this->user('Siswa');
        $contributor->givePermissionTo('teacher.document.manage');
        $root = $this->folder($owner, null, 'public');
        $this->actingAs($contributor)->post(route('teacher.documents.folders.store'), [
            'name' => 'Kontribusi', 'parent_id' => $root->id, 'visibility' => 'private', 'created_by' => $owner->id,
        ])->assertRedirect();
        $child = TeacherDocumentFolder::query()->where('parent_id', $root->id)->sole();
        $this->assertSame('public', $child->visibility);
        $this->assertSame($contributor->id, $child->created_by);

        $this->post(route('teacher.documents.store'), [
            'name' => 'Gambar bersama', 'folder_id' => $child->id, 'visibility' => 'private',
            'created_by' => $owner->id, 'path' => '/etc/passwd',
            'file' => $this->imageUpload(),
        ])->assertRedirect();
        $file = TeacherDocument::query()->sole();
        $this->assertSame($contributor->id, $file->created_by);
        $this->assertSame('public', $file->visibility);
        $this->assertSame('local', $file->disk);
        $this->assertStringStartsWith('teacher-documents/', $file->path);
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($owner)->delete(route('teacher.documents.destroy', $file))->assertForbidden();
        $this->delete(route('teacher.documents.folders.destroy', $root))->assertConflict();
        $this->assertModelExists($file);
    }

    public function test_file_is_private_outside_folders_and_parent_visibility_cannot_be_overridden(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $root = $this->folder($owner);
        $this->actingAs($owner)->post(route('teacher.documents.store'), [
            'name' => 'Rahasia', 'folder_id' => $root->id, 'visibility' => 'public',
            'file' => $this->imageUpload(),
        ])->assertRedirect();
        $file = TeacherDocument::query()->sole();
        $this->assertSame('private', $file->visibility);
        $this->actingAs($this->user())->get(route('teacher.documents.download', $file))->assertNotFound();
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($owner)->put(route('teacher.documents.destroy', $file), ['visibility' => 'public'])->assertMethodNotAllowed();
        $this->assertSame('private', $file->fresh()->visibility);
    }

    public function test_owner_can_preview_download_and_delete_own_file_without_public_storage_urls(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $file = $this->document($owner);
        Storage::disk('local')->put($file->path, '%PDF-1.4 test');
        $this->actingAs($owner)->get(route('teacher.documents.view', $file))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('teacher.documents.download', $file))->assertDownload('rapport.pdf');
        $this->delete(route('teacher.documents.destroy', $file))->assertRedirect();
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_unprivileged_users_are_rejected_before_upload_payload_validation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('teacher.documents.store'), [])->assertForbidden();
        $this->post(route('teacher.documents.folders.store'), [])->assertForbidden();
    }

    public function test_public_download_does_not_require_manage_permission_but_writes_do(): void
    {
        Storage::fake('local');
        $file = $this->document($this->user(), null, 'public');
        Storage::disk('local')->put($file->path, 'pdf');
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('teacher.document.view');
        $this->actingAs($viewer)->get(route('teacher.documents.download', $file))->assertDownload('rapport.pdf');
        $this->post(route('teacher.documents.folders.store'), ['name' => 'Tanpa izin', 'visibility' => 'public'])->assertForbidden();
        $this->delete(route('teacher.documents.destroy', $file))->assertForbidden();
        $this->assertModelExists($file);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_super_admin_reads_private_items_and_deletes_a_nonempty_tree_from_multiple_owners(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $root = $this->folder($owner);
        $child = $this->folder($owner, $root);
        $grandchild = $this->folder($this->user(), $child);
        $file = $this->document($owner, $grandchild);
        Storage::disk('local')->put($file->path, 'private');
        $this->actingAs($this->user('Super Admin'))->get(route('teacher.documents.download', $file))->assertDownload('rapport.pdf');
        $this->delete(route('teacher.documents.folders.destroy', $root))->assertRedirect();
        $this->assertModelMissing($root);
        $this->assertModelMissing($child);
        $this->assertModelMissing($grandchild);
        $this->assertModelMissing($file);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_owner_can_delete_only_empty_folder_and_cannot_delete_other_users_empty_folder(): void
    {
        $owner = $this->user();
        $root = $this->folder($owner, null, 'public');
        $child = $this->folder($owner, $root, 'public');
        $this->actingAs($this->user())->delete(route('teacher.documents.folders.destroy', $child))->assertForbidden();
        $this->actingAs($owner)->delete(route('teacher.documents.folders.destroy', $root))->assertConflict();
        $this->delete(route('teacher.documents.folders.destroy', $child))->assertRedirect();
        $this->delete(route('teacher.documents.folders.destroy', $root))->assertRedirect();
        $this->assertModelMissing($child);
        $this->assertModelMissing($root);
    }

    public function test_upload_rejects_active_content_wrong_extensions_and_oversized_files_without_persistence(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user());
        foreach ([
            UploadedFile::fake()->createWithContent('active.html', '<html><script>alert(1)</script></html>'),
            UploadedFile::fake()->createWithContent('picture.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('large.pdf', 20481),
        ] as $file) {
            $response = $this->post(route('teacher.documents.store'), ['name' => 'Ditolak', 'visibility' => 'public', 'file' => $file]);
            $this->assertTrue(session()->has('errors'), 'Upload should fail: '.$file->getClientOriginalName().' ('.$response->getStatusCode().')');
            $response->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('tr_teacher_documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_real_file_bytes_reject_html_disguised_as_pdf_even_with_pdf_client_mime(): void
    {
        Storage::fake('local');
        $handle = tmpfile();
        fwrite($handle, '<html><script>alert(1)</script></html>');
        $path = stream_get_meta_data($handle)['uri'];

        try {
            $file = new UploadedFile($path, 'disguised.pdf', 'application/pdf', null, true);
            $this->actingAs($this->user())->post(route('teacher.documents.store'), [
                'name' => 'Ditolak', 'visibility' => 'public', 'file' => $file,
            ])->assertSessionHasErrors('file');
            $this->assertDatabaseCount('tr_teacher_documents', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            fclose($handle);
        }
    }

    public function test_office_files_download_instead_of_inline_preview_and_missing_files_are_not_found(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $file = $this->document($owner);
        $file->update(['mime_type' => 'application/msword', 'original_name' => 'document.doc']);
        Storage::disk('local')->put($file->path, 'office');
        $this->actingAs($owner)->get(route('teacher.documents.view', $file))->assertStatus(415);
        $this->get(route('teacher.documents.download', $file))->assertDownload('document.doc');
        Storage::disk('local')->delete($file->path);
        $this->get(route('teacher.documents.download', $file))->assertNotFound();
    }

    public function test_listing_includes_direct_folder_file_counts_sizes_and_delete_permissions(): void
    {
        Storage::fake('local');
        $owner = $this->user();
        $folder = $this->folder($owner, null, 'public', 'Folder berisi');
        $childFolder = $this->folder($owner, $folder, 'public', 'Subfolder');
        $first = $this->document($owner, $folder, 'public', 'File pertama');
        $second = $this->document($owner, $folder, 'public', 'File kedua');
        $this->document($owner, $childFolder, 'public', 'File bersarang');
        $first->update(['size' => 1024]);
        $second->update(['size' => 2048]);
        $emptyFolder = $this->folder($owner, null, 'public', 'Folder kosong');

        $this->actingAs($owner)->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->where('folders.data.0.fileCount', 2)
            ->where('folders.data.0.size', 3072)
            ->where('folders.data.0.canDelete', false)
            ->where('folders.data.1.fileCount', 0)
            ->where('folders.data.1.size', 0)
            ->where('folders.data.1.canDelete', true));

        $this->actingAs($this->user('Super Admin'))->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->where('folders.data.0.canDelete', true));
        $this->assertModelExists($emptyFolder);
    }

    #[TestWith(['Super Admin'])]
    #[TestWith(['Staff'])]
    #[TestWith(['Siswa'])]
    public function test_non_teacher_roles_have_no_print_options_even_with_a_teacher_profile(string $role): void
    {
        $user = $this->user($role);
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('teacher.documents.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('printOptions', []));
    }

    public function test_teacher_role_has_agenda_option_without_requiring_a_teacher_profile(): void
    {
        $this->actingAs($this->user())->get(route('teacher.documents.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('printOptions', ['agenda']));
    }

    #[TestWith(['active', 'active', true, ['agenda', 'student-attendance']])]
    #[TestWith(['ended', 'active', true, ['agenda']])]
    #[TestWith(['active', 'archived', true, ['agenda']])]
    #[TestWith(['active', 'active', false, ['agenda']])]
    public function test_attendance_option_requires_own_active_homeroom_in_the_active_year(
        string $assignmentStatus,
        string $yearStatus,
        bool $ownAssignment,
        array $expectedOptions,
    ): void {
        $user = $this->user();
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => $yearStatus]);
        HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => Rombel::factory()->create()->id,
            'teacher_id' => $ownAssignment ? $teacher->id : Teacher::factory()->create()->id,
            'status' => $assignmentStatus,
        ]);

        $this->actingAs($user)->get(route('teacher.documents.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('printOptions', $expectedOptions));
    }

    public function test_listing_paginates_and_rejects_arbitrary_sort_fields(): void
    {
        $owner = $this->user();
        for ($i = 0; $i < 26; $i++) {
            $this->document($owner, null, 'private', sprintf('Dokumen %02d', $i));
        }
        $this->actingAs($owner)->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->has('documents.data', 24)->where('documents.total', 26)->where('documents.data.0.name', 'Dokumen 00'));
        $this->get(route('teacher.documents.index', ['page' => 2]))->assertInertia(fn ($page) => $page->has('documents.data', 2));
        $this->get(route('teacher.documents.index', ['sort' => 'name desc; drop table users']))->assertSessionHasErrors('sort');
        $this->get(route('teacher.documents.index', ['visibility' => 'private']))->assertInertia(fn ($page) => $page->where('documents.total', 26));
    }

    #[TestWith(['Guru', 'private', true])]
    #[TestWith(['Staff', 'public', false])]
    #[TestWith(['Super Admin', 'private', false])]
    public function test_nested_listing_reuses_parent_queries_without_changing_visibility_or_ownership(
        string $role,
        string $rootVisibility,
        bool $isOwner,
    ): void {
        $owner = $this->user();
        $viewer = $isOwner ? $owner : $this->user($role);
        $root = $this->folder($owner, null, $rootVisibility, 'Root');
        $middle = $this->folder($owner, $root, 'public', 'Middle');
        $folder = $this->folder($owner, $middle, 'public', 'Current');
        for ($i = 0; $i < 24; $i++) {
            $this->folder($owner, $folder, 'public', sprintf('Folder %02d', $i));
            $this->document($owner, $folder, 'public', sprintf('File %02d', $i));
        }
        $this->actingAs($viewer);
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        try {
            $response = $this->get(route('teacher.documents.index', ['folder' => $folder->id]));
            $folderQueries = collect($connection->getQueryLog())
                ->filter(fn (array $query): bool => str_contains($query['query'], 'from "m_teacher_document_folders"'))
                ->count();
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }

        $canDelete = $isOwner || $role === 'Super Admin';
        $response->assertInertia(fn ($page) => $page
            ->has('folders.data', 24)->has('documents.data', 24)
            ->where('folder.visibility', $rootVisibility)
            ->where('folder.breadcrumbs.0.id', $root->id)
            ->where('folder.breadcrumbs.1.id', $middle->id)
            ->where('folder.breadcrumbs.2.id', $folder->id)
            ->where('folders.data.0.visibility', $rootVisibility)
            ->where('folders.data.23.visibility', $rootVisibility)
            ->where('documents.data.0.visibility', $rootVisibility)
            ->where('documents.data.23.visibility', $rootVisibility)
            ->where('folders.data.0.canDelete', $canDelete)
            ->where('documents.data.0.canDelete', $canDelete)
            ->missing('documents.data.0.path')->missing('documents.data.0.disk'));
        $this->assertSame(5, $folderQueries, 'Folder queries must depend on ancestor depth, not the number of listed items.');
    }

    public function test_one_hundred_folders_and_files_remain_independently_paginated_without_reading_file_storage(): void
    {
        $owner = $this->user();
        for ($i = 0; $i < 100; $i++) {
            $this->folder($owner, null, 'private', sprintf('Folder %03d', $i));
            $this->document($owner, null, 'private', sprintf('File %03d', $i));
        }
        Storage::shouldReceive('disk')->never();
        $this->actingAs($owner);

        $this->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->has('folders.data', 24)->where('folders.total', 100)
            ->has('documents.data', 24)->where('documents.total', 100)
            ->where('folders.data.0.name', 'Folder 000')->where('documents.data.0.name', 'File 000'));
        $this->get(route('teacher.documents.index', ['folder_page' => 5, 'page' => 2]))->assertInertia(fn ($page) => $page
            ->has('folders.data', 4)->where('folders.current_page', 5)
            ->has('documents.data', 24)->where('documents.current_page', 2)
            ->where('folders.data.0.name', 'Folder 096')->where('documents.data.0.name', 'File 024'));
    }

    public function test_policy_enforces_owner_viewer_and_super_admin_access_matrix(): void
    {
        $owner = $this->user();
        $viewer = $this->user('Siswa');
        $admin = $this->user('Super Admin');
        $private = $this->document($owner);
        $public = $this->document($owner, null, 'public');
        $policy = app(TeacherDocumentPolicy::class);
        $this->assertTrue($policy->view($owner, $private, DocumentModule::Teacher)->allowed());
        $this->assertTrue($policy->delete($owner, $private, DocumentModule::Teacher)->allowed());
        $this->assertTrue($policy->view($viewer, $private, DocumentModule::Teacher)->denied());
        $this->assertTrue($policy->view($viewer, $public, DocumentModule::Teacher)->allowed());
        $this->assertTrue($policy->delete($viewer, $public, DocumentModule::Teacher)->denied());
        $this->assertTrue($policy->view($admin, $private, DocumentModule::Teacher)->allowed());
        $this->assertTrue($policy->delete($admin, $private, DocumentModule::Teacher)->allowed());
    }

    private function imageUpload(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6K1sAAAAASUVORK5CYII='));
    }

    private function user(string $role = 'Guru'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo('teacher.document.view');

        return $user;
    }

    private function folder(User $owner, ?TeacherDocumentFolder $parent = null, string $visibility = 'private', string $name = 'Folder'): TeacherDocumentFolder
    {
        return TeacherDocumentFolder::query()->create([
            'name' => $name, 'created_by' => $owner->id, 'parent_id' => $parent?->id, 'visibility' => $visibility,
        ]);
    }

    private function document(User $owner, ?TeacherDocumentFolder $folder = null, string $visibility = 'private', string $name = 'Dokumen'): TeacherDocument
    {
        return TeacherDocument::query()->create([
            'name' => $name, 'created_by' => $owner->id, 'teacher_document_folder_id' => $folder?->id,
            'original_name' => 'rapport.pdf', 'visibility' => $visibility, 'disk' => 'local',
            'path' => 'teacher-documents/'.Str::uuid().'.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
        ]);
    }
}
