<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class CounselingRoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('Konseling', 'web');
    }
}
