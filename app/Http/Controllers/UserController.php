<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateStudentAccountsRequest;
use App\Http\Requests\ImportStudentAccountsRequest;
use App\Models\User;
use App\Services\StudentAccountGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * Daftar user (Super Admin only).
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $roles = Role::query()->orderBy('name')->get(['id', 'name']);

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereKey($role)))
            ->when($status === 'verified', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($status === 'unverified', fn ($query) => $query->whereNull('email_verified_at'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role', 'status', 'per_page']),
            'filterOptions' => [
                'statuses' => [
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'unverified', 'label' => 'Unverified'],
                ],
            ],
            'generated_accounts' => $request->session()->get('generated_accounts'),
        ]);
    }

    /**
     * Simpan user baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User berhasil dibuat.']);

        return back();
    }

    public function generateStudentAccounts(GenerateStudentAccountsRequest $request, StudentAccountGenerator $generator): RedirectResponse
    {
        $result = $generator->generate($request->integer('year'), $request->integer('count'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['count'].' akun Siswa berhasil dibuat.',
        ]);

        return back()->with('generated_accounts', $result);
    }

    public function downloadStudentAccountsTemplate(): StreamedResponse
    {
        return response()->streamDownload(static function (): void {
            echo "Akun\r\n";
        }, 'template-akun-siswa.csv', [
            'Content-Disposition' => 'attachment; filename="template-akun-siswa.csv"',
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importStudentAccounts(ImportStudentAccountsRequest $request, StudentAccountGenerator $generator): RedirectResponse
    {
        $file = $request->file('file');
        $handle = $file?->openFile('r');

        if ($handle === null) {
            throw ValidationException::withMessages(['file' => 'CSV tidak dapat dibaca.']);
        }

        $header = $handle->fgetcsv();
        $header = is_array($header) ? array_map(static fn ($value): string => trim((string) $value, "\xEF\xBB\xBF \t"), $header) : null;

        if ($header !== ['Akun']) {
            throw ValidationException::withMessages(['file' => 'Header CSV wajib tepat satu kolom: Akun.']);
        }

        $nisns = [];
        while (($row = $handle->fgetcsv()) !== false) {
            if ($row === [null]) {
                if ($handle->fgetcsv() === false) {
                    break;
                }

                throw ValidationException::withMessages(['file' => 'CSV memiliki baris kosong.']);
            }

            if (count($row) !== 1) {
                throw ValidationException::withMessages(['file' => 'CSV memiliki format kolom yang tidak valid.']);
            }

            $nisn = trim((string) ($row[0] ?? ''));

            if ($nisn === '') {
                throw ValidationException::withMessages(['file' => 'CSV memiliki baris kosong.']);
            }

            if (preg_match('/^\d{10}$/', $nisn) !== 1) {
                throw ValidationException::withMessages(['file' => 'Format NISN tidak valid pada CSV.']);
            }

            if (in_array($nisn, $nisns, true)) {
                throw ValidationException::withMessages(['file' => 'NISN duplikat ditemukan pada CSV.']);
            }

            $nisns[] = $nisn;

            if (count($nisns) > 300) {
                throw ValidationException::withMessages(['file' => 'CSV maksimal berisi 300 NISN.']);
            }
        }

        $result = $generator->generateFromNisns($nisns);
        Inertia::flash('toast', ['type' => 'success', 'message' => $result['count'].' akun Siswa berhasil dibuat.']);

        return back()->with('generated_accounts', $result);
    }

    /**
     * Update user (nama, email, password opsional, roles).
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'User berhasil diperbarui.']);

        return back();
    }

    /**
     * Hapus user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Tidak dapat menghapus akun sendiri.']);

            return back();
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'error', 'message' => 'User berhasil dihapus.']);

        return back();
    }
}
