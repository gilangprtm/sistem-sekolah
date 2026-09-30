<?php

namespace App\Http\Requests;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $teacher = $this->route('teacher');

        if ($user === null || ! $user->can($teacher === null ? 'teacher.create' : 'teacher.update')) {
            return false;
        }

        if ($teacher === null) {
            return ! $this->filled('user_id') || $user->can('teacher.assign-account');
        }

        $currentUserId = $teacher instanceof Teacher ? $teacher->user_id : null;

        return (string) $this->input('user_id') === (string) $currentUserId || $user->can('teacher.assign-account');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $teacher = $this->route('teacher');
        $teacherId = $teacher instanceof Teacher ? $teacher->id : null;

        return [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::unique('m_teacher', 'user_id')->ignore($teacherId)],
            'staff_type' => ['required', Rule::in(['guru', 'staff'])],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('user_id') || ! $this->filled('user_id')) {
                return;
            }

            $user = User::query()->find($this->integer('user_id'));
            $teacher = $this->route('teacher');
            $teacherId = $teacher instanceof Teacher ? $teacher->id : null;
            $requiredRole = $this->input('staff_type') === 'staff' ? 'Staff' : 'Guru';

            if ($user === null || ! $user->hasRole($requiredRole) || ($user->teacher !== null && $user->teacher->id !== $teacherId)) {
                $validator->errors()->add('user_id', "Akun harus ber-role {$requiredRole} dan belum terhubung ke profil lain.");
            }
        }];
    }
}
