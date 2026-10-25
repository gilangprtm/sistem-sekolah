<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\LmsCourse;
use App\Services\LmsCourseAccess;
use App\Services\LmsCourseContent;
use App\Services\LmsCourseResponses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CourseController extends Controller
{
    public function index(Request $request, LmsCourseAccess $access): SymfonyResponse
    {
        Gate::authorize('viewStudentCourses', LmsCourse::class);
        $period = $access->activePeriod();
        $courses = $period === null ? collect() : $access->availableToStudent($request->user())
            ->with(['subject:id,name', 'approvedRevision:id,course_id,title,description'])
            ->orderBy('subject_id')->orderBy('id')->get()
            ->map(fn (LmsCourse $course): array => [
                'id' => $course->id, 'title' => $course->approvedRevision->title,
                'description' => $course->approvedRevision->description, 'subject' => $course->subject->name,
                'grade_level' => $course->grade_level,
            ])->values();

        return $this->private(Inertia::render('student-app/courses/index', [
            'courses' => $courses, 'period' => $period?->only(['name']),
        ]), $request);
    }

    public function show(Request $request, LmsCourse $course, LmsCourseResponses $responses, LmsCourseContent $content, LmsCourseAccess $access): SymfonyResponse
    {
        Gate::authorize('viewStudentCourses', LmsCourse::class);
        $course = $access->availableToStudent($request->user())->with(['subject:id,name', 'approvedRevision'])->findOrFail($course->id);
        $context = $responses->studentContext($request->user(), $course);
        $attempt = $context['attempt'];
        $learningContent = $content->learningContent($context['revision'], $course, true);

        return $this->private(Inertia::render('student-app/courses/show', [
            'course' => [
                'id' => $course->id, 'title' => $context['revision']->title,
                'description' => $context['revision']->description, 'subject' => $course->subject->name,
                'grade_level' => $course->grade_level, 'revision_id' => $context['revision']->id,
                'revision_number' => $context['revision']->revision_number, 'content' => $learningContent,
                'status' => $attempt === null ? 'not_started' : $attempt->status,
                'answers' => (object) $responses->answers($attempt),
                'answerHtml' => (object) $responses->displayAnswers($attempt),
                'submitted_page_ids' => $responses->submittedPages($attempt, $context['revision']),
                'attempt_token' => $responses->token($attempt, $context['revision']),
            ],
        ]), $request);
    }

    public function save(Request $request, LmsCourse $course, LmsCourseResponses $responses): RedirectResponse
    {
        Gate::authorize('viewStudentCourses', LmsCourse::class);
        $data = $request->validate([
            'revision_id' => ['required', 'integer'], 'attempt_token' => ['required', 'string', 'size:64'],
            'page_id' => ['required', 'string', 'uuid'],
            'answers' => ['present', 'array', 'max:100'], 'answers.*' => ['nullable', 'string', 'max:30000'],
            'answer_formats' => ['sometimes', 'array', 'max:100'], 'answer_formats.*' => ['required', 'string', 'in:html'],
        ]);
        $responses->save($request->user(), $course, $data);

        return to_route('student-app.courses.show', $course)->with('success', 'Jawaban pada tab ini berhasil dikumpulkan.');
    }

    public function media(Request $request, LmsCourse $course, string $media, LmsCourseResponses $responses, LmsCourseContent $content): SymfonyResponse
    {
        Gate::authorize('viewStudentCourses', LmsCourse::class);
        $context = $responses->studentContext($request->user(), $course);

        return $content->mediaResponse($course, $context['revision'], $media);
    }

    private function private(Response $response, Request $request): SymfonyResponse
    {
        $response = $response->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
