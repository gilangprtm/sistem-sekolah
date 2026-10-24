<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('m_lms_courses', function (Blueprint $table): void {
            $table->unsignedBigInteger('approved_revision_id')->nullable();
            $table->foreign(['approved_revision_id', 'id'], 'lms_course_approved_revision_fk')
                ->references(['id', 'course_id'])->on('tr_lms_course_revisions')->restrictOnDelete();
        });

        DB::table('m_lms_courses')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('tr_lms_course_revisions')
                    ->whereColumn('tr_lms_course_revisions.course_id', 'm_lms_courses.id')
                    ->where('tr_lms_course_revisions.status', 'approved');
            })
            ->update([
                'approved_revision_id' => DB::raw('(SELECT tr_lms_course_revisions.id FROM tr_lms_course_revisions WHERE tr_lms_course_revisions.course_id = m_lms_courses.id AND tr_lms_course_revisions.status = \'approved\' ORDER BY tr_lms_course_revisions.revision_number DESC LIMIT 1)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('m_lms_courses', function (Blueprint $table): void {
            $table->dropForeign(Schema::getConnection()->getDriverName() === 'sqlite'
                ? ['approved_revision_id', 'id']
                : 'lms_course_approved_revision_fk');
            $table->dropColumn('approved_revision_id');
        });
    }
};
