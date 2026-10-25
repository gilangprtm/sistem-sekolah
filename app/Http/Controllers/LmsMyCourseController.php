<?php

namespace App\Http\Controllers;

use App\Models\LmsCourse;
use App\Models\Rombel;
use App\Models\Subject;
use App\Services\LmsCourseAccess;
use App\Services\LmsCourseContent;
use App\Services\LmsCourseGrading;
use App\Services\LmsCourseResponses;
use App\Services\XlsxWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
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

        $request->validate(['rombel_id' => ['sometimes', 'integer', 'min:1'], 'student_id' => ['sometimes', 'integer', 'min:1'], 'revision_id' => ['sometimes', 'integer', 'min:1']]);
        $classes = $responses->classes($request->user(), $course);
        $rombelId = $request->integer('rombel_id') ?: ($classes[0]['id'] ?? null);
        $responseData = $rombelId === null ? ['students' => [], 'selected' => null, 'gradebook' => null, 'revision' => $revision, 'revisions' => []] : $responses->response(
            $request->user(), $course, $rombelId, $request->integer('student_id') ?: null, $request->integer('revision_id') ?: null,
        );
        $isHistory = $responseData['revision']->id !== $revision->id;
        if ($responseData['selected'] !== null) {
            if (! $request->user()->can('grade', $course)) {
                unset($responseData['selected']['grading']);
            }
            $responseData['selected']['content'] = $content->learningContent($responseData['selected']['revision'], $course, mediaQuery: [
                'revision_id' => $responseData['revision']->id, 'rombel_id' => $rombelId, 'student_id' => $responseData['selected']['student_id'],
            ]);
            unset($responseData['selected']['revision']);
        }
        $response = Inertia::render('lms/my-courses/show', [
            'canGrade' => ! $isHistory && $request->user()->can('grade', $course),
            'gradebook' => $request->user()->can('grade', $course) ? $responseData['gradebook'] : null,
            'responseRevisions' => $responseData['revisions'], 'selectedRevisionId' => $responseData['revision']->id,
            'isHistory' => $isHistory,
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

    public function grade(Request $request, LmsCourse $course, LmsCourseAccess $access, LmsCourseGrading $grading, LmsCourseResponses $responses): RedirectResponse
    {
        Gate::authorize('grade', $course);
        $course = $access->available($request->user())->findOrFail($course->id);
        $data = $request->validate([
            'rombel_id' => ['required', 'integer', 'min:1'], 'student_id' => ['required', 'integer', 'min:1'],
            'revision_id' => ['sometimes', 'required', 'integer', 'min:1'],
            'page_id' => ['required', 'uuid'], 'grading_token' => ['required', 'string', 'size:64'],
            'scores' => ['required', 'array', 'min:1', 'max:100'],
            'scores.*' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
        ]);
        $grading->grade($request->user(), $course, $data, $responses);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nilai uraian berhasil disimpan.']);

        return to_route('lms.my-courses.show', [$course, 'rombel_id' => $data['rombel_id'], 'student_id' => $data['student_id']]);
    }

    public function downloadGrades(Request $request, LmsCourse $course, LmsCourseAccess $access, LmsCourseResponses $responses, XlsxWriter $writer): StreamedResponse
    {
        Gate::authorize('grade', $course);
        $course = $access->available($request->user())->with(['subject:id,name', 'approvedRevision'])->findOrFail($course->id);
        abort_if($course->approvedRevision === null, 404);
        $data = $request->validate([
            'rombel_id' => ['required', 'integer', 'min:1'],
            'revision_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $context = $responses->response($request->user(), $course, (int) $data['rombel_id'], null, isset($data['revision_id']) ? (int) $data['revision_id'] : null);
        $revision = $context['revision'];
        $rombel = Rombel::query()->findOrFail((int) $data['rombel_id']);
        $gradebook = $context['gradebook'];
        abort_if($gradebook === null, 403);
        $pages = $gradebook['pages'];
        $rows = [
            ['Daftar Nilai Siswa'],
            ['Course', $revision->title],
            ['Mata Pelajaran', $course->subject->name],
            ['Kelas', $rombel->name ?: $rombel->code],
            ['Versi Course', 'Revisi '.$revision->revision_number.($revision->id === $course->approved_revision_id ? ' · Aktif terbaru' : ' · Riwayat')],
            ['Nomor', 'Nama', ...array_column($pages, 'title')],
        ];
        foreach ($gradebook['students'] as $index => $student) {
            $scores = [];
            $results = (array) $student['scores'];
            foreach ($pages as $page) {
                $result = $results[$page['id']] ?? null;
                if (! ($result['weights_set'] ?? false)) {
                    $scores[] = 'Bobot belum diatur';
                } elseif (! ($result['submitted'] ?? false)) {
                    $scores[] = 'Belum dikumpulkan';
                } elseif ($result['score'] === null) {
                    $scores[] = 'Belum dinilai ('.$result['ungraded_essays'].')';
                } else {
                    $score = (float) $result['score'];
                    $scores[] = (floor($score) === $score ? (string) (int) $score : number_format($score, 2, '.', '')).'/100';
                }
            }
            $rows[] = [$index + 1, $student['name'], ...$scores];
        }
        $widths = [1 => 10.0, 2 => 36.0];
        foreach ($pages as $_page) {
            $widths[count($widths) + 1] = 28.0;
        }
        $content = $writer->write($rows, 'Nilai Siswa', [
            'columnWidths' => $widths, 'headerImage' => public_path('images/kop-sekolah.png'),
            'titleRow' => 1, 'headerRow' => 6, 'borderFromRow' => 7,
            'wrapColumns' => range(2, count($pages) + 2), 'centerColumns' => [1],
        ]);
        $filename = 'nilai-siswa-course-'.$course->id.'-kelas-'.$rombel->id.'-revisi-'.$revision->revision_number.'.xlsx';

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function media(Request $request, LmsCourse $course, string $media, LmsCourseAccess $access, LmsCourseContent $content, LmsCourseResponses $responses): StreamedResponse
    {
        $course = $access->available($request->user())->with('approvedRevision')->findOrFail($course->id);
        abort_if($course->approvedRevision === null, 404);

        $request->validate(['revision_id' => ['sometimes', 'integer', 'min:1'], 'rombel_id' => ['sometimes', 'integer', 'min:1'], 'student_id' => ['sometimes', 'integer', 'min:1']]);
        $revision = $course->approvedRevision;
        if ($request->has('revision_id') && $request->integer('revision_id') !== $revision->id) {
            $revision = $responses->teacherContext($request->user(), $course, $request->integer('rombel_id'), $request->integer('student_id'), $request->integer('revision_id'))['revision'];
        }

        return $content->mediaResponse($course, $revision, $media);
    }
}
