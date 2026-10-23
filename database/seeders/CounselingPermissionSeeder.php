<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CounselingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CounselingRoleSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permission = Permission::findOrCreate('counseling.note.'.$action, 'web');
            Role::findByName('Konseling', 'web')->givePermissionTo($permission);
            Role::query()->where('name', 'Super Admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
