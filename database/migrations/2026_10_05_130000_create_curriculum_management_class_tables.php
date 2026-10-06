<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_year_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['academic_year_id', 'rombel_id']);
        });

        Schema::create('tr_curriculum_student_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->string('status', 12)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['academic_year_id', 'student_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX tr_curriculum_active_student_placement_unique ON tr_curriculum_student_placements (academic_year_id, student_id) WHERE status = 'active'");

        Schema::create('tr_curriculum_homeroom_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->string('status', 12)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['academic_year_id', 'rombel_id', 'status']);
            $table->index(['academic_year_id', 'teacher_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX tr_curriculum_active_homeroom_class_unique ON tr_curriculum_homeroom_assignments (academic_year_id, rombel_id) WHERE status = 'active'");
        DB::statement("CREATE UNIQUE INDEX tr_curriculum_active_homeroom_teacher_unique ON tr_curriculum_homeroom_assignments (academic_year_id, teacher_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_homeroom_assignments');
        Schema::dropIfExists('tr_curriculum_student_placements');
        Schema::dropIfExists('tr_curriculum_year_classes');
    }
};
