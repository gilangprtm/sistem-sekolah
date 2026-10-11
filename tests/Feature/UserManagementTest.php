<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Services\StudentAccountGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;
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

    public function test_users_page_uses_mobile_safe_header_and_table_overflow_contract(): void
    {
        $source = file_get_contents(resource_path('js/pages/users/index.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'flex flex-col items-stretch justify-between gap-4 sm:flex-row sm:items-center',
            $source,
        );
        $this->assertStringContainsString(
            'flex w-full flex-col gap-2 sm:w-auto sm:flex-row',
            $source,
        );
        $this->assertStringContainsString(
            'className="w-full sm:w-auto"',
            $source,
        );
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringContainsString('break-words', $source);
        $this->assertStringContainsString('pb-24 sm:pb-6', $source);
        $this->assertStringContainsString('min-w-[640px]', $source);
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

    public function test_users_page_filters_by_role_and_verification_status(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->assignRole('Super Admin');
        $teacherRole = Role::findByName('Guru');
        $target = User::factory()->create(['name' => 'Verified Guru', 'email_verified_at' => now()]);
        $target->assignRole($teacherRole);
        User::factory()->create(['name' => 'Unverified Guru']);

        $this->actingAs($admin)
            ->get("/users?role={$teacherRole->id}&status=verified&per_page=1&page=1")
            ->assertOk()
            ->assertInertia(function ($page) use ($teacherRole) {
                $page->component('users/index')
                    ->where('filters.role', (string) $teacherRole->id)
                    ->where('filters.status', 'verified')
                    ->where('users.per_page', 1)
                    ->where('users.total', 1)
                    ->where('users.data.0.name', 'Verified Guru');
            });
    }

    public function test_super_admin_can_download_csv_template(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)
            ->get('/users/student-accounts/template');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=template-akun-siswa.csv');

        $this->assertSame("Akun\r\n", $response->streamedContent());
    }

    public function test_student_accounts_can_be_imported_from_nisn_csv_atomically(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $students = Student::factory()->count(2)->sequence(
            ['nisn' => '1234567890', 'full_name' => 'Siswa Satu'],
            ['nisn' => '0987654321', 'full_name' => 'Siswa Dua'],
        )->create();

        $response = $this->actingAs($admin)->post('/users/import-student-accounts', [
            'file' => UploadedFile::fake()->createWithContent(
                'accounts.csv',
                "Akun\r\n1234567890\r\n0987654321\r\n",
            ),
        ]);

        $response->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', '2 akun Siswa berhasil dibuat.');
        foreach ($students as $student) {
            $student->refresh();
            $this->assertNotNull($student->user_id);
            $user = User::findOrFail($student->user_id);
            $this->assertSame($student->nisn.'@sekolah.sch.id', $user->email);
            $this->assertTrue(Hash::check('password123', $user->password));
            $this->assertTrue($user->hasRole('Siswa'));
        }
    }

    public function test_student_accounts_import_accepts_300_nisn_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->count(300)->sequence(
            fn ($sequence): array => ['nisn' => sprintf('%010d', 1000000001 + $sequence->index)],
        )->create();

        $nisns = array_map(
            static fn (int $number): string => sprintf('%010d', $number),
            range(1000000001, 1000000300),
        );
        $csv = "Akun\r\n".implode("\r\n", $nisns)."\r\n";

        $this->actingAs($admin)
            ->post('/users/import-student-accounts', [
                'file' => UploadedFile::fake()->createWithContent('accounts.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.message', '300 akun Siswa berhasil dibuat.');

        $this->assertSame(300, User::query()->whereIn('email', array_map(
            static fn (string $nisn): string => $nisn.'@sekolah.sch.id',
            $nisns,
        ))->count());
    }

    public function test_student_accounts_import_rejects_duplicate_or_missing_nisn_without_partial_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['nisn' => '1234567890']);

        $this->actingAs($admin)->post('/users/import-student-accounts', [
            'file' => UploadedFile::fake()->createWithContent('accounts.csv', "Akun\n1234567890\n1234567890\n"),
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, User::query()->where('email', 'like', '%@sekolah.sch.id')->count());

        $this->actingAs($admin)->post('/users/import-student-accounts', [
            'file' => UploadedFile::fake()->createWithContent('accounts.csv', "Akun\n1234567890\n1111111111\n"),
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, User::query()->where('email', 'like', '%@sekolah.sch.id')->count());
    }

    public function test_student_accounts_import_rejects_invalid_csv_shapes_and_bounds(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        foreach (
            [
                "Wrong\n1234567890\n" => 'Header CSV wajib tepat satu kolom: Akun.',
                "Akun\n12345\n" => 'Format NISN tidak valid pada CSV.',
                "Akun\n1234567890,extra\n" => 'CSV memiliki format kolom yang tidak valid.',
                "Akun\n\n" => 'CSV memiliki baris kosong.',
            ] as $csv => $message
        ) {
            $this->actingAs($admin)
                ->post('/users/import-student-accounts', [
                    'file' => UploadedFile::fake()->createWithContent('accounts.csv', $csv),
                ])
                ->assertSessionHasErrors(['file' => $message]);
        }

        $csv = "Akun\n".implode("\n", array_map(static fn (int $number): string => sprintf('%010d', $number), range(1, 301)));
        $this->actingAs($admin)
            ->post('/users/import-student-accounts', [
                'file' => UploadedFile::fake()->createWithContent('accounts.csv', $csv),
            ])
            ->assertSessionHasErrors(['file' => 'CSV maksimal berisi 300 NISN.']);
    }

    public function test_student_accounts_import_rejects_existing_account_without_partial_creation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['nisn' => '1234567890']);
        User::factory()->create(['email' => '1234567890@sekolah.sch.id']);
        Student::factory()->create(['nisn' => '0987654321']);

        $this->actingAs($admin)
            ->post('/users/import-student-accounts', [
                'file' => UploadedFile::fake()->createWithContent('accounts.csv', "Akun\n1234567890\n0987654321\n"),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('users', ['email' => '0987654321@sekolah.sch.id']);
    }

    public function test_only_authorized_users_can_download_and_import_student_account_csv(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->get('/users/student-accounts/template')->assertRedirect('/login');
        $this->actingAs($user)->get('/users/student-accounts/template')->assertForbidden();
        $this->actingAs($user)->post('/users/import-student-accounts')->assertForbidden();
    }

    public function test_super_admin_can_generate_bounded_student_accounts(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $response = $this->actingAs($admin)
            ->post('/users/generate-student-accounts', ['year' => 2026, 'count' => 2]);

        $response->assertRedirect();
        $response->assertSessionHas('inertia.flash_data.toast.type', 'success');
        $response->assertSessionHas('inertia.flash_data.toast.message', '2 akun Siswa berhasil dibuat.');
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
