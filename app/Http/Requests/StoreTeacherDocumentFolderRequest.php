<?php

namespace App\Http\Requests;

use App\Models\TeacherDocumentFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherDocumentFolderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'visibility' => ['required_without:parent_id', 'nullable', Rule::in(['private', 'public'])],
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function parentFolder(): ?TeacherDocumentFolder
    {
        if ($this->integer('parent_id') < 1) {
            return null;
        }

        return TeacherDocumentFolder::query()->findOrFail($this->integer('parent_id'));
    }
}
