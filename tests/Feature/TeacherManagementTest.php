<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_teacher_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/teachers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('teachers/index'));
    }

    public function test_super_admin_can_open_teacher_create_page_with_role_filtered_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $guru = User::factory()->create(['email' => 'guru@example.com']);
        $guru->assignRole('Guru');
        $staff = User::factory()->create(['email' => 'staff@example.com']);
        $staff->assignRole('Staff');
        $student = User::factory()->create(['email' => 'siswa@example.com']);
        $student->assignRole('Siswa');

        $this->actingAs($admin)
            ->get('/teachers/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('teachers/create')
                ->where('availableAccounts.guru', fn ($accounts): bool => $accounts->count() === 1 && $accounts->first()['email'] === $guru->email)
                ->where('availableAccounts.staff', fn ($accounts): bool => $accounts->count() === 1 && $accounts->first()['email'] === $staff->email));
    }

    public function test_super_admin_can_create_update_and_delete_teacher(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($admin)
            ->post('/teachers', [
                'staff_type' => 'guru',
                'user_id' => $guru->id,
                'full_name' => 'Guru Satu',
                'birth_date' => '1985-01-02',
                'status' => 'active',
            ])
            ->assertRedirect('/teachers');

        $teacher = Teacher::query()->firstOrFail();
        $this->assertDatabaseHas('m_teacher', ['id' => $teacher->id, 'user_id' => $guru->id, 'staff_type' => 'guru']);

        $this->actingAs($admin)
            ->patch("/teachers/{$teacher->id}", [
                'staff_type' => 'guru',
                'user_id' => $guru->id,
                'full_name' => 'Guru Diperbarui',
                'status' => 'inactive',
            ])
            ->assertRedirect('/teachers');
        $this->assertDatabaseHas('m_teacher', ['id' => $teacher->id, 'full_name' => 'Guru Diperbarui', 'status' => 'inactive']);

        $this->actingAs($admin)->delete("/teachers/{$teacher->id}")->assertRedirect();
        $this->assertDatabaseMissing('m_teacher', ['id' => $teacher->id]);
    }

    public function test_teacher_rejects_wrong_role_duplicate_link_and_future_birth_date(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $staff = User::factory()->create();
        $staff->assignRole('Staff');

        $this->actingAs($admin)
            ->post('/teachers', [
                'staff_type' => 'guru',
                'user_id' => $staff->id,
                'full_name' => 'Wrong Role',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($admin)
            ->post('/teachers', [
                'staff_type' => 'staff',
                'user_id' => $staff->id,
                'full_name' => 'Staff Satu',
                'status' => 'active',
            ])
            ->assertRedirect('/teachers');

        $this->actingAs($admin)
            ->post('/teachers', [
                'staff_type' => 'staff',
                'user_id' => $staff->id,
                'full_name' => 'Duplicate Link',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($admin)
            ->post('/teachers', [
                'staff_type' => 'guru',
                'full_name' => 'Future Date',
                'birth_date' => now()->addDay()->toDateString(),
                'status' => 'active',
            ])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_deleting_linked_user_nulls_teacher_account_reference(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($admin)->post('/teachers', [
            'staff_type' => 'guru',
            'user_id' => $guru->id,
            'full_name' => 'Guru Terhapus',
            'status' => 'active',
        ]);
        $teacher = Teacher::query()->firstOrFail();

        $guru->delete();

        $this->assertDatabaseHas('m_teacher', ['id' => $teacher->id, 'user_id' => null]);
    }

    public function test_assignment_permission_is_required_for_attach_or_unlink(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $operator = User::factory()->create();
        $operator->givePermissionTo(['teacher.view', 'teacher.update']);
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($admin)->post('/teachers', [
            'staff_type' => 'guru',
            'user_id' => $guru->id,
            'full_name' => 'Guru Satu',
            'status' => 'active',
        ]);
        $teacher = Teacher::query()->firstOrFail();

        $this->actingAs($operator)
            ->patch("/teachers/{$teacher->id}", [
                'staff_type' => 'guru',
                'user_id' => null,
                'full_name' => 'Guru Satu',
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_non_authorized_user_and_guest_cannot_manage_teachers(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole('Guru');

        $this->actingAs($guru)->get('/teachers')->assertForbidden();
        $this->app['auth']->logout();
        $this->get('/teachers')->assertRedirect('/login');
    }

    public function test_teacher_list_supports_search_and_pagination(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/teachers?search=aminah&per_page=1&page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('teachers/index')->where('filters.search', 'aminah')->where('teachers.per_page', 1));
    }

    public function test_teacher_list_applies_type_gender_and_status_filters_server_side(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Teacher::factory()->create([
            'full_name' => 'Guru Aktif Perempuan',
            'staff_type' => 'guru',
            'gender' => 'P',
            'status' => 'active',
        ]);
        Teacher::factory()->create([
            'full_name' => 'Staff Tidak Aktif Laki-laki',
            'staff_type' => 'staff',
            'gender' => 'L',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->get('/teachers?staff_type=guru&gender=P&status=active')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.staff_type', 'guru')
                ->where('filters.gender', 'P')
                ->where('filters.status', 'active')
                ->where('teachers.total', 1)
                ->where('teachers.data.0.full_name', 'Guru Aktif Perempuan'));
    }

    public function test_teacher_accepts_nip_and_nuptk_for_guru_and_staff_and_exposes_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/teachers', [
            'staff_type' => 'guru',
            'nip' => '123456789012345678',
            'nuptk' => '1234567890123456',
            'full_name' => 'Guru Dengan Identitas',
            'status' => 'active',
        ])->assertRedirect('/teachers');

        $teacher = Teacher::query()->firstOrFail();
        $this->assertSame('123456789012345678', $teacher->nip);
        $this->assertSame('1234567890123456', $teacher->nuptk);

        $this->actingAs($admin)
            ->get('/teachers')
            ->assertInertia(fn ($page) => $page
                ->where('teachers.data.0.nip', '123456789012345678')
                ->where('teachers.data.0.nuptk', '1234567890123456'));
    }

    public function test_teacher_rejects_non_ascii_digit_or_wrong_length_nip_and_nuptk(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->post('/teachers', [
                'staff_type' => 'staff',
                'nip' => '１２３４５６７８９０１２３４５６７８',
                'nuptk' => '123456789012345',
                'full_name' => 'Staff Invalid Identity',
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['nip', 'nuptk']);
    }

    public function test_teacher_rejects_duplicate_nip_and_nuptk_globally(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/teachers', [
            'staff_type' => 'guru',
            'nip' => '123456789012345678',
            'nuptk' => '1234567890123456',
            'full_name' => 'First Identity',
            'status' => 'active',
        ])->assertRedirect('/teachers');

        $this->actingAs($admin)->post('/teachers', [
            'staff_type' => 'staff',
            'nip' => '123456789012345678',
            'full_name' => 'Duplicate NIP',
            'status' => 'active',
        ])->assertSessionHasErrors('nip');

        $this->actingAs($admin)->post('/teachers', [
            'staff_type' => 'staff',
            'nuptk' => '1234567890123456',
            'full_name' => 'Duplicate NUPTK',
            'status' => 'active',
        ])->assertSessionHasErrors('nuptk');
    }
}
