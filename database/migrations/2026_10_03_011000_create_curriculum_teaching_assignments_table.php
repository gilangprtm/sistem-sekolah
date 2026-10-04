<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_plan_id')->constrained('tr_curriculum_schedule_plans')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('m_subjects')->restrictOnDelete();
            $table->foreignId('teacher_subject_id')->constrained('m_teacher_subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->unsignedSmallInteger('weekly_jp');
            $table->string('subject_code', 30);
            $table->string('subject_name', 150);
            $table->string('teacher_name', 150);
            $table->string('rombel_code', 30);
            $table->string('status', 10)->default('active');
            $table->timestamps();
            $table->unique(['schedule_plan_id', 'rombel_id', 'subject_id']);
            $table->index(['academic_period_id', 'rombel_id', 'subject_id']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE tr_curriculum_teaching_assignments ADD CONSTRAINT tr_curriculum_teaching_assignments_weekly_jp_range CHECK (weekly_jp BETWEEN 1 AND 15)',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_teaching_assignments');
    }
};
