<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_students', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tahun_angkatan')->nullable()->after('nis');
        });
    }

    public function down(): void
    {
        Schema::table('m_students', function (Blueprint $table): void {
            $table->dropColumn('tahun_angkatan');
        });
    }
};
