<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherSubjectRequest;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TeacherSubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $teacherSubjects = TeacherSubject::query()
            ->with(['teacher:id,full_name,staff_type,status', 'subject:id,code,name,status'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('teacher', fn ($teacher) => $teacher->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('subject', fn ($subject) => $subject->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            })
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('teacher-subjects/index', [
            'teacherSubjects' => $teacherSubjects,
            'filters' => $request->only(['search', 'per_page']),
            'teachers' => Teacher::query()->where('staff_type', 'guru')->where('status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
            'subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(TeacherSubjectRequest $request): RedirectResponse
    {
        $data = $request->validated();
        /** @var Subject $subject */
        $subject = Subject::query()->findOrFail($data['subject_id']);

        try {
            DB::transaction(function () use ($data, $subject): void {
                TeacherSubject::query()->create([
                    'teacher_id' => $data['teacher_id'],
                    'subject_id' => $data['subject_id'],
                    'suffix' => $data['suffix'],
                    'code' => $subject->code.$data['suffix'],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['suffix' => 'Kode atau pasangan Guru dan Mata Pelajaran sudah digunakan.']);
        }

        return redirect()->route('teacher-subjects.index')->with('success', 'Guru Mata Pelajaran berhasil ditambahkan.');
    }

    public function destroy(TeacherSubject $teacherSubject): RedirectResponse
    {
        $teacherSubject->delete();

        return redirect()->route('teacher-subjects.index')->with('success', 'Relasi Guru Mata Pelajaran berhasil dihapus.');
    }
}
