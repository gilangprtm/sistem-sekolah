<?php

namespace App\Http\Requests;

use App\Models\LmsCourse;
use Illuminate\Validation\Rule;

class SaveLmsCourseContentRequest extends SaveLmsCourseRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'subject_id' => ['sometimes', 'required', 'integer', Rule::exists('m_subjects', 'id')->where('status', 'active')],
            'grade_level' => ['sometimes', 'required', 'string', Rule::in(LmsCourse::GRADE_LEVELS)],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'revision_id' => ['required', 'string'],
            'content_json' => ['required', 'string', 'json', 'max:1000000'],
            'uploads' => ['sometimes', 'array', 'max:10'],
            'uploads.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'content' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'reviewed_by' => ['prohibited'],
            'archived_at' => ['prohibited'],

        ];
    }
}
