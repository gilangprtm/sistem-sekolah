<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentAccountGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_access_users_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get('/users')
            ->assertOk();
    }

    public function test_users_page_supports_search_and_bounded_pagination(): void
    {
        $admin = User::factory()->create(['name' => 'Administrator']);
        $admin->assignRole('Super Admin');
        User::factory()->create(['name' => 'Target User', 'email' => 'target@example.com']);
        User::factory()->create(['name' => 'Other User', 'email' => 'other@example.com']);

        $this->actingAs($admin)
            ->get('/users?search=target&per_page=1&page=1')
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('users/index')
                    ->where('filters.search', 'target')
                    ->where('filters.per_page', '1')
                    ->where('users.per_page', 1)
                    ->where('users.total', 1)
                    ->where('users.data.0.email', 'target@example.com');
            });
    }

    public function test_super_admin_can_generate_bounded_student_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 2]);

        $response->assertRedirect();
        $response->assertSessionHas('generated_accounts', [
            'count' => 2,
            'first_sequence' => 1,
            'last_sequence' => 2,
            'emails' => [
                '2026001@sekolah.sch.id',
                '2026002@sekolah.sch.id',
            ],
            'security_warning' => 'Akun dibuat dengan password awal bersama; wajib ganti password melalui alur reset yang akan disediakan.',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Siswa 2026001@sekolah.sch.id',
            'email' => '2026001@sekolah.sch.id',
        ]);
        $student = User::query()->where('email', '2026001@sekolah.sch.id')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $student->password));
        $this->assertTrue($student->hasRole('Siswa'));
        $this->assertNotNull($student->email_verified_at);
        $this->assertArrayNotHasKey('password', $response->getSession()->all('generated_accounts'));

        $this->actingAs($admin)
            ->get('/users')
            ->assertInertia(function ($page) {
                $page->component('users/index')
                    ->where('generated_accounts.count', 2)
                    ->missing('generated_accounts.password');
            });
    }

    public function test_student_account_generation_continues_year_sequence_without_reusing_gaps(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        User::factory()->create(['email' => '2026001@sekolah.sch.id']);
        User::factory()->create(['email' => '2026003@sekolah.sch.id']);
        User::factory()->create(['email' => '2025999@sekolah.sch.id']);

        $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => '2026004@sekolah.sch.id']);
        $this->assertDatabaseHas('users', ['email' => '2026005@sekolah.sch.id']);
        $this->assertDatabaseMissing('users', ['email' => '2026002@sekolah.sch.id']);
    }

    public function test_student_account_generation_allows_exact_sequence_999_boundary(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        User::factory()->create(['email' => '2026998@sekolah.sch.id']);

        $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => '2026999@sekolah.sch.id']);
    }

    public function test_student_account_generation_rejects_exact_collision_and_creates_no_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $injected = false;
        DB::listen(function (QueryExecuted $query) use (&$injected): void {
            if (! $injected && str_contains(strtolower($query->sql), 'where "email" in')) {
                $injected = true;
                User::factory()->create(['email' => '2026001@sekolah.sch.id']);
            }
        });

        $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 1])
            ->assertSessionHasErrors('count');

        $this->assertDatabaseMissing('users', ['email' => '2026001@sekolah.sch.id']);
        $this->assertSame(0, User::query()->where('email', 'like', '2026%@sekolah.sch.id')->count());
    }

    public function test_student_account_generation_rolls_back_when_siswa_role_is_missing(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        DB::table('roles')->where('name', 'Siswa')->delete();

        $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 2])
            ->assertSessionHasErrors('count');

        $this->assertDatabaseMissing('users', ['email' => '2026001@sekolah.sch.id']);
    }

    public function test_student_account_generation_rolls_back_when_batch_creation_fails_midway(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $insertCount = 0;
        DB::listen(function (QueryExecuted $query) use (&$insertCount): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'insert into "users"')) {
                $insertCount++;
                if ($insertCount === 2) {
                    throw new RuntimeException('forced batch failure');
                }
            }
        });

        try {
            app(StudentAccountGenerator::class)->generate(2026, 2);
            $this->fail('Expected the batch failure to be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced batch failure', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => '2026001@sekolah.sch.id']);
        $this->assertDatabaseMissing('users', ['email' => '2026002@sekolah.sch.id']);
    }

    public function test_student_account_generation_rejects_invalid_bounds_and_is_atomic_on_overflow(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        User::factory()->create(['email' => '2026999@sekolah.sch.id']);

        $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 202, 'count' => 0])
            ->assertSessionHasErrors(['year', 'count']);

        $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 2])
            ->assertSessionHasErrors('count');

        $this->assertDatabaseMissing('users', ['email' => '2026001@sekolah.sch.id']);
    }

    public function test_non_admin_cannot_generate_student_accounts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->get('/users')
            ->assertForbidden();
    }

    public function test_guest_cannot_generate_student_accounts(): void
    {
        $this->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 1])
            ->assertRedirect('/login');
    }

    public function test_guest_cannot_access_users_page(): void
    {
        $this->get('/users')
            ->assertRedirect('/login');
    }
}
