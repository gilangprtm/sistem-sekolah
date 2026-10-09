<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\RombelPeriodUsage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RombelPeriodUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function activePeriod(): AcademicPeriod
    {
        $year = AcademicYear::query()->create([
            'year' => '2026/2027',
            'status' => 'active',
        ]);

        return $year->periods()->create([
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_assign_active_rombel_to_active_period(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create();

        $this->actingAs($this->admin())
            ->post('/kurikulum/academic-period-rombels', [
                'academic_period_id' => $period->id,
                'rombel_id' => $rombel->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tr_curriculum_period_rombels', [
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
        ]);
    }

    public function test_inactive_anchor_and_duplicate_assignment_are_rejected(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/kurikulum/academic-period-rombels', [
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post('/kurikulum/academic-period-rombels', [
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
        ])->assertSessionHasErrors('rombel_id');

        $rombel->update(['status' => 'inactive']);
        $this->actingAs($admin)->post('/kurikulum/academic-period-rombels', [
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
        ])->assertSessionHasErrors('rombel_id');
    }

    public function test_inactive_period_cannot_receive_usage(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'inactive']);
        $period = $year->periods()->create(['code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'inactive']);
        $rombel = Rombel::factory()->create();

        $this->actingAs($this->admin())
            ->post('/kurikulum/academic-period-rombels', ['academic_period_id' => $period->id, 'rombel_id' => $rombel->id])
            ->assertSessionHasErrors('academic_period_id');
    }

    public function test_usage_can_be_removed_without_deleting_global_rombel(): void
    {
        $period = $this->activePeriod();
        $rombel = Rombel::factory()->create();
        $usage = RombelPeriodUsage::query()->create([
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
        ]);

        $this->actingAs($this->admin())
            ->delete("/kurikulum/academic-period-rombels/{$usage->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('tr_curriculum_period_rombels', ['id' => $usage->id]);
        $this->assertModelExists($rombel);
    }

    public function test_non_privileged_user_cannot_access_usage_routes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)->get('/kurikulum/academic-period-rombels')->assertForbidden();
        $this->actingAs($user)->post('/kurikulum/academic-period-rombels')->assertForbidden();
    }

    public function test_usage_schema_is_transactional_and_restrictive(): void
    {
        $this->assertTrue(Schema::hasColumns('tr_curriculum_period_rombels', ['academic_period_id', 'rombel_id', 'created_at', 'updated_at']));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'academic_period_id'));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'academic_year_id'));
    }
}
