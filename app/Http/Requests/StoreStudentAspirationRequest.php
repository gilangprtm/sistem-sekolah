<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentAspirationRequest extends FormRequest
{
    /**
     * Determine if the authenticated student may send an aspiration.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Siswa') === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'content.required' => 'Aspirasi wajib diisi.',
            'content.string' => 'Aspirasi harus berupa teks.',
            'content.max' => 'Aspirasi maksimal 5.000 karakter.',
        ];
    }
}
