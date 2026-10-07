<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\StudentPlacement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user()->student;

        $placement = $student === null
            ? null
            : StudentPlacement::query()
                ->with('rombel:id,name,code')
                ->where('student_id', $student->id)
                ->where('status', 'active')
                ->latest('started_at')
                ->first();

        return Inertia::render('student-app/dashboard', [
            'student' => $student?->only(['full_name', 'nis']),
            'rombel' => $placement?->rombel?->name ?? $placement?->rombel?->code,
        ]);
    }
}
