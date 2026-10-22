<?php

namespace App\Http\Requests;

use App\DocumentModule;
use App\Models\TeacherDocumentFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherDocumentFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = DocumentModule::tryFrom(explode('.', $this->route()?->getName() ?? '')[0]);

        return $module !== null && $this->user() !== null && $module->canManage($this->user());
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

        return TeacherDocumentFolder::query()->where('module', DocumentModule::fromRequest($this)->value)->findOrFail($this->integer('parent_id'));
    }
}
