<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveTeacherAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('teacher.agenda.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'agenda_date' => ['required', 'date_format:Y-m-d'],
            'block_key' => ['required', 'string', 'size:64'],
            'context_token' => ['required', 'string', 'size:64'],
            'material' => ['required', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'agenda_date.required' => 'Tanggal agenda wajib diisi.',
            'agenda_date.date_format' => 'Format tanggal agenda tidak valid.',
            'material.required' => 'Materi yang diajarkan wajib diisi.',
            'material.max' => 'Materi tidak boleh melebihi 10.000 karakter.',
        ];
    }
}
