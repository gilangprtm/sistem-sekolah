<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
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

    public function test_super_admin_can_view_subject_page_with_server_pagination_and_filters(): void
    {
        $admin = $this->admin();
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);
        Subject::factory()->create(['code' => 'BIO', 'name' => 'Biologi', 'status' => 'inactive']);

        $this->actingAs($admin)
            ->get('/subjects?search=MTK&status=active&per_page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('subjects/index')
                ->where('subjects.total', 1)
                ->where('subjects.data.0.code', 'MTK')
                ->where('filters.search', 'MTK')
                ->where('filters.status', 'active'));
    }

    public function test_subject_create_trims_valid_display_values_and_uses_default_jp_per_class(): void
    {
        $this->actingAs($this->admin())
            ->post('/subjects', [
                'code' => '  IPA_1  ',
                'name' => '  Ilmu Pengetahuan Alam  ',
                'status' => 'active',
                'color' => ' #dcebff ',
            ])
            ->assertRedirect('/subjects')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('m_subjects', [
            'code' => 'IPA_1',
            'name' => 'Ilmu Pengetahuan Alam',
            'status' => 'active',
            'jp_per_class' => 1,
            'color' => '#DCEBFF',
        ]);
    }

    public function test_subject_create_and_update_persist_valid_jp_per_class(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/subjects', [
                'code' => 'IPA',
                'name' => 'Ilmu Pengetahuan Alam',
                'status' => 'active',
                'jp_per_class' => 8,
                'color' => '#FDE68A',
            ])
            ->assertRedirect('/subjects')
            ->assertSessionHasNoErrors();

        $subject = Subject::query()->where('code', 'IPA')->firstOrFail();
        $this->assertSame(8, $subject->jp_per_class);

        $this->actingAs($admin)
            ->patch("/subjects/{$subject->id}", [
                'code' => 'IPA',
                'name' => 'Ilmu Pengetahuan Alam',
                'status' => 'active',
                'jp_per_class' => 12,
                'color' => '#BFDBFE',
            ])
            ->assertRedirect('/subjects')
            ->assertSessionHasNoErrors();

        $subject->refresh();
        $this->assertSame(12, $subject->jp_per_class);
        $this->assertSame('#BFDBFE', $subject->color);
    }

    public function test_subject_rejects_invalid_color(): void
    {
        $this->actingAs($this->admin())
            ->post('/subjects', [
                'code' => 'WARNA',
                'name' => 'Warna Invalid',
                'status' => 'active',
                'color' => 'blue',
            ])
            ->assertSessionHasErrors('color');
    }

    public function test_subject_rejects_jp_per_class_outside_positive_bounded_range(): void
    {
        foreach ([0, 16] as $jpPerClass) {
            $this->actingAs($this->admin())
                ->post('/subjects', [
                    'code' => "JP{$jpPerClass}",
                    'name' => "Jam {$jpPerClass}",
                    'status' => 'active',
                    'jp_per_class' => $jpPerClass,
                ])
                ->assertSessionHasErrors('jp_per_class');
        }
    }

    public function test_subject_rejects_lowercase_code_and_empty_trimmed_name(): void
    {
        $this->actingAs($this->admin())
            ->post('/subjects', [
                'code' => 'math',
                'name' => 'Matematika',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($this->admin())
            ->post('/subjects', [
                'code' => 'MATH',
                'name' => '   ',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_subject_rejects_case_insensitive_duplicate_values(): void
    {
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        $this->actingAs($this->admin())
            ->post('/subjects', [
                'code' => 'mtk',
                'name' => 'MATEMATIKA',
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['code', 'name']);
    }

    public function test_database_rejects_case_insensitive_duplicate_subject_code(): void
    {
        Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        $this->expectException(QueryException::class);
        Subject::query()->create(['code' => 'mtk', 'name' => 'Fisika', 'status' => 'active']);
    }

    public function test_subject_can_be_archived_without_deleting_and_reactivated_by_update(): void
    {
        $admin = $this->admin();
        $subject = Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']);

        $this->actingAs($admin)
            ->post("/subjects/{$subject->id}/archive")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $subject->refresh();
        $this->assertSame('inactive', $subject->status);
        $this->assertModelExists($subject);

        $this->actingAs($admin)
            ->patch("/subjects/{$subject->id}", [
                'code' => $subject->code,
                'name' => $subject->name,
                'status' => 'active',
            ])
            ->assertRedirect('/subjects');

        $this->assertSame('active', $subject->refresh()->status);
    }

    public function test_subject_has_no_physical_delete_endpoint(): void
    {
        $subject = Subject::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/subjects/{$subject->id}")
            ->assertStatus(405);

        $this->assertModelExists($subject);
    }

    public function test_non_admin_cannot_manage_subjects(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $subject = Subject::factory()->create();

        $this->actingAs($user)->get('/subjects')->assertForbidden();
        $this->actingAs($user)->post('/subjects', [
            'code' => 'MTK',
            'name' => 'Matematika',
            'status' => 'active',
        ])->assertForbidden();
        $this->actingAs($user)->post("/subjects/{$subject->id}/archive")->assertForbidden();
    }
}
