<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
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

        $totalNilai = InventoryItem::query()
            ->get()
            ->sum(fn (InventoryItem $item) => (float) $item->harga * $item->units()->count());

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
        ]);
    }
}
