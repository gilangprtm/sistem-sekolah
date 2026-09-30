<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_students_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('students/index'));
    }

    public function test_super_admin_can_open_student_create_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/create')
                ->where('availableAccounts', fn ($accounts): bool => $accounts->isEmpty()));
    }

    public function test_super_admin_can_open_student_edit_page_with_current_linked_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $this->actingAs($admin)
            ->get("/students/{$studentId}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/edit')
                ->where('student.id', $studentId)
                ->where('availableAccounts', fn ($accounts): bool => $accounts->count() === 1
                    && $accounts->first()['id'] === $studentUser->id));
    }

    public function test_super_admin_can_create_student_with_available_siswa_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $studentUser->id,
                'nis' => 'NIS-001',
                'full_name' => 'Siti Aminah',
                'gender' => 'P',
                'birth_place' => 'Bandung',
                'birth_date' => '2012-05-10',
                'address' => 'Jl. Melati',
                'status' => 'active',
            ])
            ->assertRedirect('/students');

        $this->assertDatabaseHas('m_students', [
            'user_id' => $studentUser->id,
            'nis' => 'NIS-001',
            'full_name' => 'Siti Aminah',
            'gender' => 'P',
            'status' => 'active',
        ]);
    }

    public function test_student_create_rejects_non_siswa_or_already_linked_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $guru->id,
                'full_name' => 'Invalid Account',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');

        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ])->assertRedirect('/students');

        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $studentUser->id,
                'full_name' => 'Duplicate Student',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_student_create_page_only_exposes_unlinked_siswa_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $linkedAccount = User::factory()->create();
        $linkedAccount->assignRole('Siswa');
        $unlinkedAccount = User::factory()->create();
        $unlinkedAccount->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $linkedAccount->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/students/create')
            ->assertInertia(fn ($page) => $page
                ->where('availableAccounts', fn ($accounts): bool => $accounts->count() === 1
                    && $accounts->first()['id'] === $unlinkedAccount->id
                    && array_keys($accounts->first()) === ['id', 'email']));
    }

    public function test_student_validation_rejects_duplicate_nis_and_missing_name(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'nis' => 'NIS-001',
                'full_name' => '',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('full_name');

        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');
        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'nis' => 'NIS-001',
            'full_name' => 'First Student',
            'status' => 'active',
        ])->assertRedirect('/students');

        $secondUser = User::factory()->create();
        $secondUser->assignRole('Siswa');
        $this->actingAs($admin)
            ->post('/students', [
                'user_id' => $secondUser->id,
                'nis' => 'NIS-001',
                'full_name' => 'Second Student',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('nis');
    }

    public function test_student_list_supports_search_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/students?search=aminah&per_page=1&page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('students/index')
                ->where('filters.search', 'aminah')
                ->where('students.per_page', 1));
    }

    public function test_non_admin_and_guest_cannot_manage_students(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($guru)->get('/students')->assertForbidden();
        $this->app['auth']->logout();
        $this->get('/students')->assertRedirect('/login');
    }

    public function test_student_rejects_future_birth_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/students', [
                'full_name' => 'Future Student',
                'birth_date' => now()->addDay()->toDateString(),
                'status' => 'active',
            ])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_account_assignment_permission_is_required_to_attach_or_unlink(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $operator = User::factory()->create();
        $operator->assignRole('Guru');
        $operator->givePermissionTo(['student.view', 'student.update']);
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Assigned Student',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $this->actingAs($operator)
            ->patch("/students/{$studentId}", [
                'user_id' => null,
                'full_name' => 'Assigned Student',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_index_preserves_currently_linked_account_for_edit_context(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Linked Student',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/students')
            ->assertInertia(fn ($page) => $page
                ->where('students.data.0.user.id', $studentUser->id)
                ->where('students.data.0.user.email', $studentUser->email));
    }

    public function test_deleting_linked_user_nulls_student_account_reference(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Unlinked After Delete',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $studentUser->delete();

        $this->assertDatabaseHas('m_students', [
            'id' => $studentId,
            'user_id' => null,
        ]);
    }

    public function test_super_admin_can_update_and_delete_student(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Siswa');

        $this->actingAs($admin)->post('/students', [
            'user_id' => $studentUser->id,
            'full_name' => 'Initial Student',
            'status' => 'active',
        ]);
        $studentId = (int) $this->app['db']->table('m_students')->value('id');

        $this->actingAs($admin)
            ->patch("/students/{$studentId}", [
                'user_id' => $studentUser->id,
                'full_name' => 'Updated Student',
                'status' => 'inactive',
            ])
            ->assertRedirect('/students');
        $this->assertDatabaseHas('m_students', ['id' => $studentId, 'full_name' => 'Updated Student', 'status' => 'inactive']);

        $this->actingAs($admin)->delete("/students/{$studentId}")->assertRedirect();
        $this->assertDatabaseMissing('m_students', ['id' => $studentId]);
    }
}
