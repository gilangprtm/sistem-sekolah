<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntangibleAssetType;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\InventoryUnit;
use App\Models\TangibleAssetType;
use App\Services\RegisterGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryApiController extends Controller
{
    /**
     * Daftar inventaris (search/filter/pagination).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoryItem::query()->withCount('units')->with(['category', 'inventoryType', 'tangibleAssetType', 'intangibleAssetType']);

        if ($request->filled('category')) {
            $query->where('inventory_category_id', $request->integer('category'));
        }

        if ($request->filled('inventory_type')) {
            $query->where('inventory_type_id', $request->integer('inventory_type'));
        }
        if ($request->filled('asset_kind')) {
            $query->where('asset_kind', $request->string('asset_kind')->toString());
        }
        if ($request->filled('tangible_asset_type')) {
            $query->where('tangible_asset_type_id', $request->integer('tangible_asset_type'));
        }
        if ($request->filled('intangible_asset_type')) {
            $query->where('intangible_asset_type_id', $request->integer('intangible_asset_type'));
        }

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_barang', 'like', "%{$search}%")
                    ->orWhere('nama_jenis_barang', 'like', "%{$search}%")
                    ->orWhere('merk_type', 'like', "%{$search}%")
                    ->orWhereHas('units', fn ($q) => $q->where('register', 'like', "%{$search}%"));
            });
        }

        if ($request->tahun) {
            $query->where('tahun_pembelian', $request->tahun);
        }

        if ($request->kondisi) {
            $query->whereHas('units', fn ($q) => $q->where('condition', $request->kondisi));
        }

        if ($request->asal) {
            $query->where('asal_perolehan', $request->asal);
        }

        if ($request->satuan) {
            $query->where('satuan', $request->satuan);
        }

        $items = $query->orderBy('kode_barang')->paginate($validated['per_page'] ?? 15);

        // Tambah total per item
        $items->getCollection()->transform(function ($item) {
            $item->setAttribute('total', (float) $item->harga * $item->units_count);

            return $item;
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar inventaris.',
            'data' => $items,
        ]);
    }

    /**
     * Detail inventaris + units.
     */
    public function show(InventoryItem $item): JsonResponse
    {
        $item->load('units', 'category', 'inventoryType', 'tangibleAssetType', 'intangibleAssetType');
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
