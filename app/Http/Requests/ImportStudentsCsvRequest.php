<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImportStudentsCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('student.view')
            && $user->can('student.create')
            && $user->can('student.update');
    }

    /**
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:1024',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    $extension = $value instanceof UploadedFile
                        ? strtolower($value->getClientOriginalExtension())
                        : '';

                    if (! in_array($extension, ['xlsx', 'csv'], true)) {
                        $fail('File import harus berformat Excel (.xlsx).');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File Excel wajib dipilih.',
            'file.file' => 'File import tidak valid.',
            'file.max' => 'Ukuran file Excel maksimal 1 MB.',
        ];
    }
}
