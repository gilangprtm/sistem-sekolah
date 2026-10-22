<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_teacher_student_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('m_teacher')->restrictOnDelete();
            $table->string('teacher_name');
            $table->text('content');
            $table->timestamps();
            $table->index(['student_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_teacher_student_notes');
    }
};
