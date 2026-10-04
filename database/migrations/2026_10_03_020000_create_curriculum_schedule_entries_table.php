<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_schedule_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_plan_id')->constrained('tr_curriculum_schedule_plans')->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained('tr_curriculum_teaching_assignments')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('m_subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->string('day', 12);
            $table->unsignedTinyInteger('lesson_number');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->string('subject_code', 30);
            $table->string('subject_name', 150);
            $table->string('teacher_name', 150);
            $table->string('rombel_code', 30);
            $table->timestamps();
            $table->unique(['schedule_plan_id', 'rombel_id', 'day', 'lesson_number']);
            $table->unique(['schedule_plan_id', 'teacher_id', 'day', 'lesson_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_schedule_entries');
    }
};
