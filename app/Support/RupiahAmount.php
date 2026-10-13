<?php

namespace App\Support;

final class RupiahAmount
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return self::canonicalize($value) ?? trim($value);
    }

    /**
     * @return array<int, string>
     */
    public static function canonicalValidationRules(?int $maxIntegerDigits = null, string $minimum = '0'): array
    {
        $integerPattern = $maxIntegerDigits === null
            ? '\\d+'
            : '\\d{1,'.$maxIntegerDigits.'}';

        return [
            'numeric',
            'regex:/^'.$integerPattern.'(?:\\.\\d{1,2})?$/',
            'min:'.$minimum,
            'decimal:0,2',
        ];
    }

    public static function canonicalize(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return $value.'.00';
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/', $value, $matches) === 1) {
            return str_replace('.', '', $matches[1]).'.'.str_pad($matches[2], 2, '0');
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value) === 1) {
            return str_replace('.', '', $value).'.00';
        }

        if (preg_match('/^(\d+)[,.](\d{1,2})$/', $value, $matches) === 1) {
            return $matches[1].'.'.str_pad($matches[2], 2, '0');
        }

        return null;
    }
}
