<?php

namespace App\Http\Controllers;

use App\Http\Requests\LmsCourseTransitionRequest;
use App\Http\Requests\SaveLmsCourseContentRequest;
use App\Http\Requests\SaveLmsCourseRequest;
use App\Models\LmsCourse;
use App\Models\LmsCourseRevision;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\LmsCourseContent;
use App\Services\LmsCourseLifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LmsCourseDesignController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $subjectId = $request->integer('subject_id');
        $gradeLevel = $request->string('grade_level')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $user = $request->user();

        $courses = LmsCourse::query()
            ->with(['subject:id,name', 'designer:id,full_name', 'latestRevision', 'approvedRevision'])
            ->withExists(['revisions as has_approved_revision' => fn (Builder $revision): Builder => $revision->where('status', LmsCourseRevision::STATUS_APPROVED)])
            ->whereNull('archived_at')
            ->when(! $user->can('manageDesigns', LmsCourse::class), fn (Builder $query) => $query->where('created_by', $user->teacher->id ?? 0))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('latestRevision', fn (Builder $revision) => $revision
                ->where(function (Builder $matches) use ($search): void {
                    $matches->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereRaw('LOWER(description) LIKE ?', ['%'.mb_strtolower($search).'%']);
                })))
            ->when($subjectId > 0, fn (Builder $query) => $query->where('subject_id', $subjectId))
            ->when($gradeLevel !== '', fn (Builder $query) => $query->where('grade_level', $gradeLevel))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            ->through(fn (LmsCourse $course): array => [
                'id' => $course->id, 'title' => $course->latestRevision->title ?? $course->title,
                'description' => $course->latestRevision?->description, 'subject' => $course->subject->name,
                'grade_level' => $course->grade_level, 'designer' => $course->designer->full_name,
                'revision_number' => $course->latestRevision?->revision_number,
                'status' => $course->approved_revision_id !== null ? 'active' : 'draft', 'editable' => $request->user()->can('update', $course),
                'can_revise' => $request->user()->can('revise', $course),
                'revision_id' => $course->latestRevision?->editToken($course),
                'updated_at' => $course->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('lms/design/index', [
            'courses' => $courses,
            'filters' => ['search' => $search, 'subject_id' => $subjectId > 0 ? $subjectId : null, 'grade_level' => $gradeLevel, 'per_page' => $perPage],
            'filterOptions' => ['subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']), 'gradeLevels' => LmsCourse::GRADE_LEVELS],
            'designers' => $user->can('manageDesigns', LmsCourse::class) ? Teacher::query()->where('status', 'active')->where('staff_type', 'guru')->orderBy('full_name')->get(['id', 'full_name']) : [],
            'canCreate' => $user->can('create', LmsCourse::class), 'canChooseDesigner' => $user->can('manageDesigns', LmsCourse::class),
        ]);
    }

    public function store(SaveLmsCourseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $designerId = $request->user()->can('manageDesigns', LmsCourse::class) ? (int) $data['designer_id'] : (int) $request->user()->teacher?->id;
        $course = DB::transaction(function () use ($data, $designerId): LmsCourse {
            $course = LmsCourse::query()->create(['subject_id' => $data['subject_id'], 'grade_level' => $data['grade_level'], 'title' => $data['title'], 'created_by' => $designerId]);
            $course->revisions()->create(['revision_number' => 1, 'title' => $data['title'], 'description' => $data['description'] ?? null, 'status' => LmsCourseRevision::STATUS_DRAFT, 'content_schema_version' => 1, 'content' => ['materials' => []], 'created_by' => $designerId]);

            return $course;
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draf course berhasil dibuat.']);

        return redirect()->route('lms.design.edit', $course);
    }

    public function edit(Request $request, LmsCourse $course): Response
    {
        abort_unless($request->user()->can('view', $course), 404);
        $revision = $course->revisions()->orderByDesc('revision_number')->firstOrFail();

        return Inertia::render('lms/design/edit', [
            'course' => [
                'id' => $course->id, 'subject_id' => $course->subject_id, 'grade_level' => $course->grade_level,
                'title' => $revision->title, 'description' => $revision->description, 'revision_id' => $revision->editToken($course),
                'revision_number' => $revision->revision_number, 'status' => $revision->lifecycleStatus(),
                'editable' => $request->user()->can('update', $course),
                'can_revise' => $request->user()->can('revise', $course),
                'content' => $this->editorContent($revision, $course),
                'has_approved_revision' => $course->hasApprovedRevision(),
            ],
            'contentUpdateUrl' => route('lms.design.content.update', $course),
            'subjects' => Subject::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => LmsCourse::GRADE_LEVELS,
            'reviseUrl' => route('lms.design.revise', $course),
        ]);
    }

    /** @return array<string, mixed> */
    private function editorContent(LmsCourseRevision $revision, LmsCourse $course): array
    {
        return app(LmsCourseContent::class)->editorContent($revision, $course);
    }

    public function updateContent(SaveLmsCourseContentRequest $request, LmsCourse $course, LmsCourseContent $service): RedirectResponse
    {
        $data = $request->validated();
        $content = $service->normalize(json_decode($data['content_json'], true, 512, JSON_THROW_ON_ERROR));
        $uploads = $request->file('uploads', []);
        $storedPaths = [];
        try {
            DB::transaction(function () use ($course, $data, $content, $uploads, $service, &$storedPaths): void {
                $locked = LmsCourse::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
                $revision = $locked->revisions()->orderByDesc('revision_number')->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $locked);
                if (! hash_equals($revision->editToken($locked), $data['revision_id'])) {
                    throw ValidationException::withMessages(['revision_id' => 'Draf course telah berubah. Muat ulang halaman sebelum menyimpan.']);
                }
                if ($locked->hasApprovedRevision() && (((int) ($data['subject_id'] ?? $locked->subject_id) !== $locked->subject_id) || (($data['grade_level'] ?? $locked->grade_level) !== $locked->grade_level))) {
                    throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran dan jenjang tidak dapat diubah setelah publikasi pertama.']);
                }
                $assets = $revision->content['assets'] ?? [];
                $references = [];
                foreach ($service->media($content) as $media) {
                    if ($media['type'] !== 'youtube') {
                        if (isset($references[$media['asset_id']]) && $references[$media['asset_id']] !== $media['type']) {
                            throw ValidationException::withMessages(['content' => 'Tipe referensi lampiran tidak konsisten.']);
                        }
                        $references[$media['asset_id']] = $media['type'];
                    }
                }
                foreach ($uploads as $id => $file) {
                    $type = $file->getMimeType() === 'application/pdf' ? 'pdf' : 'image';
                    if (! Str::isUuid((string) $id) || isset($assets[$id]) || ($references[$id] ?? null) !== $type) {
                        throw ValidationException::withMessages(['content' => 'Referensi unggahan tidak sesuai konten.']);
                    }
                    $path = $file->store('lms/courses/'.$locked->id, 'local');
                    abort_if($path === false, 500, 'File gagal disimpan.');
                    $storedPaths[] = $path;
                    $assets[$id] = ['path' => $path, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'type' => $type, 'mime' => $file->getMimeType()];
                }
                foreach ($references as $id => $type) {
                    if (! isset($assets[$id]) || $assets[$id]['type'] !== $type) {
                        throw ValidationException::withMessages(['content' => 'Lampiran tidak tersedia pada course ini.']);
                    }
                }
                $identityFields = $locked->hasApprovedRevision() ? ['subject_id', 'grade_level'] : ['title', 'subject_id', 'grade_level'];
                $locked->update(array_intersect_key($data, array_flip($identityFields)));
                $locked->touch();
                $revision->update(['title' => $data['title'] ?? $revision->title, 'description' => array_key_exists('description', $data) ? $data['description'] : $revision->description, 'content' => [...$content, 'assets' => $assets]]);
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Identitas dan materi draf berhasil disimpan.']);

        return redirect()->route('lms.design');
    }

    public function viewMedia(Request $request, LmsCourse $course, string $media, LmsCourseContent $service): StreamedResponse
    {
        abort_unless($request->user()->can('view', $course), 404);

        return $service->mediaResponse($course, $course->latestRevision, $media);
    }

    public function revise(LmsCourseTransitionRequest $request, LmsCourse $course, LmsCourseLifecycle $lifecycle): RedirectResponse
    {
        $lifecycle->revise($request->user(), $course, $request->validated('revision_id'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Revisi baru berhasil dibuat.']);

        return redirect()->route('lms.design.edit', $course);
    }

    public function update(SaveLmsCourseRequest $request, LmsCourse $course): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($course, $data): void {
            $lockedCourse = LmsCourse::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $revision = $lockedCourse->revisions()->orderByDesc('revision_number')->lockForUpdate()->first();
            Gate::authorize('update', $lockedCourse);
            if ($revision === null || ! hash_equals($revision->editToken($lockedCourse), $data['revision_id'])) {
                throw ValidationException::withMessages(['revision_id' => 'Draf course telah berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            if ($lockedCourse->hasApprovedRevision() && ($data['subject_id'] !== $lockedCourse->subject_id || $data['grade_level'] !== $lockedCourse->grade_level)) {
                throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran dan jenjang tidak dapat diubah setelah publikasi pertama.']);
            }
            $lockedCourse->update([
                'subject_id' => $data['subject_id'], 'grade_level' => $data['grade_level'],
                'title' => $lockedCourse->hasApprovedRevision() ? $lockedCourse->title : $data['title'],
            ]);
            $revision->update(['title' => $data['title'], 'description' => $data['description'] ?? null]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Draf course berhasil diperbarui.']);

        return redirect()->route('lms.design.edit', $course);
    }
}
