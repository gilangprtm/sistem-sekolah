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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherHomeroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/guru/wali-kelas')->assertRedirect(route('login'));
    }

    public function test_user_without_permission_cannot_view_homeroom(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/guru/wali-kelas')->assertForbidden();
    }

    public function test_teacher_can_view_only_their_active_homeroom_and_students(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create([
            'code' => 'VII-A',
            'name' => 'Kelas VII A',
            'grade_level' => 'VII',
            'parallel_code' => 'A',
        ]);
        HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);
        $budi = Student::factory()->create(['nis' => '002', 'full_name' => 'Budi']);
        $ani = Student::factory()->create([
            'nis' => '001',
            'nisn' => '0099887766',
            'tahun_angkatan' => 2026,
            'full_name' => 'Ani',
            'gender' => 'P',
            'birth_place' => 'Denpasar',
            'birth_date' => '2013-01-02',
            'address' => 'Jalan Sekolah',
            'phone' => '081234567890',
            'mother_name' => 'Ibu Ani',
            'father_name' => 'Ayah Ani',
            'mother_occupation' => 'Guru',
            'father_occupation' => 'Wiraswasta',
            'parent_phone' => '+62 812-2222-3333',
        ]);
        $inactiveStudent = Student::factory()->create(['nis' => '003', 'full_name' => 'Cici']);

        foreach ([$budi, $ani] as $student) {
            StudentPlacement::query()->create([
                'academic_year_id' => $year->id,
                'rombel_id' => $rombel->id,
                'student_id' => $student->id,
                'status' => 'active',
            ]);
        }
        StudentPlacement::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $inactiveStudent->id,
            'status' => 'ended',
        ]);

        $this->actingAs($user)
            ->get('/guru/wali-kelas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('teacher/homeroom')
                ->where('academicYear.year', '2026/2027')
                ->where('homeroom.code', 'VII-A')
                ->where('homeroom.name', 'Kelas VII A')
                ->where('hasTeacherProfile', true)
                ->where('students.total', 2)
                ->where('students.current_page', 1)
                ->where('students.per_page', 10)
                ->has('students.data', 2)
                ->where('students.data.0.full_name', 'Ani')
                ->where('students.data.0.nis', '001')
                ->where('students.data.0.nisn', '0099887766')
                ->where('students.data.0.gender', 'P')
                ->where('students.data.0.phone', '081234567890')
                ->where('students.data.0.parent_phone', '+62 812-2222-3333')
                ->where('students.data.0.mother_name', 'Ibu Ani')
                ->where('students.data.0.father_name', 'Ayah Ani')
                ->where('students.data.1.full_name', 'Budi')
                ->where('students.data.1.nis', '002')
                ->has('attendanceStudents', 2)
                ->where('attendanceStudents.0.full_name', 'Ani')
                ->where('attendanceStudents.1.full_name', 'Budi')
                ->where('attendanceStudents.0.status', 'A')
                ->where('attendanceStudents.0.version', 0)
                ->missing('attendanceStudents.0.address')
                ->where('attendanceStudents.0.phone', '081234567890')
                ->where('attendanceStudents.0.parent_phone', '+62 812-2222-3333'));
    }

    public function test_student_list_honors_the_requested_page_size(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        $teacher = Teacher::factory()->create(['user_id' => $user->id]);
        $rombel = Rombel::factory()->create();
        HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $teacher->id,
            'status' => 'active',
        ]);

        foreach (['Zaki', 'Ani', 'Made'] as $name) {
            $student = Student::factory()->create(['full_name' => $name]);
            StudentPlacement::query()->create([
                'academic_year_id' => $year->id,
                'rombel_id' => $rombel->id,
                'student_id' => $student->id,
                'status' => 'active',
            ]);
        }

        $this->actingAs($user)
            ->get('/guru/wali-kelas?per_page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('students.total', 3)
                ->where('students.per_page', 2)
                ->has('students.data', 2)
                ->has('attendanceStudents', 3)
                ->where('attendanceStudents.0.full_name', 'Ani')
                ->where('attendanceStudents.1.full_name', 'Made')
                ->where('attendanceStudents.2.full_name', 'Zaki')
                ->where('attendanceStudents.0.status', 'A'));
    }

    public function test_student_table_uses_the_shared_data_table_pattern(): void
    {
        $page = file_get_contents(resource_path('js/pages/teacher/homeroom.tsx'));

        $shell = file_get_contents(resource_path('js/components/data-table/data-table-shell.tsx'));

        $this->assertIsString($page);
        $this->assertIsString($shell);
        $this->assertStringContainsString("import DataTableShell from '@/components/data-table/data-table-shell';", $page);
        $this->assertStringContainsString('columns={columns}', $page);
        $this->assertStringContainsString('getRowKey={(student) => student.id}', $page);
        $this->assertStringContainsString('selection={{', $page);
        $this->assertStringContainsString('rowActions={{', $page);
        $this->assertStringContainsString('pagination={{', $page);
        $this->assertStringContainsString("import DataTableRowActions from '@/components/data-table/data-table-row-actions';", $shell);
        $this->assertStringContainsString('<DataTableRowActions', $shell);
        $this->assertStringContainsString('<DropdownMenuItem onClick={onView}>', $page);
        $this->assertStringNotContainsString("import {\n    Table,", $page);
        $this->assertStringContainsString('Hapus pilihan', $shell);
        $this->assertStringContainsString("selectAllLabel: 'Pilih semua siswa'", $page);
        $this->assertStringContainsString('Aksi', $page);
        $this->assertStringContainsString('Lihat', $page);
        $this->assertStringContainsString('Hub Siswa', $page);
        $this->assertStringContainsString('Hub Ortu', $page);
        $this->assertStringNotContainsString('MessageCircle', $page);
        $this->assertStringNotContainsString('UsersRound', $page);
        $this->assertStringNotContainsString('Eye', $page);
        $this->assertStringContainsString('whatsappUrl(student.phone)', $page);
        $this->assertStringContainsString('whatsappUrl(student.parent_phone)', $page);
        $this->assertStringContainsString('https://wa.me/', $page);
        $this->assertStringContainsString('Detail Siswa', $page);
        $this->assertStringContainsString('size="large"', $page);
        $this->assertStringContainsString('Profil siswa', $page);
        $this->assertStringContainsString('formatStudentDate(student.birth_date)', $page);
        $this->assertStringContainsString('return `${dayNumber} ${monthName} ${year}`;', $page);
        $this->assertStringContainsString('Informasi orang tua', $page);
    }

    public function test_homeroom_dialogs_use_the_responsive_modal_shell(): void
    {
        $page = file_get_contents(resource_path('js/pages/teacher/homeroom.tsx'));
        $shell = file_get_contents(resource_path('js/components/ui/modal-shell.tsx'));

        $this->assertIsString($page);
        $this->assertIsString($shell);
        $this->assertStringContainsString("import ModalShell from '@/components/ui/modal-shell';", $page);
        $this->assertSame(1, substr_count($page, '<ModalShell size="small">'));
        $attendance = file_get_contents(resource_path('js/components/teachers/student-attendance.tsx'));
        $this->assertStringContainsString('<ModalShell size="small">', $attendance);
        $this->assertSame(1, substr_count($page, 'size="large"'));
        $this->assertStringNotContainsString('<DialogContent', $page);
        $this->assertStringContainsString("size?: 'small' | 'medium' | 'large' | 'extra-large';", $shell);
        $this->assertStringContainsString("small: 'lg:max-w-sm'", $shell);
        $this->assertStringContainsString("medium: 'lg:max-w-[35vw]'", $shell);
        $this->assertStringContainsString("large: 'lg:max-w-[60vw]'", $shell);
        $this->assertStringContainsString("'extra-large': 'lg:max-w-[85vw]'", $shell);
        $this->assertStringContainsString('sm:max-w-[calc(100%-2rem)]', $shell);
        $this->assertStringContainsString('max-h-[calc(100dvh-2rem)] overflow-y-auto', $shell);
    }

    public function test_page_uses_line_tabs_for_homeroom_sections(): void
    {
        $page = file_get_contents(resource_path('js/pages/teacher/homeroom.tsx'));

        $this->assertIsString($page);
        $this->assertStringContainsString('defaultValue="data-siswa"', $page);
        $this->assertStringContainsString('variant="line"', $page);
        $this->assertStringContainsString('className="h-11 w-full justify-start overflow-x-auto overflow-y-hidden"', $page);
        $this->assertStringContainsString('Data Siswa', $page);
        $this->assertStringContainsString('Tempat Duduk', $page);
        $this->assertStringContainsString('Absen Hari Ini', $page);
        $this->assertStringContainsString('<TabsContent value="data-siswa">', $page);
        $this->assertStringContainsString('<TabsContent value="tempat-duduk">', $page);
        $this->assertMatchesRegularExpression('/<TabsContent\s+value="absen-hari-ini"/', $page);
        $this->assertStringContainsString('<StudentAttendance', $page);
        $attendance = file_get_contents(resource_path('js/components/teachers/student-attendance.tsx'));
        $this->assertIsString($attendance);
        $this->assertStringContainsString('<DataTableShell', $attendance);
        $this->assertStringContainsString('<DialogTitle>Absen Siswa</DialogTitle>', $attendance);
        $this->assertStringContainsString('searchPlaceholder="Cari keterangan absen…"', $attendance);
        foreach (['H' => 'Hadir', 'I' => 'Izin', 'S' => 'Sakit', 'D' => 'Dispensasi', 'A' => 'Alpa'] as $code => $label) {
            $this->assertStringContainsString("{ value: '{$code}', label: '{$label}' }", $attendance);
        }
        $this->assertStringNotContainsString('attendancePreview', $page);
        $this->assertStringNotContainsString('pratinjau', $page);
        $this->assertStringNotContainsString('Pratinjau tampilan · seluruh siswa diurutkan', $page);
        $this->assertStringNotContainsString('statuses.map(', $page);
    }

    public function test_teacher_cannot_see_another_teachers_homeroom(): void
    {
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $user = User::factory()->create();
        $user->assignRole('Guru');
        Teacher::factory()->create(['user_id' => $user->id]);
        $otherTeacher = Teacher::factory()->create();
        $rombel = Rombel::factory()->create(['code' => 'VII-B', 'name' => 'Kelas VII B']);
        HomeroomAssignment::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'teacher_id' => $otherTeacher->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/guru/wali-kelas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('academicYear.year', '2026/2027')
                ->where('homeroom', null)
                ->where('attendanceStudents', null)
                ->where('students', null));
    }

    public function test_page_handles_missing_teacher_profile_and_active_year(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Guru');

        $this->actingAs($user)
            ->get('/guru/wali-kelas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('academicYear', null)
                ->where('homeroom', null)
                ->where('students', null)
                ->where('attendanceStudents', null)
                ->where('hasTeacherProfile', false));
    }
}
