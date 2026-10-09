<?php

namespace App\Http\Controllers;

use App\Http\Requests\KantinBarangRequest;
use App\Models\KantinBarang;
use App\Models\KantinCategory;
use App\Services\KantinBarangService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KantinBarangController extends Controller
{
    public function __construct(private readonly KantinBarangService $barangService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('kantin.barang.view'), 403);
        abort_unless($request->user()?->can('kantin.kategori.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $barangs = KantinBarang::query()
            ->with('category:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('kode_barang', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('kode_barang')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('kantin/barang/index', [
            'barangs' => $barangs,
            'categories' => KantinCategory::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'per_page']),
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'inactive', 'label' => 'Arsip'],
                ],
            ],
        ]);
    }

    public function store(KantinBarangRequest $request): RedirectResponse
    {
        $this->authorizeCategoryInput($request);
        $this->barangService->create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Barang Kantin berhasil dibuat.',
        ]);

        return redirect()->route('kantin.barang.index');
    }

    public function update(KantinBarangRequest $request, KantinBarang $kantinBarang): RedirectResponse
    {
        $this->authorizeCategoryInput($request);
        $this->barangService->update($kantinBarang, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Barang Kantin berhasil diperbarui.',
        ]);

        return redirect()->route('kantin.barang.index');
    }

    public function archive(Request $request, KantinBarang $kantinBarang): RedirectResponse
    {
        abort_unless($request->user()?->can('kantin.barang.delete'), 403);
        $kantinBarang->update(['status' => 'inactive']);

        return redirect()->route('kantin.barang.index')->with('success', 'Barang Kantin diarsipkan.');
    }

    private function authorizeCategoryInput(KantinBarangRequest $request): void
    {
        if ($request->filled('category_id')) {
            abort_unless($request->user()?->can('kantin.kategori.view'), 403);

            return;
        }

        if ($request->filled('category_name')) {
            abort_unless($request->user()?->can('kantin.kategori.create'), 403);
        }
    }
}
