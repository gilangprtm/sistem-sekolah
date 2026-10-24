<?php

namespace Tests\Feature;

use App\Models\CounselingNote;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\CounselingPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CounselingNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_counselor_can_create_read_update_and_delete_notes_with_server_attribution(): void
    {
        $user = $this->counselor();
        $student = Student::factory()->create(['full_name' => 'Siswa Konseling']);
        $this->travelTo(new \DateTimeImmutable('2026-10-23T03:00:00Z'));

        $response = $this->actingAs($user)->postJson(route('counseling.notes.store'), [
            'student_id' => $student->id, 'counseling_date' => '2026-10-22',
            'content' => '  Catatan privat.  ', 'created_by' => 999, 'creator_name' => 'Palsu',
            'updated_by' => 999, 'updater_name' => 'Palsu', 'created_at' => '2000-01-01',
        ])->assertCreated()->assertHeader('Cache-Control', 'no-store, private');
        $note = CounselingNote::query()->sole();
        $response->assertJsonPath('id', $note->id);
        $this->assertSame('Catatan privat.', $note->content);
        $this->assertDatabaseHas('tr_counseling_notes', [
            'id' => $note->id, 'created_by' => $user->id, 'creator_name' => $user->name,
            'updated_by' => null, 'updater_name' => null, 'created_at' => '2026-10-23 03:00:00',
        ]);
        $this->assertNotSame('Catatan privat.', DB::table('tr_counseling_notes')->value('content'));
        $this->assertArrayNotHasKey('content', $note->toArray());
        $this->assertDatabaseCount('tr_teacher_student_notes', 0);

        $otherCounselor = $this->counselor();
        $this->actingAs($otherCounselor)->getJson(route('counseling.notes.show', $note))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('note.content', 'Catatan privat.')
            ->assertJsonPath('note.student.full_name', 'Siswa Konseling')
            ->assertJsonMissingPath('note.student.address')->assertJsonMissingPath('note.student.kantin_pin_hash');
        $this->putJson(route('counseling.notes.update', $note), [
            'student_id' => $student->id, 'counseling_date' => '2026-10-23', 'content' => 'Diperbarui.',
            'created_by' => $otherCounselor->id, 'creator_name' => 'Palsu', 'updated_by' => 999, 'updater_name' => 'Palsu',
        ])->assertOk();
        $this->assertSame('Diperbarui.', $note->fresh()->content);
        $this->assertDatabaseHas('tr_counseling_notes', [
            'id' => $note->id, 'counseling_date' => '2026-10-23', 'created_by' => $user->id,
            'creator_name' => $user->name, 'updated_by' => $otherCounselor->id, 'updater_name' => $otherCounselor->name,
        ]);

        $this->deleteJson(route('counseling.notes.destroy', $note))->assertOk()->assertJsonPath('deleted', true);
        $this->assertModelMissing($note);
        $this->getJson(route('counseling.notes.show', $note))->assertNotFound();
    }

    public function test_super_admin_can_use_every_endpoint_without_teacher_profile_or_permission_grants(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $student = Student::factory()->create();
        $note = CounselingNote::factory()->create(['student_id' => $student->id]);

        $this->actingAs($user)->get(route('counseling.notes.index'))->assertOk();
        $this->getJson(route('counseling.notes.students'))->assertOk();
        $this->getJson(route('counseling.notes.show', $note))->assertOk();
        $this->postJson(route('counseling.notes.store'), ['student_id' => $student->id, 'counseling_date' => '2026-10-23', 'content' => 'Admin.'])->assertCreated();
        $this->putJson(route('counseling.notes.update', $note), ['student_id' => $student->id, 'counseling_date' => '2026-10-23', 'content' => 'Diubah admin.'])->assertOk();
        $this->assertSame('Diubah admin.', $note->fresh()->content);
        $this->deleteJson(route('counseling.notes.destroy', $note))->assertOk();
        $this->assertModelMissing($note);
        $this->assertDatabaseCount('tr_counseling_notes', 1);
    }

    public function test_guests_cannot_access_any_endpoint(): void
    {
        $note = CounselingNote::factory()->create();

        $this->get(route('counseling.notes.index'))->assertRedirect(route('login'));
        $this->getJson(route('counseling.notes.students'))->assertUnauthorized();
        $this->getJson(route('counseling.notes.show', $note))->assertUnauthorized();
        $this->postJson(route('counseling.notes.store'), [])->assertUnauthorized();
        $this->putJson(route('counseling.notes.update', $note), [])->assertUnauthorized();
        $this->deleteJson(route('counseling.notes.destroy', $note))->assertUnauthorized();
        $this->assertModelExists($note);
    }

    /** @return array<string, array{string}> */
    public static function excludedRoles(): array
    {
        return array_combine(
            ['Guru', 'Staff', 'Siswa', 'Kesiswaan', 'Sarpras', 'Admin Perpustakaan', 'Custom'],
            array_map(fn (string $role): array => [$role], ['Guru', 'Staff', 'Siswa', 'Kesiswaan', 'Sarpras', 'Admin Perpustakaan', 'Custom']),
        );
    }

    #[DataProvider('excludedRoles')]
    public function test_other_roles_cannot_access_notes_even_when_given_all_note_permissions(string $role): void
    {
        $this->seed(CounselingPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo(['counseling.note.view', 'counseling.note.create', 'counseling.note.update', 'counseling.note.delete']);
        $note = CounselingNote::factory()->create(['content' => 'Rahasia.']);

        $this->actingAs($user)->getJson(route('counseling.notes.index'))->assertForbidden()->assertDontSee('Rahasia.');
        $this->getJson(route('counseling.notes.students'))->assertForbidden();
        $this->getJson(route('counseling.notes.show', $note))->assertForbidden()->assertDontSee('Rahasia.');
        $this->postJson(route('counseling.notes.store'), [])->assertForbidden();
        $this->putJson(route('counseling.notes.update', $note), [])->assertForbidden();
        $this->deleteJson(route('counseling.notes.destroy', $note))->assertForbidden();
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, CounselingNote::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $note));
        }
        $this->assertSame('Rahasia.', $note->fresh()->content);
        $this->assertDatabaseCount('tr_counseling_notes', 1);
    }

    public function test_counseling_role_requires_view_and_each_write_permission(): void
    {
        $user = $this->counselor();
        $note = CounselingNote::factory()->create();
        $role = Role::findByName('Konseling', 'web');
        $role->syncPermissions(['counseling.note.view']);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', CounselingNote::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $note));
        $this->assertFalse(Gate::forUser($user)->allows('create', CounselingNote::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $note));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $note));
        $this->actingAs($user)->putJson(route('counseling.notes.update', $note), [])->assertForbidden();
        $this->deleteJson(route('counseling.notes.destroy', $note))->assertForbidden();
        $this->assertModelExists($note);

        $role->syncPermissions(['counseling.note.create', 'counseling.note.update', 'counseling.note.delete']);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, CounselingNote::class));
        }
        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($user)->allows($ability, $note));
        }
    }

    public function test_index_is_a_paginated_main_page_without_note_content_and_filters_by_student(): void
    {
        $user = $this->counselor();
        $student = Student::factory()->create(['full_name' => 'Nara Siswa', 'nisn' => '0012345678']);
        CounselingNote::factory()->count(11)->create(['student_id' => $student->id, 'counseling_date' => '2026-10-22', 'content' => 'Tidak dikirim dalam daftar.']);
        $newest = CounselingNote::factory()->create(['student_id' => $student->id, 'counseling_date' => '2026-10-23']);
        CounselingNote::factory()->create(['counseling_date' => '2026-10-23']);

        $this->actingAs($user)->get(route('counseling.notes.index', ['search' => 'nara']))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertDontSee('Tidak dikirim dalam daftar.')
            ->assertInertia(fn (Assert $page) => $page->component('counseling/notes/index')
                ->where('canAccessCounseling', true)->where('canCreate', true)->where('canUpdate', true)->where('canDelete', true)
                ->has('notes.data', 10)->where('notes.total', 12)->where('notes.last_page', 2)
                ->where('notes.data.0.id', $newest->id)->missing('notes.data.0.content')
                ->where('notes.data.0.student.nisn', '0012345678')
                ->missing('notes.data.0.student.nis')->missing('notes.data.0.student.address'));
        $this->get(route('counseling.notes.index', ['search' => '0012345678', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('notes.data', 2)->where('notes.current_page', 2));
        $this->get(route('counseling.notes.index', ['search' => 'tidakada']))
            ->assertInertia(fn (Assert $page) => $page->has('notes.data', 0));
    }

    public function test_index_uses_standard_history_for_initial_and_inertia_navigation(): void
    {
        $user = $this->counselor();
        CounselingNote::factory()->create(['content' => 'Isi konseling privat.']);

        $response = $this->actingAs($user)->get(route('counseling.notes.index'))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertViewHas('page', fn (array $page): bool => ! ($page['encryptHistory'] ?? false));
        $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => $response->viewData('page')['version']];
        $this->get(route('counseling.notes.index'), $headers)
            ->assertOk()->assertHeader('X-Inertia', 'true')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonMissingPath('encryptHistory')
            ->assertJsonPath('component', 'counseling/notes/index')
            ->assertJsonCount(1, 'props.notes.data')
            ->assertJsonMissingPath('props.notes.data.0.content')
            ->assertDontSee('Isi konseling privat.');
        $this->get(route('dashboard'), $headers)
            ->assertOk()->assertJsonPath('component', 'dashboard')
            ->assertJsonMissingPath('encryptHistory')->assertJsonMissingPath('props.notes');
    }

    public function test_student_lookup_is_bounded_searchable_and_exposes_only_minimum_fields(): void
    {
        $user = $this->counselor();
        Student::factory()->count(31)->create();
        $student = Student::factory()->create(['full_name' => 'Nara Khusus', 'nisn' => 'NISN-KONSELING', 'address' => 'Alamat privat']);

        $this->actingAs($user)->getJson(route('counseling.notes.students'))->assertOk()->assertJsonCount(30, 'students')->assertHeader('Cache-Control', 'no-store, private');
        $response = $this->getJson(route('counseling.notes.students', ['search' => 'NISN-KONSELING']))
            ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.id', $student->id)
            ->assertJsonPath('students.0.nisn', 'NISN-KONSELING');
        $this->assertSame(['id', 'full_name', 'nisn'], array_keys($response->json('students.0')));
        $this->getJson(route('counseling.notes.students', ['search' => str_repeat('a', 151)]))->assertUnprocessable();
    }

    public function test_student_identification_uses_nisn_instead_of_nis_without_dropping_leading_zeroes(): void
    {
        $user = $this->counselor();
        $student = Student::factory()->create([
            'full_name' => 'Siswa Identitas', 'nis' => 'NIS-LOKAL-ONLY', 'nisn' => '0001234567',
        ]);
        $note = CounselingNote::factory()->create(['student_id' => $student->id]);

        $this->actingAs($user)->getJson(route('counseling.notes.students', ['search' => '0001234567']))
            ->assertOk()->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.nisn', '0001234567')->assertJsonMissingPath('students.0.nis');
        $this->getJson(route('counseling.notes.show', $note))
            ->assertOk()->assertJsonPath('note.student.nisn', '0001234567')->assertJsonMissingPath('note.student.nis');
        $this->get(route('counseling.notes.index', ['search' => '0001234567']))
            ->assertInertia(fn (Assert $page) => $page->has('notes.data', 1)
                ->where('notes.data.0.student.nisn', '0001234567')->missing('notes.data.0.student.nis'));
        $this->getJson(route('counseling.notes.students', ['search' => 'NIS-LOKAL-ONLY']))
            ->assertOk()->assertJsonCount(0, 'students');
        $this->get(route('counseling.notes.index', ['search' => 'NIS-LOKAL-ONLY']))
            ->assertInertia(fn (Assert $page) => $page->has('notes.data', 0));
    }

    public function test_student_without_nisn_remains_available_without_falling_back_to_nis(): void
    {
        $user = $this->counselor();
        $student = Student::factory()->create([
            'full_name' => 'Siswa Tanpa Nomor Nasional', 'nisn' => null, 'nis' => 'NIS-TIDAK-DIPAKAI',
        ]);
        $note = CounselingNote::factory()->create(['student_id' => $student->id]);

        $this->actingAs($user)->getJson(route('counseling.notes.students', ['search' => 'Siswa Tanpa Nomor Nasional']))
            ->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.nisn', null)
            ->assertJsonMissingPath('students.0.nis');
        $this->getJson(route('counseling.notes.show', $note))
            ->assertOk()->assertJsonPath('note.student.nisn', null)->assertJsonMissingPath('note.student.nis');
    }

    public function test_invalid_write_payloads_do_not_create_or_change_notes(): void
    {
        $user = $this->counselor();
        $student = Student::factory()->create();
        $note = CounselingNote::factory()->create(['content' => 'Tetap.']);
        $payload = ['student_id' => $student->id, 'counseling_date' => '2026-10-23', 'content' => 'Valid.'];

        $this->actingAs($user)->postJson(route('counseling.notes.store'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id', 'counseling_date', 'content'])
            ->assertJsonPath('errors.content.0', 'Catatan konseling wajib diisi.');
        $this->postJson(route('counseling.notes.store'), [...$payload, 'student_id' => 999999])->assertUnprocessable()->assertJsonPath('errors.student_id.0', 'Siswa yang dipilih tidak tersedia.');
        $this->postJson(route('counseling.notes.store'), [...$payload, 'counseling_date' => '2026-02-30'])->assertUnprocessable()->assertJsonPath('errors.counseling_date.0', 'Tanggal konseling tidak valid.');
        $this->postJson(route('counseling.notes.store'), [...$payload, 'content' => '   '])->assertUnprocessable()->assertJsonPath('errors.content.0', 'Catatan konseling wajib diisi.');
        $this->postJson(route('counseling.notes.store'), [...$payload, 'content' => ['invalid']])->assertUnprocessable()->assertJsonPath('errors.content.0', 'Catatan konseling harus berupa teks.');
        $this->putJson(route('counseling.notes.update', $note), [...$payload, 'content' => str_repeat('a', 5001)])->assertUnprocessable()->assertJsonPath('errors.content.0', 'Catatan konseling maksimal 5.000 karakter.');
        $this->assertSame('Tetap.', $note->fresh()->content);
        $this->assertDatabaseCount('tr_counseling_notes', 1);
        $this->postJson(route('counseling.notes.store'), [...$payload, 'content' => str_repeat('a', 5000)])->assertCreated();
        $this->assertDatabaseCount('tr_counseling_notes', 2);
    }

    public function test_invalid_pagination_is_rejected(): void
    {
        $user = $this->counselor();

        $this->actingAs($user)->getJson(route('counseling.notes.index', ['page' => 0, 'per_page' => 10000]))
            ->assertUnprocessable()->assertJsonValidationErrors(['page', 'per_page']);
        $this->getJson(route('counseling.notes.index', ['search' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('search');
    }

    public function test_seeder_is_additive_idempotent_and_does_not_assign_roles_or_grant_other_roles(): void
    {
        $admin = Role::findOrCreate('Super Admin', 'web');
        $other = Role::findOrCreate('Guru', 'web');
        $custom = Permission::findOrCreate('custom.test', 'web');
        $other->givePermissionTo($custom);
        $user = User::factory()->create();
        $user->assignRole($other);
        $user->givePermissionTo($custom);
        $this->seed(CounselingPermissionSeeder::class);
        $role = Role::findByName('Konseling', 'web');
        $role->givePermissionTo($custom);

        $this->seed(CounselingPermissionSeeder::class);

        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $this->assertTrue($role->fresh()->hasPermissionTo('counseling.note.'.$action));
            $this->assertTrue($admin->fresh()->hasPermissionTo('counseling.note.'.$action));
            $this->assertFalse($other->fresh()->hasPermissionTo('counseling.note.'.$action));
        }
        $this->assertSame(4, Permission::query()->where('name', 'like', 'counseling.note.%')->count());
        $this->assertTrue($role->fresh()->hasPermissionTo('custom.test'));
        $this->assertTrue($other->fresh()->hasPermissionTo('custom.test'));
        $this->assertSame(['Guru'], $user->fresh()->getRoleNames()->all());
        $this->assertTrue($user->fresh()->hasDirectPermission('custom.test'));
        $this->assertSame(0, $role->users()->count());
    }

    public function test_other_pages_share_only_navigation_access_not_note_content(): void
    {
        $user = $this->counselor();
        $note = CounselingNote::factory()->create(['content' => 'Jangan bagikan.']);

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Jangan bagikan.')
            ->assertInertia(fn (Assert $page) => $page->where('canAccessCounseling', true)->missing('notes'));
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('canAccessCounseling', false)->missing('notes'));
        $this->assertModelExists($note);
    }

    public function test_account_deletion_preserves_notes_and_author_name_snapshots(): void
    {
        $author = User::factory()->create(['name' => 'Konselor Lama']);
        $note = CounselingNote::factory()->create(['created_by' => $author->id, 'creator_name' => $author->name, 'updated_by' => $author->id, 'updater_name' => $author->name]);

        $author->delete();

        $this->assertDatabaseHas('tr_counseling_notes', [
            'id' => $note->id, 'created_by' => null, 'updated_by' => null,
            'creator_name' => 'Konselor Lama', 'updater_name' => 'Konselor Lama',
        ]);
        $this->assertModelExists($note);
    }

    private function counselor(): User
    {
        $this->seed(CounselingPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Konseling');

        return $user;
    }
}
