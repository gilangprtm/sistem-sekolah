<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherRequest;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $teachers = Teacher::query()->with('user:id,email')->when($search !== '', function ($query) use ($search): void {
            $query->where(function ($query) use ($search): void {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('staff_type', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%"));
            });
        })->orderBy('full_name')->paginate($perPage)->withQueryString();

        return Inertia::render('teachers/index', ['teachers' => $teachers, 'filters' => $request->only(['search', 'per_page'])]);
    }

    public function create(): Response
    {
        return Inertia::render('teachers/create', ['availableAccounts' => $this->availableAccounts()]);
    }

    public function edit(Teacher $teacher): Response
    {
        $teacher->load('user:id,email');

        return Inertia::render('teachers/edit', ['teacher' => $teacher, 'availableAccounts' => $this->availableAccounts($teacher->user_id)]);
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        Teacher::query()->create($request->validated());

        return redirect()->route('teachers.index')->with('success', 'Data Guru & Staff berhasil dibuat.');
    }

    public function update(TeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($request->validated());

        return redirect()->route('teachers.index')->with('success', 'Data Guru & Staff berhasil diperbarui.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return back()->with('success', 'Data Guru & Staff berhasil dihapus.');
    }

    /** @return array{guru: array<int, array{id: int, email: string}>, staff: array<int, array{id: int, email: string}>} */
    private function availableAccounts(?int $currentUserId = null): array
    {
        $accounts = User::query()->where(function ($query) use ($currentUserId): void {
            $query->whereDoesntHave('teacher');

            if ($currentUserId !== null) {
                $query->orWhere('id', $currentUserId);
            }
        })->orderBy('email')->get(['id', 'email']);
        $result = ['guru' => [], 'staff' => []];

        foreach ($accounts as $account) {
            foreach (['Guru' => 'guru', 'Staff' => 'staff'] as $role => $type) {
                if ($account->hasRole($role)) {
                    $result[$type][] = ['id' => $account->id, 'email' => $account->email];
                }
            }
        }

        return $result;
    }
}
