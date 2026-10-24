<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStudentViolationRequest;
use App\Models\Student;
use App\Models\StudentAffairsViolationCategory;
use App\Models\StudentViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class StudentViolationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StudentViolation::class);
        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $search = $request->string('search')->trim()->toString();
        $notes = StudentViolation::query()
            ->select(['id', 'student_id', 'violation_date', 'creator_name', 'updater_name', 'created_at', 'updated_at'])
            ->with('student:id,full_name,nisn')
            ->when($search !== '', fn ($query) => $query->whereHas('student', function ($query) use ($search): void {
                $query->whereLike('full_name', '%'.$search.'%')->orWhereLike('nisn', '%'.$search.'%');
            }))
            ->orderByDesc('violation_date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (StudentViolation $note): array => $this->summary($note));

        $response = Inertia::render('student-affairs/violations/index', [
            'notes' => $notes,
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', StudentViolation::class),
            'canUpdate' => $request->user()->can('update', new StudentViolation),
            'canDelete' => $request->user()->can('delete', new StudentViolation),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function students(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StudentViolation::class);
        $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $search = $request->string('search')->trim()->toString();
        $students = Student::query()->select(['id', 'full_name', 'nisn'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('full_name', '%'.$search.'%')->orWhereLike('nisn', '%'.$search.'%');
                });
            })->orderBy('full_name')->orderBy('id')->limit(30)->get();

        return response()->json(['students' => $students])->header('Cache-Control', 'private, no-store');
    }

    public function categories(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StudentViolation::class);
        $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $search = $request->string('search')->trim()->toString();
        $categories = StudentAffairsViolationCategory::query()
            ->select(['id', 'name'])
            ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.$search.'%'))
            ->orderBy('name')->orderBy('id')->limit(30)->get();

        return response()->json(['categories' => $categories])->header('Cache-Control', 'private, no-store');
    }

    public function show(StudentViolation $studentViolation): JsonResponse
    {
        Gate::authorize('view', $studentViolation);
        $studentViolation->load('student:id,full_name,nisn');

        return response()->json(['note' => [
            ...$this->summary($studentViolation),
            'category' => $studentViolation->category,
            'content' => $studentViolation->content,
            'follow_up' => $studentViolation->follow_up,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveStudentViolationRequest $request): JsonResponse
    {
        $note = DB::transaction(function () use ($request): StudentViolation {
            $category = StudentAffairsViolationCategory::resolveName($request->validated('category'));
            $note = new StudentViolation([
                ...$request->safe()->only(['student_id', 'violation_date', 'content']),
                'category' => $category->name,
                'follow_up' => $request->validated('follow_up'),
                'created_by' => $request->user()->id,
                'creator_name' => $request->user()->name,
            ]);
            $note->violationCategory()->associate($category);
            $note->save();

            return $note;
        });

        return response()->json(['id' => $note->id], 201)->header('Cache-Control', 'private, no-store');
    }

    public function update(SaveStudentViolationRequest $request, StudentViolation $studentViolation): JsonResponse
    {
        DB::transaction(function () use ($request, $studentViolation): void {
            $category = StudentAffairsViolationCategory::resolveName($request->validated('category'));
            $studentViolation->fill([
                ...$request->safe()->only(['student_id', 'violation_date', 'content']),
                'category' => $category->name,
                'follow_up' => $request->validated('follow_up'),
                'updated_by' => $request->user()->id,
                'updater_name' => $request->user()->name,
            ]);
            $studentViolation->violationCategory()->associate($category);
            $studentViolation->save();
        });

        return response()->json(['id' => $studentViolation->id])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(StudentViolation $studentViolation): JsonResponse
    {
        Gate::authorize('delete', $studentViolation);
        $studentViolation->delete();

        return response()->json(['deleted' => true])->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function summary(StudentViolation $note): array
    {
        return [
            'id' => $note->id,
            'student' => $note->student?->only(['id', 'full_name', 'nisn']),
            'violation_date' => $note->getRawOriginal('violation_date'),
            'creator_name' => $note->creator_name,
            'updater_name' => $note->updater_name,
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }
}
