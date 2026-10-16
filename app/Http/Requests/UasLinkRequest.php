<?php

namespace App\Http\Requests;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\UasLink;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UasLinkRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('uas.legacy.store', 'uas.legacy.update')) {
            $this->merge(['category' => 'UAS']);
        }
    }

    public function authorize(): bool
    {
        $permission = $this->route('uasLink') instanceof UasLink ? 'curriculum.uas.update' : 'curriculum.uas.create';

        return $this->user()?->can($permission) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $yearId = AcademicYear::query()->where('status', 'active')->latest('id')->value('id');
        $periodId = $yearId === null
            ? null
            : AcademicPeriod::query()->where('academic_year_id', $yearId)->where('status', 'active')->latest('id')->value('id');
        $link = $this->route('uasLink');

        return [
            'category' => ['required', Rule::in(['UTS', 'UAS'])],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('m_subjects', 'id')->where('status', 'active'),
                Rule::unique('tr_curriculum_uas_links', 'subject_id')
                    ->where('academic_year_id', $yearId)
                    ->where('academic_period_id', $periodId)
                    ->where('category', $this->input('category'))
                    ->ignore($link instanceof UasLink ? $link->id : null),
            ],
            'link' => [
                'required',
                'url',
                'regex:/^https:\/\/(?:docs\.google\.com\/forms\/d\/[^\s]+|forms\.gle\/[^\s]+)$/i',
                'max:2048',
            ],
            'opens_at_date' => ['nullable', 'required_with:opens_at_time', 'date_format:Y-m-d'],
            'opens_at_time' => ['nullable', 'required_with:opens_at_date', 'date_format:H:i'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $date = $this->input('opens_at_date');
            $time = $this->input('opens_at_time');

            if ($date !== null && $time !== null && ! $validator->errors()->has('opens_at_date') && ! $validator->errors()->has('opens_at_time')) {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", 'Asia/Makassar');
                $parseErrors = CarbonImmutable::getLastErrors();

                if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
                    $validator->errors()->add('opens_at_date', 'Tanggal dan jam buka tidak valid.');
                }
            }

            if ($validator->errors()->has('subject_id')) {
                return;
            }

            $year = AcademicYear::query()->where('status', 'active')->latest('id')->first();
            $period = $year?->periods()->where('status', 'active')->latest('id')->first();

            if ($year === null || $period === null) {
                $validator->errors()->add('subject_id', 'Tahun Ajaran dan semester aktif belum tersedia.');

                return;
            }

            $link = $this->route('uasLink');

            if ($link instanceof UasLink && ($link->academic_period_id !== $period->id || $link->academic_year_id !== $year->id)) {
                $validator->errors()->add('subject_id', 'Link hanya dapat diubah pada konteks akademik aktif.');
            }
        }];
    }
}
