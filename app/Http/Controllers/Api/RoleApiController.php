<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleApiController extends Controller
{
    /**
     * Daftar role.
     */
    public function index(): JsonResponse
    {
        // Catatan: tidak memakai withCount('users') karena relasi users() Spatie
        // bergantung pada auth.defaults.guard (bisa berubah ke 'sanctum' saat
        // request API), yang menyebabkan "Class name must be a valid object".
        // Hitung manual via model_has_roles.
        $roles = Role::with('permissions')->orderBy('name')->get();

        $roleUserCounts = \DB::table('model_has_roles')
            ->select('role_id')
            ->selectRaw('count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $roles->each(function ($role) use ($roleUserCounts) {
            $role->setAttribute('users_count', $roleUserCounts[$role->getKey()] ?? 0);
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar role.',
            'data' => $roles,
        ]);
    }

    /**
     * Detail role.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Detail role.',
            'data' => $role,
        ]);
    }

    /**
     * Buat role baru.
     */
    public function store(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dibuat.',
            'data' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Update role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Role Super Admin tidak dapat diubah.',
                'errors' => null,
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diperbarui.',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * Hapus role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Role Super Admin tidak dapat dihapus.',
                'errors' => null,
            ], 422);
        }

        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Role masih dipakai user.',
                'errors' => null,
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil dihapus.',
            'data' => null,
        ]);
    }

    /**
     * Assign permissions ke role.
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->syncPermissions($data['permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Permission role diperbarui.',
            'data' => $role->load('permissions'),
        ]);
    }

    /**
     * Hapus satu permission dari role.
     */
    public function removePermission(Role $role, Permission $permission): JsonResponse
    {
        $role->revokePermissionTo($permission);

        return response()->json([
            'success' => true,
            'message' => 'Permission dihapus dari role.',
            'data' => $role->load('permissions'),
        ]);
    }
}
