<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use Illuminate\Http\JsonResponse;

class InventoryDashboardApiController extends Controller
{
    /**
     * KPI dashboard inventaris (dihitung dari unit/register).
     */
    public function index(): JsonResponse
    {
        $totalAset = InventoryUnit::count();
        $totalNilai = InventoryItem::query()
            ->withCount('units')
            ->get()
            ->sum(fn ($item) => (int) $item->harga * $item->units_count);

        $baik = InventoryUnit::where('condition', 'B')->count();
        $kurangBaik = InventoryUnit::where('condition', 'KB')->count();
        $rusakBerat = InventoryUnit::where('condition', 'RB')->count();

        $statistikTahun = InventoryItem::query()
            ->select('tahun_pembelian')
            ->selectRaw('count(*) as total')
            ->whereNotNull('tahun_pembelian')
            ->groupBy('tahun_pembelian')
            ->orderBy('tahun_pembelian')
            ->get();

        $statistikAsal = InventoryItem::query()
            ->select('asal_perolehan')
            ->selectRaw('count(*) as total')
            ->whereNotNull('asal_perolehan')
            ->groupBy('asal_perolehan')
            ->orderBy('asal_perolehan')
            ->get();

        $statistikKondisi = [
            ['condition' => 'B', 'label' => 'Baik', 'total' => $baik],
            ['condition' => 'KB', 'label' => 'Kurang Baik', 'total' => $kurangBaik],
            ['condition' => 'RB', 'label' => 'Rusak Berat', 'total' => $rusakBerat],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard inventaris.',
            'data' => [
                'kpis' => [
                    'total_aset' => $totalAset,
                    'total_nilai' => $totalNilai,
                    'baik' => $baik,
                    'kurang_baik' => $kurangBaik,
                    'rusak_berat' => $rusakBerat,
                    'total_kelompok' => InventoryItem::count(),
                ],
                'statistik_tahun' => $statistikTahun,
                'statistik_asal' => $statistikAsal,
                'statistik_kondisi' => $statistikKondisi,
            ],
        ]);
    }
}
