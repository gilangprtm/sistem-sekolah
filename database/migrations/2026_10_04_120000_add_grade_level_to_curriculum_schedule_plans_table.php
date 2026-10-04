<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tr_curriculum_schedule_plans', function (Blueprint $table): void {
            $table->string('grade_level', 5)->nullable()->after('academic_period_id');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::connection(null)->getConnection()->statement('DROP INDEX IF EXISTS tr_curriculum_schedule_plans_one_published');
            Schema::connection(null)->getConnection()->statement(
                "ALTER TABLE tr_curriculum_schedule_plans ADD CONSTRAINT tr_curriculum_schedule_plans_grade_level CHECK (grade_level IS NULL OR grade_level IN ('VII', 'VIII', 'IX'))",
            );
            Schema::connection(null)->getConnection()->statement(
                "CREATE UNIQUE INDEX tr_curriculum_schedule_plans_period_grade_published ON tr_curriculum_schedule_plans (academic_period_id, grade_level) WHERE status = 'published' AND grade_level IS NOT NULL",
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::connection(null)->getConnection()->statement('DROP INDEX IF EXISTS tr_curriculum_schedule_plans_period_grade_published');
            Schema::connection(null)->getConnection()->statement('ALTER TABLE tr_curriculum_schedule_plans DROP CONSTRAINT IF EXISTS tr_curriculum_schedule_plans_grade_level');
        }

        Schema::table('tr_curriculum_schedule_plans', function (Blueprint $table): void {
            $table->dropColumn('grade_level');
        });
    }
};
