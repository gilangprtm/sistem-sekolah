<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class StudentAccountGenerator
{
    /**
     * @return array{count: int, first_sequence: int, last_sequence: int, emails: array<int, string>, security_warning: string}
     */
    public function generate(int $year, int $count): array
    {
        if ($year < 1000 || $year > 9999) {
            throw ValidationException::withMessages(['year' => 'Tahun harus 4 digit.']);
        }
        if ($count < 1 || $count > 100) {
            throw ValidationException::withMessages(['count' => 'Jumlah harus antara 1 dan 100.']);
        }

        return DB::transaction(function () use ($year, $count): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$year]);
            }
            $pattern = '/^'.(int) $year.'([0-9]{3})@sekolah\\.sch\\.id$/';
            $lastSequence = User::query()
                ->where('email', 'like', sprintf('%04d%%@sekolah.sch.id', $year))
                ->pluck('email')
                ->map(function (string $email) use ($pattern): int {
                    return preg_match($pattern, $email, $matches) === 1 ? (int) $matches[1] : 0;
                })
                ->max() ?? 0;
            if ($lastSequence + $count > 999) {
                throw ValidationException::withMessages(['count' => 'Nomor akun Siswa melebihi batas 999.']);
            }

            $emails = [];
            for ($sequence = $lastSequence + 1; $sequence <= $lastSequence + $count; $sequence++) {
                $emails[] = sprintf('%04d%03d@sekolah.sch.id', $year, $sequence);
            }
            if (User::query()->whereIn('email', $emails)->exists()) {
                throw ValidationException::withMessages(['count' => 'Nomor akun Siswa bertabrakan.']);
            }

            $role = Role::query()->where('name', 'Siswa')->where('guard_name', 'web')->first();
            if ($role === null) {
                throw ValidationException::withMessages(['count' => 'Role Siswa belum tersedia.']);
            }
            $password = Hash::make('password123');
            try {
                foreach ($emails as $email) {
                    $user = new User;
                    $user->forceFill([
                        'name' => 'Siswa '.$email,
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => $password,
                    ])->save();
                    $user->assignRole($role);
                }
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['count' => 'Nomor akun Siswa bertabrakan.']);
            }

            return [
                'count' => $count,
                'first_sequence' => $lastSequence + 1,
                'last_sequence' => $lastSequence + $count,
                'emails' => $emails,
                'security_warning' => 'Akun dibuat dengan password awal bersama; wajib ganti password melalui alur reset yang akan disediakan.',
            ];
        });
    }
}
