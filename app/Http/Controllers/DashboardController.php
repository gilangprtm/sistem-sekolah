<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $isSuperAdmin = $user->hasRole('Super Admin');

        $stats = [];

        if ($isSuperAdmin) {
            $stats = [
                'users_count' => User::count(),
                'roles_count' => Role::count(),
            ];
        }

        return Inertia::render('dashboard', [
            'stats' => $stats,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }
}
