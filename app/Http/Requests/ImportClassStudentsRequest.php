<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ImportClassStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('curriculum.update') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('m_academic_years', 'id')->where('status', 'active')],
            'rombel_id' => ['required', 'integer', Rule::exists('m_rombels', 'id')->where('status', 'active')],
            'file' => [
                'required', 'file', 'max:1024',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || strtolower($value->getClientOriginalExtension()) !== 'xlsx') {
                        $fail('File import harus berformat Excel (.xlsx).');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'File Excel wajib dipilih.',
            'file.file' => 'File import tidak valid.',
            'file.max' => 'Ukuran file Excel maksimal 1 MB.',
        ];
    }
}
