<?php

namespace App\Http\Controllers\Teacher;

use App\DocumentModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherDocumentFolderRequest;
use App\Http\Requests\StoreTeacherDocumentRequest;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\TeacherDocument;
use App\Models\TeacherDocumentFolder;
use App\Models\User;
use App\Policies\TeacherDocumentPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $module = $this->module($request);
        $filters = $request->validate([
            'folder' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['name', 'newest', 'oldest'])],
            'visibility' => ['sometimes', Rule::in(['all', 'private', 'public'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'folder_page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $user = $request->user();
        $folder = isset($filters['folder']) ? TeacherDocumentFolder::query()->where('module', $module->value)->whereKey($request->integer('folder'))->firstOrFail() : null;
        if ($folder !== null) {
            abort_unless(app(TeacherDocumentPolicy::class)->view($user, $folder, $module)->allowed(), 404);
        }

        $search = trim($filters['search'] ?? '');
        $visibility = $filters['visibility'] ?? 'all';
        $folderPage = $filters['folder_page'] ?? 1;
        $documentPage = $filters['page'] ?? 1;
        $sort = $filters['sort'] ?? 'name';
        $sortColumn = $sort === 'name' ? 'name' : 'created_at';
        $direction = $sort === 'newest' ? 'desc' : 'asc';
        $children = TeacherDocumentFolder::query()->where('module', $module->value)->with('creator:id,name')->withCount(['documents', 'children'])->withSum('documents', 'size')->where('parent_id', $folder?->id)
            ->when($folder === null && ! $user->hasRole('Super Admin'), fn ($query) => $query
                ->where(fn ($query) => $query->where('visibility', 'public')->orWhere('created_by', $user->id)))
            ->when($visibility !== 'all', fn ($query) => $query->where('visibility', $visibility))
            ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.$search.'%'))
            ->orderBy($sortColumn, $direction)->orderBy('id')->paginate(24, ['*'], 'folder_page', $folderPage)->withQueryString();

        $documents = TeacherDocument::query()->where('module', $module->value)->with('creator:id,name')->where('teacher_document_folder_id', $folder?->id)
            ->when($folder === null && ! $user->hasRole('Super Admin'), fn ($query) => $query
                ->where(fn ($query) => $query->where('visibility', 'public')->orWhere('created_by', $user->id)))
            ->when($visibility !== 'all', fn ($query) => $query->where('visibility', $visibility))
            ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.$search.'%'))
            ->orderBy($sortColumn, $direction)->orderBy('id')->paginate(24, ['*'], 'page', $documentPage)->withQueryString();

        foreach ($children->getCollection() as $child) {
            $child->setRelation('parent', $folder);
        }
        foreach ($documents->getCollection() as $document) {
            $document->setRelation('folder', $folder);
        }

        $children->through(fn (TeacherDocumentFolder $item): array => [
            'id' => $item->id, 'name' => $item->name, 'visibility' => $this->effectiveVisibility($item),
            'owner' => $item->creator->name, 'url' => route($module->routeName(), ['folder' => $item->id]),
            'fileCount' => $item->documents_count, 'size' => $item->documents_sum_size ?? 0,
            'createdAt' => $item->created_at->toIso8601String(),
            'deleteUrl' => route($module->routeName('folders.destroy'), $item),
            'canDelete' => ($user->hasRole('Super Admin') || ($item->documents_count === 0 && $item->children_count === 0))
                && app(TeacherDocumentPolicy::class)->delete($user, $item, $module)->allowed(),
        ]);

        $documents->through(fn (TeacherDocument $item): array => [
            'id' => $item->id, 'name' => $item->name, 'originalName' => $item->original_name,
            'mimeType' => $item->mime_type, 'size' => $item->size, 'visibility' => $this->effectiveVisibility($item),
            'owner' => $item->creator->name, 'canDelete' => app(TeacherDocumentPolicy::class)->delete($user, $item, $module)->allowed(),
            'createdAt' => $item->created_at->toIso8601String(),
            'viewUrl' => route($module->routeName('view'), $item), 'downloadUrl' => route($module->routeName('download'), $item),
            'deleteUrl' => route($module->routeName('destroy'), $item),
        ]);

        return Inertia::render('teacher/documents/index', [
            'folder' => $folder === null ? null : [
                'id' => $folder->id, 'name' => $folder->name, 'visibility' => $this->effectiveVisibility($folder),
                'owner' => $folder->creator->name,
                'breadcrumbs' => collect($folder->lineage())->map(fn (TeacherDocumentFolder $ancestor): array => [
                    'id' => $ancestor->id, 'name' => $ancestor->name,
                    'url' => route($module->routeName(), ['folder' => $ancestor->id]),
                ])->values()->all(),
                'canDelete' => app(TeacherDocumentPolicy::class)->delete($user, $folder, $module)->allowed(),
                'deleteUrl' => route($module->routeName('folders.destroy'), $folder),
            ],
            'folders' => $children, 'documents' => $documents,
            'search' => $search, 'sort' => $sort, 'visibility' => $visibility,
            'module' => ['key' => $module->value, 'label' => $module->label()],
            'documentRoutes' => [
                'index' => route($module->routeName()),
                'store' => route($module->routeName('store')),
                'storeFolder' => route($module->routeName('folders.store')),
            ],
            'printOptions' => $module === DocumentModule::Teacher ? $this->printOptions($user) : [],
            'canManage' => app(TeacherDocumentPolicy::class)->create($user, $module),
        ]);
    }

    /**
     * @return list<string>
     */
    private function printOptions(User $user): array
    {
        if (! $user->hasRole('Guru')) {
            return [];
        }

        $options = ['agenda'];
        $teacher = $user->teacher;
        $academicYear = AcademicYear::query()->where('status', 'active')->latest('id')->first();

        if ($teacher !== null && $academicYear !== null && HomeroomAssignment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->exists()) {
            $options[] = 'student-attendance';
        }

        return $options;
    }

    public function storeFolder(StoreTeacherDocumentFolderRequest $request): RedirectResponse
    {
        $module = $this->module($request);
        abort_unless(app(TeacherDocumentPolicy::class)->create($request->user(), $module), 403);
        $parent = $request->parentFolder();
        if ($parent !== null) {
            abort_unless(app(TeacherDocumentPolicy::class)->view($request->user(), $parent, $module)->allowed(), 404);
        }
        $name = trim($request->validated('name'));
        $visibility = $parent === null ? $request->validated('visibility') : $parent->visibility;
        TeacherDocumentFolder::query()->create([
            'parent_id' => $parent?->id, 'created_by' => $request->user()->id, 'module' => $module->value,
            'name' => $name, 'visibility' => $visibility,
        ]);

        return to_route($module->routeName(), $parent ? ['folder' => $parent->id] : []);
    }

    public function storeDocument(StoreTeacherDocumentRequest $request): RedirectResponse
    {
        $module = $this->module($request);
        abort_unless(app(TeacherDocumentPolicy::class)->create($request->user(), $module), 403);
        $data = $request->validated();
        $folder = isset($data['folder_id']) ? TeacherDocumentFolder::query()->where('module', $module->value)->whereKey($request->integer('folder_id'))->firstOrFail() : null;
        if ($folder !== null) {
            abort_unless(app(TeacherDocumentPolicy::class)->view($request->user(), $folder, $module)->allowed(), 404);
        }

        $file = $request->file('file');
        $path = $file->store($module === DocumentModule::Teacher ? 'teacher-documents' : 'module-documents/'.$module->value, 'local');
        if ($path === false) {
            abort(500, 'Dokumen gagal disimpan.');
        }
        try {
            TeacherDocument::query()->create([
                'teacher_document_folder_id' => $folder?->id, 'module' => $module->value,
                'created_by' => $request->user()->id,
                'name' => trim($data['name']),
                'original_name' => $file->getClientOriginalName(),
                'disk' => 'local', 'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'visibility' => $folder === null ? $data['visibility'] : $folder->visibility,
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return to_route($module->routeName(), $folder ? ['folder' => $folder->id] : []);
    }

    public function view(Request $request, TeacherDocument $document): StreamedResponse
    {
        $module = $this->module($request);
        abort_unless(app(TeacherDocumentPolicy::class)->view($request->user(), $document, $module)->allowed(), 404);
        abort_unless(in_array($document->mime_type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true), 415);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function download(Request $request, TeacherDocument $document): StreamedResponse
    {
        $module = $this->module($request);
        abort_unless(app(TeacherDocumentPolicy::class)->view($request->user(), $document, $module)->allowed(), 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name, [
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Request $request, TeacherDocument $document): RedirectResponse
    {
        $module = $this->module($request);
        app(TeacherDocumentPolicy::class)->delete($request->user(), $document, $module)->authorize();
        $folderId = $document->teacher_document_folder_id;
        $disk = $document->disk;
        $path = $document->path;
        $document->delete();
        Storage::disk($disk)->delete($path);

        return to_route($module->routeName(), $folderId ? ['folder' => $folderId] : []);
    }

    public function destroyFolder(Request $request, TeacherDocumentFolder $folder): RedirectResponse
    {
        $module = $this->module($request);
        $user = $request->user();
        app(TeacherDocumentPolicy::class)->delete($user, $folder, $module)->authorize();
        $isSuperAdmin = $user->hasRole('Super Admin');
        $contents = $this->descendantContents($folder);
        abort_if(! $isSuperAdmin && ($contents['folders'] !== [] || $contents['documents'] !== []), 409, 'Folder harus kosong sebelum dihapus.');
        $parentId = $folder->parent_id;

        DB::transaction(function () use ($folder, $contents, $isSuperAdmin): void {
            if ($isSuperAdmin) {
                TeacherDocument::query()->whereIn('id', array_column($contents['documents'], 'id'))->delete();
                foreach (array_reverse($contents['folders']) as $child) {
                    TeacherDocumentFolder::query()->whereKey($child['id'])->delete();
                }
            }
            $folder->delete();
        });
        foreach ($contents['documents'] as $document) {
            Storage::disk($document['disk'])->delete($document['path']);
        }

        return to_route($module->routeName(), $parentId ? ['folder' => $parentId] : []);
    }

    /** @return array{folders: list<array{id: int}>, documents: list<array{id: int, disk: string, path: string}>} */
    private function descendantContents(TeacherDocumentFolder $folder): array
    {
        $folders = [];
        $documents = [];
        $pending = [$folder->id];
        while ($pending !== []) {
            $parentId = array_pop($pending);
            $children = TeacherDocumentFolder::query()->where('parent_id', $parentId)->get(['id']);
            abort_if(TeacherDocumentFolder::query()->where('parent_id', $parentId)->where('module', '!=', $folder->module)->exists(), 409, 'Struktur modul folder tidak valid.');
            abort_if(TeacherDocument::query()->where('teacher_document_folder_id', $parentId)->where('module', '!=', $folder->module)->exists(), 409, 'Struktur modul dokumen tidak valid.');
            foreach ($children as $child) {
                $folders[] = ['id' => $child->id];
                $pending[] = $child->id;
            }
            foreach (TeacherDocument::query()->where('teacher_document_folder_id', $parentId)->get(['id', 'disk', 'path']) as $document) {
                $documents[] = ['id' => $document->id, 'disk' => $document->disk, 'path' => $document->path];
            }
        }

        return ['folders' => $folders, 'documents' => $documents];
    }

    private function module(Request $request): DocumentModule
    {
        $module = DocumentModule::fromRequest($request);
        abort_unless($module->canView($request->user()), 403);

        return $module;
    }

    private function effectiveVisibility(TeacherDocument|TeacherDocumentFolder $item): string
    {
        $folder = $item instanceof TeacherDocumentFolder ? $item : $item->folder;
        if ($folder === null) {
            return $item->visibility;
        }

        return collect($folder->lineage())->contains(fn (TeacherDocumentFolder $ancestor): bool => $ancestor->visibility === 'private')
            ? 'private' : 'public';
    }
}
