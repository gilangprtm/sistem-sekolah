<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MasterSchoolPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'student.view',
        'student.create',
        'student.update',
        'student.delete',
        'student.assign-account',
        'teacher.view',
        'teacher.create',
        'teacher.update',
        'teacher.delete',
        'teacher.assign-account',
        'curriculum.rombel.view',
        'curriculum.rombel.create',
        'curriculum.rombel.update',
        'curriculum.rombel.delete',
        'subject.view',
        'subject.create',
        'subject.update',
        'subject.delete',
    ];

    public function run(): void
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        try {
            DB::transaction(function (): void {
                foreach (self::PERMISSIONS as $permission) {
                    Permission::findOrCreate($permission, 'web');
                }

                foreach (['Kurikulum', 'Super Admin'] as $roleName) {
                    Role::findOrCreate($roleName, 'web')->givePermissionTo(self::PERMISSIONS);
                }
            });
        } finally {
            $permissionRegistrar->forgetCachedPermissions();
        }
    }
}
