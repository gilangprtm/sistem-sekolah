<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function teacher(array $attributes = []): Teacher
    {
        return Teacher::factory()->create(array_merge([
            'staff_type' => 'guru',
            'status' => 'active',
        ], $attributes));
    }

    private function subject(array $attributes = []): Subject
    {
        return Subject::factory()->create(array_merge([
            'code' => 'IPA',
            'name' => 'Ilmu Pengetahuan Alam',
            'status' => 'active',
        ], $attributes));
    }

    public function test_super_admin_can_list_teacher_subjects_with_search_pagination_and_query_preservation(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher(['full_name' => 'Budi Guru']);
        $subject = $this->subject();
        TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'IPA-01', 'suffix' => '-01']);

        $this->actingAs($admin)
            ->get('/kurikulum/teacher-subjects?search=IPA&per_page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('teacher-subjects/index')
                ->where('teacherSubjects.total', 1)
                ->where('teacherSubjects.data.0.code', 'IPA-01')
                ->where('filters.search', 'IPA')
                ->where('filters.per_page', '1'));
    }

    public function test_create_trims_suffix_preserves_case_and_concatenates_current_subject_code(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $subject = $this->subject(['code' => 'iPa']);

        $this->actingAs($admin)
            ->post('/kurikulum/teacher-subjects', [
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'suffix' => '  -a01  ',
            ])
            ->assertRedirect('/kurikulum/teacher-subjects')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_teacher_subjects', [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'suffix' => '-a01',
            'code' => 'iPa-a01',
        ]);
    }

    public function test_suffix_validation_rejects_empty_invalid_and_too_long_values(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $subject = $this->subject();

        foreach (['   ', 'bad value', str_repeat('A', 21)] as $suffix) {
            $this->actingAs($admin)
                ->post('/kurikulum/teacher-subjects', [
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'suffix' => $suffix,
                ])
                ->assertSessionHasErrors('suffix');
        }
    }

    public function test_new_links_require_active_guru_and_active_subject(): void
    {
        $admin = $this->admin();
        $staff = $this->teacher(['staff_type' => 'staff']);
        $inactiveTeacher = $this->teacher(['status' => 'inactive']);
        $activeSubject = $this->subject();
        $inactiveSubject = $this->subject(['code' => 'BIO', 'name' => 'Biologi', 'status' => 'inactive']);

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $staff->id,
            'subject_id' => $activeSubject->id,
            'suffix' => '01',
        ])->assertSessionHasErrors('teacher_id');

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $inactiveTeacher->id,
            'subject_id' => $activeSubject->id,
            'suffix' => '02',
        ])->assertSessionHasErrors('teacher_id');

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $this->teacher()->id,
            'subject_id' => $inactiveSubject->id,
            'suffix' => '03',
        ])->assertSessionHasErrors('subject_id');
    }

    public function test_pair_and_code_are_case_insensitively_unique(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $subject = $this->subject();
        $otherTeacher = $this->teacher(['full_name' => 'Guru Dua']);

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'suffix' => '01',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'suffix' => '02',
        ])->assertSessionHasErrors('teacher_id');

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $subject->id,
            'suffix' => '01',
        ])->assertSessionHasErrors('suffix');
    }

    public function test_existing_links_remain_readable_when_anchors_become_inactive(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $subject = $this->subject();
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'IPA01', 'suffix' => '01']);
        $teacher->update(['status' => 'inactive']);
        $subject->update(['status' => 'inactive']);

        $this->actingAs($admin)
            ->get('/kurikulum/teacher-subjects?search=IPA01')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('teacherSubjects.data.0.id', $relation->id));
    }

    public function test_subject_code_cannot_change_while_relations_exist_and_relation_code_is_snapshot(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $subject = $this->subject();
        TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'IPA01', 'suffix' => '01']);

        $this->actingAs($admin)
            ->patch("/kurikulum/subjects/{$subject->id}", ['code' => 'FIS', 'name' => $subject->name, 'status' => 'active'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('m_subjects', ['id' => $subject->id, 'code' => 'IPA']);
    }

    public function test_delete_removes_only_relation_and_allows_recreate_without_deleting_anchors(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $subject = $this->subject();
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'IPA01', 'suffix' => '01']);

        $this->actingAs($admin)
            ->delete("/kurikulum/teacher-subjects/{$relation->id}")
            ->assertRedirect('/kurikulum/teacher-subjects');

        $this->assertDatabaseMissing('m_teacher_subjects', ['id' => $relation->id]);
        $this->assertDatabaseHas('m_teacher', ['id' => $teacher->id]);
        $this->assertDatabaseHas('m_subjects', ['id' => $subject->id]);

        $this->actingAs($admin)->post('/kurikulum/teacher-subjects', [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'suffix' => '01',
        ])->assertRedirect();
    }

    public function test_non_super_admin_cannot_manage_teacher_subjects(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = $this->teacher();
        $subject = $this->subject();
        $relation = TeacherSubject::factory()->create(['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'code' => 'IPA01', 'suffix' => '01']);

        $this->actingAs($user)->get('/kurikulum/teacher-subjects')->assertForbidden();
        $this->actingAs($user)->post('/kurikulum/teacher-subjects', ['teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'suffix' => '02'])->assertForbidden();
        $this->actingAs($user)->delete("/kurikulum/teacher-subjects/{$relation->id}")->assertForbidden();
    }
}
