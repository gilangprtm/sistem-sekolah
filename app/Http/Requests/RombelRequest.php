<?php

namespace App\Http\Requests;

use App\Models\Rombel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RombelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('rombel') === null ? 'curriculum.rombel.create' : 'curriculum.rombel.update') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'grade_level' => is_string($this->input('grade_level')) ? strtoupper(trim($this->input('grade_level'))) : $this->input('grade_level'),
            'parallel_code' => is_string($this->input('parallel_code')) ? strtoupper(trim($this->input('parallel_code'))) : $this->input('parallel_code'),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $rombel = $this->route('rombel');
        $rombelId = $rombel instanceof Rombel ? $rombel->id : null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'grade_level' => ['required', Rule::in(['VII', 'VIII', 'IX'])],
            'parallel_code' => ['required', 'string', 'alpha_num', 'max:10'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'code' => ['prohibited'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['name', 'grade_level', 'parallel_code'])) {
                return;
            }

            $code = $this->input('grade_level').'-'.$this->input('parallel_code');
            $rombel = $this->route('rombel');
            $query = Rombel::query()->whereRaw('LOWER(code) = ?', [mb_strtolower($code)]);
            if ($rombel instanceof Rombel) {
                $query->whereKeyNot($rombel->id);
            }
            if ($query->exists()) {
                $validator->errors()->add('parallel_code', 'Kode Rombel sudah digunakan.');
            }
        }];
    }
}
