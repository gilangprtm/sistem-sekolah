<?php

namespace App\Http\Controllers;

use App\Models\KantinBarang;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KantinDashboardController extends Controller
{
    /** @var list<string> */
    private const PERIODS = ['today', 'week', 'month', 'year', 'all'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('kantin.dashboard.view'), 403);

        $period = $request->string('period')->trim()->toString();
        $period = in_array($period, self::PERIODS, true) ? $period : 'today';
        $now = Carbon::now();
        $periodStart = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
            default => null,
        };

        $sales = DB::table('tr_kantin_penjualan as sales')
            ->when($periodStart !== null, fn ($query) => $query
                ->where('sales.created_at', '>=', $periodStart)
                ->where('sales.created_at', '<=', $now));

        $summary = (clone $sales)
            ->selectRaw('COUNT(*) as transactions, COALESCE(SUM(total), 0) as revenue, COUNT(DISTINCT student_id) as buyers')
            ->first();

        $itemsSold = DB::table('tr_kantin_penjualan_detail as details')
            ->join('tr_kantin_penjualan as sales', 'sales.id', '=', 'details.penjualan_id')
            ->when($periodStart !== null, fn ($query) => $query
                ->where('sales.created_at', '>=', $periodStart)
                ->where('sales.created_at', '<=', $now))
            ->sum('details.quantity');

        $transactions = (clone $sales)
            ->join('m_students as students', 'students.id', '=', 'sales.student_id')
            ->select('sales.id', 'sales.total', 'sales.created_at', 'students.full_name', 'students.nis', 'students.nisn')
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id')
            ->limit(10)
            ->get();

        $topBuyers = (clone $sales)
            ->join('m_students as students', 'students.id', '=', 'sales.student_id')
            ->select('students.id', 'students.full_name', 'students.nis', 'students.nisn')
            ->selectRaw('COUNT(sales.id) as transactions, SUM(sales.total) as total')
            ->groupBy('students.id', 'students.full_name', 'students.nis', 'students.nisn')
            ->orderByDesc('total')
            ->orderBy('students.full_name')
            ->orderBy('students.id')
            ->limit(10)
            ->get();

        $topItems = DB::table('tr_kantin_penjualan_detail as details')
            ->join('tr_kantin_penjualan as sales', 'sales.id', '=', 'details.penjualan_id')
            ->join('m_kantin_barang as products', 'products.id', '=', 'details.kantin_barang_id')
            ->when($periodStart !== null, fn ($query) => $query
                ->where('sales.created_at', '>=', $periodStart)
                ->where('sales.created_at', '<=', $now))
            ->select('products.id', 'products.name', 'products.satuan')
            ->selectRaw('SUM(details.quantity) as quantity, SUM(details.subtotal) as total')
            ->groupBy('products.id', 'products.name', 'products.satuan')
            ->orderByDesc('quantity')
            ->orderBy('products.name')
            ->orderBy('products.id')
            ->limit(10)
            ->get();

        return Inertia::render('kantin/dashboard', [
            'period' => $period,
            'summary' => [
                'revenue' => $summary?->revenue ?? '0.00',
                'transactions' => (int) ($summary?->transactions ?? 0),
                'buyers' => (int) ($summary?->buyers ?? 0),
                'itemsSold' => (int) $itemsSold,
                'activeItems' => KantinBarang::query()->where('status', 'active')->count(),
            ],
            'transactions' => $transactions,
            'topBuyers' => $topBuyers,
            'topItems' => $topItems,
        ]);
    }
}
