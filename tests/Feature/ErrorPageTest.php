<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/errors/{status}', fn (int $status) => abort($status, $status === 418 ? 'PRIVATE_EXCEPTION_SENTINEL' : ''));
        Route::get('/api/_test/errors/{status}', fn (int $status) => abort($status));
    }

    public function test_supported_http_errors_render_inertia_error_page_and_preserve_status(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $this->withHeaders(['X-Inertia' => 'true'])
                ->get("/_test/errors/{$status}")
                ->assertStatus($status)
                ->assertJsonPath('component', 'errors/error')
                ->assertJsonPath('props.status', $status)
                ->assertJsonMissingPath('props.exception');
        }
    }

    public function test_json_accepting_requests_keep_the_framework_json_error_response(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->get('/_test/errors/404')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonMissingPath('component');
    }

    public function test_api_requests_keep_the_framework_json_error_response(): void
    {
        $this->getJson('/api/_test/errors/404')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonMissingPath('component');
    }

    public function test_unknown_error_status_uses_generic_copy_without_exposing_exception_message(): void
    {
        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('/_test/errors/418')
            ->assertStatus(418)
            ->assertJsonPath('component', 'errors/error')
            ->assertJsonPath('props.status', 418)
            ->assertJsonPath('props.title', 'Permintaan tidak dapat diproses')
            ->assertJsonMissingPath('props.exception');

        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringNotContainsString('PRIVATE_EXCEPTION_SENTINEL', $body);
    }

    public function test_error_page_exposes_authenticated_context_without_exception_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->get('/_test/errors/403')
            ->assertForbidden()
            ->assertJsonPath('component', 'errors/error')
            ->assertJsonMissingPath('props.authenticated')
            ->assertJsonMissingPath('props.exception');
    }

    public function test_guest_error_page_exposes_guest_context(): void
    {
        $this->withHeaders(['X-Inertia' => 'true'])
            ->get('/_test/errors/404')
            ->assertNotFound()
            ->assertJsonPath('component', 'errors/error')
            ->assertJsonMissingPath('props.authenticated');
    }

    public function test_error_page_has_only_back_navigation(): void
    {
        $source = file_get_contents(resource_path('js/pages/errors/error.tsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('Kembali', $source);
        $this->assertStringNotContainsString('Ke Dashboard', $source);
        $this->assertStringNotContainsString('Ke Dashboard', $source);
        $this->assertStringNotContainsString('Home', $source);
        $this->assertStringNotContainsString('LogIn', $source);
    }
}
