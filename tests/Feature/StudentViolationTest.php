<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentAffairsViolationCategory;
use App\Models\StudentViolation;
use App\Models\User;
use Database\Seeders\StudentViolationPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentViolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kesiswaan_can_create_read_update_and_delete_private_violation_records(): void
    {
        $user = $this->kesiswaan();
        $student = Student::factory()->create(['full_name' => 'Siswa Kesiswaan', 'nisn' => '0000123456']);
        $response = $this->actingAs($user)->postJson(route('student-affairs.violations.store'), [
            'student_id' => $student->id, 'violation_date' => '2026-10-23', 'category' => '  Kedisiplinan  ',
            'content' => '  Meninggalkan kelas tanpa izin.  ', 'follow_up' => '  Pembinaan oleh wali kelas.  ',
            'creator_name' => 'Palsu', 'created_by' => 999, 'updated_by' => 999,
        ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $note = StudentViolation::query()->sole();
        $response->assertJsonPath('id', $note->id);
        $this->assertSame('Kedisiplinan', $note->category);
        $this->assertSame('Meninggalkan kelas tanpa izin.', $note->content);
        $this->assertSame('Pembinaan oleh wali kelas.', $note->follow_up);
        $this->assertNotSame('Meninggalkan kelas tanpa izin.', DB::table('tr_student_affairs_violations')->value('content'));
        $this->assertArrayNotHasKey('content', $note->toArray());
        $this->assertDatabaseHas('tr_student_affairs_violations', [
            'id' => $note->id, 'created_by' => $user->id, 'creator_name' => $user->name, 'updated_by' => null,
        ]);

        $other = $this->kesiswaan();
        $this->actingAs($other)->getJson(route('student-affairs.violations.show', $note))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('note.category', 'Kedisiplinan')->assertJsonPath('note.content', 'Meninggalkan kelas tanpa izin.')
            ->assertJsonPath('note.follow_up', 'Pembinaan oleh wali kelas.')
            ->assertJsonPath('note.student.nisn', '0000123456')->assertJsonMissingPath('note.student.nis');
        $this->putJson(route('student-affairs.violations.update', $note), [
            'student_id' => $student->id, 'violation_date' => '2026-10-24', 'category' => 'Keterlambatan',
            'content' => 'Terlambat kembali ke kelas.', 'follow_up' => null, 'creator_name' => 'Palsu',
        ])->assertOk();
        $this->assertSame('Keterlambatan', $note->fresh()->category);
        $this->assertNull($note->fresh()->follow_up);
        $this->assertDatabaseHas('tr_student_affairs_violations', [
            'id' => $note->id, 'created_by' => $user->id, 'creator_name' => $user->name,
            'updated_by' => $other->id, 'updater_name' => $other->name,
        ]);
        $this->deleteJson(route('student-affairs.violations.destroy', $note))->assertOk()->assertJsonPath('deleted', true);
        $this->assertModelMissing($note);
        $this->getJson(route('student-affairs.violations.categories', ['search' => 'Keterlambatan']))
            ->assertOk()->assertJsonCount(1, 'categories')->assertJsonPath('categories.0.name', 'Keterlambatan');
    }

    public function test_super_admin_has_module_access_without_permissions_or_extra_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $student = Student::factory()->create();
        $note = StudentViolation::factory()->create(['student_id' => $student->id]);

        $this->actingAs($user)->get(route('student-affairs.violations.index'))->assertOk();
        $this->getJson(route('student-affairs.violations.students'))->assertOk();
        $this->getJson(route('student-affairs.violations.categories'))->assertOk();
        $this->getJson(route('student-affairs.violations.show', $note))->assertOk();
        $this->postJson(route('student-affairs.violations.store'), [
            'student_id' => $student->id, 'violation_date' => '2026-10-23', 'category' => 'Kedisiplinan', 'content' => 'Uji.',
        ])->assertCreated();
        $this->putJson(route('student-affairs.violations.update', $note), [
            'student_id' => $student->id, 'violation_date' => '2026-10-23', 'category' => 'Kedisiplinan', 'content' => 'Diubah.',
        ])->assertOk();
        $this->deleteJson(route('student-affairs.violations.destroy', $note))->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function excludedRoles(): array
    {
        return array_combine(
            ['Guru', 'Staff', 'Siswa', 'Konseling', 'Sarpras', 'Admin Perpustakaan', 'Custom'],
            array_map(fn (string $role): array => [$role], ['Guru', 'Staff', 'Siswa', 'Konseling', 'Sarpras', 'Admin Perpustakaan', 'Custom']),
        );
    }

    #[DataProvider('excludedRoles')]
    public function test_other_roles_cannot_access_even_with_violation_permissions(string $role): void
    {
        $this->seed(StudentViolationPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo([
            'student_affairs.violation.view', 'student_affairs.violation.create',
            'student_affairs.violation.update', 'student_affairs.violation.delete',
        ]);
        $note = StudentViolation::factory()->create(['content' => 'Privat.']);

        $this->actingAs($user)->get(route('student-affairs.violations.index'))->assertForbidden()->assertDontSee('Privat.');
        $this->getJson(route('student-affairs.violations.students'))->assertForbidden();
        $this->getJson(route('student-affairs.violations.categories'))->assertForbidden();
        $this->getJson(route('student-affairs.violations.show', $note))->assertForbidden()->assertDontSee('Privat.');
        $this->postJson(route('student-affairs.violations.store'), [])->assertForbidden();
        $this->putJson(route('student-affairs.violations.update', $note), [])->assertForbidden();
        $this->deleteJson(route('student-affairs.violations.destroy', $note))->assertForbidden();
        $this->assertSame('Privat.', $note->fresh()->content);
        $this->assertDatabaseCount('tr_student_affairs_violations', 1);
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', StudentViolation::class));
    }

    public function test_guest_cannot_access_violation_endpoints(): void
    {
        $note = StudentViolation::factory()->create();
        $this->get(route('student-affairs.violations.index'))->assertRedirect(route('login'));
        foreach ([
            route('student-affairs.violations.students'), route('student-affairs.violations.categories'), route('student-affairs.violations.show', $note),
        ] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson(route('student-affairs.violations.store'), [])->assertUnauthorized();
        $this->putJson(route('student-affairs.violations.update', $note), [])->assertUnauthorized();
        $this->deleteJson(route('student-affairs.violations.destroy', $note))->assertUnauthorized();
        $this->assertModelExists($note);
    }

    public function test_index_excludes_sensitive_text_and_supports_nisn_search_and_pagination(): void
    {
        $user = $this->kesiswaan();
        $student = Student::factory()->create(['full_name' => 'Nara Siswa', 'nisn' => '0001234567']);
        StudentViolation::factory()->count(11)->create([
            'student_id' => $student->id, 'violation_date' => '2026-10-22', 'content' => 'Uraian privat', 'follow_up' => 'Sanksi privat',
        ]);
        $newest = StudentViolation::factory()->create(['student_id' => $student->id, 'violation_date' => '2026-10-23']);
        StudentViolation::factory()->create();

        $this->actingAs($user)->get(route('student-affairs.violations.index', ['search' => 'Nara']))
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('Uraian privat')->assertDontSee('Sanksi privat')
            ->assertInertia(fn (Assert $page) => $page->component('student-affairs/violations/index')
                ->where('canAccessStudentViolations', true)->where('canCreate', true)->where('canUpdate', true)->where('canDelete', true)
                ->has('notes.data', 10)->where('notes.total', 12)->where('notes.last_page', 2)
                ->where('notes.data.0.id', $newest->id)->missing('notes.data.0.content')->missing('notes.data.0.category')
                ->where('notes.data.0.student.nisn', '0001234567')->missing('notes.data.0.student.nis'));
        $this->get(route('student-affairs.violations.index', ['search' => '0001234567', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('notes.data', 2)->where('notes.current_page', 2));
        $this->getJson(route('student-affairs.violations.students', ['search' => '0001234567']))
            ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.nisn', '0001234567');
        $this->getJson(route('student-affairs.violations.students'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('student-affairs.violations.categories'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('canAccessStudentViolations', true)->missing('notes'));
        $this->assertSame(['id', 'full_name', 'nisn'], array_keys($this->getJson(route('student-affairs.violations.students'))->json('students.0')));
    }

    public function test_required_and_optional_fields_are_validated_and_rejected_payload_does_not_mutate(): void
    {
        $user = $this->kesiswaan();
        $student = Student::factory()->create();
        $note = StudentViolation::factory()->create(['content' => 'Tetap.']);
        $payload = [
            'student_id' => $student->id, 'violation_date' => '2026-10-23', 'category' => 'Kedisiplinan', 'content' => 'Valid.',
        ];
        $this->actingAs($user)->postJson(route('student-affairs.violations.store'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id', 'violation_date', 'category', 'content']);
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'student_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'violation_date' => '2026-02-30'])->assertUnprocessable()->assertJsonValidationErrors('violation_date');
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'category' => '   '])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'category' => str_repeat('a', 151)])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'content' => '   '])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'follow_up' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('follow_up');
        $this->putJson(route('student-affairs.violations.update', $note), [...$payload, 'content' => str_repeat('a', 5001)])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->putJson(route('student-affairs.violations.update', $note), [...$payload, 'follow_up' => str_repeat('a', 5001)])->assertUnprocessable()->assertJsonValidationErrors('follow_up');
        $this->assertSame('Tetap.', $note->fresh()->content);
        $this->assertDatabaseCount('tr_student_affairs_violations', 1);
        $this->assertDatabaseCount('m_student_affairs_violation_categories', 0);
        $this->postJson(route('student-affairs.violations.store'), [...$payload, 'follow_up' => ''])->assertCreated();
        $this->assertDatabaseCount('tr_student_affairs_violations', 2);
    }

    public function test_category_lookup_is_bounded_validated_and_exposes_only_master_names(): void
    {
        $user = $this->kesiswaan();
        $student = Student::factory()->create();
        for ($i = 0; $i < 31; $i++) {
            StudentAffairsViolationCategory::resolveName(sprintf('Jenis %02d', $i));
        }
        $this->actingAs($user)->getJson(route('student-affairs.violations.categories'))
            ->assertOk()->assertJsonCount(30, 'categories')->assertJsonPath('categories.0.name', 'Jenis 00');
        $this->assertSame(['id', 'name'], array_keys($this->getJson(route('student-affairs.violations.categories'))->json('categories.0')));
        $this->getJson(route('student-affairs.violations.categories', ['search' => 'Jenis 30']))
            ->assertOk()->assertJsonCount(1, 'categories')->assertJsonPath('categories.0.name', 'Jenis 30');
        $this->getJson(route('student-affairs.violations.categories', ['search' => str_repeat('a', 151)]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson(route('student-affairs.violations.categories', ['search' => "' OR 1=1 --"]))
            ->assertOk()->assertJsonCount(0, 'categories');
        $this->postJson(route('student-affairs.violations.store'), [
            'student_id' => $student->id, 'violation_date' => '2026-10-23', 'category' => 'Nama Sah', 'content' => 'Privat.',
            'student_affairs_violation_category_id' => 999999,
        ])->assertCreated();
        $note = StudentViolation::query()->sole();
        $this->assertSame('Nama Sah', $note->violationCategory->name);
        $this->get(route('dashboard'))->assertDontSee('Nama Sah')->assertInertia(fn (Assert $page) => $page->missing('categories'));
    }

    public function test_new_categories_are_saved_for_reuse_and_matching_is_case_insensitive(): void
    {
        $user = $this->kesiswaan();
        $student = Student::factory()->create();

        $this->actingAs($user)->getJson(route('student-affairs.violations.categories', ['search' => 'Kedisiplinan']))
            ->assertOk()->assertJsonCount(0, 'categories');
        $this->postJson(route('student-affairs.violations.store'), [
            'student_id' => $student->id,
            'violation_date' => '2026-10-23',
            'category' => '  Kedisiplinan   Siswa  ',
            'content' => 'Catatan pertama.',
        ])->assertCreated();

        $this->assertDatabaseCount('m_student_affairs_violation_categories', 1);
        $categoryId = DB::table('m_student_affairs_violation_categories')->value('id');
        $this->assertDatabaseHas('tr_student_affairs_violations', [
            'student_affairs_violation_category_id' => $categoryId,
        ]);
        $this->getJson(route('student-affairs.violations.categories', ['search' => 'kedisiplinan']))
            ->assertOk()->assertJsonCount(1, 'categories')->assertJsonPath('categories.0.name', 'Kedisiplinan Siswa');

        $this->postJson(route('student-affairs.violations.store'), [
            'student_id' => $student->id,
            'violation_date' => '2026-10-24',
            'category' => 'kEDISIPLINAN SISWA',
            'content' => 'Catatan kedua.',
        ])->assertCreated();
        $this->assertDatabaseCount('m_student_affairs_violation_categories', 1);
        $this->assertSame('Kedisiplinan Siswa', StudentViolation::query()->orderBy('id')->firstOrFail()->category);
        $this->assertSame('Kedisiplinan Siswa', StudentViolation::query()->orderByDesc('id')->firstOrFail()->category);
    }

    public function test_permission_seeder_is_additive_idempotent_and_does_not_grant_other_roles_or_assign_users(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        $kesiswaan = Role::findOrCreate('Kesiswaan', 'web');
        $other = Role::findOrCreate('Guru', 'web');
        $other->givePermissionTo(Permission::findOrCreate('custom.test', 'web'));
        $user = User::factory()->create();
        $user->assignRole($other);
        $this->seed(StudentViolationPermissionSeeder::class);
        $kesiswaan->givePermissionTo('custom.test');
        $this->seed(StudentViolationPermissionSeeder::class);

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permission = 'student_affairs.violation.'.$action;
            $this->assertTrue($kesiswaan->fresh()->hasPermissionTo($permission));
            $this->assertTrue(Role::findByName('Super Admin', 'web')->hasPermissionTo($permission));
            $this->assertFalse($other->fresh()->hasPermissionTo($permission));
        }
        $this->assertTrue($kesiswaan->fresh()->hasPermissionTo('custom.test'));
        $this->assertTrue($other->fresh()->hasPermissionTo('custom.test'));
        $this->assertSame(['Guru'], $user->fresh()->getRoleNames()->all());
        $this->assertSame(0, $kesiswaan->users()->count());
        $this->assertSame(4, Permission::query()->where('name', 'like', 'student_affairs.violation.%')->count());
    }

    public function test_kesiswaan_requires_view_and_separate_action_permissions_and_navigation_follows_policy(): void
    {
        $user = $this->kesiswaan();
        $note = StudentViolation::factory()->create();
        $role = Role::findByName('Kesiswaan', 'web');
        $role->syncPermissions(['student_affairs.violation.view']);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', StudentViolation::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $note));
        $this->assertFalse(Gate::forUser($user)->allows('create', StudentViolation::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $note));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $note));
        $this->actingAs($user)->get(route('student-affairs.violations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canAccessStudentViolations', true)
                ->where('canCreate', false)->where('canUpdate', false)->where('canDelete', false));
        $this->postJson(route('student-affairs.violations.store'), [])->assertForbidden();
        $this->putJson(route('student-affairs.violations.update', $note), [])->assertForbidden();
        $this->deleteJson(route('student-affairs.violations.destroy', $note))->assertForbidden();

        $role->syncPermissions(['student_affairs.violation.create', 'student_affairs.violation.update', 'student_affairs.violation.delete']);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, StudentViolation::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $note));
        }
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('canAccessStudentViolations', false)->missing('notes'));
        $this->getJson(route('student-affairs.violations.categories'))->assertForbidden();
        $this->assertModelExists($note);
    }

    public function test_lookup_is_bounded_and_uses_names_or_nisn_without_nis_fallback(): void
    {
        $user = $this->kesiswaan();
        Student::factory()->count(31)->create();
        $student = Student::factory()->create(['full_name' => 'Siswa Tanpa NISN', 'nisn' => null, 'nis' => 'LOKAL-ONLY']);

        $this->actingAs($user)->getJson(route('student-affairs.violations.students'))->assertOk()->assertJsonCount(30, 'students');
        $this->getJson(route('student-affairs.violations.students', ['search' => 'Siswa Tanpa NISN']))
            ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.id', $student->id)->assertJsonPath('students.0.nisn', null);
        $this->getJson(route('student-affairs.violations.students', ['search' => 'LOKAL-ONLY']))
            ->assertOk()->assertJsonCount(0, 'students');
        $this->getJson(route('student-affairs.violations.students', ['search' => str_repeat('a', 151)]))
            ->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->getJson(route('student-affairs.violations.index', ['page' => 0, 'per_page' => 51]))
            ->assertUnprocessable()->assertJsonValidationErrors(['page', 'per_page']);
    }

    public function test_text_is_encrypted_hidden_and_omitted_from_unrelated_pages_and_author_snapshots_survive_deletion(): void
    {
        $user = $this->kesiswaan();
        $author = User::factory()->create(['name' => 'Petugas Lama']);
        $note = StudentViolation::factory()->create([
            'category' => 'Kategori privat', 'content' => 'Uraian privat', 'follow_up' => 'Tindak lanjut privat',
            'created_by' => $author->id, 'updated_by' => $author->id,
            'creator_name' => $author->name, 'updater_name' => $author->name,
        ]);
        foreach (['category', 'content', 'follow_up'] as $field) {
            $this->assertNotSame($note->{$field}, DB::table('tr_student_affairs_violations')->where('id', $note->id)->value($field));
            $this->assertArrayNotHasKey($field, $note->toArray());
        }
        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Uraian privat')->assertDontSee('Tindak lanjut privat')
            ->assertInertia(fn (Assert $page) => $page->missing('notes'));
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('canAccessStudentViolations', false)->missing('notes'));
        $author->delete();
        $this->assertDatabaseHas('tr_student_affairs_violations', [
            'id' => $note->id, 'created_by' => null, 'updated_by' => null,
            'creator_name' => 'Petugas Lama', 'updater_name' => 'Petugas Lama',
        ]);
        $this->assertSame('Uraian privat', $note->fresh()->content);
        $this->assertDatabaseCount('tr_counseling_notes', 0);
        $this->assertDatabaseCount('tr_teacher_student_notes', 0);
    }

    private function kesiswaan(): User
    {
        $this->seed(StudentViolationPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Kesiswaan');

        return $user;
    }
}
