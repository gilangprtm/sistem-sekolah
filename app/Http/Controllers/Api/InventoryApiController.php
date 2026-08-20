<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
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
        $query = InventoryItem::query()->withCount('units');

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

        $items = $query->orderBy('kode_barang')->paginate($request->integer('per_page', 15));

        // Tambah total per item
        $items->getCollection()->transform(function ($item) {
            $item->setAttribute('total', (int) $item->harga * $item->units_count);

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
        $item->load('units');
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
            'kode_barang' => ['required', 'string', 'max:50', 'unique:inventory_items,kode_barang'],
            'nama_jenis_barang' => ['required', 'string', 'max:255'],
            'merk_type' => ['nullable', 'string', 'max:255'],
            'no_identitas' => ['nullable', 'string', 'max:255'],
            'bahan' => ['nullable', 'string', 'max:255'],
            'asal_perolehan' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ukuran_konstruksi' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'harga' => ['required', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

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
        ]);

        $item->update(['keterangan' => $data['keterangan'] ?? null]);

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
