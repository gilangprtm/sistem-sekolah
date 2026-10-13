<?php

namespace App\Http\Requests;

use App\Support\RupiahAmount;
use Illuminate\Foundation\Http\FormRequest;

class KantinTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('kantin.saldo.topup') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        if (is_string($amount)) {
            $this->merge(['amount' => RupiahAmount::normalize($amount)]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:m_students,id,status,active'],
            'amount' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'decimal:0,2', 'min:0.01'],

        ];
    }
}
