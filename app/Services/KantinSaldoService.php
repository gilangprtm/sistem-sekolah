<?php

namespace App\Services;

use App\Models\KantinSaldo;
use App\Models\KantinSaldoTransaction;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class KantinSaldoService
{
    /**
     * @param  array{amount: numeric-string|int|float}  $data
     */
    public function topUp(Student $student, array $data, ?User $user = null): KantinSaldoTransaction
    {
        return DB::transaction(function () use ($student, $data, $user): KantinSaldoTransaction {
            KantinSaldo::query()->insertOrIgnore([
                'student_id' => $student->id,
                'saldo' => '0.00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $saldo = KantinSaldo::query()->where('student_id', $student->id)->lockForUpdate()->firstOrFail();
            $saldo = KantinSaldo::query()->whereKey($saldo->id)->lockForUpdate()->firstOrFail();
            $beforeCents = self::toCents($saldo->saldo);
            $amountCents = self::toCents($data['amount']);
            $afterCents = $beforeCents + $amountCents;
            $saldo->update(['saldo' => self::fromCents($afterCents)]);

            return KantinSaldoTransaction::query()->create([
                'student_id' => $student->id,
                'kantin_saldo_id' => $saldo->id,
                'created_by' => $user?->id,
                'type' => 'top_up',
                'amount' => self::fromCents($amountCents),
                'balance_before' => self::fromCents($beforeCents),
                'balance_after' => self::fromCents($afterCents),

            ]);
        });
    }

    private static function toCents(string|int|float $value): int
    {
        $normalized = number_format((float) $value, 2, '.', '');
        [$whole, $fraction] = explode('.', $normalized);

        return ((int) $whole * 100) + (int) $fraction;
    }

    private static function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
