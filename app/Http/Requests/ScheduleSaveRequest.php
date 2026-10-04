<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('curriculum.schedule.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'academic_period_id' => ['required', 'integer', Rule::exists('m_academic_periods', 'id')->where('status', 'active')],
            'grade' => ['nullable', Rule::in(['VII', 'VIII', 'IX'])],
            'selected_rombel_ids' => ['nullable', 'array', 'max:50'],
            'selected_rombel_ids.*' => [
                'required',
                'integer',
                Rule::exists('m_rombels', 'id')->where('status', 'active'),
            ],
            'source_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('tr_curriculum_schedule_plans', 'id')->whereIn('status', ['draft', 'published']),
            ],
            'custom_slots' => ['nullable', 'array', 'max:50'],
            'custom_slots.*.day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
            'custom_slots.*.lesson_number' => ['required', 'integer', 'min:1', 'max:15'],
            'custom_slots.*.label' => ['required', 'string', 'max:120'],
            'assignments' => ['required', 'array', 'min:1', 'max:500'],
            'assignments.*.teacher_subject_id' => ['required', 'integer', Rule::exists('m_teacher_subjects', 'id')],
            'assignments.*.rombel_ids' => ['required', 'array', 'min:1', 'max:50'],
            'assignments.*.rombel_ids.*' => ['required', 'integer', Rule::exists('m_rombels', 'id')],
            'manual_placements' => ['nullable', 'array', 'max:500'],
            'manual_placements.*.teacher_subject_id' => ['required', 'integer', Rule::exists('m_teacher_subjects', 'id')],
            'manual_placements.*.rombel_id' => ['required', 'integer', Rule::exists('m_rombels', 'id')],
            'manual_placements.*.slots' => ['required', 'array', 'min:1', 'max:15'],
            'manual_placements.*.slots.*.day' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])],
            'manual_placements.*.slots.*.lesson_number' => ['required', 'integer', 'min:1', 'max:15'],
        ];
    }
}
