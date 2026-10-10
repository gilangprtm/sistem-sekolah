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

    public function test_app_head_uses_school_logo_for_browser_and_pwa_icons(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('<meta name="application-name" content="Portal SMPN 17 Denpasar">', false)
            ->assertSee('<meta name="apple-mobile-web-app-title" content="Portal SMPN 17 Denpasar">', false)
            ->assertSee('<link rel="icon" type="image/png" sizes="1080x1080" href="/images/logo-sekolah.png?v=school-logo-1">', false)
            ->assertSee('<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico?v=school-logo-1">', false)
            ->assertSee('<link rel="icon" type="image/svg+xml" href="/favicon.svg?v=school-logo-1">', false)
            ->assertSee('<link rel="apple-touch-icon" sizes="180x180" href="/icons/apple_touch_icon.png?v=school-logo-1">', false)
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest?v=school-logo-1">', false)
            ->assertDontSee('Laravel</title>', false);

        $this->assertFileExists(public_path('images/logo-sekolah.png'));
        $this->assertStringNotContainsString(
            'Laravel',
            file_get_contents(resource_path('js/config/app-config.ts')),
        );
        $this->assertStringContainsString(
            'Portal SMPN 17 Denpasar',
            file_get_contents(resource_path('js/config/app-config.ts')),
        );
        $this->assertStringNotContainsString(
            'MyWebSite',
            file_get_contents(base_path('assets/favicon/site.webmanifest')),
        );
        $this->assertStringContainsString(
            'Portal SMPN 17 Denpasar',
            file_get_contents(base_path('assets/favicon/site.webmanifest')),
        );
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['/icons/icon_192.png?v=school-logo-1', '/icons/icon_512.png?v=school-logo-1'], array_column($manifest['icons'], 'src'));
        $this->assertSame([192, 512], array_map(
            static fn (array $icon): int => (int) explode('x', $icon['sizes'])[0],
            $manifest['icons'],
        ));
        $this->assertFileExists(public_path('icons/icon_192.png'));
        $this->assertFileExists(public_path('icons/icon_512.png'));
        $this->assertFileExists(public_path('icons/apple_touch_icon.png'));
        $this->assertStringContainsString('student-app-shell-v2', file_get_contents(public_path('sw.js')));
        $pwaRegistration = file_get_contents(resource_path('js/lib/register-student-pwa.ts'));
        $this->assertStringContainsString("'/sw.js?v=school-logo-2'", $pwaRegistration);
        $this->assertStringContainsString("updateViaCache: 'none'", $pwaRegistration);
    }

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
