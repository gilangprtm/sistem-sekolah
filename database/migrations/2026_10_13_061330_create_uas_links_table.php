<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->cascadeOnDelete();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('m_subjects')->restrictOnDelete();
            $table->string('link', 2048);
            $table->timestamps();
            $table->unique(['academic_period_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_uas_links');
    }
};
