<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_student_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('m_academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status', 1);
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['academic_year_id', 'student_id', 'attendance_date'], 'student_attendance_year_student_date_unique');
            $table->index(['academic_year_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_student_attendances');
    }
};
