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
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Kode atau nama Mata Pelajaran sudah digunakan.',
            ]);
        }

        return redirect()->route('subjects.index')->with('success', 'Mata Pelajaran berhasil dibuat.');
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

        return redirect()->route('subjects.index')->with('success', 'Mata Pelajaran berhasil diperbarui.');
    }

    public function archive(Subject $subject): RedirectResponse
    {
        $subject->update(['status' => 'inactive']);

        return redirect()->route('subjects.index')->with('success', 'Mata Pelajaran diarsipkan.');
    }
}
