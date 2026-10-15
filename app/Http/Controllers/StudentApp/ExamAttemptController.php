<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentExamAttempt;
use App\Models\UasLink;
use App\Services\StudentExamAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExamAttemptController extends Controller
{
    public function show(Request $request, UasLink $uasLink): Response|RedirectResponse
    {
        $student = $request->user()->student;
        $this->ensureAvailableToStudent($student, $uasLink);
        $attempt = StudentExamAttempt::query()
            ->where('student_id', $student->id)
            ->where('uas_link_id', $uasLink->id)
            ->whereNotNull('started_at')
            ->first();

        if ($attempt?->status === 'locked') {
            return Inertia::render('student-app/exam-locked', [
                'category' => $uasLink->category,
                'subject' => $uasLink->subject?->name,
                'subjectCode' => $uasLink->subject?->code,
                'interruptionCount' => $attempt->interruption_count,
            ]);
        }

        if ($attempt?->status !== 'active') {
            $attempt = null;
        }

        return Inertia::render('student-app/exam', [
            'attemptId' => $attempt?->id,
            'linkId' => $uasLink->id,
            'category' => $uasLink->category,
            'subject' => $uasLink->subject?->name,
            'subjectCode' => $uasLink->subject?->code,
            'formUrl' => $attempt === null ? null : $uasLink->link,
            'started' => $attempt !== null,
            'interruptionCount' => $attempt === null ? 0 : $attempt->interruption_count,
        ]);
    }

    public function start(Request $request, UasLink $uasLink, StudentExamAttemptService $service): JsonResponse
    {
        $student = $request->user()->student;
        $this->ensureAvailableToStudent($student, $uasLink);
        $attempt = $service->start($student, $uasLink);

        return response()->json(['attemptId' => $attempt->id, 'status' => $attempt->status]);
    }

    public function event(Request $request, StudentExamAttempt $attempt, StudentExamAttemptService $service): JsonResponse
    {
        abort_unless($request->user()->student?->id === $attempt->student_id, 404);
        $data = $request->validate([
            'event_type' => ['required', Rule::in(['exam_page_hidden', 'exam_page_visible'])],
        ]);
        $result = $service->recordInterruption($attempt, $data['event_type']);

        return response()->json([
            'status' => $result['attempt']->status,
            'interruptionCount' => $result['attempt']->interruption_count,
            'warning' => $result['warning'],
            'locked' => $result['locked'],
        ], $result['locked'] ? 423 : 200);
    }

    public function unlock(Request $request, StudentExamAttempt $attempt, StudentExamAttemptService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->unlock($attempt, $request->user()->id, $data['reason']);

        return back()->with('success', 'Percobaan ujian berhasil dibuka kembali.');
    }

    private function ensureAvailableToStudent(?Student $student, UasLink $uasLink): void
    {
        abort_if($student === null, 404);
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();
        $period = $year?->periods()->where('status', 'active')->latest('id')->first();

        abort_unless(
            $period !== null
                && $uasLink->academic_year_id === $year->id
                && $uasLink->academic_period_id === $period->id,
            404,
        );
        abort_if($uasLink->opens_at !== null && now()->lt($uasLink->opens_at), 403);
        $uasLink->loadMissing('subject:id,code,name');
    }
}
