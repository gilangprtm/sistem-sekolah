<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30);
            $table->string('name', 150);
            $table->string('status', 8)->default('active');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX m_subjects_code_lower_unique ON m_subjects (LOWER(code))');
        DB::statement('CREATE UNIQUE INDEX m_subjects_name_lower_unique ON m_subjects (LOWER(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('m_subjects');
    }
};
