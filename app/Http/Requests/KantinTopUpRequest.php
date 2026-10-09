<?php

namespace App\Http\Requests;

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
            $amount = trim($amount);
            if (preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?$/', $amount) === 1) {
                $parts = explode(',', $amount, 2);
                $amount = str_replace('.', '', $parts[0]).'.'.str_pad($parts[1] ?? '00', 2, '0');
            }

            $this->merge(['amount' => $amount]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:m_students,id,status,active'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],

        ];
    }
}
