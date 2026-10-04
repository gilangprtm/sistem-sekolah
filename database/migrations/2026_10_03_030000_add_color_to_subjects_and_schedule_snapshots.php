<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_subjects', function (Blueprint $table): void {
            $table->string('color', 7)->default('#F3F4F6')->after('jp_per_class');
        });
    }

    public function down(): void
    {
        Schema::table('m_subjects', function (Blueprint $table): void {
            $table->dropColumn('color');
        });
    }
};
