<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LmsPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'lms.course.view',
        'lms.course.design',
        'lms.course.approve',
        'lms.course.archive',
        'lms.run.manage',
        'lms.result.view',
        'lms.course.learn',
    ];

    public function run(): void
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        try {
            DB::transaction(function (): void {
                $permissions = collect(self::PERMISSIONS)
                    ->mapWithKeys(fn (string $name): array => [$name => Permission::findOrCreate($name, 'web')]);

                $roles = [
                    'Kurikulum' => ['lms.course.view', 'lms.course.design', 'lms.course.approve', 'lms.course.archive'],
                    'Guru' => ['lms.course.view', 'lms.course.design', 'lms.run.manage', 'lms.result.view'],
                    'Siswa' => ['lms.course.view', 'lms.course.learn'],
                ];

                foreach ($roles as $roleName => $rolePermissions) {
                    $role = Role::findOrCreate($roleName, 'web');
                    $role->givePermissionTo($permissions->only($rolePermissions)->values());
                }

                Role::findOrCreate('Super Admin', 'web')->givePermissionTo(Permission::query()->where('guard_name', 'web')->get());
            });
        } finally {
            $permissionRegistrar->forgetCachedPermissions();
        }
    }
}
