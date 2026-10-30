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
        'Sarpras',
        'Kurikulum',
        'Guru',
        'Admin Perpustakaan',
        'Siswa',
        'Kantin',
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
        'teacher.document.manage',
        'teacher.agenda.manage',
        'teacher.attendance.manage',
        'teacher.student-note.view',
        'teacher.student-note.create',
        'teacher.homeroom.view',
        'teacher.homeroom.seating.manage',
        'teacher.create',
        'teacher.update',
        'teacher.delete',
        'teacher.assign-account',
        'curriculum.view',
        'curriculum.schedule.manage',
        'curriculum.create',
        'curriculum.update',
        'curriculum.delete',
        'curriculum.uas.view',
        'curriculum.uas.create',
        'curriculum.uas.update',
        'curriculum.uas.delete',
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
        'kantin.dashboard.view',
        'kantin.barang.view',
        'kantin.barang.create',
        'kantin.barang.update',
        'kantin.barang.delete',
        'kantin.kategori.view',
        'kantin.kategori.create',
        'kantin.saldo.view',
        'kantin.saldo.topup',
        'kantin.saldo.history.view',
    ];

    /**
     * Seed role & permission awal.
     */
    public function run(): void
    {
        $this->call(SarprasRoleSeeder::class);

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

        // Sarpras mendapat permission inventaris
        $sarpras = Role::findByName('Sarpras', 'web');
        $sarpras->givePermissionTo([
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

        // Setiap role menerima baseline seluruh permission pada modul yang dimilikinya.
        // givePermissionTo mempertahankan grant tambahan yang dikelola di luar baseline ini.
        Role::findByName('Kurikulum', 'web')->givePermissionTo([
            'curriculum.view', 'curriculum.schedule.manage', 'curriculum.create', 'curriculum.update', 'curriculum.delete',
            'curriculum.teacher_subject.view', 'curriculum.teacher_subject.create', 'curriculum.teacher_subject.delete',
            'curriculum.uas.view', 'curriculum.uas.create', 'curriculum.uas.update', 'curriculum.uas.delete',
            'curriculum.rombel_usage.view', 'curriculum.rombel_usage.create', 'curriculum.rombel_usage.delete',
        ]);

        Role::findByName('Guru', 'web')->givePermissionTo([
            'teacher.agenda.manage',
            'teacher.attendance.manage',
            'teacher.student-note.view',
            'teacher.student-note.create',
            'teacher.homeroom.view',
            'teacher.homeroom.seating.manage',
        ]);

        Role::findByName('Kantin', 'web')->givePermissionTo([
            'kantin.dashboard.view', 'kantin.barang.view', 'kantin.barang.create',
            'kantin.barang.update', 'kantin.barang.delete', 'kantin.kategori.view',
            'kantin.kategori.create', 'kantin.saldo.view', 'kantin.saldo.topup',
            'kantin.saldo.history.view',
        ]);

        Role::findByName('Kesiswaan', 'web')->givePermissionTo([
            'student.card.view', 'student.card.print',
        ]);

        $this->call(CounselingPermissionSeeder::class);
        $this->call(StudentViolationPermissionSeeder::class);
        $this->call(TeacherQuizPermissionSeeder::class);
        $this->call(MasterSchoolPermissionSeeder::class);
        $this->call(LibraryPermissionSeeder::class);
        $this->call(HumasPermissionSeeder::class);
        $this->call(ModuleDocumentPermissionSeeder::class);
        $this->call(LmsPermissionSeeder::class);
    }
}
