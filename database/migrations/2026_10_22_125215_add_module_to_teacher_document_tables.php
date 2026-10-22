<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_teacher_document_folders', function (Blueprint $table): void {
            $table->string('module', 32)->default('teacher');
            $table->index(['module', 'parent_id', 'name'], 'document_folders_module_parent_name_index');
        });
        Schema::table('tr_teacher_documents', function (Blueprint $table): void {
            $table->string('module', 32)->default('teacher');
            $table->index(['module', 'teacher_document_folder_id', 'name'], 'documents_module_folder_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('tr_teacher_documents', function (Blueprint $table): void {
            $table->dropIndex('documents_module_folder_name_index');
            $table->dropColumn('module');
        });
        Schema::table('m_teacher_document_folders', function (Blueprint $table): void {
            $table->dropIndex('document_folders_module_parent_name_index');
            $table->dropColumn('module');
        });
    }
};
