<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(string $screen = 'default'): Response
    {
        $screens = [
            'default',
            'crm',
            'finance',
            'analytics',
            'productivity',
            'ecommerce',
            'academy',
            'logistics',
            'infrastructure',
            'mail',
            'chat',
            'calendar',
            'kanban',
            'tasks',
            'invoice',
            'users',
            'roles',
            'coming-soon',
            'default-v1',
            'crm-v1',
            'finance-v1',
            'analytics-v1',
        ];

        if (! in_array($screen, $screens, true)) {
            abort(404);
        }

        return Inertia::render("dashboard/{$screen}");
    }
}
