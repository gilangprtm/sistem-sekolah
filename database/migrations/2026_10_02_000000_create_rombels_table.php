<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_rombels', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->string('grade_level', 5);
            $table->string('parallel_code', 10);
            $table->string('status', 10)->default('active');
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX m_rombels_code_lower_unique ON m_rombels (LOWER(code))');
    }

    public function down(): void
    {
        Schema::dropIfExists('m_rombels');
    }
};
