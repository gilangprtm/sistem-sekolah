<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_counseling_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('m_students')->restrictOnDelete();
            $table->date('counseling_date');
            $table->text('content');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('creator_name');
            $table->string('updater_name')->nullable();
            $table->timestamps();
            $table->index(['counseling_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_counseling_notes');
    }
};
