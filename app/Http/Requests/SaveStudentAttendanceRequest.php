<?php

namespace App\Http\Requests;

use App\Models\StudentAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudentAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('teacher.attendance.manage')
            && ($user->can('teacher.agenda.manage') || $user->can('teacher.homeroom.view'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['agenda', 'homeroom'])],
            'date' => ['required', 'date_format:Y-m-d'],
            'block_key' => [Rule::requiredIf(fn (): bool => $this->input('source') === 'agenda' && ! $this->filled('agenda_id')), 'nullable', 'string', 'size:64'],
            'agenda_id' => ['nullable', 'integer', 'min:1'],
            'attendance' => ['required', 'array', 'min:1', 'max:100'],
            'attendance.*' => ['required', 'array:student_id,status,version'],
            'attendance.*.student_id' => ['required', 'integer', 'distinct', 'min:1'],
            'attendance.*.status' => ['required', Rule::in(StudentAttendance::STATUSES)],
            'attendance.*.version' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance.required' => 'Absensi siswa wajib diisi.',
            'attendance.*.status.in' => 'Status absensi harus H, I, S, D, atau A.',
            'attendance.*.version.required' => 'Versi absensi tidak tersedia. Muat ulang halaman.',
        ];
    }
}
