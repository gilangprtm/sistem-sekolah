<?php

namespace Database\Seeders;

use App\DocumentModule;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class LibraryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $collectionView = Permission::findOrCreate('library.collection.view', 'web');
        $circulationView = Permission::findOrCreate('library.circulation.view', 'web');
        $viewDocuments = Permission::findOrCreate(DocumentModule::Library->permission('view'), 'web');
        $manageDocuments = Permission::findOrCreate(DocumentModule::Library->permission('manage'), 'web');

        foreach (['Admin Perpustakaan', 'Super Admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo([
                $collectionView, $circulationView, $viewDocuments, $manageDocuments,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
