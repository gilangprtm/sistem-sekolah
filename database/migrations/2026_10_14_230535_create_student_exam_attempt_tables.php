<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->cascadeOnDelete();
            $table->foreignId('uas_link_id')->constrained('tr_curriculum_uas_links')->cascadeOnDelete();
            $table->string('status', 16)->default('active');
            $table->unsignedTinyInteger('interruption_count')->default(0);
            $table->dateTimeTz('started_at');
            $table->dateTimeTz('locked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTimeTz('unlocked_at')->nullable();
            $table->text('unlock_reason')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'uas_link_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('tr_curriculum_exam_attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained('tr_curriculum_exam_attempts')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->dateTimeTz('occurred_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedTinyInteger('interruption_count')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['exam_attempt_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_exam_attempt_events');
        Schema::dropIfExists('tr_curriculum_exam_attempts');
    }
};
