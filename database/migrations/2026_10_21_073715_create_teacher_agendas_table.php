<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_teacher_agendas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->date('agenda_date');
            $table->string('block_key', 64);
            $table->json('schedule_snapshot');
            $table->text('material');
            $table->json('attendance');
            $table->timestamps();
            $table->unique(['teacher_id', 'agenda_date', 'block_key'], 'teacher_agenda_date_block_unique');
            $table->index(['teacher_id', 'agenda_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_teacher_agendas');
    }
};
