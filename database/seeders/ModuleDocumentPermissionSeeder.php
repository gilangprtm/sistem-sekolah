<?php

namespace Database\Seeders;

use App\DocumentModule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ModuleDocumentPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (DocumentModule::cases() as $module) {
            Permission::findOrCreate($module->permission('view'), 'web');
            Permission::findOrCreate($module->permission('manage'), 'web');
        }

        $rolesByModule = [
            DocumentModule::Inventory->value => ['Sarpras'],
            DocumentModule::Curriculum->value => [],
            DocumentModule::Teacher->value => ['Guru'],
            DocumentModule::StudentAffairs->value => ['Kesiswaan'],
            DocumentModule::Canteen->value => [],
            DocumentModule::Administration->value => [],
            DocumentModule::Humas->value => ['Humas'],
        ];
        $readPermissionsByModule = [
            DocumentModule::Inventory->value => ['inventory.view', 'inventory.dashboard.view', 'inventory.room.view'],
            DocumentModule::Curriculum->value => ['curriculum.view', 'subject.view', 'curriculum.rombel.view', 'curriculum.teacher_subject.view', 'curriculum.uas.view'],
            DocumentModule::Teacher->value => ['teacher.agenda.manage', 'teacher.attendance.manage', 'teacher.homeroom.view'],
            DocumentModule::StudentAffairs->value => ['student.card.view'],
            DocumentModule::Canteen->value => ['kantin.dashboard.view', 'kantin.barang.view', 'kantin.saldo.view'],
            DocumentModule::Administration->value => ['student.view', 'teacher.view', 'users.manage', 'roles.manage'],
            DocumentModule::Humas->value => ['humas.aspiration.view'],
        ];

        foreach (DocumentModule::cases() as $module) {
            $view = Permission::findByName($module->permission('view'), 'web');
            $manage = Permission::findByName($module->permission('manage'), 'web');
            foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
                $canAccessModule = $role->name === 'Super Admin'
                    || $role->hasAnyPermission($readPermissionsByModule[$module->value]);
                if ($canAccessModule) {
                    $role->givePermissionTo($view);
                }
                if ($role->name === 'Super Admin' || in_array($role->name, $rolesByModule[$module->value], true)) {
                    $role->givePermissionTo([$view, $manage]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
