<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AutoLinkStudentAccounts
{
    /**
     * @return array{linked: int, skipped: int}
     */
    public function handle(): array
    {
        try {
            return DB::transaction(function (): array {
                $linked = 0;
                $skipped = 0;

                Student::query()
                    ->whereNull('user_id')
                    ->lockForUpdate()
                    ->chunkById(200, function ($students) use (&$linked, &$skipped): void {
                        $emails = $students
                            ->filter(static fn (Student $student): bool => is_string($student->nisn)
                                && preg_match('/^[0-9]+$/D', $student->nisn) === 1)
                            ->map(static fn (Student $student): string => $student->nisn.'@sekolah.sch.id');
                        $accounts = User::query()
                            ->whereIn('email', $emails)
                            ->whereHas('roles', static fn ($query) => $query
                                ->where('roles.name', 'Siswa')
                                ->where('roles.guard_name', 'web'))
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get(['id', 'email'])
                            ->keyBy('email');
                        $assignedAccountIds = Student::query()
                            ->whereIn('user_id', $accounts->pluck('id'))
                            ->pluck('user_id')
                            ->all();

                        foreach ($students as $student) {
                            $account = $accounts->get($student->nisn.'@sekolah.sch.id');

                            if ($account === null || in_array($account->id, $assignedAccountIds, true)) {
                                $skipped++;

                                continue;
                            }

                            $student->update(['user_id' => $account->id]);
                            $assignedAccountIds[] = $account->id;
                            $linked++;
                        }
                    });

                return ['linked' => $linked, 'skipped' => $skipped];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'accounts' => 'Penautan dibatalkan karena akun baru saja ditautkan oleh proses lain. Muat ulang data sebelum mencoba lagi.',
            ]);
        }
    }
}
