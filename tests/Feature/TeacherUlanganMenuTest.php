<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TeacherQuizPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherUlanganMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_ulangan(): void
    {
        $this->get('/guru/ulangan')->assertRedirect(route('login'));
    }

    public function test_teacher_can_open_the_ulangan_shell(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get(route('teacher.quizzes.index'))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->component('teacher/quizzes/index')
            ->where('auth.permissions', fn ($permissions): bool => $permissions->contains('teacher.quiz.view')));
    }

    public function test_users_without_ulangan_permission_cannot_open_the_shell(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)->get('/guru/ulangan')->assertForbidden();
    }

    public function test_revoking_ulangan_permission_blocks_the_teacher(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        Role::findByName('Guru', 'web')->revokePermissionTo('teacher.quiz.view');

        $this->actingAs($user)->get('/guru/ulangan')->assertForbidden();
    }

    public function test_permission_seed_is_additive_and_idempotent_without_changing_user_assignments(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $role = Role::findByName('Guru', 'web');
        $role->revokePermissionTo('teacher.quiz.view');
        $userRoleCount = $this->app['db']->table('model_has_roles')->count();

        $this->seed(TeacherQuizPermissionSeeder::class);
        $this->seed(TeacherQuizPermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.quiz.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.agenda.manage'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('teacher.quiz.view'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('teacher.quiz.view'));
        $this->assertSame($userRoleCount, $this->app['db']->table('model_has_roles')->count());
    }
}
