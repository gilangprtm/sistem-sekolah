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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jenis inventaris berhasil dibuat.']);

        return back();
    }

    public function update(Request $request, InventoryType $inventoryType): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('m_inventory_types', 'name')->ignore($inventoryType->id)]]);
        $inventoryType->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jenis inventaris berhasil diperbarui.']);

        return back();
    }

    public function destroy(InventoryType $inventoryType): RedirectResponse
    {
        $inventoryType->delete();

        Inertia::flash('toast', ['type' => 'error', 'message' => 'Jenis inventaris berhasil dihapus.']);

        return back();
    }
}
