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
        $gender = $request->string('gender')->toString();
        $status = $request->string('status')->toString();
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
            ->when($gender !== '', fn ($query) => $query->where('gender', $gender))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('full_name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('students/index', [
            'students' => $students,
            'filters' => $request->only(['search', 'gender', 'status', 'per_page']),
            'filterOptions' => [
                'genders' => [
                    ['value' => 'L', 'label' => 'Laki-laki'],
                    ['value' => 'P', 'label' => 'Perempuan'],
                ],
                'statuses' => [
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'inactive', 'label' => 'Tidak aktif'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('students/create', [
            'availableAccounts' => $this->availableAccounts(),
        ]);
    }

    public function edit(Student $student): Response
    {
        $student->load('user:id,name,email');

        return Inertia::render('students/edit', [
            'student' => $student,
            'availableAccounts' => $this->availableAccounts($student->user_id),
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
     * @return array<int, array{id: int, email: string}>
     */
    private function availableAccounts(?int $currentUserId = null): array
    {
        return User::query()
            ->role('Siswa')
            ->where(function ($query) use ($currentUserId): void {
                $query->whereDoesntHave('student');

                if ($currentUserId !== null) {
                    $query->orWhere('id', $currentUserId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'email' => $user->email,
            ])
            ->all();
    }
}
