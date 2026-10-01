<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_teacher', function (Blueprint $table): void {
            $table->string('nip', 18)->nullable()->unique()->after('staff_type');
            $table->string('nuptk', 16)->nullable()->unique()->after('nip');
        });
    }

    public function down(): void
    {
        Schema::table('m_teacher', function (Blueprint $table): void {
            $table->dropUnique('m_teacher_nip_unique');
            $table->dropUnique('m_teacher_nuptk_unique');
            $table->dropColumn(['nip', 'nuptk']);
        });
    }
};
