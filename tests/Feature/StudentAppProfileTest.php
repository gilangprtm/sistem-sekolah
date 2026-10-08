<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAppProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_student_can_view_profile_with_linked_student_data(): void
    {
        $user = User::factory()->create(['name' => 'Akun Siswa']);
        $user->assignRole('Siswa');
        Student::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Terhubung',
            'nis' => 'NIS-001',
            'tahun_angkatan' => 2025,
            'birth_place' => 'Denpasar',
            'birth_date' => '2012-05-10',
            'address' => 'Jl. Pendidikan',
            'gender' => 'P',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/profile')
                ->where('user.name', 'Akun Siswa')
                ->where('user.email', $user->email)
                ->where('student.full_name', 'Siswa Terhubung')
                ->where('student.nis', 'NIS-001')
                ->where('student.photo_url', null)
                ->where('student.birth_date', '2012-05-10T00:00:00.000000Z'));
    }

    public function test_student_can_update_own_photo_and_old_photo_is_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'photo_path' => 'students/1/old.jpg',
        ]);
        Storage::disk('public')->put($student->photo_path, 'old-image');

        $response = $this->actingAs($user)->post(route('student-app.profile.photo'), [
            'photo' => UploadedFile::fake()->create('new-photo.jpg', 100, 'image/jpeg'),
        ]);

        $student->refresh();

        $response->assertRedirect()
            ->assertSessionHas('success', 'Foto profil berhasil diperbarui.');
        $this->assertNotSame('students/1/old.jpg', $student->photo_path);
        $this->assertTrue(Storage::disk('public')->exists($student->photo_path));
        $this->assertFalse(Storage::disk('public')->exists('students/1/old.jpg'));
    }

    public function test_student_without_linked_profile_cannot_update_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.jpg', 100, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');
    }

    public function test_student_photo_update_is_limited_to_images_of_one_megabyte(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('photo');

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.jpg', 1025, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($student->fresh()->photo_path);
    }

    public function test_student_profile_is_role_protected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'))
            ->assertForbidden();
    }

    public function test_student_profile_exposes_linked_photo_url_without_storage_path(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'photo_path' => 'students/1/profile.jpg',
        ]);
        Storage::disk('public')->put($student->photo_path, 'profile-image');

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertInertia(fn ($page) => $page
                ->where('student.photo_url', Storage::disk('public')->url($student->photo_path))
                ->missing('student.photo_path'));
    }

    public function test_guest_cannot_update_student_photo(): void
    {
        $this->post(route('student-app.profile.photo'))
            ->assertRedirect(route('login'));
    }

    public function test_student_cannot_update_another_students_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create();

        $this->actingAs($user)
            ->post(route('student-app.profile.photo'), [
                'photo' => UploadedFile::fake()->create('profile.jpg', 100, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertNull($student->fresh()->photo_path);
    }

    public function test_student_without_linked_profile_can_view_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->get(route('student-app.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/profile')
                ->where('user.email', $user->email)
                ->where('student', null));
    }
}
