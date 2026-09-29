<?php

namespace App\Http\Controllers;

use App\Models\InventoryRoom;
use App\Models\InventoryUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryRoomController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $placement = $request->string('placement')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $query = InventoryRoom::query()->withCount('units')->orderBy('name');

        if ($search !== '') {
            $query->where(function ($roomQuery) use ($search): void {
                $roomQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($placement === 'assigned') {
            $query->has('units');
        }

        if ($placement === 'unassigned') {
            $query->doesntHave('units');
        }

        return Inertia::render('inventory-rooms/index', [
            'rooms' => $query->paginate($perPage)->withQueryString(),
            'filters' => [
                'search' => $search,
                'placement' => in_array($placement, ['assigned', 'unassigned'], true) ? $placement : '',
            ],
        ]);
    }

    public function edit(Request $request, InventoryRoom $inventoryRoom): Response
    {
        $lookupPerPage = min(max($request->integer('lookup_per_page', 10), 1), 50);
        $lookupSearch = $request->string('lookup_search')->trim()->toString();
        $lookupQuery = InventoryUnit::query()
            ->with([
                'item:id,kode_barang,nama_jenis_barang,merk_type',
                'room:id,name,code',
            ])
            ->orderBy('inventory_item_id')
            ->orderBy('register');

        if ($lookupSearch !== '') {
            $lookupQuery->where(function ($query) use ($lookupSearch): void {
                $query->where('register', 'like', "%{$lookupSearch}%")
                    ->orWhereHas('item', function ($itemQuery) use ($lookupSearch): void {
                        $itemQuery
                            ->where('kode_barang', 'like', "%{$lookupSearch}%")
                            ->orWhere('nama_jenis_barang', 'like', "%{$lookupSearch}%")
                            ->orWhere('merk_type', 'like', "%{$lookupSearch}%");
                    })
                    ->orWhereHas('room', fn ($roomQuery) => $roomQuery->where('name', 'like', "%{$lookupSearch}%"));
            });
        }

        $lookupUnits = $lookupQuery
            ->paginate($lookupPerPage, ['*'], 'lookup_page')
            ->withQueryString();
        $lookupUnits->getCollection()->transform(fn (InventoryUnit $unit): array => $this->unitData($unit));
        $assignedUnits = $inventoryRoom->units()
            ->with(['item:id,kode_barang,nama_jenis_barang,merk_type', 'room:id,name,code'])
            ->orderBy('inventory_item_id')
            ->orderBy('register')
            ->get()
            ->map(fn (InventoryUnit $unit): array => $this->unitData($unit));

        return Inertia::render('inventory-rooms/edit', [
            'room' => [
                'id' => $inventoryRoom->id,
                'name' => $inventoryRoom->name,
                'code' => $inventoryRoom->code,
                'description' => $inventoryRoom->description,
                'unit_ids' => $assignedUnits->pluck('id')->values(),
                'units' => $assignedUnits->values(),
            ],
            'assignedUnits' => [
                'data' => $assignedUnits->values(),
                'total' => $assignedUnits->count(),
            ],
            'lookupUnits' => $lookupUnits,
            'lookupFilters' => ['search' => $lookupSearch],
        ]);
    }

    public function create(Request $request): Response
    {
        $lookupPerPage = min(max($request->integer('lookup_per_page', 10), 1), 50);
        $lookupSearch = $request->string('lookup_search')->trim()->toString();
        $lookupQuery = InventoryUnit::query()
            ->with([
                'item:id,kode_barang,nama_jenis_barang,merk_type',
                'room:id,name,code',
            ])
            ->orderBy('inventory_item_id')
            ->orderBy('register');

        if ($lookupSearch !== '') {
            $lookupQuery->where(function ($query) use ($lookupSearch): void {
                $query->where('register', 'like', "%{$lookupSearch}%")
                    ->orWhereHas('item', function ($itemQuery) use ($lookupSearch): void {
                        $itemQuery
                            ->where('kode_barang', 'like', "%{$lookupSearch}%")
                            ->orWhere('nama_jenis_barang', 'like', "%{$lookupSearch}%")
                            ->orWhere('merk_type', 'like', "%{$lookupSearch}%");
                    })
                    ->orWhereHas('room', fn ($roomQuery) => $roomQuery->where('name', 'like', "%{$lookupSearch}%"));
            });
        }

        $lookupUnits = $lookupQuery
            ->paginate($lookupPerPage, ['*'], 'lookup_page')
            ->withQueryString();
        $lookupUnits->getCollection()->transform(fn (InventoryUnit $unit): array => $this->unitData($unit));

        return Inertia::render('inventory-rooms/create', [
            'lookupUnits' => $lookupUnits,
            'lookupFilters' => [
                'search' => $lookupSearch,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $unitIds = $request->validate([
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', 'distinct', 'exists:tr_inventory_units,id'],
        ])['unit_ids'] ?? [];

        if ($unitIds !== [] && ! $request->user()->can('inventory.room.assign')) {
            abort(403);
        }

        DB::transaction(function () use ($data, $unitIds): void {
            $room = InventoryRoom::query()->create($data);

            if ($unitIds !== []) {
                InventoryUnit::query()
                    ->whereIn('id', $unitIds)
                    ->update(['inventory_room_id' => $room->id]);
            }
        });

        return redirect()->route('inventory-rooms.index')->with('success', 'Ruangan berhasil dibuat.');
    }

    public function update(Request $request, InventoryRoom $inventoryRoom): RedirectResponse
    {
        $data = $this->validatedData($request, $inventoryRoom);
        $unitIds = $request->validate([
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['integer', 'distinct', 'exists:tr_inventory_units,id'],
        ])['unit_ids'] ?? null;

        if ($unitIds !== null && ! $request->user()->can('inventory.room.assign')) {
            abort(403);
        }

        DB::transaction(function () use ($data, $inventoryRoom, $unitIds): void {
            $inventoryRoom->update($data);

            if ($unitIds === null) {
                return;
            }

            $inventoryRoom->units()->whereNotIn('id', $unitIds)->update(['inventory_room_id' => null]);

            if ($unitIds !== []) {
                InventoryUnit::query()
                    ->whereIn('id', $unitIds)
                    ->update(['inventory_room_id' => $inventoryRoom->id]);
            }
        });

        return redirect()->route('inventory-rooms.index')->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(InventoryRoom $inventoryRoom): RedirectResponse
    {
        $inventoryRoom->delete();

        return back()->with('success', 'Ruangan berhasil dihapus.');
    }

    /**
     * @return array{id: int, register: string, display_code: string, item_name: string, item_brand: string|null, condition: string, room: array{id: int, name: string, code: string}|null}
     */
    private function unitData(InventoryUnit $unit): array
    {
        return [
            'id' => $unit->id,
            'register' => $unit->register,
            'display_code' => $unit->item->kode_barang.'.'.$unit->register,
            'item_name' => $unit->item->nama_jenis_barang,
            'item_brand' => $unit->item->merk_type,
            'condition' => $unit->condition,
            'room' => $unit->room === null ? null : [
                'id' => $unit->room->id,
                'name' => $unit->room->name,
                'code' => $unit->room->code,
            ],
        ];
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
