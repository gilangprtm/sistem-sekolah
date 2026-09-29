<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntangibleAssetType;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\InventoryUnit;
use App\Models\TangibleAssetType;
use App\Services\InventoryItemService;
use App\Services\InventoryRegisterService;
use App\Services\RegisterGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryApiController extends Controller
{
    /**
     * Daftar resource inventaris (search/filter/pagination).
     */
    public function index(Request $request, InventoryItemService $itemService): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'inventory_category_id' => ['nullable', 'integer', 'min:1'],
            'inventory_type_id' => ['nullable', 'integer', 'min:1'],
            'condition' => ['nullable', 'string', Rule::in(InventoryUnit::CONDITIONS)],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'asset_kind' => ['nullable', 'string', Rule::in(['tangible', 'intangible'])],
            'category' => ['nullable', 'integer', 'min:1'],
            'inventory_type' => ['nullable', 'integer', 'min:1'],
            'kondisi' => ['nullable', 'string', Rule::in(InventoryUnit::CONDITIONS)],
            'tahun' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'asal' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'tangible_asset_type' => ['nullable', 'integer', 'min:1'],
            'intangible_asset_type' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $resource = $itemService->paginate(
            array_filter([
                'search' => $data['search'] ?? null,
                'inventory_category_id' => $data['inventory_category_id'] ?? $data['category'] ?? null,
                'inventory_type_id' => $data['inventory_type_id'] ?? $data['inventory_type'] ?? null,
                'condition' => $data['condition'] ?? $data['kondisi'] ?? null,
                'year' => $data['year'] ?? $data['tahun'] ?? null,
                'asset_kind' => $data['asset_kind'] ?? null,
                'asal_perolehan' => $data['asal'] ?? null,
                'satuan' => $data['satuan'] ?? null,
                'tangible_asset_type_id' => $data['tangible_asset_type'] ?? null,
                'intangible_asset_type_id' => $data['intangible_asset_type'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            $data['page'] ?? 1,
            $data['per_page'] ?? 25,
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar inventaris.',
            'data' => [
                'data' => $resource['data'],
                'current_page' => $resource['meta']['current_page'],
                'per_page' => $resource['meta']['per_page'],
                'total' => $resource['meta']['total'],
                'last_page' => $resource['meta']['last_page'],
            ],
        ]);
    }

    /**
     * Daftar resource register/unit inventaris.
     */
    public function registers(Request $request, InventoryRegisterService $registerService): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'inventory_item_id' => ['nullable', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
            'condition' => ['nullable', 'string', Rule::in(InventoryUnit::CONDITIONS)],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($registerService->paginate(
            array_filter([
                'search' => $data['search'] ?? null,
                'inventory_item_id' => $data['inventory_item_id'] ?? null,
                'room_id' => $data['room_id'] ?? null,
                'condition' => $data['condition'] ?? null,
                'year' => $data['year'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            $data['page'] ?? 1,
            $data['per_page'] ?? 25,
        ));
    }

    /**
     * Detail inventaris + units.
     */
    public function show(InventoryItem $item): JsonResponse
    {
        $item->load('units.room', 'category', 'inventoryType', 'tangibleAssetType', 'intangibleAssetType');
        $item->setAttribute('total', (int) $item->harga * $item->units()->count());

        return response()->json([
            'success' => true,
            'message' => 'Detail inventaris.',
            'data' => $item,
        ]);
    }

    /**
     * Buat inventaris baru + register.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode_barang' => ['required', 'string', 'max:50', 'unique:tr_inventory_items,kode_barang'],
            'nama_jenis_barang' => ['required', 'string', 'max:255'],
            'merk_type' => ['nullable', 'string', 'max:255'],
            'no_identitas' => ['nullable', 'string', 'max:255'],
            'bahan' => ['nullable', 'string', 'max:255'],
            'asal_perolehan' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ukuran_konstruksi' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'inventory_category_id' => ['nullable', 'integer', 'exists:m_inventory_categories,id'],
            'inventory_type_id' => ['nullable', 'integer', 'exists:m_inventory_types,id'],
            'inventory_type_name' => ['nullable', 'string', 'max:255'],
            'asset_kind' => ['nullable', 'string', 'in:tangible,intangible'],
            'tangible_asset_type_id' => ['nullable', 'integer', 'exists:m_inventory_tangible_asset_types,id'],
            'tangible_asset_type_name' => ['nullable', 'string', 'max:255'],
            'intangible_asset_type_id' => ['nullable', 'integer', 'exists:m_inventory_intangible_asset_types,id'],
            'intangible_asset_type_name' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);
        $data['asset_kind'] = $data['asset_kind'] ?? 'tangible';

        if ((! empty($data['inventory_type_name']) || array_key_exists('inventory_type_id', $data)) && ! $request->user()->can('inventory.type.assign')) {
            abort(403);
        }
        if ((! empty($data['tangible_asset_type_name']) || ! empty($data['intangible_asset_type_name']) || array_key_exists('tangible_asset_type_id', $data) || array_key_exists('intangible_asset_type_id', $data)) && ! $request->user()->can('inventory.asset-type.assign')) {
            abort(403);
        }
        if (! empty($data['inventory_type_name'])) {
            $name = trim($data['inventory_type_name']);
            $type = InventoryType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            $data['inventory_type_id'] = ($type ?? InventoryType::create(['name' => $name]))->id;
        }
        foreach ([
            'tangible' => [TangibleAssetType::class, 'tangible_asset_type_name', 'tangible_asset_type_id', 'intangible_asset_type_id'],
            'intangible' => [IntangibleAssetType::class, 'intangible_asset_type_name', 'intangible_asset_type_id', 'tangible_asset_type_id'],
        ] as $kind => [$model, $nameKey, $idKey, $otherIdKey]) {
            if ($data['asset_kind'] === $kind && ! empty($data[$nameKey])) {
                $name = trim($data[$nameKey]);
                $type = $model::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
                $data[$idKey] = ($type ?? $model::create(['name' => $name]))->id;
            }
            if ($data['asset_kind'] === $kind) {
                $data[$otherIdKey] = null;
            }
        }
        unset($data['inventory_type_name'], $data['tangible_asset_type_name'], $data['intangible_asset_type_name']);
        $item = app(RegisterGeneratorService::class)->createItemWithUnits($data);

        return response()->json([
            'success' => true,
            'message' => 'Inventaris berhasil dibuat.',
            'data' => $item->load('units'),
        ], 201);
    }

    /**
     * Update keterangan saja (field lain immutable).
     */
    public function update(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'keterangan' => ['nullable', 'string'],
            'inventory_category_id' => ['nullable', 'integer', 'exists:m_inventory_categories,id'],
            'asset_kind' => ['sometimes', 'string', 'in:tangible,intangible'],
            'tangible_asset_type_id' => ['nullable', 'integer', 'exists:m_inventory_tangible_asset_types,id'],
            'intangible_asset_type_id' => ['nullable', 'integer', 'exists:m_inventory_intangible_asset_types,id'],
        ]);

        if (array_key_exists('inventory_category_id', $data) && ! $request->user()->can('inventory.category.assign')) {
            abort(403);
        }
        if (array_key_exists('inventory_type_id', $data) && ! $request->user()->can('inventory.type.assign')) {
            abort(403);
        }
        if ((array_key_exists('asset_kind', $data) || array_key_exists('tangible_asset_type_id', $data) || array_key_exists('intangible_asset_type_id', $data)) && ! $request->user()->can('inventory.asset-type.assign')) {
            abort(403);
        }

        $item->update(array_intersect_key($data, array_flip(['keterangan', 'inventory_category_id', 'inventory_type_id', 'asset_kind', 'tangible_asset_type_id', 'intangible_asset_type_id'])));

        return response()->json([
            'success' => true,
            'message' => 'Keterangan diperbarui.',
            'data' => $item->load('units'),
        ]);
    }

    /**
     * Hapus inventaris (permanen, tanpa soft delete).
     */
    public function destroy(InventoryItem $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventaris dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Tambah unit (qty increase-only).
     */
    public function addUnits(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $units = app(RegisterGeneratorService::class)->addUnits($item, $data['qty']);

        return response()->json([
            'success' => true,
            'message' => 'Unit ditambahkan.',
            'data' => $units,
        ]);
    }

    /**
     * Assign ruangan unit saat ini.
     */
    public function updateUnitRoom(Request $request, InventoryItem $item, InventoryUnit $unit): JsonResponse
    {
        if ($unit->inventory_item_id !== $item->id) {
            throw ValidationException::withMessages(['unit' => ['Unit tidak cocok dengan item.']]);
        }

        $data = $request->validate([
            'inventory_room_id' => ['nullable', 'integer', 'exists:m_inventory_rooms,id'],
        ]);

        $unit->update(['inventory_room_id' => $data['inventory_room_id'] ?? null]);

        return response()->json([
            'success' => true,
            'message' => 'Ruangan unit diperbarui.',
            'data' => $unit->load('room'),
        ]);
    }

    /**
     * Update kondisi unit.
     */
    public function updateUnitCondition(Request $request, InventoryItem $item, InventoryUnit $unit): JsonResponse
    {
        if ($unit->inventory_item_id !== $item->id) {
            throw ValidationException::withMessages(['unit' => ['Unit tidak cocok dengan item.']]);
        }

        $data = $request->validate([
            'condition' => ['required', 'string', Rule::in(['B', 'KB', 'RB'])],
        ]);

        $unit->update(['condition' => $data['condition']]);

        return response()->json([
            'success' => true,
            'message' => 'Kondisi unit diperbarui.',
            'data' => $unit,
        ]);
    }
}
