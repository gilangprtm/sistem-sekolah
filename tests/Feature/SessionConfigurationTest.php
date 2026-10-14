<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionConfigurationTest extends TestCase
{
    public function test_tracked_session_configuration_defaults_to_seven_days_and_preserves_session_close_behavior(): void
    {
        $sessionConfiguration = file_get_contents(config_path('session.php'));
        $environmentExample = file_get_contents(base_path('.env.example'));

        $this->assertIsString($sessionConfiguration);
        $this->assertIsString($environmentExample);
        $this->assertStringContainsString(
            "'lifetime' => (int) env('SESSION_LIFETIME', 10080),",
            $sessionConfiguration,
        );
        $this->assertStringContainsString('SESSION_LIFETIME=10080', $environmentExample);
        $this->assertStringContainsString(
            "'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),",
            $sessionConfiguration,
        );
    }
}
