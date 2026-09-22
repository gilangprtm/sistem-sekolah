<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryDashboardController extends Controller
{
    /**
     * Dashboard inventaris — KPI + statistik (dihitung dari unit).
     */
    public function index(): Response
    {
        $totalUnits = InventoryUnit::count();
        $totalItems = InventoryItem::count();

        $totalNilai = DB::table('tr_inventory_items as items')
            ->leftJoin('tr_inventory_units as units', 'units.inventory_item_id', '=', 'items.id')
            ->selectRaw('COALESCE(SUM(CASE WHEN units.id IS NOT NULL THEN items.harga ELSE 0 END), 0) as total_value')
            ->value('total_value');

        $categoryStats = DB::table('m_inventory_categories as categories')
            ->leftJoin('tr_inventory_items as items', 'items.inventory_category_id', '=', 'categories.id')
            ->leftJoin('tr_inventory_units as units', 'units.inventory_item_id', '=', 'items.id')
            ->select([
                'categories.id',
                'categories.name',
                DB::raw('COUNT(DISTINCT items.id) as total_items'),
                DB::raw('COUNT(units.id) as total_units'),
                DB::raw('COALESCE(SUM(items.harga), 0) as total_value'),
            ])
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('categories.name')
            ->get();

        $uncategorized = DB::table('tr_inventory_items as items')
            ->leftJoin('tr_inventory_units as units', 'units.inventory_item_id', '=', 'items.id')
            ->whereNull('items.inventory_category_id')
            ->selectRaw('COUNT(DISTINCT items.id) as total_items, COUNT(units.id) as total_units, COALESCE(SUM(items.harga), 0) as total_value')
            ->first();

        $categoryStats->push((object) [
            'id' => null,
            'name' => 'Tanpa kategori',
            'total_items' => (int) ($uncategorized->total_items ?? 0),
            'total_units' => (int) ($uncategorized->total_units ?? 0),
            'total_value' => $uncategorized->total_value ?? '0.00',
        ]);

        $kondisiCounts = InventoryUnit::query()
            ->selectRaw('condition, count(*) as total')
            ->groupBy('condition')
            ->pluck('total', 'condition');

        $statistikTahun = InventoryItem::query()
            ->selectRaw('tahun_pembelian, count(*) as total')
            ->whereNotNull('tahun_pembelian')
            ->groupBy('tahun_pembelian')
            ->orderBy('tahun_pembelian')
            ->get();

        $statistikAsal = InventoryItem::query()
            ->selectRaw('asal_perolehan, count(*) as total')
            ->whereNotNull('asal_perolehan')
            ->groupBy('asal_perolehan')
            ->orderBy('asal_perolehan')
            ->get();

        $statistikKondisi = [
            ['condition' => 'B', 'label' => 'Baik', 'total' => $kondisiCounts['B'] ?? 0],
            ['condition' => 'KB', 'label' => 'Kurang Baik', 'total' => $kondisiCounts['KB'] ?? 0],
            ['condition' => 'RB', 'label' => 'Rusak Berat', 'total' => $kondisiCounts['RB'] ?? 0],
        ];

        return Inertia::render('inventory/dashboard', [
            'kpis' => [
                'total_aset' => $totalUnits,
                'total_nilai' => round($totalNilai, 2),
                'baik' => $kondisiCounts['B'] ?? 0,
                'kurang_baik' => $kondisiCounts['KB'] ?? 0,
                'rusak_berat' => $kondisiCounts['RB'] ?? 0,
                'total_kelompok' => $totalItems,
            ],
            'statistik_tahun' => $statistikTahun,
            'statistik_asal' => $statistikAsal,
            'statistik_kondisi' => $statistikKondisi,
            'category_stats' => $categoryStats,
        ]);
    }
}
