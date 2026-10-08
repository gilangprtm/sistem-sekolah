<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_student_dashboard_exposes_safe_student_photo_url(): void
    {
        Role::create(['name' => 'Siswa']);
        Storage::fake('public');
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Siswa Dashboard',
            'nis' => 'NIS-100',
            'photo_path' => 'students/1/profile.jpg',
        ]);
        Storage::disk('public')->put($student->photo_path, 'profile-image');

        $this->actingAs($user)
            ->get(route('student-app.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('student-app/dashboard')
                ->where('student.full_name', 'Siswa Dashboard')
                ->where('student.photo_url', Storage::disk('public')->url($student->photo_path))
                ->missing('student.photo_path'));
    }

    public function test_student_dashboard_defers_external_news_from_initial_shell(): void
    {
        Role::create(['name' => 'Siswa']);
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Http::fake();

        $response = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
            ])
            ->get(route('student-app.dashboard'));

        $response->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'student-app/dashboard')
            ->assertJsonMissingPath('props.news')
            ->assertJsonPath('deferredProps.default', ['news']);
        Http::assertNothingSent();
    }

    public function test_student_dashboard_news_request_preserves_normalization_and_cache(): void
    {
        Role::create(['name' => 'Siswa']);
        $user = User::factory()->create();
        $user->assignRole('Siswa');
        Http::fake([
            'https://smpn17denpasar.sch.id/api/public/berita' => Http::response([
                'data' => [[
                    'id' => 1,
                    'title' => 'Berita sekolah',
                    'url' => 'https://smpn17denpasar.sch.id/berita/1',
                    'imageUrl' => 'https://docs.google.com/uc?id=file-123',
                ]],
            ]),
        ]);

        $initialResponse = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
            ])
            ->get(route('student-app.dashboard'));
        $initialResponse->assertOk();

        $deferredResponse = $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
                'X-Inertia-Partial-Component' => 'student-app/dashboard',
                'X-Inertia-Partial-Data' => 'news',
                'X-Inertia-Deferred' => 'true',
            ])
            ->get(route('student-app.dashboard'));

        $deferredResponse->assertOk()
            ->assertJsonPath('props.news.0.title', 'Berita sekolah')
            ->assertJsonPath(
                'props.news.0.imageUrl',
                'https://drive.google.com/thumbnail?id=file-123&sz=w1200',
            );
        Http::assertSentCount(1);
    }
}
