<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Daftar role awal sistem.
     */
    private const ROLES = [
        'Super Admin',
        'Admin Inventaris',
        'Guru',
        'Admin Perpustakaan',
        'Siswa',
    ];

    /**
     * Daftar permission per domain.
     */
    private const PERMISSIONS = [
        'users.manage',
        'roles.manage',
        'inventory.view',
        'inventory.create',
        'inventory.delete',
        'inventory.dashboard.view',
        'inventory.unit.create',
        'inventory.unit.condition.update',
    ];

    /**
     * Seed role & permission awal.
     */
    public function run(): void
    {
        // Buat permission (idempotent)
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Buat role (idempotent)
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Super Admin mendapat SEMUA permission
        $superAdmin = Role::findByName('Super Admin', 'web');
        $superAdmin->syncPermissions(self::PERMISSIONS);

        // Admin Inventaris mendapat permission inventaris
        $adminInventaris = Role::findByName('Admin Inventaris', 'web');
        $adminInventaris->syncPermissions([
            'inventory.view',
            'inventory.create',
            'inventory.delete',
            'inventory.dashboard.view',
            'inventory.unit.create',
            'inventory.unit.condition.update',
        ]);

        // Role lain tanpa permission khusus (fase selanjutnya)
        Role::findByName('Guru', 'web')->syncPermissions([]);
        Role::findByName('Admin Perpustakaan', 'web')->syncPermissions([]);
        Role::findByName('Siswa', 'web')->syncPermissions([]);
    }
}
