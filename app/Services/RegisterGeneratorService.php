<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Support\Facades\DB;

class RegisterGeneratorService
{
    /**
     * Buat inventory item baru + N unit dengan register otomatis (001..N).
     *
     * @param  array<string, mixed>  $data
     */
    public function createItemWithUnits(array $data): InventoryItem
    {
        $qty = (int) ($data['qty'] ?? 1);

        if ($qty < 1) {
            throw new \InvalidArgumentException('Qty minimal 1.');
        }

        return DB::transaction(function () use ($data, $qty) {
            $item = InventoryItem::create($data);

            $this->createUnits($item, $qty);

            return $item;
        });
    }

    /**
     * Tambah unit baru ke item (increase qty).
     *
     * @return array{0: InventoryItem, 1: int} [item, jumlah unit baru]
     */
    public function addUnits(InventoryItem $item, int $qty): array
    {
        if ($qty < 1) {
            throw new \InvalidArgumentException('Jumlah unit minimal 1.');
        }

        $created = DB::transaction(function () use ($item, $qty) {
            return $this->createUnits($item, $qty);
        });

        return [$item, $created];
    }

    /**
     * Generate register dan insert unit dalam satu transaksi.
     */
    private function createUnits(InventoryItem $item, int $qty): int
    {
        $start = $item->nextRegisterNumber();

        $units = [];
        for ($i = 0; $i < $qty; $i++) {
            $units[] = [
                'inventory_item_id' => $item->id,
                'register' => InventoryUnit::formatRegister($start + $i),
                'condition' => 'B',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        InventoryUnit::insert($units);

        return count($units);
    }
}
