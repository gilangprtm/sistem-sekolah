<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_login_uses_the_school_hero_background(): void
    {
        $loginSource = file_get_contents(resource_path('js/pages/auth/login.tsx'));
        $layoutSource = file_get_contents(resource_path('js/layouts/auth/auth-simple-layout.tsx'));

        $this->assertIsString($loginSource);
        $this->assertIsString($layoutSource);
        $this->assertStringContainsString('heroBackground: true', $loginSource);
        $this->assertStringContainsString(
            "url('/images/hero-background.png')",
            $layoutSource,
        );
        $this->assertStringContainsString('bg-slate-950/75', $layoutSource);
        $this->assertStringContainsString('bg-slate-950/35', $layoutSource);
        $this->assertStringContainsString('[&_input]:text-white', $layoutSource);
        $this->assertStringContainsString('[&_label]:text-white', $layoutSource);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_student_users_are_redirected_to_the_student_app()
    {
        Role::create(['name' => 'Siswa']);
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('student-app.dashboard', absolute: false));
    }

    public function test_student_login_ignores_intended_admin_dashboard_url(): void
    {
        Role::create(['name' => 'Siswa']);
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->get(route('dashboard'));

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('student-app.dashboard', absolute: false));
    }

    public function test_student_portal_contains_pwa_install_affordance_contract(): void
    {
        $dashboardSource = file_get_contents(resource_path('js/pages/student-app/dashboard.tsx'));
        $hookSource = file_get_contents(resource_path('js/hooks/use-student-pwa-install.ts'));

        $this->assertIsString($dashboardSource);
        $this->assertIsString($hookSource);
        $this->assertStringContainsString('Pasang aplikasi', $dashboardSource);
        $this->assertStringContainsString('Bagikan', $dashboardSource);
        $this->assertStringContainsString('beforeinstallprompt', $hookSource);
        $this->assertStringContainsString('appinstalled', $hookSource);
        $this->assertStringContainsString('preventDefault', $hookSource);
    }

    public function test_appearance_hook_uses_the_canonical_theme_mode_preference(): void
    {
        $hookSource = file_get_contents(resource_path('js/hooks/use-appearance.tsx'));
        $appSource = file_get_contents(resource_path('js/app.tsx'));

        $this->assertIsString($hookSource);
        $this->assertIsString($appSource);
        $this->assertStringContainsString('state.values.theme_mode', $hookSource);
        $this->assertStringContainsString("setPreference('theme_mode', mode)", $hookSource);
        $this->assertStringNotContainsString('localStorage', $hookSource);
        $this->assertStringNotContainsString('initializeTheme', $appSource);
    }

    public function test_student_role_middleware_allows_student_app_route(): void
    {
        Role::create(['name' => 'Siswa']);
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->get(route('student-app.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('student-app/dashboard'));
    }

    public function test_student_app_chatbot_route_is_available_to_students(): void
    {
        Role::create(['name' => 'Siswa']);
        $user = User::factory()->create();
        $user->assignRole('Siswa');

        $this->actingAs($user)
            ->get(route('student-app.chatbot'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('student-app/chatbot'));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited_by_credentials_and_ip(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post(route('login.store'), [
                    'email' => $user->email,
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_login_attempts_are_rate_limited_across_emails_from_the_same_ip(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
                ->post(route('login.store'), [
                    'email' => "unknown{$attempt}@example.com",
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post(route('login.store'), [
                'email' => 'another-user@example.com',
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_passkey_authentication_routes_are_not_available(): void
    {
        $this->get('/passkeys/login/options')->assertNotFound();
        $this->post('/passkeys/login')->assertNotFound();
        $this->get('/passkeys/confirm/options')->assertNotFound();
        $this->post('/passkeys/confirm')->assertNotFound();
        $this->get('/user/passkeys/options')->assertNotFound();
        $this->post('/user/passkeys')->assertNotFound();
        $this->delete('/user/passkeys/1')->assertNotFound();
        $this->get('/.well-known/passkey-endpoints')->assertNotFound();
    }
}
