<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_lms_course_runs', function (Blueprint $table): void {
            $table->unique(['course_id', 'academic_period_id', 'rombel_id', 'course_revision_id'], 'lms_run_revision_period_rombel_unique');
            $table->dropUnique(['course_id', 'academic_period_id', 'rombel_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('tr_lms_course_runs')->select('course_id', 'academic_period_id', 'rombel_id')
            ->groupBy('course_id', 'academic_period_id', 'rombel_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback ditolak: terdapat pelaksanaan beberapa revisi pada kelas dan semester yang sama. Riwayat tidak boleh dihapus.');
        }

        Schema::table('tr_lms_course_runs', function (Blueprint $table): void {
            $table->unique(['course_id', 'academic_period_id', 'rombel_id']);
            $table->dropUnique('lms_run_revision_period_rombel_unique');
        });
    }
};
