<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RombelPeriodUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('curriculum.rombel_usage.create') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'academic_period_id' => [
                'required',
                Rule::exists('m_academic_periods', 'id')->where('status', 'active'),
            ],
            'rombel_id' => [
                'required',
                Rule::exists('m_rombels', 'id')->where('status', 'active'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function validatedForUsage(): array
    {
        return $this->validated();
    }
}
