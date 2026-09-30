<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);

        $students = Student::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%"));
                });
            })
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('students/index', [
            'students' => $students,
            'availableAccounts' => $this->availableAccounts(),
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        Student::query()->create($request->validated());

        return redirect()->route('students.index')->with('success', 'Data Siswa berhasil dibuat.');
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $student->update($request->validated());

        return redirect()->route('students.index')->with('success', 'Data Siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return back()->with('success', 'Data Siswa berhasil dihapus.');
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function availableAccounts(): array
    {
        return User::query()
            ->role('Siswa')
            ->whereDoesntHave('student')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }
}
