<?php

namespace App\Http\Controllers;

use App\Services\SchoolNewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, SchoolNewsService $schoolNews): Response|RedirectResponse
    {
        if ($request->user()->hasRole('Siswa')) {
            return to_route('student-app.dashboard');
        }

        return Inertia::render('dashboard', [
            'news' => $schoolNews->get(),
        ]);
    }
}
