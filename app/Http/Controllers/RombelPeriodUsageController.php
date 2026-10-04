<?php

namespace App\Http\Controllers;

use App\Http\Requests\RombelPeriodUsageRequest;
use App\Models\AcademicPeriod;
use App\Models\Rombel;
use App\Models\RombelPeriodUsage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RombelPeriodUsageController extends Controller
{
    public function index(Request $request): Response
    {
        $periods = AcademicPeriod::query()
            ->with('academicYear:id,year')
            ->where('status', 'active')
            ->orderByDesc('id')
            ->get(['id', 'academic_year_id', 'code', 'name']);
        $requestedPeriodId = $request->integer('academic_period_id');
        $selectedPeriod = $periods->firstWhere('id', $requestedPeriodId) ?? $periods->first();
        $selectedPeriodId = $selectedPeriod?->id;

        $usages = $selectedPeriod
            ? RombelPeriodUsage::query()
                ->with('rombel:id,code,name,grade_level,parallel_code,status')
                ->where('academic_period_id', $selectedPeriod->id)
                ->orderBy('rombel_id')
                ->get()
            : collect();
        $availableRombels = $selectedPeriod
            ? Rombel::query()
                ->where('status', 'active')
                ->whereDoesntHave('periodUsages', fn ($query) => $query->where('academic_period_id', $selectedPeriod->id))
                ->orderBy('grade_level')
                ->orderBy('parallel_code')
                ->get(['id', 'code', 'name', 'grade_level', 'parallel_code'])
            : collect();

        return Inertia::render('academic-period-rombels/index', [
            'periods' => $periods,
            'selectedPeriodId' => $selectedPeriodId,
            'usages' => $usages,
            'availableRombels' => $availableRombels,
        ]);
    }

    public function store(RombelPeriodUsageRequest $request): RedirectResponse
    {
        try {
            RombelPeriodUsage::query()->create($request->validatedForUsage());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'rombel_id' => 'Rombel sudah digunakan pada semester tersebut.',
            ]);
        }

        return redirect()
            ->route('academic-period-rombels.index', ['academic_period_id' => $request->validated('academic_period_id')])
            ->with('success', 'Rombel berhasil digunakan pada semester tersebut.');
    }

    public function destroy(RombelPeriodUsage $rombelPeriodUsage): RedirectResponse
    {
        $periodId = $rombelPeriodUsage->academic_period_id;
        $rombelPeriodUsage->delete();

        return redirect()
            ->route('academic-period-rombels.index', ['academic_period_id' => $periodId])
            ->with('success', 'Penggunaan Rombel berhasil dilepas.');
    }
}
