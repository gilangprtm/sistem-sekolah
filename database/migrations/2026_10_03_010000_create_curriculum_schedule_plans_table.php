<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_schedule_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->foreignId('source_plan_id')->nullable()->constrained('tr_curriculum_schedule_plans')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 10)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->string('payload_hash', 64);
            $table->index(['academic_period_id', 'status']);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE tr_curriculum_schedule_plans ADD CONSTRAINT tr_curriculum_schedule_plans_status CHECK (status IN ('draft', 'published', 'archived'))",
            );
            Schema::getConnection()->statement("CREATE UNIQUE INDEX tr_curriculum_schedule_plans_one_draft ON tr_curriculum_schedule_plans (academic_period_id) WHERE status = 'draft'");
            Schema::getConnection()->statement("CREATE UNIQUE INDEX tr_curriculum_schedule_plans_one_published ON tr_curriculum_schedule_plans (academic_period_id) WHERE status = 'published'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_schedule_plans');
    }
};
