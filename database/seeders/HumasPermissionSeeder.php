<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class HumasPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('humas.aspiration.view', 'web');
        $role = Role::findOrCreate('Humas', 'web');
        $role->givePermissionTo($permission);
        Role::query()
            ->where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
