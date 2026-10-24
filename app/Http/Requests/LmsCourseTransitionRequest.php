<?php

namespace App\Http\Requests;

use App\Models\LmsCourse;
use Illuminate\Foundation\Http\FormRequest;

class LmsCourseTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        $ability = match ($this->route()?->getName()) {
            'lms.design.revise' => 'revise',
            'lms.management.activate' => 'activate',
            'lms.management.archive' => 'archive',
            'lms.management.restore' => 'restore',
            default => null,
        };

        return $course instanceof LmsCourse && $ability !== null && ($this->user()?->can($ability, $course) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'revision_id' => ['required', 'string', 'size:64'],
            'decision' => ['prohibited'],
            'review_note' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'revision_id.required' => 'Muat ulang halaman sebelum melanjutkan aksi.',
            'revision_id.size' => 'Token revisi tidak valid. Muat ulang halaman.',
        ];
    }
}
