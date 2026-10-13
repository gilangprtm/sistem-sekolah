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

            if (preg_match('/^\d+$/', $amount) === 1) {
                $amount .= '.00';
            } elseif (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $amount) === 1) {
                $amount = str_replace('.', '', $amount).'.00';
            } elseif (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/', $amount, $matches) === 1) {
                $amount = str_replace('.', '', $matches[1]).'.'.str_pad($matches[2], 2, '0');
            } elseif (preg_match('/^(\d+)[,.](\d{1,2})$/', $amount, $matches) === 1) {
                $amount = $matches[1].'.'.str_pad($matches[2], 2, '0');
            }

            $this->merge(['amount' => $amount]);
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
