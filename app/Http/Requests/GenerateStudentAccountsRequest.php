<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateStudentAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:1000', 'max:9999'],
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'year.min' => 'Tahun harus 4 digit.',
            'year.max' => 'Tahun harus 4 digit.',
            'count.min' => 'Jumlah harus antara 1 dan 100.',
            'count.max' => 'Jumlah harus antara 1 dan 100.',
        ];
    }
}
