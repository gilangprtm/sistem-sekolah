<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('m_lms_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('m_subjects')->restrictOnDelete();
            $table->string('grade_level', 5);
            $table->string('title', 180);
            $table->foreignId('created_by')->constrained('m_teacher')->restrictOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['subject_id', 'grade_level']);
        });

        Schema::create('tr_lms_course_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('m_lms_courses')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('content_schema_version')->default(1);
            $table->jsonb('content');
            $table->foreignId('created_by')->constrained('m_teacher')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'revision_number']);
            $table->unique(['id', 'course_id'], 'lms_revision_course_unique');
            $table->index(['course_id', 'status']);
        });

        Schema::create('tr_lms_course_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('m_lms_courses')->restrictOnDelete();
            $table->foreignId('course_revision_id');
            $table->foreign(['course_revision_id', 'course_id'], 'lms_run_revision_course_fk')
                ->references(['id', 'course_id'])->on('tr_lms_course_revisions')->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->foreignId('schedule_teaching_assignment_id')->constrained('tr_curriculum_teaching_assignments', indexName: 'lms_run_teaching_assignment_fk')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['id', 'course_id'], 'lms_run_course_unique');
            $table->unique(['course_id', 'academic_period_id', 'rombel_id']);
            $table->index(['academic_period_id', 'rombel_id']);
        });

        Schema::create('tr_lms_course_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_run_id');
            $table->foreignId('course_id');
            $table->foreignId('course_revision_id');
            $table->foreign(['course_run_id', 'course_id'], 'lms_attempt_run_course_fk')
                ->references(['id', 'course_id'])->on('tr_lms_course_runs')->restrictOnDelete();
            $table->foreign(['course_revision_id', 'course_id'], 'lms_attempt_revision_course_fk')
                ->references(['id', 'course_id'])->on('tr_lms_course_revisions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->jsonb('progress')->nullable();
            $table->timestamps();
            $table->unique(['course_run_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('tr_lms_course_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_attempt_id')->constrained('tr_lms_course_attempts')->cascadeOnDelete();
            $table->string('question_key', 100);
            $table->jsonb('answer');
            $table->timestamp('answered_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->unique(['course_attempt_id', 'question_key']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE tr_lms_course_revisions ADD CONSTRAINT lms_revision_number_positive CHECK (revision_number > 0 AND content_schema_version > 0)',
            );
            Schema::getConnection()->statement(
                'ALTER TABLE tr_lms_course_answers ADD CONSTRAINT lms_answer_score_nonnegative CHECK (score >= 0)',
            );
            Schema::getConnection()->statement(
                "ALTER TABLE tr_lms_course_revisions ADD CONSTRAINT tr_lms_course_revisions_status CHECK (status IN ('draft', 'pending_approval', 'approved', 'rejected'))",
            );
            Schema::getConnection()->statement(
                "ALTER TABLE tr_lms_course_attempts ADD CONSTRAINT tr_lms_course_attempts_status CHECK (status IN ('in_progress', 'submitted'))",
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_lms_course_answers');
        Schema::dropIfExists('tr_lms_course_attempts');
        Schema::dropIfExists('tr_lms_course_runs');
        Schema::dropIfExists('tr_lms_course_revisions');
        Schema::dropIfExists('m_lms_courses');
    }
};
