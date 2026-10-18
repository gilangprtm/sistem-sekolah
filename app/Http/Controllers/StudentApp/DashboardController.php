<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Models\StudentPlacement;
use App\Services\SchoolNewsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, SchoolNewsService $schoolNews): Response
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
            'student' => $student === null ? null : array_merge(
                $student->only(['full_name', 'nis']),
                [
                    'photo_url' => $this->photoUrl($student->photo_path),
                    'has_kantin_pin' => $student->kantin_pin_hash !== null,
                ],
            ),
            'rombel' => $placement?->rombel->name ?? $placement?->rombel->code,
            'news' => $schoolNews->get(),
        ]);
    }

    private function photoUrl(?string $photoPath): ?string
    {
        return $photoPath === null
            ? null
            : Storage::disk('public')->url($photoPath);
    }
}
