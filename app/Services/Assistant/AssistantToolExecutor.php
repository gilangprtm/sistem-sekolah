<?php

namespace App\Services\Assistant;

use App\Models\InventoryItem;
use App\Models\InventoryRoom;
use App\Models\InventoryUnit;
use App\Models\User;

class AssistantToolExecutor
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int|string, mixed>
     */
    public function execute(User $user, string $name, array $arguments): array
    {
        abort_unless($user->can('inventory.view'), 403);

        return match ($name) {
            'inventory_summary' => [
                'total_items' => InventoryItem::query()->count(),
                'total_units' => InventoryItem::query()->withCount('units')->get()->sum('units_count'),
            ],
            'inventory_query' => $this->queryInventory($arguments),
            'inventory_register_query' => $this->queryInventoryRegisters($arguments),
            'inventory_room_query' => $this->queryInventoryRooms($arguments),
            'inventory_search' => InventoryItem::query()
                ->with('category')
                ->withCount('units')
                ->where(function ($query) use ($arguments) {
                    $term = (string) ($arguments['query'] ?? '');
                    $query->where('kode_barang', 'like', "%{$term}%")
                        ->orWhere('nama_jenis_barang', 'like', "%{$term}%")
                        ->orWhere('merk_type', 'like', "%{$term}%");
                })
                ->limit(20)
                ->get()
                ->map(fn (InventoryItem $item) => [
                    'kode_barang' => $item->kode_barang,
                    'nama' => $item->nama_jenis_barang,
                    'kategori' => $item->category?->name,
                    'qty' => $item->units_count,
                ])
                ->values()
                ->all(),
            default => throw new \InvalidArgumentException('Unknown assistant tool.'),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRooms(array $arguments): array
    {
        $operation = $arguments['operation'] ?? 'summary';
        if (! is_string($operation) || ! in_array($operation, ['summary', 'list'], true)) {
            throw new \InvalidArgumentException('Invalid inventory room operation.');
        }

        $filters = $arguments['filters'] ?? [];
        if (! is_array($filters) || array_diff(array_keys($filters), ['room_id', 'room_name', 'room_code', 'placement']) !== []) {
            throw new \InvalidArgumentException('Invalid inventory room filters.');
        }

        $query = InventoryRoom::query()->withCount('units')->orderBy('name');
        if (isset($filters['room_id'])) {
            if (! is_int($filters['room_id']) && ! (is_string($filters['room_id']) && ctype_digit($filters['room_id']))) {
                throw new \InvalidArgumentException('Invalid inventory room filter.');
            }
            $query->where('id', (int) $filters['room_id']);
        }
        foreach (['room_name' => 'name', 'room_code' => 'code'] as $filter => $column) {
            if (isset($filters[$filter])) {
                if (! is_string($filters[$filter]) || mb_strlen($filters[$filter]) > 100) {
                    throw new \InvalidArgumentException('Invalid inventory room filter.');
                }
                $query->where($column, 'like', '%'.trim($filters[$filter]).'%');
            }
        }
        if (($filters['placement'] ?? null) === 'assigned') {
            $query->has('units');
        } elseif (($filters['placement'] ?? null) === 'unassigned') {
            $query->doesntHave('units');
        } elseif (isset($filters['placement'])) {
            throw new \InvalidArgumentException('Invalid inventory room placement.');
        }

        if ($operation === 'summary') {
            return [
                'total_rooms' => (clone $query)->count(),
                'rooms_with_units' => (clone $query)->has('units')->count(),
                'assigned_units' => (clone $query)->withCount('units')->get()->sum('units_count'),
                'rooms' => (clone $query)->has('units')->get(['id', 'name', 'code'])->map(fn (InventoryRoom $room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'code' => $room->code,
                    'register_count' => $room->units_count,
                ])->values()->all(),
            ];
        }

        $page = $this->integerArgument($arguments, 'page', 1);
        $perPage = $this->integerArgument($arguments, 'per_page', 25, 50);
        $rooms = $query->paginate($perPage, ['id', 'name', 'code', 'description'], 'page', $page);

        return [
            'data' => $rooms->getCollection()->map(fn (InventoryRoom $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'code' => $room->code,
                'description' => $room->description,
                'register_count' => $room->units_count,
            ])->values()->all(),
            'pagination' => ['page' => $rooms->currentPage(), 'per_page' => $rooms->perPage(), 'total' => $rooms->total(), 'last_page' => $rooms->lastPage()],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRegisters(array $arguments): array
    {
        $allowedFields = [
            'id', 'inventory_item_id', 'register', 'condition', 'display_code',
            'room.id', 'room.name', 'room.code',
            'item.kode_barang', 'item.nama_jenis_barang', 'item.merk_type',
            'item.tahun_pembelian', 'item.harga', 'item.asal_perolehan',
            'item.satuan', 'item.inventory_category_id', 'item.inventory_type_id',
            'item.asset_kind',
        ];
        $fields = $this->allowlistedFields($arguments['fields'] ?? null, $allowedFields);
        $query = InventoryUnit::query()
            ->select(['id', 'inventory_item_id', 'inventory_room_id', 'register', 'condition'])
            ->with(['item:id,kode_barang,nama_jenis_barang,merk_type,tahun_pembelian,harga,asal_perolehan,satuan,inventory_category_id,inventory_type_id,asset_kind', 'room:id,name,code']);
        $filters = $this->registerFilters($this->withoutProviderDefaultRegisterFilters($arguments['filters'] ?? null));

        if (isset($filters['room_id'])) {
            $query->where('inventory_room_id', $filters['room_id']);
        }
        if (isset($filters['room_name'])) {
            if ($filters['room_name'] === '__unassigned__') {
                $query->whereNull('inventory_room_id');
            } else {
                $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('name', 'like', "%{$filters['room_name']}%"));
            }
        }
        if (isset($filters['room_code'])) {
            $query->whereHas('room', fn ($roomQuery) => $roomQuery->where('code', 'like', "%{$filters['room_code']}%"));
        }

        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($builder) use ($term) {
                $builder->where('register', 'like', "%{$term}%")
                    ->orWhereHas('item', function ($itemQuery) use ($term) {
                        $itemQuery
                            ->where('kode_barang', 'like', "%{$term}%")
                            ->orWhere('nama_jenis_barang', 'like', "%{$term}%")
                            ->orWhere('merk_type', 'like', "%{$term}%");
                    });
            });
        }

        foreach (['register', 'condition', 'inventory_item_id'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['kode_barang', 'tahun_pembelian', 'asal_perolehan', 'satuan', 'inventory_category_id', 'inventory_type_id', 'asset_kind'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->whereHas('item', fn ($itemQuery) => $itemQuery->where($field, $filters[$field]));
            }
        }

        $sort = $this->registerSort($arguments['sort'] ?? null);
        if ($sort['field'] === 'kode_barang') {
            $query->orderBy(
                InventoryItem::query()->select('kode_barang')
                    ->whereColumn('tr_inventory_items.id', 'tr_inventory_units.inventory_item_id'),
                $sort['direction'] === 'desc' ? 'desc' : 'asc',
            );
        } else {
            $query->orderBy(
                $sort['field'],
                $sort['direction'] === 'desc' ? 'desc' : 'asc',
            );
        }
        $perPage = $this->integerArgument($arguments, 'per_page', 25, 50);
        $page = $this->integerArgument($arguments, 'page', 1);
        $result = $query->paginate($perPage, ['*'], 'page', $page);
        $itemFields = array_values(array_filter($fields, fn (string $field): bool => str_starts_with($field, 'item.')));
        $rows = $result->getCollection()->map(function (InventoryUnit $unit) use ($fields, $itemFields): array {
            $row = [];
            foreach (['id', 'inventory_item_id', 'register', 'condition'] as $field) {
                if (in_array($field, $fields, true)) {
                    $row[$field] = $unit->{$field};
                }
            }
            if (in_array('display_code', $fields, true)) {
                $row['display_code'] = $unit->item->kode_barang.'.'.$unit->register;
            }
            $roomFields = array_values(array_filter($fields, fn (string $field): bool => str_starts_with($field, 'room.')));
            if ($roomFields !== []) {
                $row['room'] = [];
                foreach ($roomFields as $field) {
                    $roomField = substr($field, 5);
                    $row['room'][$roomField] = $unit->room?->{$roomField};
                }
            }
            if ($itemFields !== []) {
                $row['item'] = [];
                foreach ($itemFields as $field) {
                    $itemField = substr($field, 5);
                    $row['item'][$itemField] = $unit->item->{$itemField};
                }
            }

            return $row;
        })->values()->all();

        return [
            'data' => $rows,
            'pagination' => ['page' => $result->currentPage(), 'per_page' => $result->perPage(), 'total' => $result->total(), 'last_page' => $result->lastPage()],
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private function allowlistedFields(mixed $value, array $allowed): array
    {
        if ($value === null) {
            return $allowed;
        }
        if (! is_array($value) || ! array_is_list($value) || count($value) > count($allowed) || array_filter($value, 'is_string') !== $value) {
            throw new \InvalidArgumentException('Invalid assistant fields.');
        }

        $fields = array_values(array_unique($value));
        if (array_diff($fields, $allowed) !== []) {
            throw new \InvalidArgumentException('Unsupported assistant field.');
        }

        return $fields !== [] ? $fields : $allowed;
    }

    private function withoutProviderDefaultRegisterFilters(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $defaultTemplate = [
            'search' => '',
            'register' => '',
            'inventory_item_id' => 0,
            'kode_barang' => '',
            'tahun_pembelian' => 0,
            'asal_perolehan' => '',
            'satuan' => '',
            'inventory_category_id' => 0,
            'inventory_type_id' => 0,
            'asset_kind' => 'tangible',
        ];
        $condition = $value['condition'] ?? null;

        if (is_string($condition) && array_diff_assoc($value, ['condition' => $condition] + $defaultTemplate) === [] && array_diff_assoc(['condition' => $condition] + $defaultTemplate, $value) === []) {
            return ['condition' => $condition];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function registerFilters(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (! is_array($value)) {
            throw new \InvalidArgumentException('Invalid assistant filters.');
        }

        $allowed = ['search', 'register', 'condition', 'inventory_item_id', 'kode_barang', 'tahun_pembelian', 'asal_perolehan', 'satuan', 'inventory_category_id', 'inventory_type_id', 'asset_kind', 'room_id', 'room_name', 'room_code'];
        if (array_diff(array_keys($value), $allowed) !== []) {
            throw new \InvalidArgumentException('Unsupported assistant filter.');
        }
        $filters = [];
        foreach ($value as $field => $filter) {
            if (in_array($field, ['search', 'register', 'kode_barang', 'asal_perolehan', 'satuan', 'room_name', 'room_code'], true)) {
                if (! is_string($filter) || mb_strlen($filter) > 100) {
                    throw new \InvalidArgumentException('Invalid assistant filter value.');
                }
                $filters[$field] = trim($filter);
            } elseif ($field === 'asset_kind') {
                if (! is_string($filter) || ! in_array($filter, ['tangible', 'intangible'], true)) {
                    throw new \InvalidArgumentException('Invalid assistant asset kind.');
                }
                $filters[$field] = $filter;
            } elseif (in_array($field, ['inventory_item_id', 'tahun_pembelian', 'inventory_category_id', 'inventory_type_id', 'room_id'], true)) {
                if (! is_int($filter) && ! (is_string($filter) && ctype_digit($filter))) {
                    throw new \InvalidArgumentException('Invalid assistant filter value.');
                }
                $filters[$field] = (int) $filter;
            } elseif ($field === 'condition') {
                if (! is_string($filter) || ! in_array($filter, InventoryUnit::CONDITIONS, true)) {
                    throw new \InvalidArgumentException('Invalid assistant condition.');
                }
                $filters[$field] = $filter;
            }
        }

        return $filters;
    }

    /** @return array{field: string, direction: string} */
    private function registerSort(mixed $value): array
    {
        if ($value === null) {
            return ['field' => 'id', 'direction' => 'asc'];
        }
        if (! is_array($value) || array_diff(array_keys($value), ['field', 'direction']) !== []) {
            throw new \InvalidArgumentException('Invalid assistant sort.');
        }
        $fields = ['id', 'inventory_item_id', 'register', 'condition', 'kode_barang'];
        $field = $value['field'] ?? 'id';
        $direction = $value['direction'] ?? 'asc';
        if (! is_string($field) || ! in_array($field, $fields, true) || ! is_string($direction) || ! in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Unsupported assistant sort.');
        }

        return ['field' => $field, 'direction' => $direction];
    }

    /** @param array<string, mixed> $arguments */
    private function integerArgument(array $arguments, string $key, int $default, ?int $maximum = null): int
    {
        $value = $arguments[$key] ?? $default;
        $integer = is_int($value) || (is_string($value) && ctype_digit($value))
            ? (int) $value
            : null;
        if ($integer === null || $integer < 1 || ($maximum !== null && $integer > $maximum)) {
            throw new \InvalidArgumentException('Invalid assistant pagination.');
        }

        return $integer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventory(array $arguments): array
    {
        $allowedFields = [
            'kode_barang', 'nama_jenis_barang', 'merk_type', 'asal_perolehan',
            'tahun_pembelian', 'harga', 'keterangan', 'inventory_category_id',
            'inventory_type_id', 'asset_kind', 'tangible_asset_type_id',
            'intangible_asset_type_id',
        ];
        $fields = array_values(array_intersect($arguments['fields'] ?? $allowedFields, $allowedFields));
        $fields = $fields !== [] ? $fields : $allowedFields;
        $query = InventoryItem::query()->select($fields)->withCount('units');
        $filters = is_array($arguments['filters'] ?? null) ? $arguments['filters'] : [];
        if (isset($filters['search']) && is_string($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($builder) use ($term) {
                $builder->where('kode_barang', 'like', "%{$term}%")
                    ->orWhere('nama_jenis_barang', 'like', "%{$term}%")
                    ->orWhere('merk_type', 'like', "%{$term}%");
            });
        }
        foreach (['inventory_category_id', 'inventory_type_id', 'asset_kind', 'tahun_pembelian'] as $field) {
            if (array_key_exists($field, $filters)) {
                $query->where($field, $filters[$field]);
            }
        }
        $sort = is_array($arguments['sort'] ?? null) ? $arguments['sort'] : [];
        $sortField = $sort['field'] ?? 'id';
        if (! in_array($sortField, $allowedFields, true)) {
            $sortField = 'id';
        }
        $direction = ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $perPage = min(max((int) ($arguments['per_page'] ?? 25), 1), 100);
        $page = max((int) ($arguments['page'] ?? 1), 1);
        $result = $query->orderBy($sortField, $direction)->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->items(),
            'pagination' => ['page' => $result->currentPage(), 'per_page' => $result->perPage(), 'total' => $result->total(), 'last_page' => $result->lastPage()],
        ];
    }
}
