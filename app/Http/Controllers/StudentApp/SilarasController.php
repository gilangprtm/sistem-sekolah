<?php

namespace App\Http\Controllers\StudentApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentAspirationRequest;
use App\Models\HumasAspiration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SilarasController extends Controller
{
    public function store(StoreStudentAspirationRequest $request): RedirectResponse
    {
        $student = $request->user()->student;

        if ($student === null) {
            return back(fallback: route('student-app.services'))->withErrors([
                'content' => 'Akun ini belum terhubung dengan data siswa.',
            ]);
        }

        HumasAspiration::query()->create([
            'student_id' => $student->id,
            'content' => $request->validated('content'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aspirasi berhasil dikirim.']);

        return back(fallback: route('student-app.services'));
    }
}
