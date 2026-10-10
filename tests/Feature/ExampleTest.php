<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shell_uses_portal_header_label(): void
    {
        $this->assertStringContainsString(
            'Portal SMPN 17 Denpasar',
            file_get_contents(resource_path('js/components/app-header.tsx')),
        );
        $this->assertStringContainsString(
            'Portal SMPN 17 Denpasar',
            file_get_contents(resource_path('js/components/dashboard/app-sidebar.tsx')),
        );
    }

    public function test_returns_the_school_landing_page(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('welcome')
                ->where('auth.user', null));

        $this->assertStringContainsString(
            "backgroundImage: \"url('/images/hero-background.png')\"",
            file_get_contents(resource_path('js/pages/welcome.tsx')),
        );
        $this->assertStringContainsString(
            'src="/images/logo-sekolah.png"',
            file_get_contents(resource_path('js/pages/welcome.tsx')),
        );
    }
}
