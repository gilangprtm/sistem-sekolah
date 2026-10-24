<?php

namespace App\Http\Controllers;

use App\Http\Requests\LmsCourseTransitionRequest;
use App\Models\LmsCourse;
use App\Models\LmsCourseRevision;
use App\Models\Subject;
use App\Services\LmsCourseContent;
use App\Services\LmsCourseLifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LmsCourseManagementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage', LmsCourse::class);
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $subjectId = $request->integer('subject_id');
        $gradeLevel = $request->string('grade_level')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        if ($status === '' && $request->boolean('archived')) {
            $status = 'archived';
        }
        $statuses = ['draft', 'active', 'archived'];

        $courses = LmsCourse::query()
            ->with(['subject:id,name', 'designer:id,full_name', 'latestRevision', 'approvedRevision'])
            ->when(in_array($status, $statuses, true), function (Builder $query) use ($status): void {
                if ($status === 'archived') {
                    $query->whereNotNull('archived_at');

                    return;
                }

                $query->whereNull('archived_at');
                if ($status === 'active') {
                    $query->whereNotNull('approved_revision_id');

                    return;
                }

                $query->whereNull('approved_revision_id');
            })
            ->when($subjectId > 0, fn (Builder $query) => $query->where('subject_id', $subjectId))
            ->when($gradeLevel !== '', fn (Builder $query) => $query->where('grade_level', $gradeLevel))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('latestRevision', fn (Builder $revision) => $revision->where(function (Builder $matches) use ($search): void {
                $matches->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($search).'%']);
            })))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            ->through(fn (LmsCourse $course): array => [
                'id' => $course->id,
                'title' => $course->latestRevision->title ?? $course->title,
                'active_title' => $course->approvedRevision?->title,
                'description' => $course->latestRevision?->description,
                'subject' => $course->subject->name,
                'grade_level' => $course->grade_level,
                'designer' => $course->designer->full_name,
                'revision_number' => $course->latestRevision?->revision_number,
                'status' => $course->archived_at !== null
                    ? 'archived'
                    : ($course->approved_revision_id !== null ? 'active' : 'draft'),
                'approved_revision_number' => $course->approvedRevision?->revision_number,
                'archived_at' => $course->archived_at?->toIso8601String(),
                'revision_id' => $course->latestRevision?->editToken($course),
                'updated_at' => $course->updated_at?->toIso8601String(),
                'can_activate' => $request->user()->can('activate', $course),
                'can_archive' => $request->user()->can('archive', $course),
                'can_restore' => $request->user()->can('restore', $course),
                'can_revise' => $request->user()->can('revise', $course),
            ]);

        return Inertia::render('lms/management/index', [
            'courses' => $courses,
            'filters' => ['search' => $search, 'status' => in_array($status, $statuses, true) ? $status : '', 'subject_id' => $subjectId ?: null, 'grade_level' => $gradeLevel, 'per_page' => $perPage],
            'filterOptions' => ['subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'gradeLevels' => LmsCourse::GRADE_LEVELS],
        ]);
    }

    public function show(Request $request, LmsCourse $course): Response
    {
        Gate::authorize('inspect', $course);
        $course->load(['subject:id,name', 'designer:id,full_name', 'revisions' => fn ($query) => $query->with('designer:id,full_name')->orderByDesc('revision_number'), 'approvedRevision']);

        return Inertia::render('lms/management/show', [
            'course' => [
                'id' => $course->id, 'subject' => $course->subject->name, 'grade_level' => $course->grade_level,
                'designer' => $course->designer->full_name, 'archived_at' => $course->archived_at?->toIso8601String(),
                'approved_revision_id' => $course->approved_revision_id,
                'revisions' => $course->revisions->map(fn (LmsCourseRevision $revision): array => [
                    'id' => $revision->id, 'number' => $revision->revision_number, 'title' => $revision->title,
                    'description' => $revision->description,
                    'status' => $course->archived_at !== null
                        ? 'archived'
                        : ($revision->id === $course->approved_revision_id ? 'active' : ($revision->status === LmsCourseRevision::STATUS_APPROVED ? 'archived' : 'draft')),
                    'content' => app(LmsCourseContent::class)->editorContent($revision, $course, true), 'designer' => $revision->designer->full_name,
                    'created_at' => $revision->created_at?->toIso8601String(),
                    'is_active' => $course->archived_at === null && $revision->id === $course->approved_revision_id,
                    'revision_id' => $revision->editToken($course),
                ])->values(),
            ],
            'actions' => ['can_activate' => $request->user()->can('activate', $course), 'can_archive' => $request->user()->can('archive', $course), 'can_restore' => $request->user()->can('restore', $course), 'can_design' => $request->user()->can('view', $course)],
        ]);
    }

    public function media(Request $request, LmsCourse $course, LmsCourseRevision $revision, string $media, LmsCourseContent $service): StreamedResponse
    {
        abort_unless($request->user()->can('inspect', $course), 404);

        return $service->mediaResponse($course, $revision, $media);
    }

    public function activate(LmsCourseTransitionRequest $request, LmsCourse $course, LmsCourseLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->activate($request->user(), $course, $request->validated('revision_id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Course berhasil diaktifkan.']);

        return redirect()->route('lms.management');
    }

    public function restore(LmsCourseTransitionRequest $request, LmsCourse $course, LmsCourseLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->restore($request->user(), $course, $request->validated('revision_id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Course berhasil dipulihkan sebagai Draft.']);

        return redirect()->route('lms.management');
    }

    public function archive(LmsCourseTransitionRequest $request, LmsCourse $course, LmsCourseLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->archive($request->user(), $course, $request->validated('revision_id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Course berhasil diarsipkan.']);

        return redirect()->route('lms.management');
    }
}
