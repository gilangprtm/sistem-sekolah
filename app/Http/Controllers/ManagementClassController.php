<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\Teacher;
use App\Models\YearClass;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManagementClassController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('management-class/index', $this->pageData($request, true));
    }

    public function manage(Request $request): Response
    {
        $years = AcademicYear::query()->orderByDesc('year')->get(['id', 'year', 'status']);
        $selectedYear = $years->firstWhere('id', $request->integer('academic_year_id')) ?? $years->first();
        $selectedClass = null;
        $selectedTeacher = null;
        $selectedStudents = collect();
        $availableRombels = Rombel::query()
            ->where('status', 'active')
            ->orderBy('grade_level')
            ->orderBy('parallel_code')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'grade_level', 'parallel_code']);
        $registeredRombelIds = $selectedYear instanceof AcademicYear
            ? YearClass::query()
                ->where('academic_year_id', $selectedYear->id)
                ->pluck('rombel_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values()
            : collect();
        if ($request->integer('rombel_id') > 0 && $selectedYear instanceof AcademicYear) {
            $selectedClass = YearClass::query()
                ->with([
                    'rombel:id,code,name,grade_level,parallel_code',
                    'homeroomAssignments' => fn ($query) => $query
                        ->where('status', 'active')
                        ->with('teacher:id,full_name'),
                ])
                ->where('academic_year_id', $selectedYear->id)
                ->where('rombel_id', $request->integer('rombel_id'))
                ->first();

            if ($selectedClass !== null) {
                $homerooms = HomeroomAssignment::query()
                    ->with('teacher:id,full_name')
                    ->where('academic_year_id', $selectedYear->id)
                    ->where('rombel_id', $selectedClass->rombel_id)
                    ->where('status', 'active')
                    ->get();
                $selectedTeacher = $homerooms->first()?->teacher;
                $selectedClass->setRelation('homeroomAssignments', $homerooms);

                $selectedStudents = StudentPlacement::query()
                    ->with('student:id,nis,full_name')
                    ->where('academic_year_id', $selectedYear->id)
                    ->where('rombel_id', $selectedClass->rombel_id)
                    ->where('status', 'active')
                    ->get()
                    ->sortBy('student.full_name')
                    ->values()
                    ->map(fn (StudentPlacement $placement): array => [
                        'id' => $placement->student_id,
                        'nis' => $placement->student?->nis,
                        'full_name' => $placement->student?->full_name,
                    ]);
            }
        }

        return Inertia::render('management-class/manage', [
            'years' => $years,
            'selectedYear' => $selectedYear,
            'selectedRombelId' => $request->integer('rombel_id') > 0 ? $request->integer('rombel_id') : null,
            'selectedClass' => $selectedClass,
            'selectedTeacher' => $selectedTeacher,
            'registeredRombelIds' => $registeredRombelIds,
            'selectedStudents' => $selectedStudents,
            'availableRombels' => $availableRombels,
            'students' => Student::query()->where('status', 'active')->orderBy('full_name')->get(['id', 'nis', 'full_name']),
            'teachers' => Teacher::query()->where('status', 'active')->where('staff_type', 'guru')->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    /** @return array<string, mixed> */
    private function pageData(Request $request, bool $paginate = false): array
    {
        $years = AcademicYear::query()->orderByDesc('year')->get(['id', 'year', 'status']);
        $year = $years->firstWhere('id', $request->integer('academic_year_id')) ?? $years->first();
        $search = $request->string('search')->trim()->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $query = YearClass::query()
            ->with('rombel:id,code,name,grade_level,parallel_code')
            ->when($year !== null, fn ($query) => $query->where('academic_year_id', $year->id))
            ->when($year === null, fn ($query) => $query->whereKey(0))
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('rombel', fn ($rombelQuery) => $rombelQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('grade_level', 'like', "%{$search}%"));
            })
            ->orderBy('rombel_id');
        $classes = $paginate ? $query->paginate($perPage)->withQueryString() : $query->get();
        $classRows = $classes instanceof LengthAwarePaginator ? $classes->getCollection() : $classes;

        $rombelIds = $classRows->pluck('rombel_id');
        if ($year !== null && $rombelIds->isNotEmpty()) {
            $homerooms = HomeroomAssignment::query()
                ->with('teacher:id,full_name')
                ->where('academic_year_id', $year->id)
                ->whereIn('rombel_id', $rombelIds)
                ->where('status', 'active')
                ->get()
                ->groupBy('rombel_id');
            $memberCounts = StudentPlacement::query()
                ->where('academic_year_id', $year->id)
                ->whereIn('rombel_id', $rombelIds)
                ->where('status', 'active')
                ->selectRaw('rombel_id, count(*) as aggregate')
                ->groupBy('rombel_id')
                ->pluck('aggregate', 'rombel_id');

            $classRows->each(function (YearClass $yearClass) use ($homerooms, $memberCounts): void {
                $yearClass->setRelation('homeroomAssignments', $homerooms->get($yearClass->rombel_id, collect()));
                $yearClass->setAttribute('student_placements_count', (int) $memberCounts->get($yearClass->rombel_id, 0));
            });
        }

        return [
            'years' => $years,
            'selectedYear' => $year,
            'classes' => $classes,
            'filters' => $request->only(['academic_year_id', 'search', 'per_page']),
        ];
    }

    public function storeClass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('m_academic_years', 'id')->where('status', 'active')],
            'rombel_id' => [
                'required',
                'integer',
                Rule::exists('m_rombels', 'id')->where('status', 'active'),
                Rule::unique('tr_curriculum_year_classes', 'rombel_id')->where('academic_year_id', $request->integer('academic_year_id')),
            ],
            'teacher_id' => ['required', 'integer', Rule::exists('m_teacher', 'id')->where(fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))],
        ], [
            'rombel_id.unique' => 'Rombel tersebut sudah digunakan pada Tahun Ajaran ini.',
            'teacher_id.required' => 'Wali kelas wajib ditetapkan saat kelas dibuat.',
        ]);

        try {
            DB::transaction(function () use ($data): void {
                $yearClass = YearClass::query()->create([
                    'academic_year_id' => $data['academic_year_id'],
                    'rombel_id' => $data['rombel_id'],
                ]);
                HomeroomAssignment::query()->create([
                    'academic_year_id' => $yearClass->academic_year_id,
                    'rombel_id' => $yearClass->rombel_id,
                    'teacher_id' => $data['teacher_id'],
                    'status' => 'active',
                    'started_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'tr_curriculum_year_classes_academic_year_id_rombel_id_unique')) {
                throw ValidationException::withMessages(['rombel_id' => 'Rombel tersebut sudah digunakan pada Tahun Ajaran ini.']);
            }
            if (str_contains($exception->getMessage(), 'tr_curriculum_active_homeroom_teacher_unique')) {
                throw ValidationException::withMessages(['teacher_id' => 'Guru sudah menjadi wali kelas lain pada Tahun Ajaran tersebut.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function updateClass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('m_academic_years', 'id')->where('status', 'active')],
            'rombel_id' => ['required', 'integer', Rule::exists('m_rombels', 'id')->where('status', 'active')],
            'teacher_id' => ['required', 'integer', Rule::exists('m_teacher', 'id')->where(fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))],
            'student_ids' => ['array'],
            'student_ids.*' => ['integer', 'distinct', Rule::exists('m_students', 'id')->where('status', 'active')],
        ]);

        try {
            DB::transaction(function () use ($data): void {
                $yearClass = YearClass::query()
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('rombel_id', $data['rombel_id'])
                    ->lockForUpdate()
                    ->first();
                if ($yearClass === null) {
                    $yearClass = YearClass::query()->create([
                        'academic_year_id' => $data['academic_year_id'],
                        'rombel_id' => $data['rombel_id'],
                    ]);
                }

                $studentIds = array_map(static fn (mixed $id): int => (int) $id, $data['student_ids'] ?? []);
                $existingPlacements = StudentPlacement::query()
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('rombel_id', $data['rombel_id'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get();
                $otherPlacement = StudentPlacement::query()
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->whereIn('student_id', $studentIds)
                    ->where('rombel_id', '!=', $data['rombel_id'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->exists();
                if ($otherPlacement) {
                    throw ValidationException::withMessages(['student_ids' => 'Salah satu siswa sudah memiliki kelas aktif pada Tahun Ajaran tersebut.']);
                }

                $removedIds = $existingPlacements->pluck('student_id')->diff($studentIds);
                if ($removedIds->isNotEmpty()) {
                    StudentPlacement::query()
                        ->where('academic_year_id', $data['academic_year_id'])
                        ->where('rombel_id', $data['rombel_id'])
                        ->whereIn('student_id', $removedIds)
                        ->where('status', 'active')
                        ->update(['status' => 'ended', 'ended_at' => now()]);
                }

                $existingIds = $existingPlacements->pluck('student_id');
                $now = now();
                foreach (array_diff($studentIds, $existingIds->all()) as $studentId) {
                    StudentPlacement::query()->create([
                        'academic_year_id' => $data['academic_year_id'],
                        'rombel_id' => $data['rombel_id'],
                        'student_id' => $studentId,
                        'status' => 'active',
                        'started_at' => $now,
                    ]);
                }

                $activeAssignment = HomeroomAssignment::query()
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('rombel_id', $data['rombel_id'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
                if ($activeAssignment?->teacher_id !== $data['teacher_id']) {
                    if (HomeroomAssignment::query()
                        ->where('academic_year_id', $data['academic_year_id'])
                        ->where('teacher_id', $data['teacher_id'])
                        ->where('status', 'active')
                        ->where('rombel_id', '!=', $data['rombel_id'])
                        ->exists()) {
                        throw ValidationException::withMessages(['teacher_id' => 'Guru sudah menjadi wali kelas lain pada Tahun Ajaran tersebut.']);
                    }
                    if ($activeAssignment !== null) {
                        $activeAssignment->update(['status' => 'ended', 'ended_at' => $now]);
                    }
                    HomeroomAssignment::query()->create([
                        'academic_year_id' => $data['academic_year_id'],
                        'rombel_id' => $data['rombel_id'],
                        'teacher_id' => $data['teacher_id'],
                        'status' => 'active',
                        'started_at' => $now,
                    ]);
                }
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'tr_curriculum_year_classes_academic_year_id_rombel_id_unique')) {
                throw ValidationException::withMessages(['rombel_id' => 'Rombel tersebut sudah digunakan pada Tahun Ajaran ini.']);
            }
            if (str_contains($exception->getMessage(), 'tr_curriculum_active_student_placement_unique')) {
                throw ValidationException::withMessages(['student_ids' => 'Salah satu siswa sudah memiliki kelas aktif pada Tahun Ajaran tersebut.']);
            }
            if (str_contains($exception->getMessage(), 'tr_curriculum_active_homeroom_teacher_unique')) {
                throw ValidationException::withMessages(['teacher_id' => 'Guru sudah menjadi wali kelas lain pada Tahun Ajaran tersebut.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Manajemen kelas berhasil diperbarui.');
    }

    public function storeStudent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('m_academic_years', 'id')->where('status', 'active')],
            'rombel_id' => ['required', 'integer', Rule::exists('m_rombels', 'id')->where('status', 'active')],
            'student_id' => ['required', 'integer', Rule::exists('m_students', 'id')->where('status', 'active')],
        ]);
        if (! YearClass::query()->where('academic_year_id', $data['academic_year_id'])->where('rombel_id', $data['rombel_id'])->exists()) {
            throw ValidationException::withMessages(['rombel_id' => 'Kelas belum terdaftar pada Tahun Ajaran tersebut.']);
        }
        try {
            DB::transaction(function () use ($data): void {
                StudentPlacement::query()
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('student_id', $data['student_id'])
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();
                if (StudentPlacement::query()->where('academic_year_id', $data['academic_year_id'])->where('student_id', $data['student_id'])->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['student_id' => 'Siswa sudah memiliki kelas aktif pada Tahun Ajaran tersebut.']);
                }
                StudentPlacement::query()->create([...$data, 'status' => 'active', 'started_at' => now()]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'tr_curriculum_active_student_placement_unique')) {
                throw ValidationException::withMessages(['student_id' => 'Siswa sudah memiliki kelas aktif pada Tahun Ajaran tersebut.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Siswa berhasil ditempatkan.');
    }

    public function promoteClass(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('m_academic_years', 'id')->where('status', 'active')],
            'source_rombel_id' => ['required', 'integer', Rule::exists('m_rombels', 'id')->where('status', 'active')],
        ]);

        DB::transaction(function () use ($data): void {
            $source = Rombel::query()->whereKey($data['source_rombel_id'])->where('status', 'active')->lockForUpdate()->first();
            if ($source === null) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Rombel sumber tidak aktif atau tidak ditemukan.']);
            }

            $nextGrade = match ($source->grade_level) {
                'VII' => 'VIII',
                'VIII' => 'IX',
                default => null,
            };
            if ($nextGrade === null) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Promosi hanya dapat dilakukan dari kelas VII atau VIII.']);
            }

            $yearClassExists = YearClass::query()
                ->where('academic_year_id', $data['academic_year_id'])
                ->where('rombel_id', $source->id)
                ->exists();
            if (! $yearClassExists) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Kelas sumber belum terdaftar pada Tahun Ajaran tersebut.']);
            }

            $target = Rombel::query()
                ->where('grade_level', $nextGrade)
                ->where('parallel_code', $source->parallel_code)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            if ($target === null) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Rombel tujuan dengan kode paralel yang sama belum tersedia.']);
            }
            if (! YearClass::query()->where('academic_year_id', $data['academic_year_id'])->where('rombel_id', $target->id)->exists()) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Kelas tujuan belum terdaftar pada Tahun Ajaran tersebut.']);
            }

            $placements = StudentPlacement::query()
                ->where('academic_year_id', $data['academic_year_id'])
                ->where('rombel_id', $source->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            if ($placements->isEmpty()) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Tidak ada siswa aktif pada kelas sumber.']);
            }

            $targetHasStudents = StudentPlacement::query()
                ->where('academic_year_id', $data['academic_year_id'])
                ->where('rombel_id', $target->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->exists();
            if ($targetHasStudents) {
                throw ValidationException::withMessages(['source_rombel_id' => 'Kelas tujuan masih memiliki siswa aktif. Kosongkan kelas tujuan sebelum promosi.']);
            }

            $now = now();
            StudentPlacement::query()
                ->whereKey($placements->modelKeys())
                ->update(['status' => 'completed', 'ended_at' => $now, 'updated_at' => $now]);
            StudentPlacement::query()->insert($placements->map(fn (StudentPlacement $placement): array => [
                'academic_year_id' => $data['academic_year_id'],
                'rombel_id' => $target->id,
                'student_id' => $placement->student_id,
                'status' => 'active',
                'started_at' => $now,
                'ended_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        return back()->with('success', 'Seluruh siswa berhasil dipromosikan ke kelas berikutnya.');
    }

    public function storeHomeroom(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', Rule::exists('m_academic_years', 'id')->where('status', 'active')],
            'rombel_id' => ['required', 'integer', Rule::exists('m_rombels', 'id')->where('status', 'active')],
            'teacher_id' => ['required', 'integer', Rule::exists('m_teacher', 'id')->where(fn ($query) => $query->where('status', 'active')->where('staff_type', 'guru'))],
        ]);
        try {
            DB::transaction(function () use ($data): void {
                if (! YearClass::query()->where('academic_year_id', $data['academic_year_id'])->where('rombel_id', $data['rombel_id'])->exists()) {
                    throw ValidationException::withMessages(['rombel_id' => 'Kelas belum terdaftar pada Tahun Ajaran tersebut.']);
                }
                HomeroomAssignment::query()
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where(function ($query) use ($data): void {
                        $query->where('rombel_id', $data['rombel_id'])
                            ->orWhere('teacher_id', $data['teacher_id']);
                    })
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->get();
                HomeroomAssignment::query()->where('academic_year_id', $data['academic_year_id'])->where('rombel_id', $data['rombel_id'])->where('status', 'active')->update(['status' => 'ended', 'ended_at' => now()]);
                if (HomeroomAssignment::query()->where('academic_year_id', $data['academic_year_id'])->where('teacher_id', $data['teacher_id'])->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['teacher_id' => 'Guru sudah menjadi wali kelas lain pada Tahun Ajaran tersebut.']);
                }
                HomeroomAssignment::query()->create([...$data, 'status' => 'active', 'started_at' => now()]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'tr_curriculum_active_homeroom_')) {
                throw ValidationException::withMessages(['teacher_id' => 'Guru sudah menjadi wali kelas lain pada Tahun Ajaran tersebut.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Wali kelas berhasil ditetapkan.');
    }
}
