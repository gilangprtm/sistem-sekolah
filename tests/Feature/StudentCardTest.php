<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_authorized_user_can_view_student_card_with_connected_account_qr_and_active_placement(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $studentUser = User::factory()->create(['email' => 'siswa@example.test']);
        $studentUser->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'full_name' => 'Siswa Terhubung',
            'nis' => 'NIS-001',
            'nisn' => 'NISN-001',
            'gender' => 'L',
            'birth_place' => 'Denpasar',
            'birth_date' => '2012-05-10',
            'address' => 'Jl. Pendidikan 17',
        ]);
        $year = AcademicYear::query()->create(['year' => '2026/2027', 'status' => 'active']);
        $rombel = Rombel::factory()->create(['name' => 'VII-A', 'status' => 'active']);
        StudentPlacement::query()->create([
            'academic_year_id' => $year->id,
            'rombel_id' => $rombel->id,
            'student_id' => $student->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get("/students/cards/{$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/card')
                ->where('student.full_name', 'Siswa Terhubung')
                ->where('student.nis', 'NIS-001')
                ->where('student.nisn', 'NISN-001')
                ->where('student.gender', 'L')
                ->where('student.birth_place', 'Denpasar')
                ->where('placement.academic_year', '2026/2027')
                ->where('placement.rombel', 'VII-A')
                ->where('qrPayload', 'siswa@example.test')
                ->where('qrCode', fn ($qrCode): bool => is_string($qrCode) && str_starts_with($qrCode, 'data:image/svg+xml;base64,')));
    }

    public function test_student_card_exposes_official_photo_url_without_exposing_storage_path(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $student = Student::factory()->create([
            'photo_path' => 'students/1/photo.jpg',
        ]);
        Storage::disk('public')->put($student->photo_path, 'fake-image');

        $this->actingAs($admin)
            ->get("/students/cards/{$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('student.photo_url', Storage::disk('public')->url($student->photo_path))
                ->missing('student.photo_path'));
    }

    public function test_student_photo_is_replaced_and_old_file_is_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $student = Student::factory()->create(['photo_path' => 'students/old/photo.jpg']);
        Storage::disk('public')->put($student->photo_path, 'old-image');

        $this->actingAs($admin)
            ->post("/students/{$student->id}", [
                '_method' => 'patch',
                'full_name' => $student->full_name,
                'status' => $student->status,
                'photo' => UploadedFile::fake()->create('new-photo.jpg', 100, 'image/jpeg'),
            ])
            ->assertRedirect('/students');

        $student->refresh();
        $this->assertNotSame('students/old/photo.jpg', $student->photo_path);
        $this->assertFalse(Storage::disk('public')->exists('students/old/photo.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($student->photo_path));
    }

    public function test_student_photo_can_be_removed_explicitly(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $student = Student::factory()->create(['photo_path' => 'students/remove/photo.jpg']);
        Storage::disk('public')->put($student->photo_path, 'photo');

        $this->actingAs($admin)
            ->post("/students/{$student->id}", [
                '_method' => 'patch',
                'full_name' => $student->full_name,
                'status' => $student->status,
                'remove_photo' => true,
            ])
            ->assertRedirect('/students');

        $student->refresh();
        $this->assertNull($student->photo_path);
        $this->assertFalse(Storage::disk('public')->exists('students/remove/photo.jpg'));
    }

    public function test_student_card_navigation_is_available_only_from_kartu_pelajar_surface(): void
    {
        $masterSource = file_get_contents(resource_path('js/pages/students/index.tsx'));
        $cardsSource = file_get_contents(resource_path('js/pages/students/cards.tsx'));
        $cardSource = file_get_contents(resource_path('js/pages/students/card.tsx'));

        $this->assertIsString($masterSource);
        $this->assertIsString($cardsSource);
        $this->assertIsString($cardSource);
        $this->assertStringNotContainsString('Kartu Pelajar', $masterSource);
        $this->assertStringContainsString('href={`/students/cards/${student.id}`}', $cardsSource);
        $this->assertStringContainsString("import DataTableShell from '@/components/data-table/data-table-shell';", $cardsSource);
        $this->assertStringContainsString("import type { DataTableColumn } from '@/components/data-table/data-table-shell';", $cardsSource);
        $this->assertStringContainsString('columns={columns}', $cardsSource);
        $this->assertStringContainsString('getRowKey={(student) => student.id}', $cardsSource);
        $this->assertStringContainsString('selection={{', $cardsSource);
        $this->assertStringContainsString('rowActions={{', $cardsSource);
        $this->assertStringContainsString("variant: 'custom'", $cardsSource);
        $this->assertStringContainsString('pagination={{', $cardsSource);
        $this->assertStringContainsString("selectAllLabel: 'Pilih semua siswa'", $cardsSource);
        $this->assertStringContainsString('Hapus pilihan', file_get_contents(resource_path('js/components/data-table/data-table-shell.tsx')));
        $this->assertStringNotContainsString("import { Checkbox } from '@/components/ui/checkbox';", $cardsSource);
        $this->assertStringNotContainsString("import DataTableToolbar from '@/components/data-table/data-table-toolbar';", $cardsSource);
        $this->assertStringNotContainsString('<TableHead>', $cardsSource);
        $this->assertStringContainsString("import SecondaryPageShell from '@/components/admin/secondary-page-shell';", $cardSource);
        $this->assertStringContainsString('backHref="/students/cards"', $cardSource);
        $this->assertStringContainsString('backLabel="Kembali ke daftar kartu pelajar"', $cardSource);
        $this->assertStringContainsString('actions={', $cardSource);
        $this->assertStringContainsString('renderStudentCardPng', $cardSource);
        $this->assertStringContainsString('saveStudentCardBlob', $cardSource);
        $this->assertStringContainsString('saveStudentCardBlob', $cardSource);
        $this->assertStringContainsString('renderStudentCardPng', $cardSource);
        $this->assertStringContainsString('toast.error', $cardSource);
        $visualSource = file_get_contents(resource_path('js/components/student-card-visual.tsx'));
        $this->assertIsString($visualSource);
        $this->assertStringContainsString('CARD_BACKGROUND', $visualSource);
        $this->assertStringContainsString("import { StudentCardVisual } from '@/components/student-card-visual';", $cardSource);
        $visualSource = file_get_contents(resource_path('js/components/student-card-visual.tsx'));
        $this->assertIsString($visualSource);
        $this->assertStringContainsString('backgroundImage: `url(${CARD_BACKGROUND})`', $visualSource);
        $this->assertStringContainsString('KARTU PELAJAR SISWA', $visualSource);
        $this->assertStringContainsString('J. Kelamin', $visualSource);
        $this->assertStringContainsString('items-center text-left', $visualSource);
        $this->assertStringContainsString('whitespace-nowrap', $visualSource);
        $this->assertStringContainsString('T.T.L', $visualSource);
        $this->assertStringContainsString('Alamat', $visualSource);
        $this->assertStringContainsString('wrapStudentCardAddress(student.address)', $visualSource);
        $this->assertStringContainsString('whitespace-pre-line', $visualSource);
        $this->assertStringContainsString('renderStudentCardPng', $cardSource);
        $this->assertStringContainsString('qrCode={qrCode}', $cardSource);
        $this->assertStringContainsString('formatStudentNumbers(', $visualSource);
        $this->assertStringContainsString('return `${nis?.trim() || \'-\'} / ${nisn?.trim() || \'-\'}`;', $visualSource);
        $this->assertStringNotContainsString('Nama Lengkap', $cardSource);
        $this->assertStringNotContainsString("label: 'NISN'", $cardSource);
        $this->assertStringContainsString('qrCode={qrCode}', $cardSource);
        $this->assertStringContainsString('qrCode={qrCode}', $cardSource);
        $this->assertStringNotContainsString('foreignObject', $cardSource);
        $this->assertStringNotContainsString('image.remove()', $cardSource);
        $this->assertStringNotContainsString('window.print()', $cardSource);
    }

    public function test_student_card_renders_connected_account_qr_code_contract(): void
    {
        $cardSource = file_get_contents(resource_path('js/pages/students/card.tsx'));

        $this->assertIsString($cardSource);
        $visualSource = file_get_contents(resource_path('js/components/student-card-visual.tsx'));
        $this->assertIsString($visualSource);
        $this->assertStringContainsString('qrCode: string | null', $visualSource);
        $this->assertStringContainsString('if (qrCode !== null)', $visualSource);
        $this->assertStringContainsString('alt="QR Code akun siswa"', $visualSource);
        $this->assertStringContainsString("'Oktober'", $visualSource);
        $this->assertStringContainsString('formatBirthDate', $visualSource);
    }

    public function test_student_card_does_not_render_qr_without_connected_account(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $student = Student::factory()->create(['user_id' => null]);

        $this->actingAs($admin)
            ->get("/students/cards/{$student->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/card')
                ->where('qrPayload', null)
                ->where('qrCode', null));
    }

    public function test_student_cards_filters_by_tahun_angkatan_and_exposes_sorted_options(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['full_name' => 'Siswa 2024', 'tahun_angkatan' => 2024]);
        Student::factory()->create(['full_name' => 'Siswa 2025', 'tahun_angkatan' => 2025]);
        Student::factory()->create(['full_name' => 'Siswa Tanpa Angkatan', 'tahun_angkatan' => null]);

        $this->actingAs($admin)
            ->get('/students/cards?tahun_angkatan=2025&per_page=10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/cards')
                ->where('students.data', fn ($students) => $students->count() === 1 && $students->first()['full_name'] === 'Siswa 2025')
                ->where('filters.tahun_angkatan', '2025')
                ->where('filterOptions.angkatans', [2024, 2025]));
    }

    public function test_student_cards_ignores_invalid_or_unselected_tahun_angkatan_filter(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['full_name' => 'Siswa 2024', 'tahun_angkatan' => 2024]);
        Student::factory()->create(['full_name' => 'Siswa 2025', 'tahun_angkatan' => 2025]);

        $this->actingAs($admin)
            ->get('/students/cards?tahun_angkatan=not-a-year&per_page=10')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('students.total', 2)
                ->where('filterOptions.angkatans', [2024, 2025]));
    }

    public function test_student_cards_has_a_dedicated_read_only_route_and_table_surface(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['full_name' => 'Siswa Kartu']);

        $this->actingAs($admin)
            ->get('/students/cards?search=kartu&per_page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/cards')
                ->where('students.data.0.full_name', 'Siswa Kartu')
                ->where('students.per_page', 1)
                ->where('filters.search', 'kartu'));
    }

    public function test_student_cards_page_omits_status_filter_and_table_column(): void
    {
        $source = file_get_contents(resource_path('js/pages/students/cards.tsx'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString("id: 'status'", $source);
        $this->assertStringNotContainsString('<Label>Status</Label>', $source);
        $this->assertStringNotContainsString('filterOptions.statuses', $source);
        $this->assertStringNotContainsString('params.status', $source);
        $this->assertStringContainsString('params.gender = gender', $source);
        $this->assertStringContainsString('params.tahun_angkatan = tahunAngkatan', $source);
    }

    public function test_student_cards_print_uses_active_list_filters_and_card_download_surface(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['full_name' => 'Siswa Cetak 2024', 'tahun_angkatan' => 2024, 'gender' => 'L', 'status' => 'active']);
        Student::factory()->create(['full_name' => 'Siswa Cetak 2025', 'tahun_angkatan' => 2025, 'gender' => 'L', 'status' => 'active']);

        $this->actingAs($admin)
            ->get('/students/cards/print?search=Cetak&gender=L&status=active&tahun_angkatan=2025')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/cards-print')
                ->where('students.0.student.full_name', 'Siswa Cetak 2025')
                ->where('filters.search', 'Cetak')
                ->where('filters.tahun_angkatan', '2025'));

        $source = file_get_contents(resource_path('js/pages/students/cards-print.tsx'));
        $cardsSource = file_get_contents(resource_path('js/pages/students/cards.tsx'));
        $this->assertIsString($source);
        $this->assertIsString($cardsSource);
        $this->assertStringContainsString("import { StudentCardVisual } from '@/components/student-card-visual';", $source);
        $this->assertStringContainsString('StudentCardVisual', $source);
        $this->assertStringContainsString("header: 'Jenis Kelamin'", $cardsSource);
        $this->assertStringNotContainsString('<TableHead>Gender</TableHead>', $cardsSource);
        $this->assertStringContainsString('StudentCardVisual', $source);
        $visualSource = file_get_contents(resource_path('js/components/student-card-visual.tsx'));
        $this->assertIsString($visualSource);
        $this->assertStringContainsString('return `${nis?.trim() || \'-\'} / ${nisn?.trim() || \'-\'}`;', $visualSource);
        $this->assertStringContainsString('application/zip', $source);
        $this->assertStringContainsString('downloadCards', $source);
        $this->assertStringContainsString('Contoh kartu pelajar', $source);
        $this->assertStringContainsString('index === 0', $source);
        $this->assertStringContainsString('aria-hidden={index > 0 ? true : undefined}', $source);
        $this->assertStringContainsString("import { StudentCardVisual } from '@/components/student-card-visual';", $source);
        $this->assertStringContainsString('aria-live="polite"', $source);
        $this->assertStringContainsString('Menyiapkan kartu pelajar', $source);
        $this->assertStringContainsString('setIsDownloading(false)', $source);
        $this->assertStringContainsString('toast.error', $source);
        $this->assertStringNotContainsString('window.print()', $source);
    }

    public function test_student_cards_print_has_a_dedicated_print_surface_with_all_students(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Student::factory()->create(['full_name' => 'Siswa Cetak']);

        $this->actingAs($admin)
            ->get('/students/cards/print')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/cards-print')
                ->where('students.0.student.full_name', 'Siswa Cetak')
                ->where('students.0.qrCode', null));
    }

    public function test_kesiswaan_can_view_and_download_student_cards_without_master_student_access(): void
    {
        $kesiswaan = User::factory()->create();
        $kesiswaan->assignRole('Kesiswaan');
        $student = Student::factory()->create();

        $this->actingAs($kesiswaan)->get('/students/cards')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.permissions', fn ($permissions): bool => $permissions->contains('student.card.print')));
        $this->actingAs($kesiswaan)->get("/students/cards/{$student->id}")->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.permissions', fn ($permissions): bool => $permissions->contains('student.card.print')));
        $this->actingAs($kesiswaan)->get('/students')->assertForbidden();
        $this->actingAs($kesiswaan)->get('/students/create')->assertForbidden();
        $this->actingAs($kesiswaan)->get('/students/cards/print')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('students/cards-print')
                ->where('students.0.student.id', $student->id));
        $this->assertTrue($kesiswaan->can('student.card.view'));
        $this->assertTrue($kesiswaan->can('student.card.print'));
        $this->assertFalse($kesiswaan->can('student.view'));
        $this->assertFalse($kesiswaan->can('student.create'));
        $this->assertFalse($kesiswaan->can('student.update'));
        $this->assertFalse($kesiswaan->can('student.delete'));
    }

    public function test_card_routes_are_separate_from_master_student_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('student.card.view');
        $student = Student::factory()->create();

        $this->actingAs($viewer)->get('/students/cards')->assertOk();
        $this->actingAs($viewer)->get("/students/cards/{$student->id}")->assertOk();
        $this->actingAs($viewer)->get('/students')->assertForbidden();
    }

    public function test_student_card_print_requires_print_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('student.card.view');
        $printer = User::factory()->create();
        $printer->givePermissionTo('student.card.print');

        $this->actingAs($viewer)->get('/students/cards/print')->assertForbidden();
        $this->actingAs($printer)->get('/students/cards/print')->assertOk();
    }
}
