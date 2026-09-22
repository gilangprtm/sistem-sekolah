<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\IntangibleAssetType;
use App\Models\InventoryItem;
use App\Models\InventoryType;
use App\Models\InventoryUnit;
use App\Models\TangibleAssetType;
use App\Services\RegisterGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private readonly RegisterGeneratorService $registerGenerator
    ) {}

    /**
     * Daftar inventaris + search/filter/pagination.
     */
    public function index(Request $request): Response
    {
        $query = InventoryItem::query()
            ->withCount('units')
            ->with(['category', 'inventoryType', 'tangibleAssetType', 'intangibleAssetType']);

        // Search: kode barang, nama/jenis, merk/type, register
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('kode_barang', 'like', "%{$search}%")
                    ->orWhere('nama_jenis_barang', 'like', "%{$search}%")
                    ->orWhere('merk_type', 'like', "%{$search}%")
                    ->orWhereHas('units', fn ($u) => $u->where('register', 'like', "%{$search}%"));
            });
        }

        // Filter
        if ($tahun = $request->input('tahun')) {
            $query->where('tahun_pembelian', $tahun);
        }
        if ($kondisi = $request->input('kondisi')) {
            $query->whereHas('units', fn ($u) => $u->where('condition', $kondisi));
        }
        if ($asal = $request->input('asal')) {
            $query->where('asal_perolehan', $asal);
        }
        if ($satuan = $request->input('satuan')) {
            $query->where('satuan', $satuan);
        }
        if ($request->filled('category')) {
            $query->where('inventory_category_id', $request->integer('category'));
        }
        if ($request->filled('inventory_type')) {
            $query->where('inventory_type_id', $request->integer('inventory_type'));
        }
        if ($request->filled('asset_kind')) {
            $query->where('asset_kind', $request->string('asset_kind')->toString());
        }
        if ($request->filled('tangible_asset_type')) {
            $query->where('tangible_asset_type_id', $request->integer('tangible_asset_type'));
        }
        if ($request->filled('intangible_asset_type')) {
            $query->where('intangible_asset_type_id', $request->integer('intangible_asset_type'));
        }

        $items = $query->orderBy('kode_barang')->paginate(10)->withQueryString();

        // Use the database aggregate instead of loading every unit for the list.
        $items->getCollection()->transform(function (InventoryItem $item) {
            $item->setAttribute('qty', $item->units_count);
            $item->setAttribute('total', (float) $item->harga * $item->units_count);

            return $item;
        });

        return Inertia::render('inventory/index', [
            'items' => $items,
            'filters' => $request->only(['search', 'tahun', 'kondisi', 'asal', 'satuan', 'category', 'inventory_type', 'asset_kind', 'tangible_asset_type', 'intangible_asset_type']),
            'filterOptions' => [
                'tahun' => InventoryItem::query()->select('tahun_pembelian')->distinct()->orderBy('tahun_pembelian', 'desc')->pluck('tahun_pembelian'),
                'asal' => InventoryItem::query()->select('asal_perolehan')->distinct()->whereNotNull('asal_perolehan')->orderBy('asal_perolehan')->pluck('asal_perolehan'),
                'satuan' => InventoryItem::query()->select('satuan')->distinct()->whereNotNull('satuan')->orderBy('satuan')->pluck('satuan'),
                'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
                'inventoryTypes' => InventoryType::query()->orderBy('name')->get(['id', 'name']),
                'tangibleAssetTypes' => TangibleAssetType::query()->orderBy('name')->get(['id', 'name']),
                'intangibleAssetTypes' => IntangibleAssetType::query()->orderBy('name')->get(['id', 'name']),
                'assetKinds' => ['tangible', 'intangible'],
            ],
        ]);
    }

    /**
     * Form create inventaris.
     */
    public function create(): Response
    {
        return Inertia::render('inventory/create', [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'inventoryTypes' => InventoryType::query()->orderBy('name')->get(['id', 'name']),
            'tangibleAssetTypes' => TangibleAssetType::query()->orderBy('name')->get(['id', 'name']),
            'intangibleAssetTypes' => IntangibleAssetType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Simpan inventaris baru + generate register.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_barang' => ['required', 'string', 'max:255', 'unique:tr_inventory_items,kode_barang'],
            'nama_jenis_barang' => ['required', 'string', 'max:255'],
            'merk_type' => ['nullable', 'string', 'max:255'],
            'no_identitas' => ['nullable', 'string', 'max:255'],
            'bahan' => ['nullable', 'string', 'max:255'],
            'asal_perolehan' => ['nullable', 'string', 'max:255'],
            'tahun_pembelian' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'ukuran_konstruksi' => ['nullable', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:255'],
            'harga' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'inventory_category_id' => ['nullable', 'integer', 'exists:m_inventory_categories,id'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'inventory_type_id' => ['nullable', 'integer', 'exists:m_inventory_types,id'],
            'inventory_type_name' => ['nullable', 'string', 'max:255'],
            'asset_kind' => ['nullable', 'string', 'in:tangible,intangible'],
            'tangible_asset_type_id' => ['nullable', 'integer', 'exists:m_inventory_tangible_asset_types,id'],
            'tangible_asset_type_name' => ['nullable', 'string', 'max:255'],
            'intangible_asset_type_id' => ['nullable', 'integer', 'exists:m_inventory_intangible_asset_types,id'],
            'intangible_asset_type_name' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);
        $data['asset_kind'] = $data['asset_kind'] ?? 'tangible';

        if (($request->filled('inventory_category_id') || $request->filled('category_name')) && ! $request->user()->can('inventory.category.assign')) {
            abort(403);
        }
        if (($request->filled('inventory_type_id') || $request->filled('inventory_type_name')) && ! $request->user()->can('inventory.type.assign')) {
            abort(403);
        }
        if (($request->filled('tangible_asset_type_id') || $request->filled('tangible_asset_type_name') || $request->filled('intangible_asset_type_id') || $request->filled('intangible_asset_type_name')) && ! $request->user()->can('inventory.asset-type.assign')) {
            abort(403);
        }
        if ($request->filled('category_name')) {
            $name = trim($data['category_name']);
            $category = Category::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            $data['inventory_category_id'] = ($category ?? Category::create(['name' => $name]))->id;
        }

        if ($request->filled('inventory_type_name')) {
            $name = trim($data['inventory_type_name']);
            $type = InventoryType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            $data['inventory_type_id'] = ($type ?? InventoryType::create(['name' => $name]))->id;
        }

        if ($data['asset_kind'] === 'tangible' && $request->filled('tangible_asset_type_name')) {
            $name = trim($data['tangible_asset_type_name']);
            $type = TangibleAssetType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            $data['tangible_asset_type_id'] = ($type ?? TangibleAssetType::create(['name' => $name]))->id;
            $data['intangible_asset_type_id'] = null;
        }
        if ($data['asset_kind'] === 'intangible' && $request->filled('intangible_asset_type_name')) {
            $name = trim($data['intangible_asset_type_name']);
            $type = IntangibleAssetType::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            $data['intangible_asset_type_id'] = ($type ?? IntangibleAssetType::create(['name' => $name]))->id;
            $data['tangible_asset_type_id'] = null;
        }

        unset($data['category_name'], $data['inventory_type_name'], $data['tangible_asset_type_name'], $data['intangible_asset_type_name']);
        $this->registerGenerator->createItemWithUnits($data);

        return redirect()->route('inventory.index')->with('success', 'Inventaris berhasil ditambahkan.');
    }

    /**
     * Detail item + units.
     */
    public function show(InventoryItem $item): Response
    {
        $item->load('units', 'category', 'inventoryType', 'tangibleAssetType', 'intangibleAssetType');

        $item->setAttribute('qty', $item->units->count());
        $item->setAttribute('total', (float) $item->harga * $item->units->count());

        return Inertia::render('inventory/show', [
            'item' => $item,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update keterangan (field mutable) — immutable fields dijaga.
     */
    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'keterangan' => ['nullable', 'string'],
            'inventory_category_id' => ['nullable', 'integer', 'exists:m_inventory_categories,id'],
        ]);

        if (array_key_exists('inventory_category_id', $data) && ! $request->user()->can('inventory.category.assign')) {
            abort(403);
        }

        // Hanya mutable field dan assignment kategori yang diupdate.
        $item->update(array_intersect_key($data, array_flip(['keterangan', 'inventory_category_id'])));

        return back()->with('success', 'Keterangan berhasil diperbarui.');
    }

    /**
     * Tambah unit (increase qty) — register lanjut otomatis.
     */
    public function addUnits(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $this->registerGenerator->addUnits($item, $data['qty']);

        return back()->with('success', 'Unit berhasil ditambahkan.');
    }

    /**
     * Ubah kondisi unit.
     */
    public function updateCondition(Request $request, InventoryItem $item, InventoryUnit $unit): RedirectResponse
    {
        if ($unit->inventory_item_id !== $item->id) {
            abort(404);
        }

        $data = $request->validate([
            'condition' => ['required', 'string', 'in:B,KB,RB'],
        ]);

        $unit->update(['condition' => $data['condition']]);

        return back()->with('success', 'Kondisi unit berhasil diperbarui.');
    }

    /**
     * Delete permanen item + cascade units.
     */
    public function destroy(InventoryItem $item): RedirectResponse
    {
        $item->delete();

        return redirect()->route('inventory.index')->with('success', 'Inventaris berhasil dihapus.');
    }
}
