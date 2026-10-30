<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomSeat;
use App\Models\Rombel;
use App\Models\RombelPeriodUsage;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\UasLink;
use App\Models\User;
use App\Models\YearClass;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicYearDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_delete_unused_inactive_academic_year_and_its_periods(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $period = $year->periods()->create([
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->delete("/kurikulum/academic-years/{$year->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('m_academic_years', ['id' => $year->id]);
        $this->assertDatabaseMissing('m_academic_periods', ['id' => $period->id]);
    }

    public function test_active_academic_year_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'active']);

        $this->actingAs($admin)
            ->delete("/kurikulum/academic-years/{$year->id}")
            ->assertSessionHasErrors([
                'year' => 'Tahun Ajaran aktif tidak dapat dihapus. Nonaktifkan terlebih dahulu.',
            ]);

        $this->assertDatabaseHas('m_academic_years', ['id' => $year->id, 'status' => 'active']);
    }

    public function test_academic_year_with_referencing_academic_data_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $period = AcademicPeriod::query()->create([
            'academic_year_id' => $year->id,
            'code' => 'ganjil',
            'name' => 'Semester Ganjil',
            'status' => 'inactive',
        ]);
        $rombel = Rombel::factory()->create();
        RombelPeriodUsage::query()->create([
            'academic_period_id' => $period->id,
            'rombel_id' => $rombel->id,
        ]);

        $this->actingAs($admin)
            ->delete("/kurikulum/academic-years/{$year->id}")
            ->assertSessionHasErrors([
                'year' => 'Tahun Ajaran sudah digunakan oleh data akademik dan tidak dapat dihapus.',
            ]);

        $this->assertDatabaseHas('m_academic_years', ['id' => $year->id]);
        $this->assertDatabaseHas('m_academic_periods', ['id' => $period->id]);
    }

    /** @return array<string, array{string}> */
    public static function cascadingReferences(): array
    {
        return [
            'class' => ['class'],
            'student placement' => ['placement'],
            'homeroom assignment' => ['assignment'],
            'seat' => ['seat'],
            'exam' => ['exam'],
        ];
    }

    #[DataProvider('cascadingReferences')]
    public function test_year_deletion_preserves_data_that_would_otherwise_cascade(string $reference): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Kurikulum');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);
        $rombel = Rombel::factory()->create();
        $data = ['academic_year_id' => $year->id, 'rombel_id' => $rombel->id];
        $record = match ($reference) {
            'class' => YearClass::query()->create($data),
            'placement' => StudentPlacement::query()->create($data + ['student_id' => Student::factory()->create()->id]),
            'assignment' => HomeroomAssignment::query()->create($data + ['teacher_id' => Teacher::factory()->create()->id]),
            'seat' => HomeroomSeat::query()->create($data + ['seat_number' => 1]),
            'exam' => UasLink::query()->create([
                'academic_year_id' => $year->id,
                'academic_period_id' => $year->periods()->create([
                    'code' => 'ganjil', 'name' => 'Semester Ganjil', 'status' => 'inactive',
                ])->id,
                'subject_id' => Subject::factory()->create()->id,
                'link' => 'https://example.com/exam',
            ]),
        };

        $this->actingAs($operator)
            ->delete("/kurikulum/academic-years/{$year->id}")
            ->assertSessionHasErrors([
                'year' => 'Tahun Ajaran sudah digunakan oleh data akademik dan tidak dapat dihapus.',
            ]);

        $this->assertDatabaseHas('m_academic_years', ['id' => $year->id]);
        $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
    }

    public function test_guest_cannot_delete_academic_year(): void
    {
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);

        $this->delete("/kurikulum/academic-years/{$year->id}")->assertRedirectToRoute('login');

        $this->assertDatabaseHas('m_academic_years', ['id' => $year->id]);
    }

    public function test_curriculum_role_can_delete_through_legacy_route(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('Kurikulum');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);

        $this->actingAs($operator)->delete("/academic-years/{$year->id}")->assertRedirect();

        $this->assertDatabaseMissing('m_academic_years', ['id' => $year->id]);
    }

    public function test_delete_action_visibility_follows_permission(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo('curriculum.view');

        $this->actingAs($operator)->get('/kurikulum/academic-years')
            ->assertInertia(fn ($page) => $page->where('canDelete', false));

        $operator->givePermissionTo('curriculum.delete');

        $this->get('/kurikulum/academic-years')
            ->assertInertia(fn ($page) => $page->where('canDelete', true));
    }

    public function test_year_deletion_requires_curriculum_delete_permission(): void
    {
        $operator = User::factory()->create();
        $operator->givePermissionTo('curriculum.view');
        $year = AcademicYear::query()->create(['year' => '2025/2026', 'status' => 'inactive']);

        $this->actingAs($operator)
            ->delete("/kurikulum/academic-years/{$year->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('m_academic_years', ['id' => $year->id]);
    }
}
