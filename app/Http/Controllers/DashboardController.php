<?php

namespace App\Http\Controllers;

use App\Services\SchoolNewsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(SchoolNewsService $schoolNews): Response
    {
        return Inertia::render('dashboard', [
            'news' => $schoolNews->get(),
        ]);
    }
}
