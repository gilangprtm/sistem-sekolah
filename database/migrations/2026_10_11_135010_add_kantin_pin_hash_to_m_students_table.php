<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_students', function (Blueprint $table): void {
            $table->string('kantin_pin_hash')->nullable()->after('parent_phone');
        });
    }

    public function down(): void
    {
        Schema::table('m_students', function (Blueprint $table): void {
            $table->dropColumn('kantin_pin_hash');
        });
    }
};
