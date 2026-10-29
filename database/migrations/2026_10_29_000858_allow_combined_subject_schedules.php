<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_subjects', function (Blueprint $table): void {
            $table->boolean('allow_combined')->default(false);
        });

        Schema::table('tr_curriculum_schedule_entries', function (Blueprint $table): void {
            $table->dropUnique(['schedule_plan_id', 'teacher_id', 'day', 'lesson_number']);
            $table->index(['academic_period_id', 'teacher_id', 'day', 'lesson_number'], 'schedule_entries_teacher_slot_index');
        });
    }

    public function down(): void
    {
        $hasCombinedEntries = DB::table('tr_curriculum_schedule_entries')
            ->select('schedule_plan_id', 'teacher_id', 'day', 'lesson_number')
            ->groupBy('schedule_plan_id', 'teacher_id', 'day', 'lesson_number')
            ->havingRaw('COUNT(*) > 1')->exists();
        if ($hasCombinedEntries) {
            throw new RuntimeException('Pisahkan jadwal gabungan sebelum membatalkan migrasi ini.');
        }

        Schema::table('tr_curriculum_schedule_entries', function (Blueprint $table): void {
            $table->dropIndex('schedule_entries_teacher_slot_index');
            $table->unique(['schedule_plan_id', 'teacher_id', 'day', 'lesson_number']);
        });
        Schema::table('m_subjects', function (Blueprint $table): void {
            $table->dropColumn('allow_combined');
        });
    }
};
