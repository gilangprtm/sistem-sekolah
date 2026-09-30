<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_access_roles_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/roles')
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('roles/index')
                    ->has('roles')
                    ->has('permissions');
            });
    }

    public function test_roles_page_loads_permissions_per_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/roles')
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('roles/index')
                    ->has('roles.data')
                    ->has('roles.data.0.permissions')
                    ->where('roles.per_page', 10);
            });
    }

    public function test_roles_page_supports_search_and_bounded_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/roles?search=Guru&per_page=1&page=1')
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('roles/index')
                    ->where('filters.search', 'Guru')
                    ->where('filters.per_page', '1')
                    ->where('roles.per_page', 1)
                    ->where('roles.total', 1)
                    ->where('roles.data.0.name', 'Guru');
            });
    }

    public function test_non_admin_cannot_access_roles_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->get('/roles')
            ->assertForbidden();
    }

    public function test_super_admin_role_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $role = Role::findByName('Super Admin');

        $this->actingAs($admin)
            ->delete("/roles/{$role->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'Super Admin']);
    }
}
