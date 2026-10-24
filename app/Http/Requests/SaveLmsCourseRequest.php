<?php

namespace App\Http\Requests;

use App\Models\LmsCourse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLmsCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof LmsCourse
            ? $this->user()?->can('update', $course) ?? false
            : $this->user()?->can('create', LmsCourse::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('m_subjects', 'id')->where('status', 'active'),
            ],
            'grade_level' => ['required', 'string', Rule::in(LmsCourse::GRADE_LEVELS)],
            'description' => ['nullable', 'string', 'max:10000'],
            'revision_id' => [
                Rule::requiredIf($this->route('course') instanceof LmsCourse),
                'nullable',
                'string',
            ],
            'designer_id' => [
                Rule::excludeIf(! $this->isMethod('post') || ! $this->user()?->can('manageDesigns', LmsCourse::class)),
                Rule::requiredIf($this->isMethod('post') && $this->user()?->can('manageDesigns', LmsCourse::class)),
                'nullable',
                'integer',
                Rule::exists('m_teacher', 'id')->where('status', 'active')->where('staff_type', 'guru'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul course wajib diisi.',
            'title.max' => 'Judul course maksimal 180 karakter.',
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'subject_id.exists' => 'Mata pelajaran tidak tersedia atau sudah diarsipkan.',
            'grade_level.required' => 'Jenjang wajib dipilih.',
            'grade_level.in' => 'Jenjang yang dipilih tidak tersedia.',
            'description.max' => 'Deskripsi maksimal 10.000 karakter.',
            'designer_id.required' => 'Guru perancang wajib dipilih.',
            'designer_id.exists' => 'Guru perancang tidak tersedia atau tidak aktif.',

            'revision_id.required' => 'Draf course telah berubah. Muat ulang halaman sebelum menyimpan.',
            'revision_id.string' => 'Draf course tidak valid. Muat ulang halaman sebelum menyimpan.',
        ];
    }
}
