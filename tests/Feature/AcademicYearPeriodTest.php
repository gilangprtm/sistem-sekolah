<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicYearPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_academic_year_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->get('/academic-years')->assertOk()->assertInertia(fn ($page) => $page->component('academic-years/index'));
    }

    public function test_non_admin_cannot_manage_academic_years(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get('/academic-years')->assertForbidden();
    }

    public function test_year_activation_sets_dates_and_deactivates_previous_year(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/academic-years', ['year' => '2025/2026', 'status' => 'active'])->assertRedirect();
        $first = AcademicYear::query()->firstOrFail();
        $this->assertNotNull($first->start_date);
        $this->assertNull($first->end_date);

        $this->actingAs($admin)->post('/academic-years', ['year' => '2026/2027', 'status' => 'active'])->assertRedirect();
        $first->refresh();
        $second = AcademicYear::query()->where('year', '2026/2027')->firstOrFail();
        $this->assertSame('inactive', $first->status);
        $this->assertNotNull($first->end_date);
        $this->assertSame('active', $second->status);
        $this->assertNotNull($second->start_date);
        $this->assertSame(1, AcademicYear::query()->where('status', 'active')->count());
    }

    public function test_period_belongs_to_year_and_activation_dates_are_automatic(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->post('/academic-years', ['year' => '2025/2026', 'status' => 'active']);
        $year = AcademicYear::query()->firstOrFail();

        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'active'])->assertRedirect();
        $period = AcademicPeriod::query()->firstOrFail();
        $this->assertSame($year->id, $period->academic_year_id);
        $this->assertNotNull($period->start_date);
        $this->assertNull($period->end_date);

        $this->actingAs($admin)->patch("/academic-periods/{$period->id}", ['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'inactive'])->assertRedirect();
        $period->refresh();
        $this->assertNotNull($period->end_date);
    }

    public function test_invalid_year_and_period_values_are_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)->post('/academic-years', ['year' => '2025', 'status' => 'active'])->assertSessionHasErrors('year');
        $this->actingAs($admin)->post('/academic-years', ['year' => '2025/2026', 'status' => 'inactive'])->assertRedirect();
        $year = AcademicYear::query()->firstOrFail();
        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'bad', 'name' => 'Bad', 'status' => 'active'])->assertSessionHasErrors('code');
    }

    public function test_duplicate_semester_code_is_rejected_on_create_and_update(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->post('/academic-years', ['year' => '2025/2026', 'status' => 'active']);
        $year = AcademicYear::query()->firstOrFail();
        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'inactive']);
        $first = AcademicPeriod::query()->firstOrFail();

        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'ganjil', 'name' => 'Duplikat', 'status' => 'inactive'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'genap', 'name' => 'Semester Genap', 'status' => 'inactive']);
        $second = AcademicPeriod::query()->where('code', 'genap')->firstOrFail();
        $this->actingAs($admin)->patch("/academic-periods/{$second->id}", ['code' => 'ganjil', 'name' => 'Bentrok', 'status' => 'inactive'])->assertSessionHasErrors('code');
        $this->assertSame('ganjil', $first->refresh()->code);
    }

    public function test_year_and_period_update_lifecycle_controls_preserve_system_dates(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->post('/academic-years', ['year' => '2025/2026', 'status' => 'active']);
        $year = AcademicYear::query()->firstOrFail();
        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'active']);
        $period = AcademicPeriod::query()->firstOrFail();
        $startDate = $period->start_date?->toDateString();

        $this->actingAs($admin)->patch("/academic-periods/{$period->id}", ['code' => 'ganjil', 'name' => 'Ganjil Diperbarui', 'status' => 'inactive'])->assertRedirect();
        $period->refresh();
        $this->assertSame('Ganjil Diperbarui', $period->name);
        $this->assertSame($startDate, $period->start_date?->toDateString());
        $this->assertNotNull($period->end_date);

        $this->actingAs($admin)->patch("/academic-years/{$year->id}", ['year' => '2025/2026', 'status' => 'inactive'])->assertRedirect();
        $year->refresh();
        $this->assertNotNull($year->end_date);
    }

    public function test_active_semester_is_rejected_when_parent_year_is_inactive(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->post('/academic-years', ['year' => '2025/2026', 'status' => 'inactive']);
        $year = AcademicYear::query()->firstOrFail();

        $this->actingAs($admin)->post("/academic-years/{$year->id}/periods", ['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'active'])->assertSessionHasErrors('status');
    }

    public function test_academic_year_index_filters_by_search_and_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        AcademicYear::query()->create(['year' => '2024/2025', 'status' => 'inactive']);
        AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/academic-years?search=2025&status=active')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('academic-years/index')
                ->where('years.total', 1)
                ->where('years.data.0.year', '2025/2026')
                ->where('filters.search', '2025')
                ->where('filters.status', 'active'));
    }

    public function test_academic_year_index_searches_semester_name_and_respects_page_size(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $year->periods()->create([
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);
        AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'inactive']);

        $this->actingAs($admin)
            ->get('/academic-years?search=Ganjil&per_page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('years.total', 1)
                ->where('years.per_page', 1)
                ->where('years.data.0.year', '2025/2026'));
    }

    public function test_curriculum_lifecycle_mutations_require_create_or_update_permission(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo('curriculum.view');

        $this->actingAs($operator)->post('/academic-years', ['year' => '2025/2026', 'status' => 'inactive'])->assertForbidden();
    }
}
