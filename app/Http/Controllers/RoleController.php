<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Daftar role + permission (Super Admin only).
     */
    public function index(Request $request): Response
    {
        $roles = Role::withCount('users')->orderBy('name')->get();

        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Simpan role baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create(['name' => $data['name']]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return back()->with('success', 'Role berhasil dibuat.');
    }

    /**
     * Update role (nama + permissions).
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        // Super Admin tidak boleh diubah namanya (dijaga sistem)
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Role Super Admin tidak dapat diubah.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update(['name' => $data['name']]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    /**
     * Hapus role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Role Super Admin tidak dapat dihapus.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Role masih dipakai user; tidak dapat dihapus.');
        }

        $role->delete();

        return back()->with('success', 'Role berhasil dihapus.');
    }
}
