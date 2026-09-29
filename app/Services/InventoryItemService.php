<?php

namespace App\Services;

use App\Models\InventoryItem;
use Illuminate\Pagination\LengthAwarePaginator;

final class InventoryItemService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = InventoryItem::query()
            ->withCount('units')
            ->with(['category:id,name,slug', 'inventoryType:id,name,slug'])
            ->orderBy('kode_barang');
        $search = $filters['search'] ?? null;

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('kode_barang', 'like', "%{$search}%")
                    ->orWhere('nama_jenis_barang', 'like', "%{$search}%")
                    ->orWhere('merk_type', 'like', "%{$search}%")
                    ->orWhereHas('units', fn ($unitQuery) => $unitQuery->where('register', 'like', "%{$search}%"));
            });
        }

        foreach (['inventory_category_id', 'inventory_type_id', 'tangible_asset_type_id', 'intangible_asset_type_id', 'tahun_pembelian', 'asset_kind'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['condition'] ?? null) !== null) {
            $query->whereHas('units', fn ($unitQuery) => $unitQuery->where('condition', $filters['condition']));
        }
        if (($filters['asal_perolehan'] ?? null) !== null) {
            $query->where('asal_perolehan', $filters['asal_perolehan']);
        }
        if (($filters['satuan'] ?? null) !== null) {
            $query->where('satuan', $filters['satuan']);
        }
        if (($filters['year'] ?? null) !== null) {
            $query->where('tahun_pembelian', $filters['year']);
        }

        /** @var LengthAwarePaginator<int, InventoryItem> $result */
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->getCollection()->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'code' => $item->kode_barang,
                'name' => $item->nama_jenis_barang,
                'brand_type' => $item->merk_type,
                'purchase_year' => $item->tahun_pembelian,
                'price' => $item->harga,
                'source' => $item->asal_perolehan,
                'unit' => $item->satuan,
                'category' => $item->category === null ? null : [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                    'slug' => $item->category->slug,
                ],
                'inventory_type' => $item->inventoryType === null ? null : [
                    'id' => $item->inventoryType->id,
                    'name' => $item->inventoryType->name,
                    'slug' => $item->inventoryType->slug,
                ],
                'asset_kind' => $item->asset_kind,
                'register_count' => $item->units_count,
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
