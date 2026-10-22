<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TeacherStudentNotePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['teacher.student-note.view', 'teacher.student-note.create'] as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            foreach (['Guru', 'Super Admin'] as $roleName) {
                Role::findByName($roleName, 'web')->givePermissionTo($permission);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
