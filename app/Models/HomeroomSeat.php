<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HomeroomSeat extends Model
{
    protected $table = 'tr_teacher_homeroom_seats';

    protected $fillable = ['academic_year_id', 'rombel_id', 'seat_number', 'student_id'];

    protected function casts(): array
    {
        return ['seat_number' => 'integer', 'student_id' => 'integer'];
    }

    /**
     * @return array{seats: array<int, array{seat_number: int, student_id: int}>, version: string, students: list<array{id: int, full_name: string, photo_url: string|null}>}
     */
    public static function layout(int $academicYearId, int $rombelId): array
    {
        $students = Student::query()
            ->where('status', 'active')
            ->whereIn('id', StudentPlacement::query()->select('student_id')
                ->where('academic_year_id', $academicYearId)
                ->where('rombel_id', $rombelId)
                ->where('status', 'active'))
            ->orderBy('full_name')->orderBy('id')
            ->get(['id', 'full_name', 'photo_path'])
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'photo_url' => $student->photo_path === null ? null : Storage::disk('public')->url($student->photo_path),
            ])->all();

        return [...static::snapshot($academicYearId, $rombelId), 'students' => array_values($students)];
    }

    /** @return array{seats: array<int, array{seat_number: int, student_id: int}>, version: string} */
    public static function snapshot(int $academicYearId, int $rombelId): array
    {
        $activeStudents = StudentPlacement::query()
            ->select('student_id')
            ->where('academic_year_id', $academicYearId)
            ->where('rombel_id', $rombelId)
            ->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->where('status', 'active'));

        $seats = static::query()
            ->where('academic_year_id', $academicYearId)
            ->where('rombel_id', $rombelId)
            ->whereIn('student_id', $activeStudents)
            ->orderBy('seat_number')
            ->get(['seat_number', 'student_id'])
            ->map(fn (self $seat): array => [
                'seat_number' => $seat->seat_number,
                'student_id' => $seat->student_id,
            ])->all();

        return ['seats' => $seats, 'version' => hash('sha256', json_encode([$academicYearId, $rombelId, $seats], JSON_THROW_ON_ERROR))];
    }
}
