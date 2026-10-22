<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentNoteRequest;
use App\Models\Student;
use App\Models\TeacherStudentNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class StudentNoteController extends Controller
{
    public function index(Request $request, Student $student): JsonResponse
    {
        Gate::authorize('viewAny', TeacherStudentNote::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $notes = TeacherStudentNote::query()->where('student_id', $student->id)
            ->orderByDesc('id')->paginate(20, ['id', 'teacher_name', 'content', 'created_at'])
            ->through(fn (TeacherStudentNote $note): array => $this->note($note));

        return response()->json([
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'photo_url' => $student->photo_path === null ? null : Storage::disk('public')->url($student->photo_path),
            ],
            'can_create' => $request->user()->can('create', TeacherStudentNote::class),
            'notes' => [
                'data' => $notes->items(),
                'current_page' => $notes->currentPage(),
                'last_page' => $notes->lastPage(),
                'total' => $notes->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreStudentNoteRequest $request, Student $student): JsonResponse
    {
        $teacher = $request->user()->teacher;
        $note = TeacherStudentNote::query()->create([
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->full_name,
            'content' => $request->validated('content'),
        ]);

        return response()->json(['note' => $this->note($note)], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array{id: int, teacher_name: string, content: string, created_at: string|null} */
    private function note(TeacherStudentNote $note): array
    {
        return [
            'id' => $note->id,
            'teacher_name' => $note->teacher_name,
            'content' => $note->content,
            'created_at' => $note->created_at?->toISOString(),
        ];
    }
}
