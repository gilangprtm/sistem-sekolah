<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CounselingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CounselingRoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_only_the_counseling_role_without_permissions_or_members(): void
    {
        $this->seed(CounselingRoleSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'Konseling', 'guard_name' => 'web']);
        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('permissions', 0);
        $this->assertDatabaseCount('role_has_permissions', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertDatabaseCount('model_has_permissions', 0);
    }

    public function test_repeated_seeding_preserves_existing_counseling_permissions_and_members(): void
    {
        $this->seed(CounselingRoleSeeder::class);
        $role = Role::findByName('Konseling', 'web');
        $permission = Permission::findOrCreate('student.view', 'web');
        $role->givePermissionTo($permission);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->seed(CounselingRoleSeeder::class);

        $this->assertDatabaseCount('roles', 1);
        $this->assertSame($role->id, Role::findByName('Konseling', 'web')->id);
        $this->assertTrue($role->fresh()->hasPermissionTo($permission));
        $this->assertTrue($user->fresh()->hasRole('Konseling', 'web'));
    }

    public function test_seeder_preserves_other_roles_permissions_and_account_assignments(): void
    {
        $role = Role::findOrCreate('Guru', 'web');
        $rolePermission = Permission::findOrCreate('teacher.agenda.manage', 'web');
        $directPermission = Permission::findOrCreate('student.view', 'web');
        $role->givePermissionTo($rolePermission);
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo($directPermission);

        $this->seed(CounselingRoleSeeder::class);

        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('permissions', 2);
        $this->assertDatabaseCount('role_has_permissions', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('model_has_permissions', 1);
        $this->assertTrue($role->fresh()->hasPermissionTo($rolePermission));
        $this->assertTrue($user->fresh()->hasRole('Guru', 'web'));
        $this->assertTrue($user->hasDirectPermission($directPermission));
        $this->assertFalse($user->hasRole('Konseling', 'web'));
        $this->assertSame([], Role::findByName('Konseling', 'web')->permissions->all());
    }
}
