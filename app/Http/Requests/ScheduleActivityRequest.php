<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleActivityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('label'))) {
            $this->merge(['label' => trim($this->input('label'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('curriculum.schedule.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'schedule_plan_id' => ['required', 'integer', Rule::exists('tr_curriculum_schedule_plans', 'id')->where('status', 'published')],
            'day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
            'lesson_number' => ['required', 'integer', 'min:1', 'max:15'],
            'label' => ['required', 'string', 'max:120'],
            'selected_rombel_ids' => ['nullable', 'array', 'max:50'],
            'selected_rombel_ids.*' => ['required', 'integer', Rule::exists('m_rombels', 'id')->where('status', 'active')],
        ];
    }
}
