<?php

namespace App\Services;

use App\Models\KantinBarang;
use App\Models\KantinSaldo;
use App\Models\KantinSaldoTransaction;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class KantinPosService
{
    /** @param array<int, array{product_id: int, quantity: int}> $items */
    public function checkout(Student $student, array $items): array
    {
        return DB::transaction(function () use ($student, $items): array {
            $saldo = KantinSaldo::query()->where('student_id', $student->id)->lockForUpdate()->first();
            $before = $saldo === null ? 0 : $this->cents($saldo->saldo);
            $lines = [];
            $total = 0;

            foreach ($items as $item) {
                $product = KantinBarang::query()->whereKey($item['product_id'])->where('status', 'active')->first();
                if ($product === null) {
                    throw ValidationException::withMessages(['items' => 'Ada barang yang sudah tidak tersedia.']);
                }
                $price = $this->cents($product->harga);
                $subtotal = $price * $item['quantity'];
                $total += $subtotal;
                $lines[] = ['kantin_barang_id' => $product->id, 'quantity' => $item['quantity'],
                    'harga' => $this->money($price), 'subtotal' => $this->money($subtotal)];
            }

            if ($total <= 0) {
                throw ValidationException::withMessages(['items' => 'Total belanja harus lebih dari nol.']);
            }
            if ($saldo === null || $before < $total) {
                throw ValidationException::withMessages(['balance' => 'Saldo tidak mencukupi. Silakan top up terlebih dahulu.']);
            }

            $after = $before - $total;
            $saleId = DB::table('tr_kantin_penjualan')->insertGetId([
                'student_id' => $student->id, 'total' => $this->money($total),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $line) {
                DB::table('tr_kantin_penjualan_detail')->insert([
                    ...$line, 'penjualan_id' => $saleId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $saldo->update(['saldo' => $this->money($after)]);
            KantinSaldoTransaction::query()->create([
                'student_id' => $student->id,
                'kantin_saldo_id' => $saldo->id,
                'created_by' => null,
                'type' => 'purchase',
                'amount' => $this->money($total),
                'balance_before' => $this->money($before),
                'balance_after' => $this->money($after),
            ]);

            return ['transaction_id' => $saleId, 'total' => $this->money($total), 'balance' => $this->money($after)];
        }, 3);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = explode('.', $amount);
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
