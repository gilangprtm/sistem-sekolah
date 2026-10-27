<?php

namespace Tests\Feature;

use App\DocumentModule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_seeder_completes_from_an_empty_database_and_can_be_repeated(): void
    {
        $this->assertDatabaseCount('permissions', 0);

        $this->seed(DatabaseSeeder::class);

        $permissionCount = Permission::query()->count();
        $roleCount = Role::query()->count();
        $admin = User::query()->where('email', 'admin@sistemsekolah.test')->sole();
        $this->assertTrue($admin->hasRole('Super Admin'));
        $this->assertDatabaseCount('m_rombels', 30);
        $this->assertTrue(Role::findByName('Guru', 'web')->hasPermissionTo('lms.run.manage'));
        $this->assertTrue(Role::findByName('Konseling', 'web')->hasPermissionTo('counseling.note.view'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('teacher.student-note.create'));

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('permissions', $permissionCount);
        $this->assertDatabaseCount('roles', $roleCount);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('m_rombels', 30);
        $this->assertSame($admin->id, User::query()->where('email', 'admin@sistemsekolah.test')->sole()->id);

        $superAdmin = Role::findByName('Super Admin', 'web');
        foreach (DocumentModule::cases() as $module) {
            $this->assertTrue($superAdmin->hasPermissionTo($module->permission('view')));
            $this->assertTrue($superAdmin->hasPermissionTo($module->permission('manage')));
        }

        $this->assertTrue(Role::findByName('Sarpras', 'web')->hasPermissionTo('inventory.document.manage'));
        $this->assertTrue(Role::findByName('Konseling', 'web')->hasPermissionTo('counseling.document.manage'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('inventory.document.view'));
        $this->assertFalse(Role::findByName('Guru', 'web')->hasPermissionTo('inventory.document.manage'));
    }
}
