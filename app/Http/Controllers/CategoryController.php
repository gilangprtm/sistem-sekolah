<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->withCount('inventoryItems')
            ->addSelect([
                'total_units' => InventoryUnit::query()
                    ->selectRaw('count(*)')
                    ->join('tr_inventory_items', 'inventory_items.id', '=', 'inventory_units.inventory_item_id')
                    ->whereColumn('inventory_items.inventory_category_id', 'categories.id'),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_categories', 'name')],
            'description' => ['nullable', 'string'],
        ]);

        Category::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil dibuat.']);

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori berhasil diperbarui.']);

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        Inertia::flash('toast', ['type' => 'error', 'message' => 'Kategori berhasil dihapus.']);

        return back();
    }
}
