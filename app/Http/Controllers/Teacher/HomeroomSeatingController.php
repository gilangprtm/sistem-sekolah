<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\HomeroomAssignment;
use App\Models\HomeroomSeat;
use App\Models\Rombel;
use App\Models\StudentPlacement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeroomSeatingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seats' => ['required', 'array', 'size:40'],
            'seats.*' => ['required', 'array:seat_number,student_id'],
            'seats.*.seat_number' => ['required', 'integer', 'between:1,40', 'distinct'],
            'seats.*.student_id' => ['present', 'nullable', 'integer', 'distinct'],
            'version' => ['required', 'string', 'size:64'],
        ]);

        $context = $this->context($request);
        $assignments = collect($request->array('seats'))->keyBy('seat_number');

        if ($assignments->keys()->sort()->values()->all() !== range(1, 40)) {
            throw ValidationException::withMessages([
                'seats' => 'Denah harus memuat tepat 40 meja bernomor 1 hingga 40.',
            ]);
        }

        $studentIds = $assignments
            ->pluck('student_id')
            ->filter(fn ($id): bool => $id !== null)
            ->values();

        $result = DB::transaction(function () use ($request, $context, $validated, $assignments, $studentIds): array {
            Rombel::query()->whereKey($context['rombel_id'])->lockForUpdate()->firstOrFail();

            if ($this->context($request) !== $context) {
                throw ValidationException::withMessages(['seats' => 'Penugasan wali kelas telah berubah. Muat ulang halaman.']);
            }

            StudentPlacement::query()
                ->where('academic_year_id', $context['year_id'])
                ->where('rombel_id', $context['rombel_id'])
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $eligibleCount = StudentPlacement::query()
                ->where('academic_year_id', $context['year_id'])
                ->where('rombel_id', $context['rombel_id'])
                ->where('status', 'active')
                ->whereIn('student_id', $studentIds)
                ->whereHas('student', fn ($query) => $query->where('status', 'active'))
                ->distinct()->count('student_id');

            if ($eligibleCount !== $studentIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'seats' => 'Pilih siswa aktif yang terdaftar di kelas wali Anda.',
                ]);
            }

            HomeroomSeat::query()
                ->where('academic_year_id', $context['year_id'])
                ->where('rombel_id', $context['rombel_id'])
                ->orderBy('seat_number')
                ->lockForUpdate()
                ->get(['id']);

            $snapshot = HomeroomSeat::snapshot($context['year_id'], $context['rombel_id']);

            if (! hash_equals($snapshot['version'], $validated['version'])) {
                return ['conflict' => true, ...$snapshot];
            }

            HomeroomSeat::query()
                ->where('academic_year_id', $context['year_id'])
                ->where('rombel_id', $context['rombel_id'])
                ->update(['student_id' => null]);

            foreach (range(1, 40) as $seatNumber) {
                HomeroomSeat::query()->updateOrCreate(
                    [
                        'academic_year_id' => $context['year_id'],
                        'rombel_id' => $context['rombel_id'],
                        'seat_number' => $seatNumber,
                    ],
                    ['student_id' => $assignments->get($seatNumber)['student_id'] ?? null],
                );
            }

            $snapshot = HomeroomSeat::snapshot($context['year_id'], $context['rombel_id']);

            return ['conflict' => false, ...$snapshot];
        }, 3);

        if ($result['conflict']) {
            throw ValidationException::withMessages([
                'seats' => 'Denah telah berubah. Muat ulang halaman sebelum mengatur tempat duduk kembali.',
            ]);
        }

        return to_route('teacher.homeroom');
    }

    /** @return array{year_id: int, rombel_id: int} */
    private function context(Request $request): array
    {
        $academicYear = AcademicYear::query()
            ->where('status', 'active')
            ->latest('id')
            ->first();
        $teacherId = $request->user()->teacher?->id;

        abort_if($academicYear === null || $teacherId === null, 404);

        $assignment = HomeroomAssignment::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('teacher_id', $teacherId)
            ->where('status', 'active')
            ->first();

        abort_if($assignment === null, 404);

        return ['year_id' => $academicYear->id, 'rombel_id' => $assignment->rombel_id];
    }
}
