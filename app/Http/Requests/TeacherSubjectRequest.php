<?php

namespace App\Http\Requests;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('curriculum.teacher_subject.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'suffix' => is_string($this->input('suffix')) ? trim($this->input('suffix')) : $this->input('suffix'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'integer', Rule::exists('m_teacher', 'id')],
            'subject_id' => ['required', 'integer', Rule::exists('m_subjects', 'id')],
            'suffix' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/', 'max:20'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['teacher_id', 'subject_id', 'suffix'])) {
                return;
            }

            $teacher = Teacher::query()->find($this->integer('teacher_id'));
            $subject = Subject::query()->find($this->integer('subject_id'));

            if ($teacher === null || $teacher->staff_type !== 'guru' || $teacher->status !== 'active') {
                $validator->errors()->add('teacher_id', 'Guru harus aktif dan bertipe Guru.');
            }

            if ($subject === null || $subject->status !== 'active') {
                $validator->errors()->add('subject_id', 'Mata Pelajaran harus aktif.');
            }

            if ($teacher === null || $subject === null) {
                return;
            }

            $code = $subject->code.$this->input('suffix');
            if (mb_strlen($code) > 50) {
                $validator->errors()->add('suffix', 'Kode Guru Mata Pelajaran maksimal 50 karakter.');
            }

            if (TeacherSubject::query()->where('teacher_id', $teacher->id)->where('subject_id', $subject->id)->exists()) {
                $validator->errors()->add('teacher_id', 'Guru sudah terhubung dengan Mata Pelajaran ini.');
            }

            if (TeacherSubject::query()->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->exists()) {
                $validator->errors()->add('suffix', 'Kode Guru Mata Pelajaran sudah digunakan.');
            }
        }];
    }
}
