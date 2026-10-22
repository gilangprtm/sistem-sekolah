<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('teacher.document.manage') === true
            || $this->user()?->hasRole('Super Admin') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'folder_id' => ['nullable', 'integer', 'min:1'],
            'visibility' => ['required_without:folder_id', 'nullable', Rule::in(['private', 'public'])],
            'file' => ['required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,gif',
                'mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/jpeg,image/png,image/webp,image/gif',
                'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,gif'],
        ];
    }
}
