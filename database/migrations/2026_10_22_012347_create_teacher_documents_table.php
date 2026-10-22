<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tr_teacher_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_document_folder_id')->nullable()->constrained('m_teacher_document_folders')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('original_name', 255);
            $table->string('disk', 32)->default('local');
            $table->string('path', 512)->unique();
            $table->string('mime_type', 127);
            $table->string('visibility', 16);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->index(['teacher_document_folder_id', 'name']);
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tr_teacher_documents');
    }
};
