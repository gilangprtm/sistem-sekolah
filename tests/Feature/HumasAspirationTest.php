<?php

namespace Tests\Feature;

use App\Models\HumasAspiration;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\HumasPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HumasAspirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_humas_can_read_aspirations_and_student_identity_with_pagination(): void
    {
        $user = $this->humas();
        $student = Student::factory()->create(['full_name' => 'Nara Siswa', 'nisn' => '0001234567']);
        HumasAspiration::factory()->count(11)->create([
            'student_id' => $student->id,
            'content' => 'Isi aspirasi privat.',
        ]);
        $newest = HumasAspiration::factory()->create([
            'student_id' => $student->id,
            'content' => 'Mohon tambah tempat berteduh.',
        ]);
        HumasAspiration::factory()->create();

        $this->actingAs($user)->get(route('humas.silaras.index', ['search' => 'Nara']))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('humas/silaras/index')
                ->has('aspirations.data', 10)
                ->where('aspirations.total', 12)
                ->where('aspirations.last_page', 2)
                ->where('aspirations.data.0.id', $newest->id)
                ->where('aspirations.data.0.content', 'Mohon tambah tempat berteduh.')
                ->where('aspirations.data.0.student.full_name', 'Nara Siswa')
                ->where('aspirations.data.0.student.nisn', '0001234567')
                ->where('filters.search', 'Nara'));
        $this->get(route('humas.silaras.index', ['search' => '0001234567', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('aspirations.data', 2)
                ->where('aspirations.current_page', 2));
    }

    public function test_super_admin_can_read_silaras_without_humas_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Super Admin', 'web'));

        $this->actingAs($user)->get(route('humas.silaras.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('humas/silaras/index')
                ->has('aspirations.data', 0));
    }

    public function test_guests_are_redirected_and_users_without_humas_access_are_forbidden(): void
    {
        $aspiration = HumasAspiration::factory()->create(['content' => 'Rahasia aspirasi.']);

        $this->get(route('humas.silaras.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Guru', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('humas.aspiration.view', 'web'));

        $this->actingAs($user)->get(route('humas.silaras.index'))
            ->assertForbidden()
            ->assertDontSee('Rahasia aspirasi.');
        $this->assertModelExists($aspiration);
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', HumasAspiration::class));
    }

    /** @return array<string, array{string}> */
    public static function rolesWithoutHumasAccess(): array
    {
        return array_combine(
            ['Guru', 'Staff', 'Siswa', 'Kesiswaan', 'Konseling', 'Sarpras', 'Admin Perpustakaan'],
            array_map(fn (string $role): array => [$role], [
                'Guru', 'Staff', 'Siswa', 'Kesiswaan', 'Konseling', 'Sarpras', 'Admin Perpustakaan',
            ]),
        );
    }

    #[DataProvider('rolesWithoutHumasAccess')]
    public function test_only_humas_and_super_admin_receive_silaras_permission_from_seeder(string $roleName): void
    {
        $this->seed(HumasPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        $this->actingAs($user)->get(route('humas.silaras.index'))->assertForbidden();
    }

    public function test_humas_requires_view_permission_and_navigation_uses_the_same_authorization(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Humas', 'web'));

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', HumasAspiration::class));
        $this->actingAs($user)->get(route('humas.silaras.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('canAccessSilaras', false)->missing('aspirations'));

        $this->seed(HumasPermissionSeeder::class);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', HumasAspiration::class));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('canAccessSilaras', true)->missing('aspirations'));
    }

    public function test_index_exposes_only_the_needed_student_fields_and_preserves_plain_text(): void
    {
        $user = $this->humas();
        $student = Student::factory()->create(['address' => 'Alamat privat', 'phone' => '08123456789']);
        $content = "<script>alert('xss')</script>\nSaran siswa.";
        $aspiration = HumasAspiration::factory()->for($student)->create(['content' => $content]);

        $response = $this->actingAs($user)->get(route('humas.silaras.index'));
        $response->assertInertia(fn (Assert $page) => $page
            ->where('aspirations.data.0.content', $content)
            ->missing('aspirations.data.0.student.address')
            ->missing('aspirations.data.0.student.phone')
            ->missing('aspirations.data.0.student.user_id'));
        $this->assertNotSame($content, DB::table('tr_humas_aspirations')->where('id', $aspiration->id)->value('content'));
        $this->assertArrayNotHasKey('content', $aspiration->toArray());
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidFilters(): array
    {
        return [
            'search length' => [['search' => str_repeat('a', 151)], 'search'],
            'search type' => [['search' => ['name']], 'search'],
            'page minimum' => [['page' => 0], 'page'],
            'page maximum' => [['page' => 100001], 'page'],
            'page integer' => [['page' => 'wrong'], 'page'],
            'page size minimum' => [['per_page' => 0], 'per_page'],
            'page size maximum' => [['per_page' => 51], 'per_page'],
            'page size integer' => [['per_page' => 'wrong'], 'per_page'],
        ];
    }

    /** @param array<string, mixed> $filters */
    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_422(array $filters, string $field): void
    {
        $this->actingAs($this->humas())
            ->getJson(route('humas.silaras.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_search_page_size_and_empty_results_remain_read_only(): void
    {
        $user = $this->humas();
        $student = Student::factory()->create(['full_name' => 'Nara Siswa', 'nisn' => '0001234567']);
        HumasAspiration::factory()->count(3)->for($student)->create();

        $this->actingAs($user)->get(route('humas.silaras.index', ['search' => '  nara  ', 'per_page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'nara')->where('aspirations.per_page', 2)
                ->where('aspirations.total', 3)->has('aspirations.data', 2));
        $this->get(route('humas.silaras.index', ['search' => "' OR 1=1 --"]))
            ->assertInertia(fn (Assert $page) => $page->has('aspirations.data', 0));
        $this->postJson(route('humas.silaras.index'), ['content' => 'Tidak boleh ditambahkan.'])
            ->assertMethodNotAllowed();
        $this->putJson(route('humas.silaras.index'), ['content' => 'Tidak boleh diubah.'])
            ->assertMethodNotAllowed();
        $this->deleteJson(route('humas.silaras.index'))->assertMethodNotAllowed();
        $this->assertDatabaseCount('tr_humas_aspirations', 3);
    }

    public function test_seeder_grants_view_permission_to_humas_and_super_admin_only(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        $this->seed(HumasPermissionSeeder::class);
        $permission = Permission::findByName('humas.aspiration.view', 'web');
        $humas = Role::findByName('Humas', 'web');
        $superAdmin = Role::findByName('Super Admin', 'web');

        $this->assertTrue($humas->hasPermissionTo($permission));
        $this->assertTrue($superAdmin->hasPermissionTo($permission));
        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseCount('role_has_permissions', 2);
    }

    public function test_repeated_seeding_preserves_existing_permissions_and_role_members(): void
    {
        $user = $this->humas();
        $permission = Permission::findOrCreate('student.view', 'web');
        Role::findByName('Humas', 'web')->givePermissionTo($permission);
        $user->givePermissionTo($permission);

        $this->seed(HumasPermissionSeeder::class);
        $this->seed(HumasPermissionSeeder::class);

        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('permissions', 2);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertTrue($user->fresh()->hasRole('Humas', 'web'));
        $this->assertTrue($user->fresh()->hasDirectPermission($permission));
        $this->assertTrue(Role::findByName('Humas', 'web')->hasPermissionTo($permission));
    }

    private function humas(): User
    {
        $this->seed(HumasPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Humas');

        return $user;
    }
}
