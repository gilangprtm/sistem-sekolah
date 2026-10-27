<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MasterSchoolPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterSchoolModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_kurikulum_can_open_all_master_school_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Kurikulum');

        $this->actingAs($user)->get('/students')->assertOk();
        $this->get('/teachers')->assertOk();
        $this->get('/kurikulum/rombels')->assertOk();
        $this->get('/kurikulum/subjects')->assertOk();
    }

    public function test_super_admin_can_open_all_master_school_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->actingAs($user)->get('/students')->assertOk();
        $this->get('/teachers')->assertOk();
        $this->get('/kurikulum/rombels')->assertOk();
        $this->get('/kurikulum/subjects')->assertOk();
    }

    public function test_users_without_master_school_permissions_are_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get('/students')->assertForbidden();
        $this->get('/teachers')->assertForbidden();
        $this->get('/kurikulum/rombels')->assertForbidden();
        $this->get('/kurikulum/subjects')->assertForbidden();
    }

    public function test_master_school_permission_seeder_grants_management_permissions_only_to_target_roles(): void
    {
        $kurikulum = User::factory()->create();
        $kurikulum->assignRole('Kurikulum');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('Super Admin');
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');

        foreach ([
            'student.view', 'student.create', 'student.update', 'student.delete', 'student.assign-account',
            'teacher.view', 'teacher.create', 'teacher.update', 'teacher.delete', 'teacher.assign-account',
            'curriculum.rombel.view', 'curriculum.rombel.create', 'curriculum.rombel.update', 'curriculum.rombel.delete',
            'subject.view', 'subject.create', 'subject.update', 'subject.delete',
        ] as $permission) {
            $this->assertTrue($kurikulum->can($permission));
            $this->assertTrue($superAdmin->can($permission));
            $this->assertFalse($teacher->can($permission));
        }
    }

    public function test_targeted_seeder_is_idempotent_and_preserves_existing_permissions_and_user_roles(): void
    {
        $kurikulum = User::factory()->create();
        $kurikulum->assignRole('Kurikulum');
        $teacher = User::factory()->create();
        $teacher->assignRole('Guru');
        $teacher->givePermissionTo('subject.view');
        $permissionCount = $this->app['db']->table('role_has_permissions')->count();
        $userRoleCount = $this->app['db']->table('model_has_roles')->count();

        $this->seed(MasterSchoolPermissionSeeder::class);
        $this->seed(MasterSchoolPermissionSeeder::class);

        $this->assertSame($permissionCount, $this->app['db']->table('role_has_permissions')->count());
        $this->assertSame($userRoleCount, $this->app['db']->table('model_has_roles')->count());
        $this->assertTrue($kurikulum->fresh()->can('lms.course.design'));
        $this->assertTrue($teacher->fresh()->can('subject.view'));
        $this->assertFalse($teacher->fresh()->can('student.view'));
        $this->assertFalse($kurikulum->fresh()->can('users.manage'));
        $this->assertFalse($kurikulum->fresh()->can('roles.manage'));
        $this->assertFalse($kurikulum->fresh()->can('student.card.view'));
    }

    public function test_guests_are_redirected_to_login_from_master_school_pages(): void
    {
        $this->get('/students')->assertRedirect(route('login'));
        $this->get('/teachers')->assertRedirect(route('login'));
        $this->get('/kurikulum/rombels')->assertRedirect(route('login'));
        $this->get('/kurikulum/subjects')->assertRedirect(route('login'));
    }
}
