<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\LmsCourse;
use App\Models\Rombel;
use App\Models\ScheduleEntry;
use App\Models\SchedulePlan;
use App\Models\ScheduleTeachingAssignment;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\LmsPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LmsMyCourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LmsPermissionSeeder::class);
    }

    public function test_guest_cannot_open_my_courses_or_media(): void
    {
        foreach ([route('lms.my-courses'), route('lms.my-courses.show', 1), route('lms.my-courses.media', [1, Str::uuid()])] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    #[TestWith(['Siswa', false, 'active', 'guru', false])]
    #[TestWith(['Kurikulum', false, 'active', 'guru', false])]
    #[TestWith(['Guru', false, 'active', 'guru', false])]
    #[TestWith(['Guru', true, 'inactive', 'guru', false])]
    #[TestWith(['Guru', true, 'active', 'staff', false])]
    #[TestWith(['Guru', true, 'active', 'guru', true])]
    #[TestWith(['Super Admin', false, 'active', 'guru', true])]
    public function test_browse_policy_and_sidebar_follow_teacher_permission_and_profile(string $role, bool $profile, string $status, string $staffType, bool $expected): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        if ($profile) {
            Teacher::factory()->create(['user_id' => $user->id, 'status' => $status, 'staff_type' => $staffType]);
        }
        $this->assertSame($expected, $user->can('browseMyCourses', LmsCourse::class));
        if ($expected) {
            $this->actingAs($user)->get(route('lms.my-courses'))->assertOk()->assertInertia(fn ($page) => $page
                ->component('lms/my-courses/index')->where('canAccessLmsMyCourses', true));
        } else {
            $this->actingAs($user)->get(route('lms.my-courses'))->assertForbidden();
        }
    }

    public function test_teacher_without_view_permission_is_denied_even_with_profile(): void
    {
        $user = User::factory()->create();
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->assertFalse($user->can('browseMyCourses', LmsCourse::class));
        $this->actingAs($user)->get(route('lms.my-courses'))->assertForbidden();
    }

    public function test_only_matching_active_courses_are_listed_once_regardless_of_designer_or_number_of_lessons(): void
    {
        [$user, $teacher] = $this->teacher();
        $period = $this->period();
        $subject = Subject::factory()->create();
        $course = $this->course($subject);
        $anotherCourse = $this->course($subject);
        $wrongGrade = $this->course($subject, 'VIII');
        $wrongSubject = $this->course(Subject::factory()->create());
        $draft = $this->course($subject, active: false);
        $archived = $this->course($subject);
        $archived->forceFill(['archived_at' => now()])->save();
        $assignment = $this->schedule($teacher, $period, $subject);
        $this->entry($assignment, 2);
        $this->schedule($teacher, $period, $subject, parallel: 'B');

        $this->actingAs($user)->get(route('lms.my-courses'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('lms/my-courses/index')
                ->has('courses.data', 2)->where('courses.total', 2)
                ->where('courses.data.0.id', $course->id)->where('courses.data.1.id', $anotherCourse->id)
                ->missing('courses.data.0.content')->missing('courses.data.0.revision_id')
                ->where('activePeriod.name', 'Ganjil')->where('activePeriod.academic_year', '2026/2027'));
        foreach ([$wrongGrade, $wrongSubject, $draft, $archived] as $hidden) {
            $this->get(route('lms.my-courses.show', $hidden))->assertNotFound();
        }
    }

    #[TestWith(['draft'])]
    #[TestWith(['archived'])]
    public function test_unpublished_schedules_do_not_grant_access(string $status): void
    {
        [$user, $teacher] = $this->teacher();
        $period = $this->period();
        $subject = Subject::factory()->create();
        $course = $this->course($subject);
        $this->schedule($teacher, $period, $subject, status: $status);

        $this->actingAs($user)->get(route('lms.my-courses'))->assertInertia(fn ($page) => $page->has('courses.data', 0));
        $this->get(route('lms.my-courses.show', $course))->assertNotFound();
    }

    public function test_only_current_teacher_and_active_period_schedule_grant_access(): void
    {
        [$user, $teacher] = $this->teacher();
        [, $otherTeacher] = $this->teacher();
        $period = $this->period();
        $oldPeriod = AcademicPeriod::query()->create(['academic_year_id' => $period->academic_year_id, 'code' => 'genap', 'name' => 'Genap', 'status' => 'inactive']);
        $subject = Subject::factory()->create();
        $course = $this->course($subject);
        $this->schedule($otherTeacher, $period, $subject);
        $this->schedule($teacher, $oldPeriod, $subject);

        $this->actingAs($user)->get(route('lms.my-courses'))->assertInertia(fn ($page) => $page->has('courses.data', 0));
        $this->get(route('lms.my-courses.show', $course))->assertNotFound();
        $this->schedule($teacher, $period, $subject);
        $this->get(route('lms.my-courses.show', $course))->assertOk();
        $period->academicYear->update(['status' => 'inactive']);
        $this->get(route('lms.my-courses'))->assertInertia(fn ($page) => $page->has('courses.data', 0)->where('activePeriod', null));
        $this->get(route('lms.my-courses.show', $course))->assertNotFound();
    }

    public function test_assignments_without_entries_or_inactive_assignments_do_not_grant_access(): void
    {
        [$user, $teacher] = $this->teacher();
        $period = $this->period();
        $subject = Subject::factory()->create();
        $course = $this->course($subject);
        $assignment = $this->schedule($teacher, $period, $subject);
        $assignment->update(['status' => 'inactive']);
        $this->actingAs($user)->get(route('lms.my-courses.show', $course))->assertNotFound();
        $assignment->update(['status' => 'active']);
        ScheduleEntry::query()->where('teaching_assignment_id', $assignment->id)->delete();
        $this->get(route('lms.my-courses.show', $course))->assertNotFound();
    }

    public function test_teacher_without_active_semester_has_safe_empty_state_and_admin_can_browse_all_active_courses(): void
    {
        [$user] = $this->teacher();
        $course = $this->course(Subject::factory()->create());
        $this->actingAs($user)->get(route('lms.my-courses'))->assertInertia(fn ($page) => $page->has('courses.data', 0)->where('activePeriod', null));
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->get(route('lms.my-courses'))->assertInertia(fn ($page) => $page->has('courses.data', 1)->where('isAdmin', true));
        $this->get(route('lms.my-courses.show', $course))->assertOk();
    }

    public function test_search_and_details_use_active_revision_and_never_leak_new_draft_or_answer_keys(): void
    {
        [$user, $teacher] = $this->teacher();
        $subject = Subject::factory()->create();
        $this->schedule($teacher, $this->period(), $subject);
        $course = $this->course($subject);
        $active = $course->approvedRevision;
        $active->update(['title' => 'Aljabar Aktif', 'description' => 'Belajar persamaan']);
        $course->revisions()->create(['revision_number' => 2, 'title' => 'RAHASIA DRAF', 'description' => 'RAHASIA DESKRIPSI', 'content' => $this->content(), 'status' => 'draft', 'created_by' => $course->created_by]);

        $this->actingAs($user)->get(route('lms.my-courses', ['search' => 'aljabar']))->assertInertia(fn ($page) => $page
            ->has('courses.data', 1)->where('courses.data.0.title', 'Aljabar Aktif')->where('courses.data.0.revision_number', 1));
        $this->get(route('lms.my-courses', ['search' => 'RAHASIA']))->assertInertia(fn ($page) => $page->has('courses.data', 0));
        $this->get(route('lms.my-courses.show', $course))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('lms/my-courses/show')
                ->where('course.title', 'Aljabar Aktif')->where('course.revision_number', 1)
                ->where('course.content.materials.0.body', '<p>Materi aman</p>')
                ->missing('course.content.materials.0.questions.0.options.0.is_correct')
                ->missing('course.content.materials.0.sections.0.questions.0.options.0.is_correct')
                ->missing('course.revisions')->missing('course.latestRevision'));
        $this->assertDatabaseCount('tr_lms_course_runs', 0);
        $this->assertDatabaseCount('tr_lms_course_attempts', 0);
        $this->assertDatabaseCount('tr_lms_course_answers', 0);
    }

    public function test_filters_and_pagination_are_bounded_and_keep_all_available_subject_options(): void
    {
        [$user, $teacher] = $this->teacher();
        $period = $this->period();
        $firstSubject = Subject::factory()->create();
        $secondSubject = Subject::factory()->create();
        $first = $this->course($firstSubject);
        $this->course($firstSubject);
        $this->course($secondSubject, 'VIII');
        $this->schedule($teacher, $period, $firstSubject);
        $this->schedule($teacher, $period, $secondSubject, 'VIII');
        $this->actingAs($user)->get(route('lms.my-courses', ['per_page' => 1]))->assertInertia(fn ($page) => $page
            ->where('courses.total', 3)->where('courses.per_page', 1)->has('courses.data', 1)->has('filterOptions.subjects', 2));
        $this->get(route('lms.my-courses', ['subject_id' => $firstSubject->id, 'grade_level' => 'VII']))->assertInertia(fn ($page) => $page
            ->where('courses.total', 2)->where('courses.data.0.id', $first->id)->has('filterOptions.subjects', 2));
        $this->get(route('lms.my-courses', ['subject_id' => $secondSubject->id, 'grade_level' => 'VII']))->assertInertia(fn ($page) => $page->has('courses.data', 0));
        $this->get(route('lms.my-courses', ['per_page' => 9999]))->assertInertia(fn ($page) => $page->where('courses.per_page', 50));
    }

    public function test_media_is_private_scoped_to_active_revision_and_revoked_when_schedule_or_course_changes(): void
    {
        Storage::fake('local');
        [$user, $teacher] = $this->teacher();
        [, $otherTeacher] = $this->teacher();
        $subject = Subject::factory()->create();
        $period = $this->period();
        $assignment = $this->schedule($teacher, $period, $subject);
        $course = $this->course($subject);
        $active = $course->approvedRevision;
        $asset = (string) Str::uuid();
        $path = 'lms/courses/'.$course->id.'/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-test');
        $content = $this->content();
        $content['materials'][0]['media'] = [['id' => (string) Str::uuid(), 'type' => 'pdf', 'asset_id' => $asset]];
        $content['assets'] = [$asset => ['name' => 'materi.pdf', 'type' => 'pdf', 'path' => $path, 'mime' => 'application/pdf']];
        $active->update(['content' => $content]);
        $draftAsset = (string) Str::uuid();
        $draftContent = $content;
        $draftContent['materials'][0]['media'][0]['asset_id'] = $draftAsset;
        $draftContent['assets'] = [$draftAsset => $content['assets'][$asset]];
        $course->revisions()->create(['revision_number' => 2, 'title' => 'Draft', 'content' => $draftContent, 'status' => 'draft', 'created_by' => $course->created_by]);

        $this->actingAs($user)->get(route('lms.my-courses.show', $course))->assertInertia(fn ($page) => $page
            ->where('course.content.assets.'.$asset.'.url', route('lms.my-courses.media', [$course, $asset]))
            ->missing('course.content.assets.'.$asset.'.path'));
        $this->get(route('lms.my-courses.media', [$course, $asset]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('lms.my-courses.media', [$course, $draftAsset]))->assertNotFound();
        $this->get(route('lms.my-courses.media', [$course, 'invalid']))->assertNotFound();
        $other = $this->course($subject);
        $this->get(route('lms.my-courses.media', [$other, $asset]))->assertNotFound();
        $assignment->update(['teacher_id' => $otherTeacher->id]);
        $this->get(route('lms.my-courses.show', $course))->assertNotFound();
        $this->get(route('lms.my-courses.media', [$course, $asset]))->assertNotFound();
        $assignment->update(['teacher_id' => $teacher->id]);
        $course->forceFill(['archived_at' => now()])->save();
        $this->get(route('lms.my-courses.show', $course))->assertNotFound();
        $this->get(route('lms.my-courses.media', [$course, $asset]))->assertNotFound();
    }

    /** @return array{User, Teacher} */
    private function teacher(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function period(): AcademicPeriod
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);

        return AcademicPeriod::query()->create(['academic_year_id' => $year->id, 'code' => 'ganjil', 'name' => 'Ganjil', 'status' => 'active']);
    }

    private function course(Subject $subject, string $grade = 'VII', bool $active = true): LmsCourse
    {
        $designer = Teacher::factory()->create();
        $course = LmsCourse::query()->create(['subject_id' => $subject->id, 'grade_level' => $grade, 'title' => 'Course sekolah', 'created_by' => $designer->id]);
        $revision = $course->revisions()->create(['revision_number' => 1, 'title' => 'Course sekolah', 'description' => 'Materi pembelajaran', 'content' => $this->content(), 'status' => $active ? 'approved' : 'draft', 'created_by' => $designer->id]);
        if ($active) {
            $course->forceFill(['approved_revision_id' => $revision->id])->save();
        }

        return $course;
    }

    private function schedule(Teacher $teacher, AcademicPeriod $period, Subject $subject, string $grade = 'VII', string $status = 'published', string $parallel = 'A'): ScheduleTeachingAssignment
    {
        $plan = SchedulePlan::factory()->create(['academic_period_id' => $period->id, 'grade_level' => $grade, 'status' => $status, 'created_by' => $teacher->user_id]);
        $rombel = Rombel::query()->firstOrCreate(['code' => $grade.'-'.$parallel], ['name' => $grade.'-'.$parallel, 'grade_level' => $grade, 'parallel_code' => $parallel, 'status' => 'active']);
        $teacherSubject = TeacherSubject::query()->where('teacher_id', $teacher->id)->where('subject_id', $subject->id)->first()
            ?? TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id]);
        $assignment = ScheduleTeachingAssignment::query()->create([
            'schedule_plan_id' => $plan->id, 'academic_period_id' => $period->id, 'rombel_id' => $rombel->id,
            'subject_id' => $subject->id, 'teacher_subject_id' => $teacherSubject->id, 'teacher_id' => $teacher->id,
            'weekly_jp' => 2, 'subject_code' => $subject->code, 'subject_name' => $subject->name,
            'teacher_name' => $teacher->full_name, 'rombel_code' => $rombel->code, 'status' => 'active',
        ]);
        $this->entry($assignment);

        return $assignment;
    }

    private function entry(ScheduleTeachingAssignment $assignment, int $lesson = 1): void
    {
        ScheduleEntry::query()->create([
            ...$assignment->only(['schedule_plan_id', 'academic_period_id', 'rombel_id', 'subject_id', 'teacher_id', 'subject_code', 'subject_name', 'teacher_name', 'rombel_code']),
            'teaching_assignment_id' => $assignment->id, 'day' => 'monday', 'lesson_number' => $lesson, 'start_time' => '07:30', 'end_time' => '08:10',
        ]);
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        $question = fn (): array => ['id' => (string) Str::uuid(), 'type' => 'multiple_choice', 'prompt' => 'Pilih jawaban', 'options' => [
            ['id' => (string) Str::uuid(), 'text' => 'A', 'is_correct' => true], ['id' => (string) Str::uuid(), 'text' => 'B', 'is_correct' => false],
        ]];
        $page = fn (): array => ['id' => (string) Str::uuid(), 'title' => 'Materi', 'body' => '<p>Materi aman</p><script>alert(1)</script>', 'media' => [], 'questions' => [$question()]];

        return ['materials' => [[...$page(), 'sections' => [$page()]]]];
    }
}
