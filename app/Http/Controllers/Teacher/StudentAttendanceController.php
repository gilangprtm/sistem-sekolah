<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudentAttendanceRequest;
use App\Services\StudentAttendanceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StudentAttendanceController extends Controller
{
    public function update(SaveStudentAttendanceRequest $request, StudentAttendanceService $service): RedirectResponse
    {
        $service->save($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kehadiran harian berhasil disimpan.']);

        return back();
    }
}
