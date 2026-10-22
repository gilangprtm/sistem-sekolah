<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TeacherDocumentPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ModuleDocumentPermissionSeeder::class);
    }
}
