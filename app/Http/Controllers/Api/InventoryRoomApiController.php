<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryRoom;
use App\Services\InventoryRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryRoomApiController extends Controller
{
    public function index(Request $request, InventoryRoomService $roomService): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar ruangan inventaris.',
            ...$roomService->paginate(
                $data['search'] ?? null,
                $data['page'] ?? 1,
                $data['per_page'] ?? 25,
            ),
        ]);
    }

    public function show(InventoryRoom $inventoryRoom): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail ruangan inventaris.',
            'data' => $inventoryRoom->loadCount('units'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $room = InventoryRoom::query()->create($this->validatedData($request));

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dibuat.',
            'data' => $room,
        ], 201);
    }

    public function update(Request $request, InventoryRoom $inventoryRoom): JsonResponse
    {
        $inventoryRoom->update($this->validatedData($request, $inventoryRoom));

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil diperbarui.',
            'data' => $inventoryRoom->fresh(),
        ]);
    }

    public function destroy(InventoryRoom $inventoryRoom): JsonResponse
    {
        $inventoryRoom->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ruangan berhasil dihapus.',
            'data' => null,
        ]);
    }

    /** @return array{name: string, code: string, description: string|null} */
    private function validatedData(Request $request, ?InventoryRoom $inventoryRoom = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_rooms', 'name')->ignore($inventoryRoom?->id)],
            'code' => ['required', 'string', 'max:50', Rule::unique('m_inventory_rooms', 'code')->ignore($inventoryRoom?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
