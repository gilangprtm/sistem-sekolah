<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\InventoryCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryApiController extends Controller
{
    public function index(Request $request, InventoryCategoryService $categoryService): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori.',
            ...$categoryService->paginate(
                $data['search'] ?? null,
                $data['page'] ?? 1,
                $data['per_page'] ?? 25,
            ),
        ]);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail kategori.',
            'data' => $category->loadCount('inventoryItems'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:m_inventory_categories,name'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dibuat.',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus.',
            'data' => null,
        ]);
    }
}
