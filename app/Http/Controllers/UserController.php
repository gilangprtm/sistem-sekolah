<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateStudentAccountsRequest;
use App\Models\User;
use App\Services\StudentAccountGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

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

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function generateStudentAccounts(GenerateStudentAccountsRequest $request, StudentAccountGenerator $generator): RedirectResponse
    {
        $result = $generator->generate($request->integer('year'), $request->integer('count'));

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

        return back()->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Hapus user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return back()->with('success', 'User berhasil dihapus.');
    }
}
