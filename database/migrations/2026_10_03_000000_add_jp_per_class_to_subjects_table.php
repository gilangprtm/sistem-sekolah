<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_subjects', function (Blueprint $table): void {
            $table->unsignedSmallInteger('jp_per_class')->default(1)->after('status');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE m_subjects ADD CONSTRAINT m_subjects_jp_per_class_range CHECK (jp_per_class BETWEEN 1 AND 15)',
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE m_subjects DROP CONSTRAINT IF EXISTS m_subjects_jp_per_class_range',
            );
        }

        Schema::table('m_subjects', function (Blueprint $table): void {
            $table->dropColumn('jp_per_class');
        });
    }
};
