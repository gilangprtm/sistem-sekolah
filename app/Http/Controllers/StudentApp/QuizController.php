<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentQuizAttempt;
use App\Models\TeacherQuiz;
use App\Services\StudentQuizAttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $this->student($request);
        $quizzes = TeacherQuiz::query()->where('is_open', true)
            ->with(['subject:id,code,name', 'creator:id,name', 'attempts' => fn ($query) => $query->where('student_id', $student->id)])
            ->latest('id')->paginate(20)->through(fn (TeacherQuiz $quiz): array => [
                'id' => $quiz->id,
                'subject' => $quiz->subject?->name,
                'teacher' => $quiz->creator?->name,
                'attempt' => $quiz->attempts->first()?->only(['status', 'interruption_count']),
            ]);

        return Inertia::render('student-app/quizzes/index', ['quizzes' => $quizzes]);
    }

    public function show(Request $request, TeacherQuiz $quiz, StudentQuizAttemptService $service): Response
    {
        $student = $this->student($request);
        $service->ensureOpen($quiz);
        $attempt = $quiz->attempts()->where('student_id', $student->id)->first();
        $reopen = $request->boolean('reopen') && $attempt !== null && $attempt->interruption_count > 0;
        $count = $attempt === null ? 0 : $attempt->interruption_count;
        $started = $attempt?->status === 'active' && $count > 0 && ! $reopen;

        return Inertia::render('student-app/quizzes/attempt', [
            'attemptId' => $attempt?->id,
            'linkId' => $quiz->id,
            'category' => 'Ulangan',
            'subject' => $quiz->subject?->name,
            'subjectCode' => $quiz->subject?->code,
            'formUrl' => $started ? $quiz->link : null,
            'started' => $started,
            'interruptionCount' => $count,
            'reopen' => $reopen,
            'locked' => $attempt?->status === 'locked',
        ]);
    }

    public function start(Request $request, TeacherQuiz $quiz, StudentQuizAttemptService $service): JsonResponse
    {
        $request->validate([
            'reopen' => ['sometimes', 'boolean'],
            'expected_count' => ['required', 'integer', 'min:0', 'max:4'],
        ]);
        $result = $service->start($this->student($request), $quiz, $request->boolean('reopen'), $request->integer('expected_count'));

        return $this->result($result);
    }

    public function event(Request $request, StudentQuizAttempt $attempt, StudentQuizAttemptService $service): JsonResponse
    {
        $this->ownsAttempt($request, $attempt);
        $data = $request->validate(['event_type' => ['required', Rule::in(['exam_page_hidden', 'exam_page_visible'])]]);

        return $this->result($service->recordInterruption($attempt, $data['event_type']));
    }

    public function complete(Request $request, StudentQuizAttempt $attempt, StudentQuizAttemptService $service): JsonResponse
    {
        $this->ownsAttempt($request, $attempt);
        $attempt = $service->complete($attempt);

        return response()->json(['status' => $attempt->status, 'interruptionCount' => $attempt->interruption_count]);
    }

    public function state(Request $request, TeacherQuiz $quiz): JsonResponse
    {
        $student = $this->student($request);
        $attempt = $quiz->attempts()->where('student_id', $student->id)->first();

        return response()->json(['open' => $quiz->is_open, 'locked' => $attempt?->status === 'locked'])
            ->header('Cache-Control', 'private, no-store');
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_if($student === null, 404);

        return $student;
    }

    private function ownsAttempt(Request $request, StudentQuizAttempt $attempt): void
    {
        abort_unless($this->student($request)->id === $attempt->student_id, 404);
    }

    /** @param array{attempt: StudentQuizAttempt, warning: bool, locked: bool} $result */
    private function result(array $result): JsonResponse
    {
        return response()->json([
            'attemptId' => $result['attempt']->id,
            'status' => $result['attempt']->status,
            'interruptionCount' => $result['attempt']->interruption_count,
            'warning' => $result['warning'],
            'locked' => $result['locked'],
        ], $result['locked'] ? 423 : 200);
    }
}
