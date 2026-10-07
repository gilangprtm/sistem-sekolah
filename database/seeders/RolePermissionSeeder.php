<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        'Kesiswaan',
        'Staff',
    ];

    /**
     * Daftar permission per domain.
     */
    private const PERMISSIONS = [
        'users.manage',
        'roles.manage',
        'student.view',
        'student.card.view',
        'student.card.print',
        'student.create',
        'student.update',
        'student.delete',
        'student.assign-account',
        'teacher.view',
        'teacher.create',
        'teacher.update',
        'teacher.delete',
        'teacher.assign-account',
        'curriculum.view',
        'curriculum.schedule.manage',
        'curriculum.create',
        'curriculum.update',
        'curriculum.delete',
        'subject.view',
        'subject.create',
        'subject.update',
        'subject.delete',
        'curriculum.teacher_subject.view',
        'curriculum.teacher_subject.create',
        'curriculum.teacher_subject.delete',
        'curriculum.rombel.view',
        'curriculum.rombel.create',
        'curriculum.rombel.update',
        'curriculum.rombel.delete',
        'curriculum.rombel_usage.view',
        'curriculum.rombel_usage.create',
        'curriculum.rombel_usage.delete',
        'inventory.view',
        'inventory.create',
        'inventory.delete',
        'inventory.dashboard.view',
        'inventory.unit.create',
        'inventory.unit.condition.update',
        'inventory.category.view',
        'inventory.category.create',
        'inventory.category.update',
        'inventory.category.delete',
        'inventory.category.assign',
        'inventory.type.assign',
        'inventory.type.view',
        'inventory.type.create',
        'inventory.type.update',
        'inventory.type.delete',
        'inventory.asset-type.assign',
        'inventory.room.view',
        'inventory.room.create',
        'inventory.room.update',
        'inventory.room.delete',
        'inventory.room.assign',
    ];

    /**
     * Seed role & permission awal.
     */
    public function run(): void
    {
        // Flush cache permission Spatie (penting saat cache driver = redis,
        // agar syncPermissions tidak melihat cache stale).
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Buat permission (idempotent)
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Buat role (idempotent)
        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Refresh cache after creating records so syncPermissions resolves them.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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
            'inventory.category.view',
            'inventory.category.create',
            'inventory.category.update',
            'inventory.category.delete',
            'inventory.category.assign',
            'inventory.type.assign',
            'inventory.type.view',
            'inventory.type.create',
            'inventory.type.update',
            'inventory.type.delete',
            'inventory.asset-type.assign',
            'inventory.room.view',
            'inventory.room.create',
            'inventory.room.update',
            'inventory.room.delete',
            'inventory.room.assign',
        ]);

        // Role lain tanpa permission khusus (fase selanjutnya)
        Role::findByName('Guru', 'web')->syncPermissions([]);
        Role::findByName('Admin Perpustakaan', 'web')->syncPermissions([]);
        Role::findByName('Siswa', 'web')->syncPermissions([]);

        // Kesiswaan hanya mengelola akses kartu pelajar, tanpa CRUD master siswa.
        Role::findByName('Kesiswaan', 'web')->syncPermissions([
            'student.card.view',
        ]);
    }
}
