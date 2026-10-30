<?php

namespace Tests\Feature;

use App\DocumentModule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_roles_cannot_access_administration_documents_even_with_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (Role::query()->where('name', '!=', 'Super Admin')->get() as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $user->givePermissionTo(['administration.document.view', 'administration.document.manage']);

            $this->assertFalse(DocumentModule::Administration->canView($user), $role->name);
            $this->assertFalse(DocumentModule::Administration->canManage($user), $role->name);
        }
    }

    #[TestWith(['/users'])]
    #[TestWith(['/roles'])]
    public function test_administration_web_endpoints_reject_non_super_admin_with_manage_permissions(string $path): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(['Kurikulum', 'Guru']);
        $user->givePermissionTo(['users.manage', 'roles.manage']);

        $this->actingAs($user)->get($path)->assertForbidden();
        $this->post($path, [])->assertForbidden();
    }

    #[TestWith(['/api/v1/users'])]
    #[TestWith(['/api/v1/roles'])]
    public function test_administration_api_endpoints_return_403_for_non_super_admin_with_manage_permissions(string $path): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(['Kurikulum', 'Guru']);
        $user->givePermissionTo(['users.manage', 'roles.manage']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson($path)->assertForbidden();
        $this->postJson($path, [])->assertForbidden();
    }

    public function test_legacy_administration_permissions_do_not_expose_document_navigation(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(['Kurikulum', 'Guru']);
        $user->givePermissionTo(['administration.document.view', 'administration.document.manage']);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('documentNavigation', fn ($navigation) => ! $navigation->contains('key', 'administration')));
    }
}
