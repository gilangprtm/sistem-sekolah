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
use Inertia\Inertia;
use Inertia\Response;

class KantinSaldoController extends Controller
{
    /** @var list<string> */
    private const PERIODS = ['today', 'week', 'month', 'year', 'all'];

    public function __construct(private readonly KantinSaldoService $saldoService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('kantin.saldo.view'), 403);
        abort_unless($request->user()?->can('kantin.saldo.history.view'), 403);

        $search = $request->string('search')->trim()->toString();
        $period = $request->string('period')->trim()->toString();
        $period = in_array($period, self::PERIODS, true) ? $period : 'all';
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $canTopUp = $request->user()?->can('kantin.saldo.topup') ?? false;
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

        $ledger = (clone $ledgerQuery)
            ->with('student:id,nis,nisn,full_name')
            ->latest()
            ->paginate($perPage, ['*'], 'ledger_page')
            ->withQueryString();

        return Inertia::render('kantin/saldo/index', [
            'students' => $students,
            'canTopUp' => $canTopUp,
            'ledger' => $ledger,
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
        $this->saldoService->topUp($student, $request->validated(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Saldo siswa berhasil ditambahkan.',
        ]);

        return redirect()->route('kantin.saldo.index');
    }
}
