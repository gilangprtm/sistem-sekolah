<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LibraryPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_library_menu_routes_render_their_placeholder_sections_for_library_staff(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin Perpustakaan');

        $this->actingAs($user)->get(route('library.collection.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('library/placeholder')->where('section', 'collection'));
        $this->get(route('library.circulation.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('library/placeholder')->where('section', 'circulation'));
        $this->get(route('library.documents.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('teacher/documents/index')
                ->where('module.key', 'library')->where('module.label', 'Perpustakaan'));
    }

    public function test_library_menu_routes_redirect_guests_to_login(): void
    {
        $this->get(route('library.collection.index'))->assertRedirect(route('login'));
        $this->get(route('library.circulation.index'))->assertRedirect(route('login'));
        $this->get(route('library.documents.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_library_permissions_are_forbidden_from_library_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get(route('library.collection.index'))->assertForbidden();
        $this->get(route('library.circulation.index'))->assertForbidden();
        $this->get(route('library.documents.index'))->assertForbidden();
    }

    public function test_permission_seeder_grants_library_menus_and_documents_only_to_library_staff_and_super_admin(): void
    {
        $this->seed(LibraryPermissionSeeder::class);
        $librarian = User::factory()->create();
        $librarian->assignRole('Admin Perpustakaan');
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->assertTrue($librarian->can('library.collection.view'));
        $this->assertTrue($librarian->can('library.circulation.view'));
        $this->assertTrue($librarian->can('library.document.view'));
        $this->assertTrue($librarian->can('library.document.manage'));
        $this->assertFalse($teacher->can('library.collection.view'));
        $this->assertFalse($teacher->can('library.circulation.view'));
        $this->assertFalse($teacher->can('library.document.view'));
        $this->assertFalse($teacher->can('library.document.manage'));
        $this->assertTrue($admin->can('library.collection.view'));
        $this->assertTrue($admin->can('library.circulation.view'));
        $this->assertTrue($admin->can('library.document.manage'));
    }

    public function test_individual_menu_permissions_do_not_grant_other_library_menus_or_documents(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('library.collection.view');

        $this->actingAs($user)->get(route('library.collection.index'))->assertOk();
        $this->get(route('library.circulation.index'))->assertForbidden();
        $this->get(route('library.documents.index'))->assertForbidden();

        $user->revokePermissionTo('library.collection.view');
        $user->givePermissionTo('library.circulation.view');

        $this->get(route('library.collection.index'))->assertForbidden();
        $this->get(route('library.circulation.index'))->assertOk();
        $this->get(route('library.documents.index'))->assertForbidden();
    }

    public function test_super_admin_can_open_library_menus_without_assigned_library_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $user->roles->first()->revokePermissionTo(['library.collection.view', 'library.circulation.view']);

        $this->actingAs($user)->get(route('library.collection.index'))->assertOk();
        $this->get(route('library.circulation.index'))->assertOk();
    }
}
