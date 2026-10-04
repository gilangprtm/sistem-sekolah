<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_schedule_custom_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_plan_id')->constrained('tr_curriculum_schedule_plans')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->string('day', 12);
            $table->unsignedTinyInteger('lesson_number');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->string('label', 120);
            $table->timestamps();
            $table->unique(['schedule_plan_id', 'day', 'lesson_number']);
            $table->index(['academic_period_id', 'day', 'lesson_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_schedule_custom_slots');
    }
};
