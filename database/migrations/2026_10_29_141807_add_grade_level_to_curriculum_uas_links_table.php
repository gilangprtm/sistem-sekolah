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
            $table->string('grade_level', 5)->nullable()->after('category');
            $table->dropUnique('tr_curriculum_uas_links_year_period_subject_category_unique');
            $table->unique(
                ['academic_year_id', 'academic_period_id', 'subject_id', 'category', 'grade_level'],
                'curriculum_exam_context_grade_unique',
            );
        });

        DB::statement('CREATE UNIQUE INDEX curriculum_exam_unassigned_grade_unique ON tr_curriculum_uas_links (academic_year_id, academic_period_id, subject_id, category) WHERE grade_level IS NULL');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tr_curriculum_uas_links ADD CONSTRAINT curriculum_exam_grade_check CHECK (grade_level IS NULL OR grade_level IN ('VII', 'VIII', 'IX'))");
        }
    }

    public function down(): void
    {
        $hasMultipleGrades = DB::table('tr_curriculum_uas_links')
            ->select('academic_year_id', 'academic_period_id', 'subject_id', 'category')
            ->groupBy('academic_year_id', 'academic_period_id', 'subject_id', 'category')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleGrades) {
            throw new RuntimeException('Rollback dibatalkan: terdapat ujian lintas jenjang untuk mapel dan konteks yang sama. Rekonsiliasi data terlebih dahulu tanpa menghapus riwayat ujian.');
        }

        DB::statement('DROP INDEX IF EXISTS curriculum_exam_unassigned_grade_unique');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tr_curriculum_uas_links DROP CONSTRAINT IF EXISTS curriculum_exam_grade_check');
        }

        Schema::table('tr_curriculum_uas_links', function (Blueprint $table): void {
            $table->dropUnique('curriculum_exam_context_grade_unique');
            $table->dropColumn('grade_level');
            $table->unique(
                ['academic_year_id', 'academic_period_id', 'subject_id', 'category'],
                'tr_curriculum_uas_links_year_period_subject_category_unique',
            );
        });
    }
};
