<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
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
            ->withCount('units');

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

        $items = $query->with('units')->orderBy('kode_barang')->paginate(10)->withQueryString();

        // Transform: qty = jumlah unit, total = qty × harga
        $items->getCollection()->transform(function (InventoryItem $item) {
            $item->setAttribute('qty', $item->units->count());
            $item->setAttribute('total', (float) $item->harga * $item->units->count());

            return $item;
        });

        return Inertia::render('inventory/index', [
            'items' => $items,
            'filters' => $request->only(['search', 'tahun', 'kondisi', 'asal', 'satuan']),
            'filterOptions' => [
                'tahun' => InventoryItem::query()->select('tahun_pembelian')->distinct()->orderBy('tahun_pembelian', 'desc')->pluck('tahun_pembelian'),
                'asal' => InventoryItem::query()->select('asal_perolehan')->distinct()->whereNotNull('asal_perolehan')->orderBy('asal_perolehan')->pluck('asal_perolehan'),
                'satuan' => InventoryItem::query()->select('satuan')->distinct()->whereNotNull('satuan')->orderBy('satuan')->pluck('satuan'),
            ],
        ]);
    }

    /**
     * Form create inventaris.
     */
    public function create(): Response
    {
        return Inertia::render('inventory/create');
    }

    /**
     * Simpan inventaris baru + generate register.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_barang' => ['required', 'string', 'max:255', 'unique:inventory_items,kode_barang'],
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
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $this->registerGenerator->createItemWithUnits($data);

        return redirect()->route('inventory.index')->with('success', 'Inventaris berhasil ditambahkan.');
    }

    /**
     * Detail item + units.
     */
    public function show(InventoryItem $item): Response
    {
        $item->load('units');

        $item->setAttribute('qty', $item->units->count());
        $item->setAttribute('total', (float) $item->harga * $item->units->count());

        return Inertia::render('inventory/show', [
            'item' => $item,
        ]);
    }

    /**
     * Update keterangan (field mutable) — immutable fields dijaga.
     */
    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'keterangan' => ['nullable', 'string'],
        ]);

        // Hanya mutable field yang diupdate; immutable tidak pernah disentuh
        $item->update(['keterangan' => $data['keterangan'] ?? null]);

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
