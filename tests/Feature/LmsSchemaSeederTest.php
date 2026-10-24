<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\LmsCourse;
use App\Models\Rombel;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Student;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\LmsPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LmsSchemaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_lms_seed_creates_no_demo_courses_answers_or_account_assignments(): void
    {
        $this->seed(LmsPermissionSeeder::class);

        foreach (['m_lms_courses', 'tr_lms_course_revisions', 'tr_lms_course_runs', 'tr_lms_course_attempts', 'tr_lms_course_answers', 'model_has_roles', 'model_has_permissions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_answers_keep_their_original_revision_when_course_is_revised_and_archived(): void
    {
        $context = $this->createCourseContext();
        $answerId = DB::table('tr_lms_course_answers')->insertGetId([
            'course_attempt_id' => $context['attempt_id'],
            'question_key' => 'question-1',
            'answer' => json_encode(['text' => 'Jawaban siswa'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('tr_lms_course_revisions')->insert([
            'course_id' => $context['course_id'],
            'revision_number' => 2,
            'title' => 'Course direvisi',
            'content' => '{"materials":[]}',
            'created_by' => $context['teacher_id'],
        ]);

        $this->seed(LmsPermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $course = LmsCourse::query()->findOrFail($context['course_id']);
        $this->actingAs($admin)->post(route('lms.management.archive', $course), ['revision_id' => $course->latestRevision->editToken($course)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('m_lms_courses', ['id' => $course->id, 'archived_by' => $admin->id]);

        $this->assertDatabaseHas('tr_lms_course_attempts', ['id' => $context['attempt_id'], 'course_revision_id' => $context['revision_id']]);
        $this->assertDatabaseHas('tr_lms_course_answers', ['id' => $answerId, 'question_key' => 'question-1']);
        $this->assertSame('Materi awal', json_decode(DB::table('tr_lms_course_revisions')->where('id', $context['revision_id'])->value('content'), true)['materials'][0]['title']);
    }

    public function test_attempt_cannot_reference_a_revision_from_another_course(): void
    {
        $context = $this->createCourseContext();
        $otherCourseId = DB::table('m_lms_courses')->insertGetId([
            'subject_id' => $context['subject_id'], 'grade_level' => 'VII',
            'title' => 'Course lain', 'created_by' => $context['teacher_id'],
        ]);
        $otherRevisionId = DB::table('tr_lms_course_revisions')->insertGetId([
            'course_id' => $otherCourseId, 'revision_number' => 1,
            'title' => 'Course lain', 'content' => '{"materials":[]}', 'created_by' => $context['teacher_id'],
        ]);

        $this->expectException(QueryException::class);

        DB::table('tr_lms_course_attempts')->where('id', $context['attempt_id'])->update(['course_revision_id' => $otherRevisionId]);
    }

    public function test_same_question_cannot_have_duplicate_answers_in_an_attempt(): void
    {
        $context = $this->createCourseContext();
        $answer = ['course_attempt_id' => $context['attempt_id'], 'question_key' => 'question-1', 'answer' => '{"text":"Jawaban"}'];
        DB::table('tr_lms_course_answers')->insert($answer);

        $this->expectException(QueryException::class);

        DB::table('tr_lms_course_answers')->insert($answer);
    }

    public function test_revision_used_by_a_student_attempt_cannot_be_deleted(): void
    {
        $context = $this->createCourseContext();

        $this->expectException(QueryException::class);

        DB::table('tr_lms_course_revisions')->where('id', $context['revision_id'])->delete();
    }

    public function test_migration_can_be_rolled_back_and_reapplied_on_an_empty_test_database(): void
    {
        $migration = require database_path('migrations/2026_10_23_095932_create_lms_course_tables.php');
        $pointerMigration = require database_path('migrations/2026_10_24_013823_add_approved_revision_to_lms_courses_table.php');

        $pointerMigration->down();
        $migration->down();
        $this->assertFalse(Schema::hasTable('m_lms_courses'));
        $this->assertFalse(Schema::hasTable('tr_lms_course_answers'));
        $migration->up();
        $pointerMigration->up();

        $this->assertTrue(Schema::hasColumn('m_lms_courses', 'approved_revision_id'));
        $this->assertTrue(Schema::hasTable('m_lms_courses'));
        $this->assertTrue(Schema::hasTable('tr_lms_course_answers'));
    }

    public function test_approved_pointer_cannot_reference_another_courses_revision(): void
    {
        $first = $this->createCourseContext();
        $second = $this->createCourseContext('2027/2028');
        $this->expectException(QueryException::class);
        DB::table('m_lms_courses')->where('id', $first['course_id'])->update(['approved_revision_id' => $second['revision_id']]);
    }

    public function test_lms_permission_seeder_creates_curriculum_and_assigns_scoped_permissions(): void
    {
        $this->seed(LmsPermissionSeeder::class);

        $curriculum = Role::findByName('Kurikulum', 'web');
        $teacher = Role::findByName('Guru', 'web');
        $student = Role::findByName('Siswa', 'web');
        $superAdmin = Role::findByName('Super Admin', 'web');

        $this->assertDatabaseMissing('permissions', ['name' => 'lms.course.approve']);
        $this->assertTrue($curriculum->hasPermissionTo('lms.course.archive'));
        $this->assertTrue($curriculum->hasPermissionTo('lms.course.design'));
        $this->assertTrue($teacher->hasPermissionTo('lms.course.design'));
        $this->assertTrue($teacher->hasPermissionTo('lms.run.manage'));
        $this->assertTrue($teacher->hasPermissionTo('lms.result.view'));
        $this->assertFalse($teacher->hasPermissionTo('lms.course.archive'));
        $this->assertTrue($student->hasPermissionTo('lms.course.learn'));
        $this->assertFalse($student->hasPermissionTo('lms.course.archive'));
        $this->assertTrue($superAdmin->hasPermissionTo('lms.course.archive'));
        $this->assertTrue($superAdmin->hasPermissionTo('lms.course.design'));
    }

    public function test_role_permission_seeder_invokes_lms_permission_seeder(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'Kurikulum', 'guard_name' => 'web']);
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('lms.course.archive'));
    }

    public function test_reseeding_lms_permissions_is_idempotent_and_preserves_other_role_permissions(): void
    {
        $teacher = Role::findOrCreate('Guru', 'web');
        $existingPermission = Permission::findOrCreate('teacher.agenda.manage', 'web');
        $teacher->givePermissionTo($existingPermission);
        $user = User::factory()->create();
        $user->assignRole($teacher);
        $curriculum = Role::findOrCreate('Kurikulum', 'web');
        $curriculum->givePermissionTo(Permission::findOrCreate('lms.course.approve', 'web'));

        $this->seed(LmsPermissionSeeder::class);
        $this->seed(LmsPermissionSeeder::class);

        $this->assertSame(6, DB::table('permissions')->where('name', 'like', 'lms.%')->count());
        $this->assertDatabaseMissing('permissions', ['name' => 'lms.course.approve']);
        $this->assertTrue($teacher->fresh()->hasPermissionTo($existingPermission));
        $this->assertTrue($user->fresh()->hasRole('Guru'));
        $this->assertSame($curriculum->id, Role::findByName('Kurikulum', 'web')->id);
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo($existingPermission));
        $this->assertSame(1, DB::table('model_has_roles')->count());
    }

    /** @return array{course_id: int, revision_id: int, run_id: int, attempt_id: int, teacher_id: int, subject_id: int} */
    private function createCourseContext(string $yearCode = '2026/2027'): array
    {
        $year = AcademicYear::create(['year' => $yearCode, 'status' => 'inactive']);
        $period = AcademicPeriod::create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil']);
        $rombel = Rombel::factory()->create(['grade_level' => 'VII']);
        $teacherSubject = TeacherSubject::factory()->create();
        $plan = SchedulePlan::factory()->create(['academic_period_id' => $period->id, 'created_by' => User::factory()->create()->id]);
        $assignment = ScheduleTeachingAssignment::create([
            'schedule_plan_id' => $plan->id, 'academic_period_id' => $period->id, 'rombel_id' => $rombel->id,
            'subject_id' => $teacherSubject->subject_id, 'teacher_subject_id' => $teacherSubject->id,
            'teacher_id' => $teacherSubject->teacher_id, 'weekly_jp' => 1,
            'subject_code' => 'IPA', 'subject_name' => 'IPA', 'teacher_name' => 'Guru', 'rombel_code' => 'VII-A',
        ]);
        $courseId = DB::table('m_lms_courses')->insertGetId([
            'subject_id' => $teacherSubject->subject_id, 'grade_level' => 'VII',
            'title' => 'Course awal', 'created_by' => $teacherSubject->teacher_id,
        ]);
        $revisionId = DB::table('tr_lms_course_revisions')->insertGetId([
            'course_id' => $courseId, 'revision_number' => 1, 'title' => 'Course awal', 'status' => 'approved',
            'content' => '{"materials":[{"id":"material-1","title":"Materi awal","blocks":[{"id":"question-1","type":"essay","prompt":"Jelaskan"}]}]}',
            'created_by' => $teacherSubject->teacher_id,
        ]);
        $runId = DB::table('tr_lms_course_runs')->insertGetId([
            'course_id' => $courseId, 'course_revision_id' => $revisionId,
            'academic_period_id' => $period->id, 'rombel_id' => $rombel->id,
            'schedule_teaching_assignment_id' => $assignment->id, 'teacher_id' => $teacherSubject->teacher_id,
        ]);
        $attemptId = DB::table('tr_lms_course_attempts')->insertGetId([
            'course_run_id' => $runId, 'course_id' => $courseId, 'course_revision_id' => $revisionId, 'student_id' => Student::factory()->create()->id,
        ]);

        return ['course_id' => $courseId, 'revision_id' => $revisionId, 'run_id' => $runId, 'attempt_id' => $attemptId, 'teacher_id' => $teacherSubject->teacher_id, 'subject_id' => $teacherSubject->subject_id];
    }
}
