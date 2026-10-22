<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherStudentNote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TeacherStudentNotePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherStudentNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_all_authorized_teachers_can_read_student_notes_and_profile(): void
    {
        [$firstUser, $firstTeacher] = $this->teacher();
        [$secondUser] = $this->teacher();
        $student = Student::factory()->create(['full_name' => 'Nara Siswa']);
        TeacherStudentNote::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $firstTeacher->id,
            'teacher_name' => $firstTeacher->full_name,
            'content' => 'Perlu contoh visual.',
        ]);

        $this->actingAs($secondUser)->getJson(route('teacher.student-notes.index', $student))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('student.full_name', 'Nara Siswa')
            ->assertJsonPath('can_create', true)
            ->assertJsonPath('notes.data.0.teacher_name', $firstTeacher->full_name)
            ->assertJsonPath('notes.data.0.content', 'Perlu contoh visual.');
        $this->actingAs($firstUser)->getJson(route('teacher.student-notes.index', $student))->assertOk();
    }

    public function test_teacher_can_add_a_note_for_same_student_without_falsifying_author(): void
    {
        [$user, $profile] = $this->teacher();
        $student = Student::factory()->create();
        $this->travelTo(new \DateTimeImmutable('2026-10-22T10:00:00+00:00'));

        $this->actingAs($user)->postJson(route('teacher.student-notes.store', $student), [
            'content' => '  Berikan instruksi bertahap.  ',
            'teacher_id' => 999,
            'teacher_name' => 'Palsu',
            'student_id' => Student::factory()->create()->id,
            'created_at' => '2000-01-01T00:00:00Z',
        ])->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('note.teacher_name', $profile->full_name)
            ->assertJsonPath('note.content', 'Berikan instruksi bertahap.')
            ->assertJsonPath('note.created_at', '2026-10-22T10:00:00.000000Z');

        $this->assertDatabaseHas('tr_teacher_student_notes', [
            'student_id' => $student->id,
            'teacher_id' => $profile->id,
            'teacher_name' => $profile->full_name,
            'content' => 'Berikan instruksi bertahap.',
        ]);
    }

    public function test_notes_are_read_only_after_creation_and_each_teacher_can_append(): void
    {
        [$firstUser, $firstProfile] = $this->teacher();
        [$secondUser, $secondProfile] = $this->teacher();
        $student = Student::factory()->create();
        $note = TeacherStudentNote::factory()->create([
            'student_id' => $student->id,
            'teacher_id' => $firstProfile->id,
            'teacher_name' => $firstProfile->full_name,
            'content' => 'Tetap apa adanya.',
        ]);

        $this->actingAs($secondUser)->putJson('/guru/siswa/'.$student->id.'/catatan/'.$note->id, [
            'content' => 'Tidak boleh mengubah.',
        ])->assertNotFound();
        $this->deleteJson('/guru/siswa/'.$student->id.'/catatan/'.$note->id)->assertNotFound();
        $this->postJson(route('teacher.student-notes.store', $student), ['content' => 'Tambahan guru kedua.'])->assertCreated();

        $this->assertDatabaseHas('tr_teacher_student_notes', ['id' => $note->id, 'content' => 'Tetap apa adanya.']);
        $this->assertDatabaseHas('tr_teacher_student_notes', [
            'student_id' => $student->id,
            'teacher_id' => $secondProfile->id,
            'content' => 'Tambahan guru kedua.',
        ]);
        $this->assertSame(2, TeacherStudentNote::query()->where('student_id', $student->id)->count());
        $this->actingAs($firstUser)->getJson(route('teacher.student-notes.index', $student))->assertOk()->assertJsonPath('notes.total', 2);
    }

    public function test_guest_non_teacher_and_teacher_without_note_permission_are_denied(): void
    {
        $student = Student::factory()->create();
        $this->getJson(route('teacher.student-notes.index', $student))->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('teacher.student-notes.index', $student))->assertForbidden();

        [$teacher] = $this->teacher();
        $teacher->removeRole('Guru');
        $this->actingAs($teacher)->getJson(route('teacher.student-notes.index', $student))->assertForbidden();
        $this->postJson(route('teacher.student-notes.store', $student), ['content' => 'Tidak diizinkan.'])->assertForbidden();
    }

    public function test_note_content_is_required_trimmed_and_limited_to_5000_characters(): void
    {
        [$user] = $this->teacher();
        $student = Student::factory()->create();

        $this->actingAs($user)->postJson(route('teacher.student-notes.store', $student), ['content' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('content')
            ->assertJsonPath('errors.content.0', 'Catatan siswa wajib diisi.');
        $this->postJson(route('teacher.student-notes.store', $student), ['content' => str_repeat('a', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors('content')
            ->assertJsonPath('errors.content.0', 'Catatan siswa maksimal 5.000 karakter.');
        $this->postJson(route('teacher.student-notes.store', $student), ['content' => ['invalid']])
            ->assertUnprocessable()->assertJsonPath('errors.content.0', 'Catatan siswa harus berupa teks.');
        $this->postJson(route('teacher.student-notes.store', $student), ['content' => "  Catatan pembelajaran\nlanjutan  "])
            ->assertCreated()->assertJsonPath('note.content', "Catatan pembelajaran\nlanjutan");
        $this->assertDatabaseCount('tr_teacher_student_notes', 1);
    }

    public function test_permission_seeder_grants_append_and_read_to_teachers_and_super_admin_only(): void
    {
        $this->assertTrue(Role::findByName('Guru', 'web')->hasPermissionTo('teacher.student-note.view'));
        $this->assertTrue(Role::findByName('Guru', 'web')->hasPermissionTo('teacher.student-note.create'));
        $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo('teacher.student-note.create'));
        $this->assertFalse(Role::findByName('Siswa', 'web')->hasPermissionTo('teacher.student-note.view'));
        $this->assertSame(2, Permission::query()->whereIn('name', ['teacher.student-note.view', 'teacher.student-note.create'])->count());
    }

    public function test_history_is_paginated_newest_first_and_scoped_only_to_the_requested_student(): void
    {
        [$user, $teacher] = $this->teacher();
        $student = Student::factory()->create();
        $other = Student::factory()->create();
        $notes = TeacherStudentNote::factory()->count(21)->create([
            'student_id' => $student->id, 'teacher_id' => $teacher->id,
        ]);
        TeacherStudentNote::factory()->create(['student_id' => $other->id, 'teacher_id' => $teacher->id, 'content' => 'Siswa lain.']);
        $url = route('teacher.student-notes.index', $student);

        $this->actingAs($user)->getJson($url.'?student_id='.$other->id.'&per_page=1000&teacher_id=999')
            ->assertOk()->assertJsonCount(20, 'notes.data')->assertJsonPath('notes.total', 21)
            ->assertJsonPath('notes.last_page', 2)->assertJsonPath('notes.data.0.id', $notes->last()->id)
            ->assertJsonMissing(['content' => 'Siswa lain.']);
        $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(1, 'notes.data')
            ->assertJsonPath('notes.current_page', 2)->assertJsonPath('notes.data.0.id', $notes->first()->id);
        $this->getJson($url.'?page=invalid')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson($url.'?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson($url.'?page=100001')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_server_preserves_author_name_snapshot_even_after_profile_changes(): void
    {
        [$user, $teacher] = $this->teacher();
        $teacher->update(['full_name' => 'Guru Awal']);
        $student = Student::factory()->create();
        $this->actingAs($user)->postJson(route('teacher.student-notes.store', $student), ['content' => 'Catatan awal.'])->assertCreated();
        $teacher->update(['full_name' => 'Guru Baru']);

        $this->getJson(route('teacher.student-notes.index', $student))->assertOk()->assertJsonPath('notes.data.0.teacher_name', 'Guru Awal');
    }

    public function test_note_permission_policy_requires_active_teacher_and_separate_read_create_grants(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['teacher.student-note.view', 'teacher.student-note.create']);
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', TeacherStudentNote::class));
        $teacher = Teacher::factory()->create(['user_id' => $user->id, 'staff_type' => 'staff']);
        $user->unsetRelation('teacher');
        $this->assertFalse(Gate::forUser($user)->allows('create', TeacherStudentNote::class));
        $teacher->update(['staff_type' => 'guru', 'status' => 'inactive']);
        $user->unsetRelation('teacher');
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', TeacherStudentNote::class));
        $teacher->update(['status' => 'active']);
        $user->unsetRelation('teacher');
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', TeacherStudentNote::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', TeacherStudentNote::class));
        $user->revokePermissionTo('teacher.student-note.create');
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', TeacherStudentNote::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', TeacherStudentNote::class));
        $user->givePermissionTo('teacher.student-note.create')->revokePermissionTo('teacher.student-note.view');
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', TeacherStudentNote::class));
        $this->assertFalse(Gate::forUser($user)->allows('create', TeacherStudentNote::class));
    }

    public function test_read_only_teacher_cannot_post_and_author_cannot_edit_or_delete_notes(): void
    {
        [$user, $teacher] = $this->teacher();
        $user->removeRole('Guru');
        $user->givePermissionTo('teacher.student-note.view');
        $student = Student::factory()->create();
        $note = TeacherStudentNote::factory()->create(['teacher_id' => $teacher->id, 'student_id' => $student->id]);

        $this->actingAs($user)->getJson(route('teacher.student-notes.index', $student))
            ->assertOk()->assertJsonPath('can_create', false);
        $this->postJson(route('teacher.student-notes.store', $student), ['content' => 'Dilarang.'])->assertForbidden();
        $this->assertFalse(Gate::forUser($user)->allows('update', $note));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $note));
        $this->assertDatabaseCount('tr_teacher_student_notes', 1);
    }

    public function test_note_seeder_is_additive_idempotent_and_does_not_grant_unrelated_roles(): void
    {
        $role = Role::findByName('Guru', 'web');
        $role->givePermissionTo(Permission::findOrCreate('teacher.custom.test', 'web'));
        $role->revokePermissionTo(['teacher.student-note.view', 'teacher.student-note.create']);
        $this->seed(TeacherStudentNotePermissionSeeder::class);
        $this->seed(TeacherStudentNotePermissionSeeder::class);

        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.custom.test'));
        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.student-note.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('teacher.student-note.create'));
        $this->assertSame(2, Permission::query()->whereIn('name', ['teacher.student-note.view', 'teacher.student-note.create'])->count());
        foreach (['Siswa', 'Staff', 'Kesiswaan', 'Admin Inventaris', 'Admin Perpustakaan'] as $name) {
            $this->assertFalse(Role::findByName($name, 'web')->hasPermissionTo('teacher.student-note.view'));
            $this->assertFalse(Role::findByName($name, 'web')->hasPermissionTo('teacher.student-note.create'));
        }
    }

    /** @return array{User, Teacher} */
    private function teacher(): array
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create([
            'user_id' => $user->id,
            'full_name' => fake()->firstName().' Guru',
        ]);

        return [$user, $teacher];
    }
}
