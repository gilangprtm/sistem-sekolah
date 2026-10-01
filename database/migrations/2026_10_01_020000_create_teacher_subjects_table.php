<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_teacher_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('m_subjects')->restrictOnDelete();
            $table->string('suffix', 20);
            $table->string('code', 50);
            $table->timestamps();

            $table->unique(['teacher_id', 'subject_id']);
        });

        DB::statement('CREATE UNIQUE INDEX m_teacher_subjects_code_lower_unique ON m_teacher_subjects (LOWER(code))');
    }

    public function down(): void
    {
        Schema::dropIfExists('m_teacher_subjects');
    }
};
