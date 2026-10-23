<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class StudentViolationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::findOrCreate('Kesiswaan', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permission = Permission::findOrCreate('student_affairs.violation.'.$action, 'web');
            $role->givePermissionTo($permission);
            Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
