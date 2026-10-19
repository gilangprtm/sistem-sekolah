<?php

namespace App\Http\Controllers;

use App\Http\Requests\KantinTopUpRequest;
use App\Models\KantinSaldo;
use App\Models\KantinSaldoTransaction;
use App\Models\Student;
use App\Services\KantinSaldoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KantinSaldoController extends Controller
{
    /** @var list<string> */
    private const PERIODS = ['today', 'week', 'month', 'year', 'all'];

    public function __construct(private readonly KantinSaldoService $saldoService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('kantin.saldo.view'), 403);
        abort_unless($request->user()->can('kantin.saldo.history.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $period = $request->string('period')->trim()->toString();
        $period = in_array($period, self::PERIODS, true) ? $period : 'all';
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $canTopUp = $request->user()->can('kantin.saldo.topup');
        $now = Carbon::now();
        $periodStart = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->startOfWeek(Carbon::MONDAY),
            'month' => $now->copy()->startOfMonth(),
            'year' => $now->copy()->startOfYear(),
            default => null,
        };
        $students = Student::query()
            ->with('kantinSaldo:id,student_id,saldo')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();

        $ledgerQuery = KantinSaldoTransaction::query()
            ->when($periodStart !== null, fn ($query) => $query
                ->where('created_at', '>=', $periodStart)
                ->where('created_at', '<=', $now))
            ->when($search !== '', fn ($query) => $query->whereHas('student', function ($studentQuery) use ($search): void {
                $studentQuery->where('full_name', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%");
            }));

        $topUpSummary = (clone $ledgerQuery)
            ->where('type', 'top_up')
            ->selectRaw('COUNT(*) as top_up_count, COALESCE(SUM(amount), 0) as top_up_amount')
            ->first();

        $mapTransaction = static function (KantinSaldoTransaction $transaction): array {
            return [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => (string) $transaction->amount,
                'balance_before' => (string) $transaction->balance_before,
                'balance_after' => (string) $transaction->balance_after,
                'created_at' => $transaction->created_at->utc()->toRfc3339String(),
                'student' => [
                    'nis' => $transaction->student?->nis,
                    'full_name' => $transaction->student?->full_name,
                ],
            ];
        };

        $purchaseHistory = DB::table('tr_kantin_penjualan as sales')
            ->join('m_students as students', 'students.id', '=', 'sales.student_id')
            ->when($periodStart !== null, fn ($query) => $query
                ->where('sales.created_at', '>=', $periodStart)
                ->where('sales.created_at', '<=', $now))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('students.full_name', 'like', "%{$search}%")
                    ->orWhere('students.nis', 'like', "%{$search}%")
                    ->orWhere('students.nisn', 'like', "%{$search}%");
            }))
            ->select([
                'sales.id',
                'sales.student_id',
                'sales.total as amount',
                'sales.created_at',
                'students.nis',
                'students.full_name',
            ])
            ->orderByDesc('sales.created_at')
            ->orderByDesc('sales.id')
            ->paginate($perPage, ['*'], 'purchase_page')
            ->withQueryString();

        $purchaseIds = collect($purchaseHistory->items())->pluck('id')->all();
        $purchaseDetails = DB::table('tr_kantin_penjualan_detail as details')
            ->join('m_kantin_barang as products', 'products.id', '=', 'details.kantin_barang_id')
            ->whereIn('details.penjualan_id', $purchaseIds)
            ->orderBy('details.id')
            ->get([
                'details.penjualan_id',
                'products.name as barang_name',
                'details.quantity',
                'details.harga',
                'details.subtotal',
            ])
            ->groupBy('penjualan_id');

        $purchaseHistory->through(function (object $sale) use ($purchaseDetails): array {
            return [
                'id' => (int) $sale->id,
                'type' => 'purchase',
                'amount' => number_format((float) $sale->amount, 2, '.', ''),
                'balance_before' => null,
                'balance_after' => null,
                'created_at' => Carbon::parse($sale->created_at)->utc()->toRfc3339String(),
                'student' => [
                    'nis' => $sale->nis,
                    'full_name' => $sale->full_name,
                ],
                'details' => collect($purchaseDetails->get($sale->id, []))->map(static fn (object $detail): array => [
                    'barang_name' => (string) $detail->barang_name,
                    'quantity' => (int) $detail->quantity,
                    'harga' => number_format((float) $detail->harga, 2, '.', ''),
                    'subtotal' => number_format((float) $detail->subtotal, 2, '.', ''),
                ])->values()->all(),
            ];
        });

        $topUpHistory = (clone $ledgerQuery)
            ->where('type', 'top_up')
            ->with('student:id,nis,nisn,full_name')
            ->latest()
            ->paginate($perPage, ['*'], 'top_up_page')
            ->through($mapTransaction)
            ->withQueryString();

        return Inertia::render('kantin/saldo/index', [
            'students' => $students,
            'canTopUp' => $canTopUp,
            'purchaseHistory' => $purchaseHistory,
            'topUpHistory' => $topUpHistory,
            'filters' => $request->only(['search', 'period', 'per_page']),
            'summary' => [
                'students' => Student::query()->count(),
                'balance' => KantinSaldo::query()->sum('saldo'),
                'transactions' => KantinSaldoTransaction::query()->count(),
                'topUp' => [
                    'count' => (int) ($topUpSummary?->getAttribute('top_up_count') ?? 0),
                    'amount' => $topUpSummary?->getAttribute('top_up_amount') ?? '0.00',
                ],
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('kantin.saldo.topup'), 403);

        $search = $request->string('search')->trim()->toString();
        $students = Student::query()
            ->where('status', 'active')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->limit(25)
            ->get(['id', 'nis', 'nisn', 'full_name']);

        return response()->json($students);
    }

    public function storeTopUp(KantinTopUpRequest $request): RedirectResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $this->saldoService->topUp(
            $student,
            ['amount' => (float) $request->validated('amount')],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Saldo siswa berhasil ditambahkan.',
        ]);

        return redirect()->route('kantin.saldo.index');
    }
}
