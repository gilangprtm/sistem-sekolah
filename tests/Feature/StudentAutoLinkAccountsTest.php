<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Services\AutoLinkStudentAccounts;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StudentAutoLinkAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorized_operator_links_matching_unlinked_student_accounts_without_creating_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $account = User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        $account->assignRole('Siswa');
        $student = Student::factory()->create(['nisn' => '2026001']);

        $this->actingAs($admin)
            ->post('/students/auto-link-accounts')
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'success')
            ->assertInertiaFlash('toast.message', '1 akun berhasil dihubungkan. 0 siswa dilewati.');

        $this->assertDatabaseHas('m_students', ['id' => $student->id, 'user_id' => $account->id]);
        $this->assertSame(2, User::query()->count());
    }

    public function test_automatic_linking_skips_missing_nisn_missing_or_used_accounts_and_non_siswa_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $validAccount = User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        $validAccount->assignRole('Siswa');
        $usedAccount = User::factory()->create(['email' => '2026002@sekolah.sch.id']);
        $usedAccount->assignRole('Siswa');
        $otherRoleAccount = User::factory()->create(['email' => '2026004@sekolah.sch.id']);
        $otherRoleAccount->assignRole('Guru');
        Student::factory()->create(['nisn' => '2026001']);
        Student::factory()->create(['nisn' => null]);
        Student::factory()->create(['nisn' => '9999999', 'user_id' => $usedAccount->id]);
        Student::factory()->create(['nisn' => '2026002']);
        Student::factory()->create(['nisn' => '2026003']);
        Student::factory()->create(['nisn' => '2026004']);

        $this->actingAs($admin)
            ->post('/students/auto-link-accounts')
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '1 akun berhasil dihubungkan. 4 siswa dilewati.');

        $this->assertDatabaseHas('m_students', ['nisn' => '2026001', 'user_id' => $validAccount->id]);
        $this->assertDatabaseHas('m_students', ['nisn' => '9999999', 'user_id' => $usedAccount->id]);
        $this->assertDatabaseHas('m_students', ['nisn' => '2026002', 'user_id' => null]);
        $this->assertDatabaseHas('m_students', ['nisn' => '2026003', 'user_id' => null]);
        $this->assertDatabaseHas('m_students', ['nisn' => '2026004', 'user_id' => null]);
        $this->assertDatabaseHas('m_students', ['nisn' => null, 'user_id' => null]);
    }

    public function test_automatic_linking_preserves_leading_zeroes_in_nisn(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $account = User::factory()->create(['email' => '000123@sekolah.sch.id']);
        $account->assignRole('Siswa');
        $student = Student::factory()->create(['nisn' => '000123']);

        $this->actingAs($admin)
            ->post('/students/auto-link-accounts')
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '1 akun berhasil dihubungkan. 0 siswa dilewati.');

        $this->assertDatabaseHas('m_students', ['id' => $student->id, 'user_id' => $account->id]);
    }

    public function test_automatic_linking_does_not_guess_accounts_from_names_or_other_email_domains(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $wrongDomain = User::factory()->create(['email' => '2026001@example.com']);
        $wrongDomain->assignRole('Siswa');
        $invalidNisnAccount = User::factory()->create(['email' => 'NISN-123@sekolah.sch.id']);
        $invalidNisnAccount->assignRole('Siswa');
        $missingNisn = Student::factory()->create(['full_name' => '2026001', 'nisn' => null]);
        $wrongDomainStudent = Student::factory()->create(['nisn' => '2026001']);
        $invalidNisnStudent = Student::factory()->create(['nisn' => 'NISN-123']);

        $this->actingAs($admin)
            ->post('/students/auto-link-accounts', ['user_id' => $wrongDomain->id])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '0 akun berhasil dihubungkan. 3 siswa dilewati.');

        foreach ([$missingNisn, $wrongDomainStudent, $invalidNisnStudent] as $student) {
            $this->assertDatabaseHas('m_students', ['id' => $student->id, 'user_id' => null]);
        }
    }

    public function test_automatic_linking_processes_students_beyond_the_first_chunk(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo(['student.view', 'student.update', 'student.assign-account']);
        Student::factory()->count(200)->create();
        $account = User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        $account->assignRole('Siswa');
        $student = Student::factory()->create(['nisn' => '2026001']);

        $this->actingAs($operator)
            ->post('/students/auto-link-accounts')
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '1 akun berhasil dihubungkan. 200 siswa dilewati.');

        $this->assertDatabaseHas('m_students', ['id' => $student->id, 'user_id' => $account->id]);
    }

    public function test_repeating_automatic_linking_does_not_change_existing_links(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $account = User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        $account->assignRole('Siswa');
        $student = Student::factory()->create(['nisn' => '2026001', 'user_id' => $account->id]);

        $this->actingAs($admin)
            ->post('/students/auto-link-accounts')
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '0 akun berhasil dihubungkan. 0 siswa dilewati.');

        $this->assertDatabaseHas('m_students', ['id' => $student->id, 'user_id' => $account->id]);
    }

    public function test_operator_without_account_assignment_permission_cannot_run_automatic_linking(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Guru');
        $operator->givePermissionTo(['student.view', 'student.update']);
        $account = User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        $account->assignRole('Siswa');
        $student = Student::factory()->create(['nisn' => '2026001']);

        $this->actingAs($operator)
            ->post('/students/auto-link-accounts')
            ->assertForbidden();

        $this->assertDatabaseHas('m_students', ['id' => $student->id, 'user_id' => null]);
    }

    public function test_automatic_linking_requires_student_update_and_account_assignment_permissions(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo(['student.view', 'student.assign-account']);

        $this->actingAs($operator)
            ->post('/students/auto-link-accounts')
            ->assertForbidden();
    }

    public function test_guest_cannot_run_automatic_account_linking(): void
    {
        $this->post('/students/auto-link-accounts')->assertRedirect('/login');
    }

    public function test_automatic_linking_rolls_back_all_links_when_a_database_write_fails(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $firstAccount = User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        $firstAccount->assignRole('Siswa');
        $secondAccount = User::factory()->create(['email' => '2026002@sekolah.sch.id']);
        $secondAccount->assignRole('Siswa');
        $first = Student::factory()->create(['nisn' => '2026001']);
        $second = Student::factory()->create(['nisn' => '2026002']);
        $updates = 0;
        DB::listen(function (QueryExecuted $query) use (&$updates): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'update "m_students"')) {
                $updates++;

                if ($updates === 2) {
                    throw new RuntimeException('forced linking failure');
                }
            }
        });

        try {
            app(AutoLinkStudentAccounts::class)->handle();
            $this->fail('Expected the linking failure to be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced linking failure', $exception->getMessage());
        }

        $this->assertDatabaseHas('m_students', ['id' => $first->id, 'user_id' => null]);
        $this->assertDatabaseHas('m_students', ['id' => $second->id, 'user_id' => null]);
    }

    public function test_students_page_exposes_permissions_for_automatic_link_button_visibility(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo(['student.view', 'student.update', 'student.assign-account']);
        $this->actingAs($operator)
            ->get('/students')
            ->assertInertia(fn ($page) => $page
                ->component('students/index')
                ->where('auth.permissions', fn ($permissions): bool => $permissions->contains('student.assign-account')));

        $ordinaryAdmin = User::factory()->create();
        $ordinaryAdmin->givePermissionTo(['student.view', 'student.update']);
        $this->actingAs($ordinaryAdmin)
            ->get('/students')
            ->assertInertia(fn ($page) => $page
                ->component('students/index')
                ->where('auth.permissions', fn ($permissions): bool => ! $permissions->contains('student.assign-account')));
    }
}
