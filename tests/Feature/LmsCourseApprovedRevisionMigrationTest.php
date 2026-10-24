<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LmsCourseApprovedRevisionMigrationTest extends TestCase
{
    public function test_pointer_migration_backfills_latest_approved_not_latest_draft(): void
    {
        $default = DB::getDefaultConnection();
        config(['database.connections.lms_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('lms_migration_test');
        try {
            Schema::create('m_lms_courses', function (Blueprint $table): void {
                $table->id();
            });
            Schema::create('tr_lms_course_revisions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('course_id')->constrained('m_lms_courses');
                $table->unsignedInteger('revision_number');
                $table->string('status');
                $table->unique(['id', 'course_id']);
            });
            $courseId = DB::table('m_lms_courses')->insertGetId([]);
            DB::table('tr_lms_course_revisions')->insert(['course_id' => $courseId, 'revision_number' => 1, 'status' => 'approved']);
            $approvedId = DB::table('tr_lms_course_revisions')->insertGetId(['course_id' => $courseId, 'revision_number' => 2, 'status' => 'approved']);
            DB::table('tr_lms_course_revisions')->insert(['course_id' => $courseId, 'revision_number' => 3, 'status' => 'draft']);
            $migration = require database_path('migrations/2026_10_24_013823_add_approved_revision_to_lms_courses_table.php');

            $migration->up();

            $this->assertDatabaseHas('m_lms_courses', ['id' => $courseId, 'approved_revision_id' => $approvedId]);
            $this->assertDatabaseCount('tr_lms_course_revisions', 3);
        } finally {
            DB::setDefaultConnection($default);
            DB::purge('lms_migration_test');
        }
    }
}
