<?php

namespace App\Http\Controllers;

use App\Models\LmsCourse;
use App\Models\Subject;
use App\Services\LmsCourseAccess;
use App\Services\LmsCourseContent;
use App\Services\LmsCourseResponses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LmsMyCourseController extends Controller
{
    public function index(Request $request, LmsCourseAccess $access): Response
    {
        Gate::authorize('browseMyCourses', LmsCourse::class);
        $search = $request->string('search')->trim()->toString();
        $subjectId = $request->integer('subject_id');
        $gradeLevel = $request->string('grade_level')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $period = $access->activePeriod();

        $courses = $access->available($request->user())
            ->with(['subject:id,name', 'approvedRevision:id,course_id,title,description,revision_number'])
            ->when($subjectId > 0, fn (Builder $query) => $query->where('subject_id', $subjectId))
            ->when($gradeLevel !== '', fn (Builder $query) => $query->where('grade_level', $gradeLevel))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('approvedRevision', fn (Builder $revision) => $revision->where(function (Builder $matches) use ($search): void {
                $matches->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($search).'%']);
            })))
            ->orderBy('grade_level')->orderBy('subject_id')->orderBy('id')
            ->paginate($perPage)->withQueryString()
            ->through(fn (LmsCourse $course): array => [
                'id' => $course->id,
                'title' => $course->approvedRevision->title,
                'description' => $course->approvedRevision->description,
                'subject' => $course->subject->name,
                'grade_level' => $course->grade_level,
                'revision_number' => $course->approvedRevision->revision_number,
            ]);

        $response = Inertia::render('lms/my-courses/index', [
            'courses' => $courses,
            'filters' => ['search' => $search, 'subject_id' => $subjectId ?: null, 'grade_level' => $gradeLevel, 'per_page' => $perPage],
            'filterOptions' => [
                'subjects' => Subject::query()->whereIn('id', $access->available($request->user())->select('subject_id'))->orderBy('name')->get(['id', 'name']),
                'gradeLevels' => LmsCourse::GRADE_LEVELS,
            ],
            'activePeriod' => $period === null ? null : [
                'name' => $period->name,
                'academic_year' => $period->academicYear->year,
            ],
            'isAdmin' => $request->user()->hasRole('Super Admin'),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function show(Request $request, LmsCourse $course, LmsCourseAccess $access, LmsCourseContent $content, LmsCourseResponses $responses): Response
    {
        $course = $access->available($request->user())->with(['subject:id,name', 'approvedRevision'])->findOrFail($course->id);
        $revision = $course->approvedRevision;
        abort_if($revision === null, 404);

        $request->validate(['rombel_id' => ['sometimes', 'integer', 'min:1'], 'student_id' => ['sometimes', 'integer', 'min:1']]);
        $classes = $responses->classes($request->user(), $course);
        $rombelId = $request->integer('rombel_id') ?: ($classes[0]['id'] ?? null);
        $responseData = $rombelId === null ? ['students' => [], 'selected' => null] : $responses->response(
            $request->user(), $course, $rombelId, $request->integer('student_id') ?: null,
        );
        if ($responseData['selected'] !== null) {
            $responseData['selected']['content'] = $content->learningContent($responseData['selected']['revision'], $course);
            unset($responseData['selected']['revision']);
        }
        $response = Inertia::render('lms/my-courses/show', [
            'classes' => $classes, 'selectedClassId' => $rombelId,
            'students' => $responseData['students'], 'selectedResponse' => $responseData['selected'],
            'course' => [
                'id' => $course->id,
                'title' => $revision->title,
                'description' => $revision->description,
                'subject' => $course->subject->name,
                'grade_level' => $course->grade_level,
                'revision_number' => $revision->revision_number,
                'content' => $content->learningContent($revision, $course),
            ],
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function media(Request $request, LmsCourse $course, string $media, LmsCourseAccess $access, LmsCourseContent $content): StreamedResponse
    {
        $course = $access->available($request->user())->with('approvedRevision')->findOrFail($course->id);
        abort_if($course->approvedRevision === null, 404);

        return $content->mediaResponse($course, $course->approvedRevision, $media);
    }
}
