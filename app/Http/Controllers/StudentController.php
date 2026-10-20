<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportStudentsCsvRequest;
use App\Http\Requests\StudentRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\User;
use App\Services\AutoLinkStudentAccounts;
use App\Services\StudentCsvImporter;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('students/index', [
            'students' => $this->paginatedStudents($request),
            'filters' => $request->only(['search', 'gender', 'status', 'account', 'per_page']),
            'filterOptions' => $this->studentFilterOptions(),
        ]);
    }

    public function autoLinkAccounts(AutoLinkStudentAccounts $autoLink): RedirectResponse
    {
        $result = $autoLink->handle();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$result['linked']} akun berhasil dihubungkan. {$result['skipped']} siswa dilewati.",
        ]);

        return back();
    }

    public function cards(Request $request): Response
    {
        $angkatanOptions = $this->studentAngkatanOptions();

        return Inertia::render('students/cards', [
            'students' => $this->paginatedStudents($request, $angkatanOptions),
            'filters' => $request->only(['search', 'gender', 'status', 'tahun_angkatan', 'per_page']),
            'filterOptions' => array_merge(
                $this->studentFilterOptions(),
                ['angkatans' => $angkatanOptions],
            ),
        ]);
    }

    public function cardsPrint(Request $request): Response
    {
        $angkatanOptions = $this->studentAngkatanOptions();
        $students = $this->studentQuery($request, $angkatanOptions)
            ->get()
            ->map(fn (Student $student): array => $this->cardPayload($student))
            ->values();

        return Inertia::render('students/cards-print', [
            'students' => $students,
            'filters' => $request->only(['search', 'gender', 'status', 'tahun_angkatan']),
        ]);
    }

    public function card(Student $student): Response
    {
        return Inertia::render('students/card', $this->cardPayload($student));
    }

    public function downloadTemplate(): StreamedResponse
    {
        $template = app(StudentCsvImporter::class)->templateXlsx();

        return response()->streamDownload(static function () use ($template): void {
            echo $template;
        }, 'template-master-siswa.xlsx', [
            'Content-Disposition' => 'attachment; filename="template-master-siswa.xlsx"',
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadCsvTemplate(): StreamedResponse
    {
        return response()->streamDownload(static function (): void {
            echo implode(',', StudentCsvImporter::HEADERS)."\r\n";
        }, 'template-master-siswa.csv', [
            'Content-Disposition' => 'attachment; filename="template-master-siswa.csv"',
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(ImportStudentsCsvRequest $request, StudentCsvImporter $importer): RedirectResponse
    {
        $result = $importer->import($request->file('file'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['count'].' data Siswa berhasil diimpor ('.$result['created'].' dibuat, '.$result['updated'].' diperbarui).',
        ]);

        return redirect()->route('students.index');
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
                    'nisn',
                    'tahun_angkatan',
                    'full_name',
                    'gender',
                    'birth_place',
                    'birth_date',
                    'address',
                    'phone',
                    'mother_name',
                    'father_name',
                    'mother_occupation',
                    'father_occupation',
                    'parent_phone',
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data Siswa berhasil dibuat.',
        ]);

        return redirect()->route('students.index');
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data Siswa berhasil diperbarui.',
        ]);

        return redirect()->route('students.index');
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->photo_path !== null) {
            Storage::disk('public')->delete($student->photo_path);
        }

        $student->delete();

        Inertia::flash('toast', ['type' => 'error', 'message' => 'Data Siswa berhasil dihapus.']);

        return back();
    }

    /**
     * @param  array<int, int>|null  $angkatanOptions
     * @return LengthAwarePaginator<int, Student>
     */
    private function paginatedStudents(Request $request, ?array $angkatanOptions = null): LengthAwarePaginator
    {
        $perPage = min(max($request->integer('per_page', 10), 1), 50);

        return $this->studentQuery($request, $angkatanOptions)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<int, int>|null  $angkatanOptions
     * @return Builder<Student>
     */
    private function studentQuery(Request $request, ?array $angkatanOptions = null): Builder
    {
        $search = $request->string('search')->trim()->toString();
        $gender = $request->string('gender')->toString();
        $status = $request->string('status')->toString();
        $account = $request->routeIs('students.index')
            ? $request->string('account')->toString()
            : '';
        $tahunAngkatan = $angkatanOptions === null
            ? null
            : $this->validatedAngkatan($request->input('tahun_angkatan'), $angkatanOptions);

        return Student::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($gender !== '', fn ($query) => $query->where('gender', $gender))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($account === 'connected', fn ($query) => $query->whereHas('user'))
            ->when($account === 'unconnected', fn ($query) => $query->whereDoesntHave('user'))
            ->when($tahunAngkatan !== null, fn ($query) => $query->where('tahun_angkatan', $tahunAngkatan))
            ->orderBy('full_name');
    }

    /**
     * @return array<int, int>
     */
    private function studentAngkatanOptions(): array
    {
        return Student::query()
            ->whereNotNull('tahun_angkatan')
            ->distinct()
            ->orderBy('tahun_angkatan')
            ->pluck('tahun_angkatan')
            ->map(static fn (int $year): int => $year)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $angkatanOptions
     */
    private function validatedAngkatan(mixed $value, array $angkatanOptions): ?int
    {
        if ((! is_string($value) && ! is_int($value)) || ! preg_match('/^\d+$/', (string) $value)) {
            return null;
        }

        $year = (int) $value;

        return in_array($year, $angkatanOptions, true) ? $year : null;
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
     * @return array{student: array{id: int, nis: string|null, nisn: string|null, tahun_angkatan: int|null, full_name: string, gender: string|null, birth_place: string|null, birth_date: string|null, address: string|null, photo_url: string|null}, placement: array{academic_year: string, rombel: string|null}|null, qrPayload: string|null, qrCode: string|null}
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
                $student->only([
                    'id',
                    'nis',
                    'nisn',
                    'tahun_angkatan',
                    'full_name',
                    'gender',
                    'birth_place',
                    'birth_date',
                    'address',
                ]),
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
