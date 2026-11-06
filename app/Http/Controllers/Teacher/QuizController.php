<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTeacherQuizRequest;
use App\Models\AcademicYear;
use App\Models\StudentQuizAttempt;
use App\Models\Subject;
use App\Models\TeacherQuiz;
use App\Policies\TeacherQuizPolicy;
use App\Services\StudentQuizAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'availability' => ['nullable', Rule::in(['open', 'locked'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'monitor_quiz_id' => ['nullable', 'integer', 'min:1'],
            'monitor_page' => ['sometimes', 'integer', 'min:1'],
            'monitor_per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $search = $request->string('search')->trim()->toString();
        $subjectId = $request->integer('subject_id');
        $availability = $request->string('availability')->toString();
        $quizzes = TeacherQuiz::query()->where('created_by', $request->user()->id)
            ->with(['subject:id,code,name', 'academicYear:id,year', 'academicPeriod:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas('subject', fn ($query) => $query
                ->whereLike('name', '%'.$search.'%')->orWhereLike('code', '%'.$search.'%')))
            ->when($subjectId > 0, fn ($query) => $query->where('subject_id', $subjectId))
            ->when(in_array($availability, ['open', 'locked'], true), fn ($query) => $query->where('is_open', $availability === 'open'))
            ->latest('id')->paginate($request->integer('per_page', 10))->withQueryString()
            ->through(fn (TeacherQuiz $quiz): array => [
                'id' => $quiz->id,
                'subject' => $quiz->subject?->only(['id', 'code', 'name']),
                'academic_year' => $quiz->academicYear?->year,
                'academic_period' => $quiz->academicPeriod?->name,
                'link' => $quiz->link,
                'is_open' => $quiz->is_open,
            ]);
        $monitoring = null;
        if ($request->filled('monitor_quiz_id')) {
            $quiz = TeacherQuiz::query()->where('created_by', $request->user()->id)
                ->findOrFail($request->integer('monitor_quiz_id'));
            $monitoring = [
                'quiz_id' => $quiz->id,
                'subject' => $quiz->subject?->name,
                'attempts' => $quiz->attempts()->with('student:id,full_name')->where('status', 'locked')
                    ->latest('id')->paginate($request->integer('monitor_per_page', 10), ['*'], 'monitor_page')->withQueryString()
                    ->through(fn (StudentQuizAttempt $attempt): array => [
                        'id' => $attempt->id,
                        'student_name' => $attempt->student?->full_name,
                        'exit_count' => max(0, $attempt->interruption_count - 1),
                    ]),
            ];
        }
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();
        $period = $year?->periods()->where('status', 'active')->latest('id')->first();

        return Inertia::render('teacher/quizzes/index', [
            'quizzes' => $quizzes,
            'monitoring' => $monitoring,
            'subjects' => Subject::query()->taughtBy($request->user())->orderBy('name')->get(['id', 'code', 'name']),
            'filterOptions' => [
                'subjects' => Subject::query()->whereIn('id', TeacherQuiz::query()
                    ->where('created_by', $request->user()->id)->select('subject_id'))
                    ->orderBy('name')->get(['id', 'name']),
            ],
            'activeContext' => $period === null ? null : ['year' => $year->year, 'period' => $period->name],
            'filters' => ['search' => $search, 'subject_id' => $subjectId ?: null, 'availability' => $availability],
        ]);
    }

    public function store(SaveTeacherQuizRequest $request): RedirectResponse
    {
        $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();
        $period = $year?->periods()->where('status', 'active')->latest('id')->first();
        if ($period === null) {
            throw ValidationException::withMessages(['subject_id' => 'Tahun Ajaran dan semester aktif belum tersedia.']);
        }
        TeacherQuiz::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            'is_open' => false,
        ]);

        return $this->saved('Ulangan ditambahkan dalam keadaan terkunci.');
    }

    public function update(SaveTeacherQuizRequest $request, TeacherQuiz $quiz, TeacherQuizPolicy $policy): RedirectResponse
    {
        abort_unless($policy->manage($request->user(), $quiz), 404);
        $quiz->update($request->validated());

        return $this->saved('Ulangan diperbarui.');
    }

    public function availability(Request $request, TeacherQuiz $quiz, TeacherQuizPolicy $policy): RedirectResponse
    {
        abort_unless($policy->manage($request->user(), $quiz), 404);
        $data = $request->validate(['is_open' => ['required', 'boolean']]);
        DB::transaction(function () use ($quiz, $data): void {
            TeacherQuiz::query()->lockForUpdate()->findOrFail($quiz->id)->update($data);
        });

        return $this->saved($request->boolean('is_open') ? 'Ulangan dibuka untuk semua siswa.' : 'Ulangan dikunci.');
    }

    public function destroy(Request $request, TeacherQuiz $quiz, TeacherQuizPolicy $policy): RedirectResponse
    {
        abort_unless($policy->manage($request->user(), $quiz), 404);
        $quiz->delete();

        return $this->saved('Ulangan dihapus.');
    }

    public function unlock(Request $request, StudentQuizAttempt $attempt, TeacherQuizPolicy $policy, StudentQuizAttemptService $service): RedirectResponse
    {
        abort_unless($policy->manage($request->user(), $attempt->quiz), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->unlock($attempt, $request->user()->id, $data['reason']);

        return $this->saved('Blokir siswa dibuka. Status Buka/Kunci Ulangan tidak berubah.');
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
