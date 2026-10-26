<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_humas_aspirations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->text('content');
            $table->timestamps();
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_humas_aspirations');
    }
};
