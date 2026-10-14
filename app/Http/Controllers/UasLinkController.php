<?php

namespace App\Http\Controllers;

use App\Http\Requests\UasLinkRequest;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\UasLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UasLinkController extends Controller
{
    public function index(Request $request): Response
    {
        $activeYear = $this->activeYear();
        $years = AcademicYear::query()->orderByDesc('id')->get(['id', 'year']);
        $hasYearFilter = $request->query->has('academic_year_id');
        $selectedYearId = $hasYearFilter
            ? ($request->integer('academic_year_id') ?: null)
            : $activeYear?->id;
        $activePeriod = $activeYear?->periods()->where('status', 'active')->latest('id')->first();
        $search = $request->string('search')->trim()->toString();
        $category = in_array($request->query('category'), ['UTS', 'UAS'], true)
            ? $request->query('category')
            : 'UAS';
        $links = UasLink::query()
            ->with(['academicYear:id,year', 'academicPeriod:id,name,code', 'subject:id,code,name'])
            ->where('category', $category)
            ->when(! $hasYearFilter && $activeYear !== null && $activePeriod !== null, function ($query) use ($activeYear, $activePeriod): void {
                $query->where('academic_year_id', $activeYear->id)
                    ->where('academic_period_id', $activePeriod->id);
            })
            ->when(! $hasYearFilter && ($activeYear === null || $activePeriod === null), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($hasYearFilter && $selectedYearId !== null, fn ($query) => $query->where('academic_year_id', $selectedYearId))
            ->when($search !== '', fn ($query) => $query->whereHas('subject', function ($subjectQuery) use ($search): void {
                $subjectQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (UasLink $link): array => [
                'id' => $link->id,
                'category' => $link->category,
                'academic_year' => $link->academicYear?->year,
                'academic_period' => $link->academicPeriod?->name,
                'subject' => $link->subject?->only(['id', 'code', 'name']),
                'link' => $link->link,
                'opens_at' => $link->opens_at?->copy()->setTimezone('Asia/Makassar')->format('Y-m-d H:i'),
            ]);

        return Inertia::render('uas/index', [
            'links' => $links,
            'category' => $category,
            'activeContext' => $activeYear === null || $activePeriod === null ? null : [
                'academic_year_id' => $activeYear->id,
                'academic_year' => $activeYear->year,
                'academic_period_id' => $activePeriod->id,
                'academic_period' => $activePeriod->name,
            ],
            'years' => $years,
            'subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'academic_year_id' => $selectedYearId,
                'category' => $category,
                ...$request->only(['search', 'per_page']),
            ],
        ]);
    }

    public function store(UasLinkRequest $request): RedirectResponse
    {
        [$year, $period] = $this->activeContext();
        $this->ensureSubjectContext($request->integer('subject_id'), $period);
        UasLink::query()->updateOrCreate(
            [
                'academic_year_id' => $year->id,
                'academic_period_id' => $period->id,
                'subject_id' => $request->integer('subject_id'),
                'category' => $request->validated('category'),
            ],
            [
                'link' => $request->validated('link'),
                'opens_at' => $this->openingInstant($request),
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link UAS berhasil disimpan.']);

        return redirect()->route('uas.index', ['category' => $request->validated('category')]);
    }

    public function destroy(Request $request, UasLink $uasLink): RedirectResponse
    {
        $uasLink->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link UAS berhasil dihapus.']);
        $query = $request->only(['academic_year_id', 'search', 'per_page', 'page']);
        $category = $request->input('category');

        if (in_array($category, ['UTS', 'UAS'], true)) {
            $query['category'] = $category;
        }

        return redirect()->route('uas.index', $query);
    }

    public function update(UasLinkRequest $request, UasLink $uasLink): RedirectResponse
    {
        [$year, $period] = $this->activeContext();
        $this->ensureSubjectContext($request->integer('subject_id'), $period);
        $uasLink->update([
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'subject_id' => $request->integer('subject_id'),
            'category' => $request->validated('category'),
            'link' => $request->validated('link'),
            'opens_at' => $this->openingInstant($request),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link UAS berhasil diperbarui.']);

        return redirect()->route('uas.index', ['category' => $request->validated('category')]);
    }

    /** @return array{0: AcademicYear, 1: AcademicPeriod} */
    private function activeContext(): array
    {
        $year = $this->activeYear();
        $period = $year?->periods()->where('status', 'active')->latest('id')->first();

        if ($year === null || $period === null) {
            throw ValidationException::withMessages(['subject_id' => 'Tahun Ajaran dan semester aktif belum tersedia.']);
        }

        return [$year, $period];
    }

    private function activeYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('status', 'active')->latest('id')->first();
    }

    private function openingInstant(UasLinkRequest $request): ?string
    {
        $date = $request->validated('opens_at_date');
        $time = $request->validated('opens_at_time');

        if ($date === null || $time === null) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", 'Asia/Makassar')->utc()->toDateTimeString();
    }

    private function ensureSubjectContext(int $subjectId, AcademicPeriod $period): void
    {
        if (! Subject::query()->whereKey($subjectId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'Mata Pelajaran aktif tidak ditemukan.']);
        }

        if ($period->status !== 'active' || $period->academicYear?->status !== 'active') {
            throw ValidationException::withMessages(['subject_id' => 'Link UAS hanya dapat dikelola pada konteks aktif.']);
        }
    }
}
