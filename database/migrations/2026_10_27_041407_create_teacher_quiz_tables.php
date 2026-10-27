<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_teacher_quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('m_subjects')->restrictOnDelete();
            $table->string('link', 2048);
            $table->boolean('is_open')->default(false);
            $table->timestamps();
            $table->index(['created_by', 'academic_year_id']);
            $table->index(['is_open', 'academic_period_id']);
        });

        Schema::create('tr_teacher_quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->cascadeOnDelete();
            $table->foreignId('teacher_quiz_id')->constrained('tr_teacher_quizzes')->cascadeOnDelete();
            $table->string('status', 16)->default('active');
            $table->unsignedTinyInteger('interruption_count')->default(0);
            $table->dateTimeTz('started_at');
            $table->dateTimeTz('locked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTimeTz('unlocked_at')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'teacher_quiz_id']);
            $table->index(['teacher_quiz_id', 'status']);
        });

        Schema::create('tr_teacher_quiz_attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained('tr_teacher_quiz_attempts')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->dateTimeTz('occurred_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedTinyInteger('interruption_count')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['quiz_attempt_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_teacher_quiz_attempt_events');
        Schema::dropIfExists('tr_teacher_quiz_attempts');
        Schema::dropIfExists('tr_teacher_quizzes');
    }
};
