<?php

namespace Tests\Feature;

use App\DocumentModule;
use App\Models\TeacherDocument;
use App\Models\TeacherDocumentFolder;
use App\Models\User;
use Database\Seeders\ModuleDocumentPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ModuleDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_module_document_routes_are_distinct_and_super_admin_can_access_each(): void
    {
        $admin = $this->user('Super Admin');
        $paths = [
            '/inventaris/dokumen' => 'inventory.documents.index',
            '/kurikulum/dokumen' => 'curriculum.documents.index',
            '/guru/dokumen' => 'teacher.documents.index',
            '/kesiswaan/dokumen' => 'student_affairs.documents.index',
            '/kantin/dokumen' => 'canteen.documents.index',
            '/administrasi/dokumen' => 'administration.documents.index',
            '/humas/dokumen' => 'humas.documents.index',
        ];

        foreach ($paths as $path => $name) {
            $this->actingAs($admin)->get($path)->assertOk()
                ->assertInertia(fn ($page) => $page->component('teacher/documents/index'));
            $this->assertSame($path, route($name, absolute: false));
        }
    }

    public function test_private_and_public_visibility_is_limited_to_users_within_their_document_module(): void
    {
        $teacher = $this->user('Guru');
        $otherTeacher = $this->user('Guru');
        $curriculumUser = $this->user('Staff');
        $curriculumUser->givePermissionTo(['curriculum.document.view', 'curriculum.document.manage']);
        $private = $this->document($teacher, DocumentModule::Teacher, 'private', 'Rahasia Guru');
        $public = $this->document($teacher, DocumentModule::Teacher, 'public', 'Umum Guru');

        $this->actingAs($otherTeacher)->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->where('documents.total', 1)->where('documents.data.0.name', 'Umum Guru'));
        $this->actingAs($curriculumUser)->get(route('curriculum.documents.index'))->assertInertia(fn ($page) => $page
            ->where('documents.total', 0));
        $this->get(route('curriculum.documents.download', $public))->assertNotFound();
        $this->get(route('curriculum.documents.view', $private))->assertNotFound();
        $this->delete(route('curriculum.documents.destroy', $public))->assertNotFound();
        $this->get(route('teacher.documents.download', $private))->assertForbidden();
        $this->assertModelExists($private);
        $this->assertModelExists($public);
    }

    public function test_module_access_is_enforced_for_listing_creation_upload_and_super_admin(): void
    {
        Storage::fake('local');
        $teacher = $this->user('Guru');
        $curriculumUser = $this->user('Staff');
        $curriculumUser->givePermissionTo(['curriculum.document.view', 'curriculum.document.manage']);
        $teacherFolder = $this->folder($teacher, DocumentModule::Teacher);

        $this->actingAs($teacher)->get(route('curriculum.documents.index'))->assertForbidden();
        $this->post(route('curriculum.documents.folders.store'), ['name' => 'Folder lintas modul', 'visibility' => 'public'])->assertForbidden();
        $this->actingAs($curriculumUser)->post(route('curriculum.documents.store'), [
            'name' => 'Salah folder', 'folder_id' => $teacherFolder->id,
            'file' => UploadedFile::fake()->create('dokumen.pdf', 16, 'application/pdf'),
        ])->assertNotFound();
        $this->assertDatabaseCount('m_teacher_document_folders', 1);
        $this->assertDatabaseCount('tr_teacher_documents', 0);
        Storage::disk('local')->assertDirectoryEmpty('module-documents/curriculum');

        $admin = $this->user('Super Admin');
        $this->actingAs($admin)->post(route('curriculum.documents.folders.store'), ['name' => 'Kurikulum', 'visibility' => 'private'])->assertRedirect(route('curriculum.documents.index'));
        $this->assertDatabaseHas('m_teacher_document_folders', ['name' => 'Kurikulum', 'module' => 'curriculum']);
    }

    public function test_seeder_assigns_module_document_permissions_only_to_mapped_roles_and_super_admin(): void
    {
        $this->seed(ModuleDocumentPermissionSeeder::class);
        $this->seed(ModuleDocumentPermissionSeeder::class);

        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('curriculum.document.view'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('administration.document.manage'));
        $this->assertTrue(Role::findByName('Guru', 'web')->hasPermissionTo('teacher.document.manage'));
        $this->assertTrue(Role::findByName('Sarpras', 'web')->hasPermissionTo('inventory.document.manage'));
        $this->assertTrue(Role::findByName('Kesiswaan', 'web')->hasPermissionTo('student_affairs.document.manage'));
        $this->assertTrue(Role::findByName('Humas', 'web')->hasPermissionTo('humas.document.view'));
        $this->assertTrue(Role::findByName('Humas', 'web')->hasPermissionTo('humas.document.manage'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('humas.document.manage'));
        $this->assertFalse(Role::findByName('Guru', 'web')->hasPermissionTo('curriculum.document.view'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('teacher.document.view'));
        $this->assertFalse(Role::findByName('Admin Perpustakaan', 'web')->hasPermissionTo('administration.document.view'));
        $this->assertFalse(Role::findByName('Staff', 'web')->hasPermissionTo('humas.document.view'));
    }

    #[TestWith(['inventory.documents.index', '/inventaris/dokumen'])]
    #[TestWith(['curriculum.documents.index', '/kurikulum/dokumen'])]
    #[TestWith(['teacher.documents.index', '/guru/dokumen'])]
    #[TestWith(['student_affairs.documents.index', '/kesiswaan/dokumen'])]
    #[TestWith(['canteen.documents.index', '/kantin/dokumen'])]
    #[TestWith(['administration.documents.index', '/administrasi/dokumen'])]
    #[TestWith(['humas.documents.index', '/humas/dokumen'])]
    public function test_module_documents_use_expected_canonical_route(string $routeName, string $path): void
    {
        $this->assertSame($path, route($routeName, absolute: false));
    }

    public function test_every_module_keeps_uploads_folders_links_and_super_admin_actions_in_its_own_scope(): void
    {
        Storage::fake('local');
        $admin = $this->user('Super Admin');
        $otherOwner = $this->user('Guru');
        $this->actingAs($admin);

        foreach ([DocumentModule::Inventory, DocumentModule::Curriculum, DocumentModule::Teacher, DocumentModule::StudentAffairs, DocumentModule::Canteen, DocumentModule::Administration, DocumentModule::Humas] as $module) {
            $foreign = $this->folder($otherOwner, $module, 'private');
            $this->get(route($module->routeName(), ['folder' => $foreign->id]))->assertInertia(fn ($page) => $page
                ->where('folder.id', $foreign->id)
                ->where('folder.deleteUrl', route($module->routeName('folders.destroy'), $foreign)));
            $this->post(route($module->routeName('folders.store')), [
                'name' => 'Entri '.$module->value, 'visibility' => 'private', 'module' => 'forged',
            ])->assertRedirect(route($module->routeName()));
            $root = TeacherDocumentFolder::query()->where('name', 'Entri '.$module->value)->sole();
            $this->assertSame($module->value, $root->module);
            $this->post(route($module->routeName('store')), [
                'name' => 'Unggahan '.$module->value, 'folder_id' => $root->id,
                'module' => 'forged', 'visibility' => 'public',
                'file' => UploadedFile::fake()->createWithContent('pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6K1sAAAAASUVORK5CYII=')),
            ])->assertRedirect(route($module->routeName(), ['folder' => $root->id]));
            $file = TeacherDocument::query()->where('teacher_document_folder_id', $root->id)->sole();
            $this->assertSame($module->value, $file->module);
            $this->assertSame('private', $file->visibility);
            Storage::disk('local')->assertExists($file->path);
            $this->get(route($module->routeName(), ['folder' => $root->id]))->assertInertia(fn ($page) => $page
                ->where('module.key', $module->value)
                ->where('documentRoutes.store', route($module->routeName('store')))
                ->where('documents.total', 1)
                ->where('documents.data.0.downloadUrl', route($module->routeName('download'), $file))
                ->where('folder.breadcrumbs.0.url', route($module->routeName(), ['folder' => $root->id])));
            $this->get(route($module->routeName('view'), $file))->assertOk();
            $this->get(route($module->routeName('download'), $file))->assertDownload('pixel.png');
            $this->delete(route($module->routeName('destroy'), $file))->assertRedirect(route($module->routeName(), ['folder' => $root->id]));
            $this->assertModelMissing($file);
            Storage::disk('local')->assertMissing($file->path);
            $this->delete(route($module->routeName('folders.destroy'), $root))->assertRedirect(route($module->routeName()));
            $this->assertModelMissing($root);
        }
    }

    #[TestWith(['curriculum'])]
    #[TestWith(['humas'])]
    public function test_cross_module_folder_ids_are_rejected_even_for_super_admin_without_mutation(string $module): void
    {
        Storage::fake('local');
        $admin = $this->user('Super Admin');
        $folder = $this->folder($admin, DocumentModule::Teacher, 'public');
        $file = $this->document($admin, DocumentModule::Teacher, 'public', 'Agenda');
        $this->actingAs($admin);

        $this->get(route($module.'.documents.index', ['folder' => $folder->id]))->assertNotFound();
        $this->post(route($module.'.documents.folders.store'), ['parent_id' => $folder->id, 'name' => 'Bocor'])->assertNotFound();
        $this->delete(route($module.'.documents.folders.destroy', $folder))->assertNotFound();
        $this->get(route($module.'.documents.view', $file))->assertNotFound();
        $this->get(route($module.'.documents.download', $file))->assertNotFound();
        $this->delete(route($module.'.documents.destroy', $file))->assertNotFound();
        $this->assertModelExists($folder);
        $this->assertModelExists($file);
        $this->assertDatabaseCount('m_teacher_document_folders', 1);
    }

    public function test_read_only_module_permission_has_navigation_and_download_but_no_write_access(): void
    {
        Storage::fake('local');
        $viewer = $this->user('Staff');
        $viewer->givePermissionTo('curriculum.document.view');
        $file = $this->document($this->user('Super Admin'), DocumentModule::Curriculum, 'public', 'Kurikulum');
        Storage::disk('local')->put($file->path, 'pdf');
        $this->actingAs($viewer);

        $this->get(route('curriculum.documents.index'))->assertInertia(fn ($page) => $page
            ->where('canManage', false)->where('printOptions', [])
            ->has('documentNavigation', 1)
            ->where('documentNavigation.0.url', route('curriculum.documents.index'))
            ->where('documents.total', 1));
        $this->get(route('curriculum.documents.download', $file))->assertDownload('report.pdf');
        $this->post(route('curriculum.documents.folders.store'), ['name' => 'Ditolak', 'visibility' => 'public'])->assertForbidden();
        $this->post(route('curriculum.documents.store'), [])->assertForbidden();
        $this->delete(route('curriculum.documents.destroy', $file))->assertForbidden();
        $this->get(route('teacher.documents.index'))->assertForbidden();
        $this->assertModelExists($file);
    }

    public function test_humas_documents_are_isolated_and_use_the_silaras_view_permission_only_as_module_access(): void
    {
        $humas = $this->user('Humas');
        $silarasViewer = $this->user('Staff');
        $silarasViewer->givePermissionTo('humas.aspiration.view');

        $this->actingAs($humas)->get(route('humas.documents.index'))->assertInertia(fn ($page) => $page
            ->where('module.key', 'humas')
            ->where('module.label', 'Humas')
            ->where('canManage', true)
            ->has('documentNavigation', 1)
            ->where('documentNavigation.0.key', 'humas')
            ->where('documentNavigation.0.url', route('humas.documents.index')));
        $this->get(route('teacher.documents.index'))->assertForbidden();

        $this->actingAs($silarasViewer)->get(route('humas.documents.index'))->assertForbidden();
        $this->assertDatabaseCount('m_teacher_document_folders', 0);
        $this->assertDatabaseCount('tr_teacher_documents', 0);
    }

    public function test_humas_members_can_download_public_documents_but_cannot_read_private_or_delete_another_members_file(): void
    {
        Storage::fake('local');
        $owner = $this->user('Humas');
        $viewer = $this->user('Humas');
        $public = $this->document($owner, DocumentModule::Humas, 'public', 'Publik Humas');
        $private = $this->document($owner, DocumentModule::Humas, 'private', 'Pribadi Humas');
        $this->document($this->user('Guru'), DocumentModule::Teacher, 'public', 'Publik Guru');
        Storage::disk('local')->put($public->path, 'pdf');

        $this->actingAs($viewer)->get(route('humas.documents.index'))->assertInertia(fn ($page) => $page
            ->where('documents.total', 1)
            ->where('documents.data.0.name', 'Publik Humas')
            ->where('documents.data.0.canDelete', false));
        $this->get(route('humas.documents.download', $public))->assertDownload('report.pdf');
        $this->get(route('humas.documents.view', $private))->assertNotFound();
        $this->delete(route('humas.documents.destroy', $public))->assertForbidden();
        $this->assertModelExists($public);
        $this->assertModelExists($private);
        Storage::disk('local')->assertExists($public->path);
    }

    public function test_guru_manage_permission_keeps_document_navigation_when_view_permission_has_not_been_seeded(): void
    {
        $teacher = $this->user('Guru');
        Role::findByName('Guru', 'web')->revokePermissionTo('teacher.document.view');
        $this->assertTrue($teacher->can('teacher.document.manage'));
        $this->assertFalse($teacher->can('teacher.document.view'));

        $this->actingAs($teacher)->get(route('teacher.documents.index'))->assertInertia(fn ($page) => $page
            ->has('documentNavigation', 1)
            ->where('documentNavigation.0.key', 'teacher')
            ->where('documentNavigation.0.url', route('teacher.documents.index')));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function folder(User $owner, DocumentModule $module, string $visibility = 'private'): TeacherDocumentFolder
    {
        return TeacherDocumentFolder::query()->create([
            'name' => 'Folder '.$module->value,
            'created_by' => $owner->id,
            'module' => $module->value,
            'visibility' => $visibility,
        ]);
    }

    private function document(User $owner, DocumentModule $module, string $visibility, string $name): TeacherDocument
    {
        return TeacherDocument::query()->create([
            'name' => $name,
            'created_by' => $owner->id,
            'module' => $module->value,
            'original_name' => 'report.pdf',
            'visibility' => $visibility,
            'disk' => 'local',
            'path' => 'module-documents/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);
    }
}
