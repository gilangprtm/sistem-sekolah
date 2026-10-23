<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCounselingNoteRequest;
use App\Models\CounselingNote;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class CounselingNoteController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CounselingNote::class);
        $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $search = $request->string('search')->trim()->toString();
        $notes = CounselingNote::query()
            ->select(['id', 'student_id', 'counseling_date', 'creator_name', 'updater_name', 'created_at', 'updated_at'])
            ->with('student:id,full_name,nisn')
            ->when($search !== '', fn ($query) => $query->whereHas('student', function ($query) use ($search): void {
                $query->whereLike('full_name', '%'.$search.'%')->orWhereLike('nisn', '%'.$search.'%');
            }))
            ->orderByDesc('counseling_date')->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (CounselingNote $note): array => $this->summary($note));

        Inertia::encryptHistory();

        $response = Inertia::render('counseling/notes/index', [
            'notes' => $notes,
            'filters' => ['search' => $search],
            'canCreate' => $request->user()->can('create', CounselingNote::class),
            'canUpdate' => $request->user()->can('update', new CounselingNote),
            'canDelete' => $request->user()->can('delete', new CounselingNote),
        ])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function students(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CounselingNote::class);
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

    public function show(CounselingNote $counselingNote): JsonResponse
    {
        Gate::authorize('view', $counselingNote);
        $counselingNote->load('student:id,full_name,nisn');

        return response()->json(['note' => [
            ...$this->summary($counselingNote), 'content' => $counselingNote->content,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveCounselingNoteRequest $request): JsonResponse
    {
        $note = CounselingNote::query()->create([
            ...$request->safe()->only(['student_id', 'counseling_date', 'content']),
            'created_by' => $request->user()->id,
            'creator_name' => $request->user()->name,
        ]);

        return response()->json(['id' => $note->id], 201)->header('Cache-Control', 'private, no-store');
    }

    public function update(SaveCounselingNoteRequest $request, CounselingNote $counselingNote): JsonResponse
    {
        $counselingNote->update([
            ...$request->safe()->only(['student_id', 'counseling_date', 'content']),
            'updated_by' => $request->user()->id,
            'updater_name' => $request->user()->name,
        ]);

        return response()->json(['id' => $counselingNote->id])->header('Cache-Control', 'private, no-store');
    }

    public function destroy(CounselingNote $counselingNote): JsonResponse
    {
        Gate::authorize('delete', $counselingNote);
        $counselingNote->delete();

        return response()->json(['deleted' => true])->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function summary(CounselingNote $note): array
    {
        return [
            'id' => $note->id,
            'student' => $note->student?->only(['id', 'full_name', 'nisn']),
            'counseling_date' => $note->getRawOriginal('counseling_date'),
            'creator_name' => $note->creator_name,
            'updater_name' => $note->updater_name,
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }
}
