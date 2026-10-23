<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SarprasRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SarprasRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_renames_the_existing_role_without_changing_its_grants_or_members(): void
    {
        $role = Role::findOrCreate('Admin Inventaris', 'web');
        $permission = Permission::findOrCreate('inventory.view', 'web');
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);
        $roleId = $role->id;

        $this->seed(SarprasRoleSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'Admin Inventaris', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['id' => $roleId, 'name' => 'Sarpras', 'guard_name' => 'web']);
        $this->assertDatabaseCount('roles', 1);
        $this->assertSame($roleId, Role::findByName('Sarpras', 'web')->id);
        $this->assertTrue(Role::findByName('Sarpras', 'web')->hasPermissionTo($permission));
        $this->assertTrue($user->fresh()->hasRole('Sarpras', 'web'));
    }

    public function test_fresh_install_creates_sarpras_with_inventory_and_document_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'Admin Inventaris', 'guard_name' => 'web']);
        $role = Role::findByName('Sarpras', 'web');
        $this->assertTrue($role->hasPermissionTo('inventory.view'));
        $this->assertTrue($role->hasPermissionTo('inventory.room.create'));
        $this->assertTrue($role->hasPermissionTo('inventory.document.manage'));
        $this->assertFalse($role->hasPermissionTo('roles.manage'));
    }

    public function test_targeted_seeder_creates_only_sarpras_when_no_legacy_role_exists(): void
    {
        $this->seed(SarprasRoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'Sarpras', 'guard_name' => 'web']);
        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('permissions', 0);
        $this->assertDatabaseCount('role_has_permissions', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_seeder_does_not_touch_other_roles_or_guards(): void
    {
        $role = Role::findOrCreate('Admin Inventaris', 'api');
        $otherRole = Role::findOrCreate('Guru', 'web');
        $permission = Permission::findOrCreate('teacher.agenda.manage', 'web');
        $otherRole->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($otherRole);
        $user->givePermissionTo($permission);

        $this->seed(SarprasRoleSeeder::class);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Admin Inventaris', 'guard_name' => 'api']);
        $this->assertTrue($otherRole->fresh()->hasPermissionTo($permission));
        $this->assertTrue($user->fresh()->hasRole('Guru', 'web'));
        $this->assertTrue($user->hasDirectPermission($permission));
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('permissions', 1);
        $this->assertDatabaseCount('role_has_permissions', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('model_has_permissions', 1);
    }

    public function test_seeder_does_not_create_a_duplicate_when_only_the_new_role_exists(): void
    {
        $role = Role::findOrCreate('Sarpras', 'web');

        $this->seed(SarprasRoleSeeder::class);

        $this->assertDatabaseCount('roles', 1);
        $this->assertSame($role->id, Role::findByName('Sarpras', 'web')->id);
    }

    public function test_seeder_stops_without_merging_when_both_role_names_exist(): void
    {
        Role::findOrCreate('Admin Inventaris', 'web');
        Role::findOrCreate('Sarpras', 'web');

        try {
            $this->seed(SarprasRoleSeeder::class);
            $this->fail('Expected the role collision to stop the seeder.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Role Admin Inventaris dan Sarpras sama-sama tersedia. Penggabungan role memerlukan keputusan eksplisit.', $exception->getMessage());
        }

        $this->assertDatabaseHas('roles', ['name' => 'Admin Inventaris', 'guard_name' => 'web']);
        $this->assertDatabaseHas('roles', ['name' => 'Sarpras', 'guard_name' => 'web']);
        $this->assertDatabaseCount('roles', 2);
    }
}
