<?php

namespace App\Services\Assistant;

use App\Models\User;
use App\Services\InventoryCategoryService;
use App\Services\InventoryItemService;
use App\Services\InventoryRegisterService;
use App\Services\InventoryRoomService;

class AssistantToolExecutor
{
    public function __construct(
        private readonly InventoryCategoryService $categoryService,
        private readonly InventoryItemService $itemService,
        private readonly InventoryRegisterService $registerService,
        private readonly InventoryRoomService $roomService,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int|string, mixed>
     */
    public function execute(User $user, string $name, array $arguments): array
    {
        abort_unless($user->can('inventory.view'), 403);

        return match ($name) {
            'inventory_items' => $this->queryInventoryItemsResource($arguments),
            'inventory_registers' => $this->queryInventoryRegistersResource($arguments),
            'inventory_rooms' => $this->queryInventoryRoomsResource($arguments),
            'inventory_categories' => $this->queryInventoryCategoriesResource($arguments),
            default => throw new \InvalidArgumentException('Unknown assistant tool.'),
        };
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryItemsResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, [
            'search', 'inventory_category_id', 'inventory_type_id', 'condition', 'year', 'asset_kind', 'page', 'per_page',
        ]);

        return $this->itemService->paginate([
            'search' => $this->nullableString($arguments, 'search'),
            'inventory_category_id' => $this->nullableInteger($arguments, 'inventory_category_id', 1),
            'inventory_type_id' => $this->nullableInteger($arguments, 'inventory_type_id', 1),
            'condition' => $this->nullableEnum($arguments, 'condition', ['B', 'KB', 'RB']),
            'year' => $this->nullableInteger($arguments, 'year', 1900, 2100),
            'asset_kind' => $this->nullableEnum($arguments, 'asset_kind', ['tangible', 'intangible']),
        ], $this->paginationArgument($arguments, 'page'), $this->paginationArgument($arguments, 'per_page', 25, 50));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRegistersResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, [
            'search', 'inventory_item_id', 'room_id', 'condition', 'year', 'page', 'per_page',
        ]);

        return $this->registerService->paginate([
            'search' => $this->nullableString($arguments, 'search'),
            'inventory_item_id' => $this->nullableInteger($arguments, 'inventory_item_id', 1),
            'room_id' => $this->nullableInteger($arguments, 'room_id', 1),
            'condition' => $this->nullableEnum($arguments, 'condition', ['B', 'KB', 'RB']),
            'year' => $this->nullableInteger($arguments, 'year', 1900, 2100),
        ], $this->paginationArgument($arguments, 'page'), $this->paginationArgument($arguments, 'per_page', 25, 50));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryRoomsResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['search', 'page', 'per_page']);

        return $this->roomService->paginate(
            $this->nullableString($arguments, 'search'),
            $this->paginationArgument($arguments, 'page'),
            $this->paginationArgument($arguments, 'per_page', 25, 50),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function queryInventoryCategoriesResource(array $arguments): array
    {
        $this->assertAllowedArguments($arguments, ['search', 'page', 'per_page']);

        return $this->categoryService->paginate(
            $this->nullableString($arguments, 'search'),
            $this->paginationArgument($arguments, 'page'),
            $this->paginationArgument($arguments, 'per_page', 25, 50),
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $allowed
     */
    private function assertAllowedArguments(array $arguments, array $allowed): void
    {
        if (array_diff(array_keys($arguments), $allowed) !== []) {
            throw new \InvalidArgumentException('Unsupported assistant resource argument.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function nullableString(array $arguments, string $key): ?string
    {
        $value = $arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || mb_strlen($value) > 100) {
            throw new \InvalidArgumentException('Invalid assistant resource search.');
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function nullableInteger(array $arguments, string $key, int $minimum, ?int $maximum = null): ?int
    {
        $value = $arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        $integer = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
        if ($integer === null || $integer < $minimum || ($maximum !== null && $integer > $maximum)) {
            throw new \InvalidArgumentException('Invalid assistant resource filter.');
        }

        return $integer;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $allowed
     */
    private function nullableEnum(array $arguments, string $key, array $allowed): ?string
    {
        $value = $arguments[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid assistant resource filter.');
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function paginationArgument(array $arguments, string $key, int $default = 1, int $maximum = 50): int
    {
        $value = $arguments[$key] ?? $default;
        $integer = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
        if ($integer === null || $integer < 1 || $integer > $maximum) {
            throw new \InvalidArgumentException('Invalid assistant resource pagination.');
        }

        return $integer;
    }
}
