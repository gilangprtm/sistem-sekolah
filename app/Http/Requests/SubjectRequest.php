<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $subject = $this->route('subject');
        if ($user === null) {
            return false;
        }

        if ($subject === null) {
            return $user->can('subject.create');
        }

        $isArchiving = $subject instanceof Subject
            && $subject->status !== 'inactive'
            && $this->input('status') === 'inactive';

        return $user->can('subject.update')
            && (! $isArchiving || $user->can('subject.delete'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? trim($this->input('code')) : $this->input('code'),
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $subject = $this->route('subject');
        $subjectId = $subject instanceof Subject ? $subject->id : null;

        return [
            'code' => [
                'required',
                'string',
                'regex:/^[A-Z0-9_-]+$/',
                'max:30',
                Rule::unique('m_subjects', 'code')->ignore($subjectId),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('m_subjects', 'name')->ignore($subjectId),
            ],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $subject = $this->route('subject');
            if ($subject instanceof Subject
                && $subject->teacherSubjects()->exists()
                && $this->input('code') !== $subject->code) {
                $validator->errors()->add('code', 'Kode tidak dapat diubah selama masih memiliki relasi Guru Mata Pelajaran.');
            }

            $subject = $this->route('subject');
            $subjectId = $subject instanceof Subject ? $subject->id : null;

            foreach (['code', 'name'] as $field) {
                if ($validator->errors()->has($field) || ! is_string($this->input($field))) {
                    continue;
                }

                $duplicate = Subject::query()
                    ->whereRaw("LOWER({$field}) = ?", [mb_strtolower($this->input($field))])
                    ->when($subjectId !== null, function ($query) use ($subjectId): void {
                        $query->whereKeyNot($subjectId);
                    })
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add($field, "Nilai {$field} sudah digunakan.");
                }
            }
        }];
    }
}
