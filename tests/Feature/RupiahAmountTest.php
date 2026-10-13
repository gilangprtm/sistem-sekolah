<?php

namespace Tests\Feature;

use App\Support\RupiahAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RupiahAmountTest extends TestCase
{
    #[DataProvider('canonicalValues')]
    public function test_supported_rupiah_values_are_canonicalized(string $input, string $expected): void
    {
        $this->assertSame($expected, RupiahAmount::canonicalize($input));
    }

    /** @return array<string, array{string, string}> */
    public static function canonicalValues(): array
    {
        return [
            'plain integer' => ['5000', '5000.00'],
            'large plain integer' => ['50000000', '50000000.00'],
            'grouped integer' => ['5.000', '5000.00'],
            'large grouped integer' => ['50.000.000', '50000000.00'],
            'comma decimal' => ['5000,50', '5000.50'],
            'grouped comma decimal' => ['5.000,50', '5000.50'],
            'grouped exact decimal' => ['1.000.000,80', '1000000.80'],
            'dot decimal' => ['5000.50', '5000.50'],
            'short decimal' => ['5000,5', '5000.50'],
            'surrounding whitespace' => [' 5000,5 ', '5000.50'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_malformed_or_empty_values_fail_closed(string $input): void
    {
        $this->assertNull(RupiahAmount::canonicalize($input));
        $this->assertSame(trim($input), RupiahAmount::normalize($input));
    }

    /** @return array<string, array{string}> */
    public static function invalidValues(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'malformed grouping' => ['5.00.0'],
            'overprecision' => ['5.000,500'],
            'mixed separators' => ['5,000.50'],
            'negative' => ['-5000'],
            'currency text' => ['Rp 5.000'],
            'letters' => ['abc'],
        ];
    }

    public function test_non_string_null_and_numeric_values_are_preserved_for_boundary_validation(): void
    {
        $this->assertSame(null, RupiahAmount::normalize(null));
        $this->assertSame(5000, RupiahAmount::normalize(5000));
    }

    public function test_canonical_validation_rules_enforce_decimal_scale_and_storage_precision(): void
    {
        $rules = RupiahAmount::canonicalValidationRules(13);

        $this->assertSame(['numeric', 'regex:/^\\d{1,13}(?:\\.\\d{1,2})?$/', 'min:0', 'decimal:0,2'], $rules);
    }
}
