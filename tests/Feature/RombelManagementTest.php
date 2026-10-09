<?php

namespace Tests\Feature;

use App\Models\Rombel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RombelManagementTest extends TestCase
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

    public function test_super_admin_can_create_normalized_derived_rombel(): void
    {
        $this->actingAs($this->admin())
            ->post('/kurikulum/rombels', ['name' => '  Kelas VII A  ', 'grade_level' => ' vii ', 'parallel_code' => ' a1 '])
            ->assertRedirect('/kurikulum/rombels')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_rombels', [
            'code' => 'VII-A1',
            'name' => 'Kelas VII A',
            'grade_level' => 'VII',
            'parallel_code' => 'A1',
            'status' => 'active',
        ]);
    }

    public function test_invalid_grade_parallel_and_duplicate_code_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Invalid', 'grade_level' => 'VI', 'parallel_code' => 'A'])->assertSessionHasErrors('grade_level');
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Invalid', 'grade_level' => 'VII', 'parallel_code' => 'A!'])->assertSessionHasErrors('parallel_code');
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'First', 'grade_level' => 'VII', 'parallel_code' => 'A'])->assertRedirect('/kurikulum/rombels');
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Duplicate', 'grade_level' => 'vii', 'parallel_code' => 'a'])->assertSessionHasErrors('parallel_code');
    }

    public function test_rombel_has_global_master_shape_without_downstream_foreign_keys(): void
    {
        $this->assertTrue(Schema::hasColumns('m_rombels', ['code', 'name', 'grade_level', 'parallel_code', 'status', 'created_at', 'updated_at']));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'academic_year_id'));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'academic_period_id'));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'teacher_id'));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'subject_id'));
        $this->assertFalse(Schema::hasColumn('m_rombels', 'student_id'));
    }

    public function test_rombel_can_be_archived_and_reactivated_without_physical_delete(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Kelas VII A', 'grade_level' => 'VII', 'parallel_code' => 'A'])->assertRedirect();
        $rombel = Rombel::query()->firstOrFail();

        $this->actingAs($admin)->post("/kurikulum/rombels/{$rombel->id}/archive")->assertRedirect();
        $this->assertSame('inactive', $rombel->refresh()->status);
        $this->assertModelExists($rombel);
        $this->actingAs($admin)->patch("/kurikulum/rombels/{$rombel->id}", ['name' => 'Kelas VII A', 'grade_level' => 'VII', 'parallel_code' => 'A', 'status' => 'active'])->assertRedirect();
        $this->assertSame('active', $rombel->refresh()->status);
    }

    public function test_rombel_has_no_physical_delete_endpoint(): void
    {
        $admin = $this->admin();
        $rombel = Rombel::factory()->create();

        $this->actingAs($admin)->delete("/kurikulum/rombels/{$rombel->id}")->assertStatus(405);
        $this->assertModelExists($rombel);
    }

    public function test_rombel_list_search_status_pagination_and_non_admin_authorization(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Kelas VII A', 'grade_level' => 'VII', 'parallel_code' => 'A'])->assertRedirect();
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Kelas VIII B', 'grade_level' => 'VIII', 'parallel_code' => 'B'])->assertRedirect();
        $this->actingAs($admin)->post('/kurikulum/rombels', ['name' => 'Arsip IX C', 'grade_level' => 'IX', 'parallel_code' => 'C'])->assertRedirect();
        $rombel = Rombel::query()->where('code', 'IX-C')->firstOrFail();
        $rombel->update(['status' => 'inactive']);

        $this->actingAs($admin)->get('/kurikulum/rombels?search=VIII&status=active&per_page=1')->assertOk()->assertInertia(fn ($page) => $page->component('rombels/index')->where('rombels.total', 1)->where('filters.search', 'VIII'));
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $this->actingAs($user)->get('/kurikulum/rombels')->assertForbidden();

        $source = file_get_contents(resource_path('js/pages/rombels/index.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString("import { Checkbox } from '@/components/ui/checkbox';", $source);
        $this->assertStringContainsString("import DataTableToolbar from '@/components/data-table/data-table-toolbar';", $source);
        $this->assertStringContainsString('aria-label="Pilih semua rombel"', $source);
        $this->assertStringContainsString('Hapus pilihan', $source);
        $this->assertStringContainsString('colSpan={6}', $source);
    }
}
