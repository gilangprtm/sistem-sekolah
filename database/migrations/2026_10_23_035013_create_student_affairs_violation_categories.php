<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('m_student_affairs_violation_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('name_key', 64)->unique('student_affairs_violation_category_name_unique');
            $table->timestamps();
        });

        Schema::table('tr_student_affairs_violations', function (Blueprint $table): void {
            $table->foreignId('student_affairs_violation_category_id')->nullable()
                ->constrained('m_student_affairs_violation_categories', indexName: 'student_violation_category_foreign')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tr_student_affairs_violations', function (Blueprint $table): void {
            $table->dropForeign('student_violation_category_foreign');
            $table->dropColumn('student_affairs_violation_category_id');
        });

        Schema::dropIfExists('m_student_affairs_violation_categories');
    }
};
