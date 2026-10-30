<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['Sarpras', ['inventory.']])]
    #[TestWith(['Kurikulum', ['curriculum.', 'subject.']])]
    #[TestWith(['Kantin', ['kantin.', 'canteen.']])]
    #[TestWith(['Kesiswaan', ['student.card.', 'student_affairs.']])]
    #[TestWith(['Konseling', ['counseling.']])]
    #[TestWith(['Humas', ['humas.']])]
    #[TestWith(['Admin Perpustakaan', ['library.']])]
    public function test_module_owner_receives_every_registered_permission_in_its_domains(string $roleName, array $prefixes): void
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::findByName($roleName, 'web');
        $permissions = Permission::query()->where('guard_name', 'web')->pluck('name')
            ->filter(fn (string $name): bool => str_starts_with($name, $prefixes[0])
                || (isset($prefixes[1]) && str_starts_with($name, $prefixes[1])));

        $this->assertNotEmpty($permissions->all());
        foreach ($permissions as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), $roleName.' missing '.$permission);
        }
    }

    public function test_teacher_receives_every_teacher_feature_without_master_teacher_management(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $teacher = Role::findByName('Guru', 'web');
        foreach ([
            'teacher.agenda.manage', 'teacher.attendance.manage', 'teacher.homeroom.view',
            'teacher.homeroom.seating.manage', 'teacher.quiz.view', 'teacher.student-note.view',
            'teacher.student-note.create', 'teacher.document.view', 'teacher.document.manage',
            'lms.course.view', 'lms.course.design', 'lms.run.manage', 'lms.result.view',
        ] as $permission) {
            $this->assertTrue($teacher->hasPermissionTo($permission), $permission);
        }
        foreach (['teacher.view', 'teacher.create', 'teacher.update', 'teacher.delete', 'teacher.assign-account'] as $permission) {
            $this->assertFalse($teacher->hasPermissionTo($permission), $permission);
        }
    }

    public function test_curriculum_can_open_all_its_menus_and_manage_documents_without_manual_grants(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Kurikulum');
        $this->actingAs($user);

        foreach ([
            '/kurikulum/academic-years', '/kurikulum/schedule', '/kurikulum/management-class',
            '/kurikulum/teacher-subjects', '/kurikulum/ujian', '/kurikulum/dokumen',
        ] as $path) {
            $this->get($path)->assertOk();
        }
        $this->post(route('curriculum.documents.folders.store'), [
            'name' => 'Perangkat Kurikulum', 'visibility' => 'private',
        ])->assertRedirect(route('curriculum.documents.index'));

        $this->assertDatabaseHas('m_teacher_document_folders', [
            'name' => 'Perangkat Kurikulum', 'module' => 'curriculum', 'created_by' => $user->id,
        ]);
    }

    public function test_canteen_owner_can_open_its_menus_without_receiving_other_module_access(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Kantin');
        $this->actingAs($user);

        foreach (['/kantin/dashboard', '/kantin/barang', '/kantin/saldo', '/kantin/dokumen'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/users')->assertForbidden();
        $this->get('/kurikulum/academic-years')->assertForbidden();
    }

    public function test_default_roles_do_not_receive_permissions_from_unrelated_modules(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $allowedPrefixes = [
            'Sarpras' => ['inventory.'],
            'Kurikulum' => ['curriculum.', 'student.', 'teacher.', 'subject.', 'lms.'],
            'Guru' => ['teacher.', 'lms.'],
            'Kantin' => ['kantin.', 'canteen.'],
            'Kesiswaan' => ['student.card.', 'student_affairs.'],
            'Konseling' => ['counseling.'],
            'Humas' => ['humas.'],
            'Admin Perpustakaan' => ['library.'],
            'Siswa' => ['lms.'],
            'Staff' => [],
        ];
        foreach ($allowedPrefixes as $roleName => $prefixes) {
            $role = Role::findByName($roleName, 'web');
            foreach ($role->permissions as $permission) {
                $this->assertTrue(collect($prefixes)->contains(
                    fn (string $prefix): bool => str_starts_with($permission->name, $prefix)
                ), $roleName.' unexpectedly received '.$permission->name);
            }
            $this->assertFalse($role->hasPermissionTo('users.manage'));
            $this->assertFalse($role->hasPermissionTo('roles.manage'));
            $this->assertFalse($role->hasPermissionTo('administration.document.view'));
            $this->assertFalse($role->hasPermissionTo('administration.document.manage'));
        }
        $superAdmin = Role::findByName('Super Admin', 'web');
        foreach (Permission::query()->where('guard_name', 'web')->get() as $permission) {
            $this->assertTrue($superAdmin->hasPermissionTo($permission), $permission->name);
        }
        $this->assertSame([], Role::findByName('Staff', 'web')->permissions->all());
        $this->assertSame(['lms.course.learn', 'lms.course.view'], Role::findByName('Siswa', 'web')->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_reseeding_restores_baseline_without_removing_extra_grants_or_user_assignments(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::findByName('Guru', 'web');
        $role->givePermissionTo('inventory.view');
        $role->revokePermissionTo('teacher.quiz.view');
        $user = User::factory()->create();
        $user->assignRole(['Guru', 'Kurikulum']);
        $user->givePermissionTo('student.card.print');
        $roleIds = $user->roles()->orderBy('id')->pluck('id')->all();

        $this->seed(RolePermissionSeeder::class);
        $grantCount = DB::table('role_has_permissions')->count();
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($grantCount, DB::table('role_has_permissions')->count());
        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.quiz.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('inventory.view'));
        $this->assertSame($roleIds, $user->roles()->orderBy('id')->pluck('id')->all());
        $this->assertTrue($user->fresh()->hasDirectPermission('student.card.print'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('model_has_roles', 2);
        $this->assertDatabaseCount('model_has_permissions', 1);
        $this->assertSame(0, Role::findByName('Kantin', 'web')->users()->count());
    }
}
