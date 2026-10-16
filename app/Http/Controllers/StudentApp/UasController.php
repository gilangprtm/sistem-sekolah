<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\StudentExamAttempt;
use App\Models\UasLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UasController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();
        $period = $year?->periods()->where('status', 'active')->latest('id')->first();
        $now = now();
        $links = $period === null
            ? collect()
            : UasLink::query()
                ->with('subject:id,code,name')
                ->where('academic_year_id', $year->id)
                ->where('academic_period_id', $period->id)
                ->orderBy('category')
                ->orderBy('subject_id')
                ->get(['id', 'subject_id', 'category', 'link', 'opens_at'])
                ->map(function (UasLink $link) use ($student, $now): array {
                    $attempt = $student === null
                        ? null
                        : StudentExamAttempt::query()
                            ->where('student_id', $student->id)
                            ->where('uas_link_id', $link->id)
                            ->first(['interruption_count', 'status']);

                    return [
                        'id' => $link->id,
                        'category' => $link->category,
                        'subject' => $link->subject?->only(['code', 'name']),
                        'opens_at' => $link->opens_at === null ? null : CarbonImmutable::parse((string) $link->opens_at)->toIso8601String(),
                        'available' => $link->opens_at === null || $now->greaterThanOrEqualTo($link->opens_at),
                        'attempt' => $attempt?->only(['interruption_count', 'status']),
                    ];
                })
                ->values();

        return Inertia::render('student-app/uas', [
            'academicYear' => $year?->only(['year']),
            'academicPeriod' => $period?->only(['name']),
            'links' => $links->where('category', 'UAS')->values(),
            'utsLinks' => $links->where('category', 'UTS')->values(),
            'status' => $student === null ? 'student_unlinked' : ($period === null ? 'no_active_period' : 'ready'),
        ]);
    }

    public function open(Request $request, UasLink $uasLink): RedirectResponse
    {
        $student = $request->user()->student;
        $year = $student === null ? null : AcademicYear::query()->where('status', 'active')->latest('id')->first();
        $period = $year?->periods()->where('status', 'active')->latest('id')->first();

        abort_unless(
            $student !== null
                && $period !== null
                && $uasLink->academic_year_id === $year->id
                && $uasLink->academic_period_id === $period->id,
            404,
        );

        abort_if($uasLink->opens_at !== null && now()->lt($uasLink->opens_at), 403);
        $attempt = StudentExamAttempt::query()
            ->where('student_id', $student->id)
            ->where('uas_link_id', $uasLink->id)
            ->whereNotNull('started_at')
            ->first();

        abort_if($attempt?->status === 'locked', 423);
        abort_unless($attempt?->status === 'active', 403);

        return redirect()->away($uasLink->link);
    }
}
