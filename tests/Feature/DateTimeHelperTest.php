<?php

namespace Tests\Feature;

use Tests\TestCase;

class DateTimeHelperTest extends TestCase
{
    public function test_wita_helper_source_declares_explicit_timezone_and_invalid_guard(): void
    {
        $source = file_get_contents(resource_path('js/lib/date-time.ts'));

        $this->assertIsString($source);
        $this->assertStringContainsString("'Asia/Makassar'", $source);
        $this->assertStringContainsString('Number.isNaN(timestamp)', $source);
        $this->assertStringContainsString('formatWitaDate', $source);
        $this->assertStringContainsString('formatWitaDateTime', $source);
    }

    public function test_wita_helper_source_covers_utc_rollover_example(): void
    {
        $source = file_get_contents(resource_path('js/lib/date-time.ts'));

        $this->assertIsString($source);
        $this->assertStringContainsString('timeZone: WITA_TIME_ZONE', $source);
        $this->assertStringContainsString('ISO_INSTANT_PATTERN', $source);
    }
}
