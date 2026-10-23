<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SarprasRoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $legacyRole = Role::query()->where('name', 'Admin Inventaris')->where('guard_name', 'web')->lockForUpdate()->first();
            $sarprasRole = Role::query()->where('name', 'Sarpras')->where('guard_name', 'web')->lockForUpdate()->first();

            if ($legacyRole && $sarprasRole) {
                throw new RuntimeException('Role Admin Inventaris dan Sarpras sama-sama tersedia. Penggabungan role memerlukan keputusan eksplisit.');
            }

            if ($legacyRole) {
                $legacyRole->name = 'Sarpras';
                $legacyRole->save();
            } elseif (! $sarprasRole) {
                Role::findOrCreate('Sarpras', 'web');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
