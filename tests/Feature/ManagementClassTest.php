<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementClassTest extends TestCase
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

    private function year(): AcademicYear
    {
        return AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
    }

    private function createClass(User $admin, AcademicYear $year, Rombel $rombel, Teacher $teacher): void
    {
        $this->actingAs($admin)->post('/management-class/classes', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id,
        ])->assertRedirect();
    }

    public function test_admin_can_view_management_class_with_year_classes(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['grade_level' => 'VII', 'status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/management-class')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('management-class/index')
                ->where('years.0.id', $year->id)
                ->has('classes.data', 0));

        $this->actingAs($admin)
            ->post('/management-class/classes', [
                'academic_year_id' => $year->id,
                'rombel_id' => $rombel->id,
                'teacher_id' => $teacher->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get('/management-class?academic_year_id='.$year->id)
            ->assertInertia(fn ($page) => $page
                ->has('classes.data', 1)
                ->where('classes.data.0.rombel_id', $rombel->id));
    }

    public function test_year_class_rejects_duplicate_and_inactive_rombel(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();
        $payload = ['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'teacher_id' => $teacher->id];

        $this->actingAs($admin)->post('/management-class/classes', $payload)->assertRedirect();
        $this->actingAs($admin)
            ->post('/management-class/classes', $payload)
            ->assertSessionHasErrors('rombel_id', 'Rombel tersebut sudah digunakan pada Tahun Ajaran ini.');

        $rombel->update(['status' => 'inactive']);
        $this->actingAs($admin)->post('/management-class/classes', $payload)->assertSessionHasErrors('rombel_id');
    }

    public function test_management_class_table_is_viewer_only_and_manage_page_exposes_mutations(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/management-class')
            ->assertInertia(fn ($page) => $page
                ->component('management-class/index')
                ->missing('availableRombels')
                ->missing('students')
                ->missing('teachers'));

        $this->actingAs($admin)
            ->get('/management-class/manage')
            ->assertOk();
    }

    public function test_manage_page_allows_new_class_flow_without_selected_class_prop(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();

        $response = $this->actingAs($admin)
            ->get('/management-class/manage?academic_year_id='.$year->id.'&rombel_id='.$rombel->id);

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('management-class/manage')
                ->where('selectedClass', null)
                ->where('selectedYear.id', $year->id)
                ->where('selectedRombelId', $rombel->id)
                ->where('teachers.0.id', $teacher->id));

        $source = file_get_contents(resource_path('js/pages/management-class/manage.tsx'));
        $this->assertStringContainsString('!selectedYear', $source);
        $this->assertStringContainsString('!rombelId', $source);
        $this->assertStringContainsString('!teacherId', $source);
        $this->assertStringNotContainsString('!selectedClass || !teacherId', $source);
    }

    public function test_manage_student_lookup_is_bounded_and_searchable(): void
    {
        $year = $this->year();
        $admin = $this->admin();
        Student::factory()->count(3)->create(['status' => 'active']);
        Student::factory()->create([
            'status' => 'inactive',
            'full_name' => 'Inactive Student',
            'nis' => 'INACTIVE-001',
        ]);
        Student::factory()->create([
            'status' => 'active',
            'full_name' => 'Target Student',
            'nis' => 'TARGET-001',
        ]);

        $response = $this->actingAs($admin)
            ->get('/management-class/manage?academic_year_id='.$year->id.'&lookup_search=TARGET&lookup_per_page=1');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('management-class/manage')
                ->missing('students')
                ->where('lookupFilters.search', 'TARGET')
                ->where('studentLookup.total', 1)
                ->where('studentLookup.per_page', 1)
                ->where('studentLookup.data.0.full_name', 'Target Student'));

        $source = file_get_contents(resource_path('js/pages/management-class/manage.tsx'));
        $this->assertStringContainsString('const confirmStudents = () =>', $source);
        $this->assertStringContainsString('setSelectedStudentIds(lookupSelectedIds)', $source);
        $this->assertStringContainsString(
            "{ preserveState: true, preserveScroll: true, replace: true }",
            $source,
        );
        $this->assertStringNotContainsString('useEffect', $source);
    }

    public function test_class_promotion_moves_all_students_from_vii_to_viii_and_viii_to_ix_by_parallel_code(): void
    {
        $year = $this->year();
        $vii = Rombel::factory()->create(['code' => 'VII-A', 'grade_level' => 'VII', 'parallel_code' => 'A', 'status' => 'active']);
        $viii = Rombel::factory()->create(['code' => 'VIII-A', 'grade_level' => 'VIII', 'parallel_code' => 'A', 'status' => 'active']);
        $ix = Rombel::factory()->create(['code' => 'IX-A', 'grade_level' => 'IX', 'parallel_code' => 'A', 'status' => 'active']);
        $students = Student::factory()->count(2)->create(['status' => 'active']);
        $admin = $this->admin();
        $teachers = Teacher::factory()->count(3)->create(['status' => 'active', 'staff_type' => 'guru']);
        foreach ([$vii, $viii, $ix] as $index => $rombel) {
            $this->createClass($admin, $year, $rombel, $teachers[$index]);
        }

        foreach ($students as $student) {
            $this->actingAs($admin)->post('/management-class/students', [
                'academic_year_id' => $year->id,
                'rombel_id' => $vii->id,
                'student_id' => $student->id,
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post('/management-class/classes/promote', [
            'academic_year_id' => $year->id,
            'source_rombel_id' => $vii->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('tr_curriculum_student_placements', 4);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 4);
        foreach ($students as $student) {
            $this->assertDatabaseHas('tr_curriculum_student_placements', ['academic_year_id' => $year->id, 'student_id' => $student->id, 'rombel_id' => $vii->id, 'status' => 'completed']);
            $this->assertDatabaseHas('tr_curriculum_student_placements', ['academic_year_id' => $year->id, 'student_id' => $student->id, 'rombel_id' => $viii->id, 'status' => 'active']);
        }

        $this->actingAs($admin)->post('/management-class/classes/promote', [
            'academic_year_id' => $year->id,
            'source_rombel_id' => $viii->id,
        ])->assertRedirect();

        foreach ($students as $student) {
            $this->assertDatabaseHas('tr_curriculum_student_placements', ['academic_year_id' => $year->id, 'student_id' => $student->id, 'rombel_id' => $viii->id, 'status' => 'completed']);
            $this->assertDatabaseHas('tr_curriculum_student_placements', ['academic_year_id' => $year->id, 'student_id' => $student->id, 'rombel_id' => $ix->id, 'status' => 'active']);
        }
    }

    public function test_class_promotion_requires_a_matching_next_grade_class(): void
    {
        $year = $this->year();
        $vii = Rombel::factory()->create(['grade_level' => 'VII', 'parallel_code' => 'A', 'status' => 'active']);
        $viii = Rombel::factory()->create(['grade_level' => 'VIII', 'parallel_code' => 'B', 'status' => 'active']);
        $student = Student::factory()->create(['status' => 'active']);
        $admin = $this->admin();
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $this->createClass($admin, $year, $vii, $teachers[0]);
        $this->createClass($admin, $year, $viii, $teachers[1]);
        $this->actingAs($admin)->post('/management-class/students', [
            'academic_year_id' => $year->id,
            'rombel_id' => $vii->id,
            'student_id' => $student->id,
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post('/management-class/classes/promote', ['academic_year_id' => $year->id, 'source_rombel_id' => $vii->id])
            ->assertSessionHasErrors('source_rombel_id');

        $this->assertDatabaseHas('tr_curriculum_student_placements', ['student_id' => $student->id, 'rombel_id' => $vii->id, 'status' => 'active']);
    }

    public function test_class_promotion_rejects_a_non_empty_target_without_partial_mutation(): void
    {
        $year = $this->year();
        $vii = Rombel::factory()->create(['grade_level' => 'VII', 'parallel_code' => 'A', 'status' => 'active']);
        $viii = Rombel::factory()->create(['grade_level' => 'VIII', 'parallel_code' => 'A', 'status' => 'active']);
        $sourceStudent = Student::factory()->create(['status' => 'active']);
        $targetStudent = Student::factory()->create(['status' => 'active']);
        $admin = $this->admin();
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $this->createClass($admin, $year, $vii, $teachers[0]);
        $this->createClass($admin, $year, $viii, $teachers[1]);

        foreach ([[$vii, $sourceStudent], [$viii, $targetStudent]] as [$rombel, $student]) {
            $this->actingAs($admin)->post('/management-class/students', [
                'academic_year_id' => $year->id,
                'rombel_id' => $rombel->id,
                'student_id' => $student->id,
            ])->assertRedirect();
        }

        $this->actingAs($admin)
            ->post('/management-class/classes/promote', [
                'academic_year_id' => $year->id,
                'source_rombel_id' => $vii->id,
            ])
            ->assertSessionHasErrors('source_rombel_id');

        $this->assertDatabaseHas('tr_curriculum_student_placements', ['student_id' => $sourceStudent->id, 'rombel_id' => $vii->id, 'status' => 'active']);
        $this->assertDatabaseHas('tr_curriculum_student_placements', ['student_id' => $targetStudent->id, 'rombel_id' => $viii->id, 'status' => 'active']);
        $this->assertDatabaseCount('tr_curriculum_student_placements', 2);
    }

    public function test_wali_assignment_replaces_class_wali_and_rejects_teacher_on_another_class(): void
    {
        $year = $this->year();
        $rombels = Rombel::factory()->count(2)->create(['status' => 'active']);
        $teachers = Teacher::factory()->count(3)->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();
        foreach ($rombels as $index => $rombel) {
            $this->createClass($admin, $year, $rombel, $teachers[$index]);
        }

        $assign = ['academic_year_id' => $year->id, 'rombel_id' => $rombels[0]->id, 'teacher_id' => $teachers[0]->id];
        $this->actingAs($admin)->post('/management-class/homerooms', $assign)->assertRedirect();
        $this->actingAs($admin)->post('/management-class/homerooms', [...$assign, 'teacher_id' => $teachers[2]->id])->assertRedirect();
        $this->actingAs($admin)->post('/management-class/homerooms', ['academic_year_id' => $year->id, 'rombel_id' => $rombels[1]->id, 'teacher_id' => $teachers[2]->id])->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseCount('tr_curriculum_homeroom_assignments', 4);
        $this->assertDatabaseHas('tr_curriculum_homeroom_assignments', ['academic_year_id' => $year->id, 'rombel_id' => $rombels[0]->id, 'teacher_id' => $teachers[2]->id, 'status' => 'active']);
        $this->assertDatabaseCount('tr_curriculum_homeroom_assignments', 4);
    }

    public function test_class_creation_requires_a_current_homeroom_teacher(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/management-class/classes', ['academic_year_id' => $year->id, 'rombel_id' => $rombel->id])
            ->assertSessionHasErrors('teacher_id');

        $this->assertDatabaseCount('tr_curriculum_year_classes', 0);
        $this->assertDatabaseCount('tr_curriculum_homeroom_assignments', 0);
    }

    public function test_management_class_reads_each_class_with_its_own_wali_and_member_count(): void
    {
        $year = $this->year();
        $rombels = Rombel::factory()->count(2)->create(['status' => 'active']);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $students = Student::factory()->count(2)->create(['status' => 'active']);
        $admin = $this->admin();

        foreach ($rombels as $index => $rombel) {
            $this->createClass($admin, $year, $rombel, $teachers[$index]);
            $this->actingAs($admin)->post('/management-class/students', [
                'academic_year_id' => $year->id,
                'rombel_id' => $rombel->id,
                'student_id' => $students[$index]->id,
            ])->assertRedirect();
        }

        $this->actingAs($admin)
            ->get('/management-class?academic_year_id='.$year->id)
            ->assertInertia(fn ($page) => $page
                ->where('classes.data.0.student_placements_count', 1)
                ->where('classes.data.0.homeroom_assignments.0.teacher.id', $teachers[0]->id)
                ->where('classes.data.1.student_placements_count', 1)
                ->where('classes.data.1.homeroom_assignments.0.teacher.id', $teachers[1]->id));
    }

    public function test_management_class_viewer_filters_by_year_and_paginates_classes(): void
    {
        $year = $this->year();
        $otherYear = AcademicYear::query()->create(['year' => '2027/2028', 'status' => 'active']);
        $firstMatchingRombel = Rombel::factory()->create(['code' => 'VII-A', 'name' => 'Alpha Satu', 'status' => 'active']);
        $secondMatchingRombel = Rombel::factory()->create(['code' => 'VII-B', 'name' => 'Alpha Dua', 'status' => 'active']);
        $otherYearRombel = Rombel::factory()->create(['code' => 'VIII-C', 'name' => 'Alpha Tiga', 'status' => 'active']);
        $teachers = Teacher::factory()->count(3)->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();

        $this->createClass($admin, $year, $firstMatchingRombel, $teachers[0]);
        $this->createClass($admin, $year, $secondMatchingRombel, $teachers[1]);
        $this->createClass($admin, $otherYear, $otherYearRombel, $teachers[2]);

        $this->actingAs($admin)
            ->get('/management-class?academic_year_id='.$year->id.'&search=Alpha&per_page=1&page=2')
            ->assertInertia(fn ($page) => $page
                ->where('selectedYear.id', $year->id)
                ->where('classes.total', 2)
                ->where('classes.per_page', 1)
                ->where('classes.current_page', 2)
                ->has('classes.data', 1)
                ->where('classes.data.0.rombel_id', $secondMatchingRombel->id));
    }

    public function test_database_invariants_reject_duplicate_active_student_and_homeroom_records(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $secondRombel = Rombel::factory()->create(['status' => 'active']);
        $student = Student::factory()->create(['status' => 'active']);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);

        StudentPlacement::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        try {
            StudentPlacement::query()->create([
                'academic_year_id' => $year->id,
                'rombel_id' => $secondRombel->id,
                'student_id' => $student->id,
                'status' => 'active',
            ]);
            $this->fail('Expected the active student placement unique index to reject a duplicate.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teachers[0]->id,
            'status' => 'active',
        ]);

        try {
            HomeroomAssignment::query()->create([
                'academic_year_id' => $year->id,
                'rombel_id' => $rombel->id,
                'teacher_id' => $teachers[1]->id,
                'status' => 'active',
            ]);
            $this->fail('Expected the active class homeroom unique index to reject a duplicate.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        try {
            HomeroomAssignment::query()->create([
                'academic_year_id' => $year->id,
                'rombel_id' => $secondRombel->id,
                'teacher_id' => $teachers[0]->id,
                'status' => 'active',
            ]);
            $this->fail('Expected the active teacher homeroom unique index to reject a duplicate.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_management_class_manage_page_uses_a_simple_inventory_style_form(): void
    {
        $source = file_get_contents(resource_path('js/pages/management-class/manage.tsx'));
        $table = file_get_contents(resource_path('js/pages/management-class/index.tsx'));

        $this->assertIsString($source);
        $this->assertIsString($table);
        $this->assertStringContainsString('Kembali ke daftar manajemen kelas', $source);
        $this->assertStringContainsString('Tahun Ajaran', $source);
        $this->assertStringContainsString('Cari Tahun Ajaran...', $source);
        $this->assertStringContainsString('Wali Kelas', $source);
        $this->assertStringContainsString('SearchableCombobox', $source);
        $this->assertStringContainsString('Pilih kelas dari master Rombel', $source);
        $this->assertStringContainsString('Cari kode atau nama Rombel...', $source);
        $this->assertStringContainsString('Cari nama wali kelas...', $source);
        $this->assertStringContainsString('Tambah Siswa', $source);
        $this->assertStringContainsString('Simpan Perubahan', $source);
        $this->assertStringNotContainsString('Promosikan Satu Kelas', $source);
        $this->assertStringNotContainsString('/management-class/classes/promote', $source);
        $this->assertStringNotContainsString('/management-class/classes/promote', $table);
    }

    public function test_manage_page_exposes_only_active_master_rombels_for_the_class_combobox(): void
    {
        $activeRombel = Rombel::factory()->create(['code' => 'VII-A', 'status' => 'active']);
        Rombel::factory()->create(['code' => 'VII-B', 'status' => 'inactive']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/management-class/manage')
            ->assertInertia(fn ($page) => $page
                ->where('availableRombels.0.id', $activeRombel->id)
                ->where('availableRombels.0.code', 'VII-A')
                ->has('availableRombels', 1));
    }

    public function test_manage_page_marks_a_rombel_that_is_already_registered_for_the_selected_year(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $student = Student::factory()->create(['status' => 'active']);
        $admin = $this->admin();
        $this->createClass($admin, $year, $rombel, $teacher);
        $this->actingAs($admin)->post('/management-class/students', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $student->id,
        ])->assertRedirect();
        $this->assertDatabaseHas('tr_curriculum_homeroom_assignments', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/management-class/manage?academic_year_id='.$year->id.'&rombel_id='.$rombel->id)
            ->assertInertia(fn ($page) => $page
                ->where('selectedRombelId', $rombel->id)
                ->where('registeredRombelIds.0', $rombel->id)
                ->where('selectedClass.rombel_id', $rombel->id)
                ->where('selectedClass.homeroom_assignments.0.teacher.id', $teacher->id)
                ->where('selectedStudents.0.id', $student->id));

        $source = file_get_contents(resource_path('js/pages/management-class/manage.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString('Sudah terdaftar', $source);
        $this->assertStringContainsString('Kelas ini sudah terdaftar', $source);
        $this->assertStringContainsString('yang dipilih', $source);
        $this->assertStringContainsString('useState', $source);
    }

    public function test_admin_can_update_class_wali_and_students_atomically(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $students = Student::factory()->count(2)->create(['status' => 'active']);
        $admin = $this->admin();
        $this->createClass($admin, $year, $rombel, $teachers[0]);
        $this->actingAs($admin)->post('/management-class/students', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $students[0]->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post('/management-class/classes/update', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teachers[1]->id,
            'student_ids' => [$students[1]->id],
        ])->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'success')
            ->assertSessionHas('inertia.flash_data.toast.message', 'Manajemen kelas berhasil diperbarui.');

        $source = file_get_contents(resource_path('js/pages/management-class/manage.tsx'));
        $this->assertIsString($source);
        $this->assertStringContainsString("import { useFlashToast } from '@/hooks/use-flash-toast';", $source);
        $this->assertStringContainsString('useFlashToast();', $source);

        $this->assertDatabaseHas('tr_curriculum_homeroom_assignments', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teachers[0]->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('tr_curriculum_homeroom_assignments', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teachers[1]->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tr_curriculum_student_placements', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $students[0]->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('tr_curriculum_student_placements', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $students[1]->id,
            'status' => 'active',
        ]);
    }

    public function test_class_update_rejects_student_already_assigned_elsewhere_without_partial_mutation(): void
    {
        $year = $this->year();
        $rombels = Rombel::factory()->count(2)->create(['status' => 'active']);
        $teachers = Teacher::factory()->count(2)->create(['status' => 'active', 'staff_type' => 'guru']);
        $student = Student::factory()->create(['status' => 'active']);
        $admin = $this->admin();
        $this->createClass($admin, $year, $rombels[0], $teachers[0]);
        $this->createClass($admin, $year, $rombels[1], $teachers[1]);
        $this->actingAs($admin)->post('/management-class/students', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombels[1]->id,
            'student_id' => $student->id,
        ])->assertRedirect();

        $this->actingAs($admin)->post('/management-class/classes/update', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombels[0]->id,
            'teacher_id' => $teachers[0]->id,
            'student_ids' => [$student->id],
        ])->assertSessionHasErrors('student_ids');

        $this->assertDatabaseMissing('tr_curriculum_student_placements', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombels[0]->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('tr_curriculum_student_placements', [
            'academic_year_id' => $year->id,
            'rombel_id' => $rombels[1]->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_management_class_viewer_exposes_edit_action_with_class_context(): void
    {
        $year = $this->year();
        $rombel = Rombel::factory()->create(['status' => 'active']);
        $teacher = Teacher::factory()->create(['status' => 'active', 'staff_type' => 'guru']);
        $admin = $this->admin();
        $this->createClass($admin, $year, $rombel, $teacher);

        $this->actingAs($admin)
            ->get('/management-class?academic_year_id='.$year->id)
            ->assertInertia(fn ($page) => $page
                ->component('management-class/index')
                ->where('classes.data.0.rombel_id', $rombel->id));

        $this->actingAs($admin)
            ->get('/management-class/manage?academic_year_id='.$year->id.'&rombel_id='.$rombel->id)
            ->assertInertia(fn ($page) => $page
                ->component('management-class/manage')
                ->where('selectedClass.rombel_id', $rombel->id)
                ->where('selectedYear.id', $year->id));

        $table = file_get_contents(resource_path('js/pages/management-class/index.tsx'));
        $this->assertIsString($table);
        $this->assertStringContainsString('<TableHead className="text-right">', $table);
        $this->assertStringContainsString('Aksi', $table);
        $this->assertStringContainsString('Edit', $table);
        $this->assertStringContainsString('/management-class/manage?academic_year_id=', $table);
        $this->assertStringContainsString('rombel_id=${item.rombel_id}', $table);
        $this->assertStringContainsString("import DataTableToolbar from '@/components/data-table/data-table-toolbar';", $table);
        $this->assertStringContainsString("import { Checkbox } from '@/components/ui/checkbox';", $table);
        $this->assertStringContainsString('<DataTableToolbar>', $table);
        $this->assertStringContainsString('setSelected([])', $table);
        $this->assertStringContainsString('aria-label="Pilih semua kelas"', $table);
        $this->assertStringContainsString('Belum ada data kelas.', $table);
    }

    public function test_management_class_mutations_require_curriculum_update_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $year = $this->year();
        $rombel = Rombel::factory()->create();
        $teacher = Teacher::factory()->create();

        $this->actingAs($user)->get('/management-class')->assertForbidden();
        $this->actingAs($user)->get('/management-class/manage')->assertForbidden();
        $this->actingAs($user)->post('/management-class/classes', ['academic_year_id' => $year->id, 'rombel_id' => $rombel->id, 'teacher_id' => $teacher->id])->assertForbidden();
        $this->actingAs($user)->post('/management-class/classes/promote', ['academic_year_id' => $year->id, 'source_rombel_id' => $rombel->id])->assertForbidden();
    }
}
