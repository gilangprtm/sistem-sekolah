<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryTypeApiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Daftar jenis inventaris.', 'data' => InventoryType::query()->withCount('inventoryItems')->orderBy('name')->get()]);
    }

    public function show(InventoryType $inventoryType): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Detail jenis inventaris.', 'data' => $inventoryType->loadCount('inventoryItems')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_types', 'name')]]);

        return response()->json(['success' => true, 'message' => 'Jenis inventaris berhasil dibuat.', 'data' => InventoryType::create($data)], 201);
    }

    public function update(Request $request, InventoryType $inventoryType): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_types', 'name')->ignore($inventoryType->id)]]);
        $inventoryType->update($data);

        return response()->json(['success' => true, 'message' => 'Jenis inventaris berhasil diperbarui.', 'data' => $inventoryType->fresh()]);
    }

    public function destroy(InventoryType $inventoryType): JsonResponse
    {
        $inventoryType->delete();

        return response()->json(['success' => true, 'message' => 'Jenis inventaris berhasil dihapus.', 'data' => null]);
    }
}
