<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchedulePublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('curriculum.schedule.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'schedule_plan_id' => [
                'required',
                'integer',
                Rule::exists('tr_curriculum_schedule_plans', 'id')->where('status', 'draft'),
            ],
        ];
    }
}
