<?php

namespace App\Http\Requests;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $student = $this->route('student');
        $permission = $student === null ? 'student.create' : 'student.update';

        if (! $user->can($permission)) {
            return false;
        }

        if ($student === null) {
            return ! $this->filled('user_id') || $user->can('student.assign-account');
        }

        $currentUserId = $student instanceof Student ? $student->user_id : null;
        $requestedUserId = $this->input('user_id');
        $accountChanged = (string) $requestedUserId !== (string) $currentUserId;

        return ! $accountChanged || $user->can('student.assign-account');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $student = $this->route('student');
        $studentId = $student instanceof Student ? $student->id : null;

        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('m_students', 'user_id')->ignore($studentId),
            ],
            'nis' => ['nullable', 'string', 'max:255', Rule::unique('m_students', 'nis')->ignore($studentId)],
            'tahun_angkatan' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'photo' => ['nullable', 'image', 'max:1024'],
            'remove_photo' => ['nullable', 'boolean'],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('user_id') || ! $this->filled('user_id')) {
                return;
            }

            $user = User::query()->find($this->integer('user_id'));
            $student = $this->route('student');
            $studentId = $student instanceof Student ? $student->id : null;

            if ($user === null || ! $user->hasRole('Siswa') || ($user->student !== null && $user->student->id !== $studentId)) {
                $validator->errors()->add('user_id', 'Akun harus ber-role Siswa dan belum terhubung ke profil lain.');
            }
        }];
    }
}
