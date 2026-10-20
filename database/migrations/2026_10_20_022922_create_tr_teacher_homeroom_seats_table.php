<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_teacher_homeroom_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->cascadeOnDelete();
            $table->foreignId('rombel_id')->constrained('m_rombels')->restrictOnDelete();
            $table->unsignedTinyInteger('seat_number');
            $table->foreignId('student_id')->nullable()->constrained('m_students')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_year_id', 'rombel_id', 'seat_number']);
            $table->unique(['academic_year_id', 'rombel_id', 'student_id']);
            $table->index(['academic_year_id', 'rombel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_teacher_homeroom_seats');
    }
};
