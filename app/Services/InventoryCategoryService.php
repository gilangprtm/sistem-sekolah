<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;

final class InventoryCategoryService
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(?string $search = null, int $page = 1, int $perPage = 25): array
    {
        $query = Category::query()->withCount('inventoryItems')->orderBy('name');

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        /** @var LengthAwarePaginator<int, Category> $result */
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->getCollection()->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'item_count' => $category->inventory_items_count,
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
