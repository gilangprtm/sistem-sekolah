<?php

namespace App\Http\Requests;

use App\Models\StudentViolation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudentViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('student_violation');
        $ability = $note instanceof StudentViolation ? 'update' : 'create';

        return $this->user()?->can($ability, $note instanceof StudentViolation ? $note : StudentViolation::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('m_students', 'id')],
            'violation_date' => ['required', 'date_format:Y-m-d'],
            'category' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:5000'],
            'follow_up' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih.',
            'student_id.exists' => 'Siswa yang dipilih tidak tersedia.',
            'violation_date.required' => 'Tanggal pelanggaran wajib diisi.',
            'violation_date.date_format' => 'Tanggal pelanggaran tidak valid.',
            'category.required' => 'Jenis/kategori pelanggaran wajib diisi.',
            'category.string' => 'Jenis/kategori pelanggaran harus berupa teks.',
            'category.max' => 'Jenis/kategori pelanggaran maksimal 150 karakter.',
            'content.required' => 'Uraian pelanggaran wajib diisi.',
            'content.string' => 'Uraian pelanggaran harus berupa teks.',
            'content.max' => 'Uraian pelanggaran maksimal 5.000 karakter.',
            'follow_up.string' => 'Tindak lanjut/sanksi harus berupa teks.',
            'follow_up.max' => 'Tindak lanjut/sanksi maksimal 5.000 karakter.',
        ];
    }
}
