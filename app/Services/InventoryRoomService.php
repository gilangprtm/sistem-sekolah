<?php

namespace App\Services;

use App\Models\InventoryRoom;

class InventoryRoomService
{
    /**
     * @return array{data: array<int, array{id: int, code: string, name: string, register_count: int}>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(?string $search = null, int $page = 1, int $perPage = 25): array
    {
        $query = InventoryRoom::query()
            ->withCount('units')
            ->orderBy('name');

        $search = is_string($search) ? trim($search) : null;
        if ($search !== null && $search !== '') {
            $query->where(function ($roomQuery) use ($search): void {
                $roomQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $rooms = $query->paginate($perPage, ['id', 'code', 'name'], 'page', $page);

        return [
            'data' => $rooms->getCollection()->map(fn (InventoryRoom $room): array => [
                'id' => $room->id,
                'code' => $room->code,
                'name' => $room->name,
                'register_count' => (int) $room->units_count,
            ])->values()->all(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
                'last_page' => $rooms->lastPage(),
            ],
        ];
    }
}
