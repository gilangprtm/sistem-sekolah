<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\KantinSaldo;
use App\Models\KantinSaldoTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KantinController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;
        $purchasesPerPage = min(max($request->integer('purchases_per_page', 10), 1), 50);
        $topupsPerPage = min(max($request->integer('topups_per_page', 10), 1), 50);

        if ($student === null) {
            $purchases = $this->emptyPaginator($request, $purchasesPerPage, 'purchases_page');
            $topups = $this->emptyPaginator($request, $topupsPerPage, 'topups_page');
            $balance = '0.00';
        } else {
            $balance = KantinSaldo::query()
                ->where('student_id', $student->id)
                ->value('saldo') ?? '0.00';
            $purchases = $this->purchases($request, $student->id, $purchasesPerPage);
            $topups = $this->topups($request, $student->id, $topupsPerPage);
        }

        return Inertia::render('student-app/kantin', [
            'has_kantin_pin' => $student?->kantin_pin_hash !== null,
            'balance' => ['saldo' => (string) $balance],
            'purchases' => Inertia::scroll($purchases),
            'topups' => Inertia::scroll($topups),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, date: string, total: string, details: array<int, array{barang_name: string, quantity: int, harga: string, subtotal: string}>}>
     */
    private function purchases(Request $request, int $studentId, int $perPage): LengthAwarePaginator
    {
        $rawPurchases = DB::table('tr_kantin_penjualan')
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'created_at', 'total'], 'purchases_page');
        /** @var Collection<int, object{id: int, created_at: string, total: string}> $purchaseItems */
        $purchaseItems = collect($rawPurchases->items());
        $purchaseIds = $purchaseItems->pluck('id')->map(static fn (mixed $id): int => (int) $id);
        $detailsByPurchase = DB::table('tr_kantin_penjualan_detail as details')
            ->join('m_kantin_barang as products', 'products.id', '=', 'details.kantin_barang_id')
            ->whereIn('details.penjualan_id', $purchaseIds->all())
            ->orderBy('details.id')
            ->get([
                'details.penjualan_id',
                'products.name as barang_name',
                'details.quantity',
                'details.harga',
                'details.subtotal',
            ])
            ->groupBy('penjualan_id');
        /** @var Collection<int, Collection<int, array<string, mixed>>> $detailsByPurchase */
        $detailsByPurchase = $detailsByPurchase->map(
            static fn (Collection $details): Collection => $details->map(
                static fn (object $detail): array => get_object_vars($detail),
            ),
        );

        $data = $purchaseItems->map(function (object $purchase) use ($detailsByPurchase): array {
            /** @var Collection<int, array<string, mixed>> $details */
            $details = $detailsByPurchase->get((int) $purchase->id, collect());

            return [
                'id' => (int) $purchase->id,
                'date' => $this->utcTimestamp($purchase->created_at),
                'total' => (string) $purchase->total,
                'details' => $details->map(static fn (array $detail): array => [
                    'barang_name' => (string) ($detail['barang_name'] ?? ''),
                    'quantity' => (int) ($detail['quantity'] ?? 0),
                    'harga' => (string) ($detail['harga'] ?? '0.00'),
                    'subtotal' => (string) ($detail['subtotal'] ?? '0.00'),
                ])->values()->all(),
            ];
        })->values();

        return new LengthAwarePaginator(
            $data->all(),
            $rawPurchases->total(),
            $rawPurchases->perPage(),
            $rawPurchases->currentPage(),
            ['path' => $request->url(), 'pageName' => 'purchases_page'],
        );
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, date: string, amount: string, balance_before: string, balance_after: string}>
     */
    private function topups(Request $request, int $studentId, int $perPage): LengthAwarePaginator
    {
        $rawTopups = KantinSaldoTransaction::query()
            ->where('student_id', $studentId)
            ->where('type', 'top_up')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'created_at', 'amount', 'balance_before', 'balance_after'], 'topups_page');

        $data = collect($rawTopups->items())->map(fn (KantinSaldoTransaction $topup): array => [
            'id' => $topup->id,
            'date' => $this->utcTimestamp($topup->created_at),
            'amount' => (string) $topup->amount,
            'balance_before' => (string) $topup->balance_before,
            'balance_after' => (string) $topup->balance_after,
        ])->values();

        return new LengthAwarePaginator(
            $data->all(),
            $rawTopups->total(),
            $rawTopups->perPage(),
            $rawTopups->currentPage(),
            ['path' => $request->url(), 'pageName' => 'topups_page'],
        );
    }

    private function utcTimestamp(mixed $value): string
    {
        return Carbon::parse($value)->utc()->toRfc3339String();
    }

    /** @return LengthAwarePaginator<int, never> */
    private function emptyPaginator(Request $request, int $perPage, string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            $perPage,
            $request->integer($pageName, 1),
            ['path' => $request->url(), 'pageName' => $pageName],
        );
    }
}
