<?php

namespace App\Http\Requests;

use App\Models\Subject;
use App\Models\TeacherQuiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveTeacherQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quiz = $this->route('quiz');
        if ($quiz instanceof TeacherQuiz) {
            abort_unless($this->user()->can('teacher.quiz.view') && $quiz->created_by === $this->user()->id, 404);
        }

        return $this->user()->can('teacher.quiz.view');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'link' => ['required', 'url', 'regex:/^https:\/\/(?:docs\.google\.com\/forms\/d\/[^\s]+|forms\.gle\/[^\s]+)$/i', 'max:2048'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('subject_id')
                && ! Subject::query()->taughtBy($this->user())->whereKey($this->integer('subject_id'))->exists()) {
                $validator->errors()->add('subject_id', 'Pilih Mata Pelajaran aktif yang Anda ampu.');
            }
        }];
    }
}
