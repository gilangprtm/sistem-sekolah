<?php

namespace App\Http\Requests;

use App\Models\TeacherStudentNote;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TeacherStudentNote::class) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:5000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.required' => 'Catatan siswa wajib diisi.',
            'content.string' => 'Catatan siswa harus berupa teks.',
            'content.max' => 'Catatan siswa maksimal 5.000 karakter.',
        ];
    }
}
