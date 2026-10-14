<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->string('category', 3)->default('UAS')->after('subject_id');
        });

        DB::table('tr_curriculum_uas_links')->whereNull('category')->update(['category' => 'UAS']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tr_curriculum_uas_links ADD CONSTRAINT tr_curriculum_uas_links_category_check CHECK (category IN ('UTS', 'UAS'))");
        }

        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->dropUnique('tr_curriculum_uas_links_academic_period_id_subject_id_unique');
            $table->unique(
                ['academic_year_id', 'academic_period_id', 'subject_id', 'category'],
                'tr_curriculum_uas_links_year_period_subject_category_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->dropUnique('tr_curriculum_uas_links_year_period_subject_category_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tr_curriculum_uas_links DROP CONSTRAINT tr_curriculum_uas_links_category_check');
        }

        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->dropColumn('category');
            $table->unique(
                ['academic_period_id', 'subject_id'],
                'tr_curriculum_uas_links_academic_period_id_subject_id_unique',
            );
        });
    }
};
