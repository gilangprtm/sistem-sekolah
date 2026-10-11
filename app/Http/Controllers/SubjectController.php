<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $subjects = Subject::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('subjects/index', [
            'subjects' => $subjects,
            'filters' => $request->only(['search', 'status', 'per_page']),
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'inactive', 'label' => 'Arsip'],
                ],
            ],
        ]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        try {
            Subject::query()->create([
                'code' => $request->validated('code'),
                'name' => $request->validated('name'),
                'status' => $request->validated('status', 'active'),
                'jp_per_class' => $request->validated('jp_per_class', 1),
                'color' => $request->validated('color', '#F3F4F6'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Kode atau nama Mata Pelajaran sudah digunakan.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mata Pelajaran berhasil dibuat.']);

        return redirect()->route('subjects.index');
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        try {
            $subject->update($request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Kode atau nama Mata Pelajaran sudah digunakan.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mata Pelajaran berhasil diperbarui.']);

        return redirect()->route('subjects.index');
    }

    public function archive(Subject $subject): RedirectResponse
    {
        $subject->update(['status' => 'inactive']);

        Inertia::flash('toast', ['type' => 'error', 'message' => 'Mata Pelajaran diarsipkan.']);

        return redirect()->route('subjects.index');
    }
}
