<?php

namespace App\Http\Requests;

use App\Models\CounselingNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCounselingNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('counseling_note');
        $ability = $note instanceof CounselingNote ? 'update' : 'create';

        return $this->user()?->can($ability, $note instanceof CounselingNote ? $note : CounselingNote::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists('m_students', 'id')],
            'counseling_date' => ['required', 'date_format:Y-m-d'],
            'content' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Siswa wajib dipilih.',
            'student_id.exists' => 'Siswa yang dipilih tidak tersedia.',
            'counseling_date.required' => 'Tanggal konseling wajib diisi.',
            'counseling_date.date_format' => 'Tanggal konseling tidak valid.',
            'content.required' => 'Catatan konseling wajib diisi.',
            'content.string' => 'Catatan konseling harus berupa teks.',
            'content.max' => 'Catatan konseling maksimal 5.000 karakter.',
        ];
    }
}
