<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TeacherQuizPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        try {
            $permission = Permission::findOrCreate('teacher.quiz.view', 'web');
            Role::findOrCreate('Guru', 'web')->givePermissionTo($permission);
            Role::findOrCreate('Super Admin', 'web')->givePermissionTo($permission);
        } finally {
            $permissionRegistrar->forgetCachedPermissions();
        }
    }
}
