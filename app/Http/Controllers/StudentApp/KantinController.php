<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KantinController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('student-app/kantin', [
            'has_kantin_pin' => $request->user()->student?->kantin_pin_hash !== null,
        ]);
    }
}
