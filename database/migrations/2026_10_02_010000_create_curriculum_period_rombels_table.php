<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_curriculum_period_rombels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_period_id')->constrained('m_academic_periods')->restrictOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['academic_period_id', 'rombel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_curriculum_period_rombels');
    }
};
