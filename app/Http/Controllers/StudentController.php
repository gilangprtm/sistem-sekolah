<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('students/index', [
            'students' => $this->paginatedStudents($request),
            'filters' => $request->only(['search', 'gender', 'status', 'per_page']),
            'filterOptions' => $this->studentFilterOptions(),
        ]);
    }

    public function cards(Request $request): Response
    {
        return Inertia::render('students/cards', [
            'students' => $this->paginatedStudents($request),
            'filters' => $request->only(['search', 'gender', 'status', 'per_page']),
            'filterOptions' => $this->studentFilterOptions(),
        ]);
    }

    public function cardsPrint(): Response
    {
        $students = Student::query()
            ->with('user:id,email')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Student $student): array => $this->cardPayload($student))
            ->values();

        return Inertia::render('students/cards-print', [
            'students' => $students,
        ]);
    }

    public function card(Student $student): Response
    {
        return Inertia::render('students/card', $this->cardPayload($student));
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
            'student' => array_merge(
                $student->only([
                    'id',
                    'user_id',
                    'nis',
                    'tahun_angkatan',
                    'full_name',
                    'gender',
                    'birth_place',
                    'birth_date',
                    'address',
                    'status',
                ]),
                ['photo_url' => $this->photoUrl($student)],
            ),
            'availableAccounts' => $this->availableAccounts($student->user_id),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $photo = $data['photo'] ?? null;
        unset($data['photo'], $data['remove_photo']);

        $student = Student::query()->create($data);

        if ($photo instanceof UploadedFile) {
            $student->update(['photo_path' => $photo->store("students/{$student->id}", 'public')]);
        }

        return redirect()->route('students.index')->with('success', 'Data Siswa berhasil dibuat.');
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $photo = $data['photo'] ?? null;
        $removePhoto = (bool) ($data['remove_photo'] ?? false);
        unset($data['photo'], $data['remove_photo']);

        $oldPhotoPath = $student->photo_path;
        $newPhotoPath = null;

        if ($photo instanceof UploadedFile) {
            $newPhotoPath = $photo->store("students/{$student->id}", 'public');
            $data['photo_path'] = $newPhotoPath;
        } elseif ($removePhoto && $oldPhotoPath !== null) {
            $data['photo_path'] = null;
        }

        $student->update($data);

        if ($oldPhotoPath !== null && ($newPhotoPath !== null || $removePhoto)) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('students.index')->with('success', 'Data Siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->photo_path !== null) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $student->delete();

        return back()->with('success', 'Data Siswa berhasil dihapus.');
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    private function paginatedStudents(Request $request): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->toString();
        $gender = $request->string('gender')->toString();
        $status = $request->string('status')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);

        return Student::query()
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
    }

    /**
     * @return array{genders: array<int, array{value: string, label: string}>, statuses: array<int, array{value: string, label: string}>}
     */
    private function studentFilterOptions(): array
    {
        return [
            'genders' => [
                ['value' => 'L', 'label' => 'Laki-laki'],
                ['value' => 'P', 'label' => 'Perempuan'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Tidak aktif'],
            ],
        ];
    }

    /**
     * @return array{student: array{id: int, nis: string|null, tahun_angkatan: int|null, full_name: string, birth_place: string|null, birth_date: string|null, address: string|null, photo_url: string|null}, placement: array{academic_year: string, rombel: string|null}|null, qrPayload: string|null, qrCode: string|null}
     */
    private function cardPayload(Student $student): array
    {
        $student->loadMissing('user:id,email');
        $activeYear = AcademicYear::query()->where('status', 'active')->first();
        $placement = $activeYear === null
            ? null
            : StudentPlacement::query()
                ->with('rombel:id,name,code')
                ->where('academic_year_id', $activeYear->id)
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();
        $qrPayload = $student->user?->email;
        $qrCode = $qrPayload === null
            ? null
            : base64_encode((new Writer(new ImageRenderer(new RendererStyle(240, 2), new SvgImageBackEnd)))->writeString($qrPayload));
        $rombel = $placement?->rombel;

        return [
            'student' => array_merge(
                $student->only(['id', 'nis', 'tahun_angkatan', 'full_name', 'birth_place', 'birth_date', 'address']),
                ['photo_url' => $this->photoUrl($student)],
            ),
            'placement' => $placement === null ? null : [
                'academic_year' => $activeYear->year,
                'rombel' => $rombel === null ? null : ($rombel->name ?: $rombel->code),
            ],
            'qrPayload' => $qrPayload,
            'qrCode' => $qrCode === null ? null : 'data:image/svg+xml;base64,'.$qrCode,
        ];
    }

    private function photoUrl(Student $student): ?string
    {
        return $student->photo_path === null
            ? null
            : Storage::disk('public')->url($student->photo_path);
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
