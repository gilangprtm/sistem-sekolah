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
        $this->assertStringContainsString('href="/students/cards"', $cardSource);
        $this->assertStringContainsString('downloadCardImage', $cardSource);
        $this->assertStringContainsString('link.download = fileNameForStudent(student.full_name)', $cardSource);
        $this->assertStringContainsString('document.body.appendChild(link)', $cardSource);
        $this->assertStringContainsString('canvas.toBlob', $cardSource);
        $this->assertStringContainsString('toast.error', $cardSource);
        $this->assertStringContainsString('const dataUrl = await imageDataUrl(source)', $cardSource);
        $this->assertStringContainsString('context.drawImage(logo', $cardSource);
        $this->assertStringContainsString("canvas.toBlob((blob) =>", $cardSource);
        $this->assertStringNotContainsString('foreignObject', $cardSource);
        $this->assertStringNotContainsString('image.remove()', $cardSource);
        $this->assertStringNotContainsString('window.print()', $cardSource);
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

    public function test_kesiswaan_can_view_student_cards_without_master_student_access(): void
    {
        $kesiswaan = User::factory()->create();
        $kesiswaan->assignRole('Kesiswaan');
        $student = Student::factory()->create();

        $this->actingAs($kesiswaan)->get('/students/cards')->assertOk();
        $this->actingAs($kesiswaan)->get("/students/cards/{$student->id}")->assertOk();
        $this->actingAs($kesiswaan)->get('/students')->assertForbidden();
        $this->actingAs($kesiswaan)->get('/students/create')->assertForbidden();
        $this->actingAs($kesiswaan)->get('/students/cards/print')->assertForbidden();
        $this->assertTrue($kesiswaan->can('student.card.view'));
        $this->assertFalse($kesiswaan->can('student.card.print'));
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
