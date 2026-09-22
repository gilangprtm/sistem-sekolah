<?php

namespace App\Http\Controllers;

use App\Models\InventoryType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventoryTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('inventory-types/index', [
            'types' => InventoryType::query()->withCount('inventoryItems')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_types', 'name')]]);
        InventoryType::create($data);

        return back()->with('success', 'Jenis inventaris berhasil dibuat.');
    }

    public function update(Request $request, InventoryType $inventoryType): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_types', 'name')->ignore($inventoryType->id)]]);
        $inventoryType->update($data);

        return back()->with('success', 'Jenis inventaris berhasil diperbarui.');
    }

    public function destroy(InventoryType $inventoryType): RedirectResponse
    {
        $inventoryType->delete();

        return back()->with('success', 'Jenis inventaris berhasil dihapus.');
    }
}
