<?php

namespace App\Services;

use App\Models\InventoryUnit;
use Illuminate\Pagination\LengthAwarePaginator;

final class InventoryRegisterService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = InventoryUnit::query()
            ->select(['id', 'inventory_item_id', 'inventory_room_id', 'register', 'condition'])
            ->with([
                'item:id,kode_barang,nama_jenis_barang,merk_type,tahun_pembelian,harga,asal_perolehan,satuan,inventory_category_id,inventory_type_id,asset_kind',
                'room:id,name,code',
            ])
            ->orderBy('id');

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('register', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQuery) use ($search): void {
                        $itemQuery->where('kode_barang', 'like', "%{$search}%")
                            ->orWhere('nama_jenis_barang', 'like', "%{$search}%")
                            ->orWhere('merk_type', 'like', "%{$search}%");
                    });
            });
        }
        if (isset($filters['inventory_item_id'])) {
            $query->where('inventory_item_id', $filters['inventory_item_id']);
        }
        if (isset($filters['room_id'])) {
            $query->where('inventory_room_id', $filters['room_id']);
        }
        if (isset($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }
        if (isset($filters['year'])) {
            $query->whereHas('item', fn ($itemQuery) => $itemQuery->where('tahun_pembelian', $filters['year']));
        }

        /** @var LengthAwarePaginator<int, InventoryUnit> $result */
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->getCollection()->map(fn (InventoryUnit $unit): array => [
                'id' => $unit->id,
                'register' => $unit->register,
                'display_code' => $unit->item->kode_barang.'.'.$unit->register,
                'condition' => $unit->condition,
                'item' => [
                    'id' => $unit->item->id,
                    'code' => $unit->item->kode_barang,
                    'name' => $unit->item->nama_jenis_barang,
                    'brand_type' => $unit->item->merk_type,
                    'purchase_year' => $unit->item->tahun_pembelian,
                    'price' => $unit->item->harga,
                    'source' => $unit->item->asal_perolehan,
                    'unit' => $unit->item->satuan,
                ],
                'room' => $unit->room === null ? null : [
                    'id' => $unit->room->id,
                    'code' => $unit->room->code,
                    'name' => $unit->room->name,
                ],
            ])->values()->all(),
            'meta' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ];
    }
}
