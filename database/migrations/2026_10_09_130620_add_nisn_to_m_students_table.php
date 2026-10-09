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
        Schema::table('m_students', function (Blueprint $table): void {
            $table->string('nisn', 20)->nullable()->unique()->after('nis');
        });
    }

    public function down(): void
    {
        Schema::table('m_students', function (Blueprint $table): void {
            $table->dropUnique(['nisn']);
            $table->dropColumn('nisn');
        });
    }
};
