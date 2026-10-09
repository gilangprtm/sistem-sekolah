<?php

use App\Http\Controllers\TeacherSubjectController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.teacher_subject.view'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::get('/teacher-subjects', [TeacherSubjectController::class, 'index'])->name('teacher-subjects.index');
        Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store'])->middleware('can:curriculum.teacher_subject.create')->name('teacher-subjects.store');
        Route::delete('/teacher-subjects/{teacherSubject}', [TeacherSubjectController::class, 'destroy'])->middleware('can:curriculum.teacher_subject.delete')->name('teacher-subjects.destroy');
    });

Route::middleware(['auth', 'verified', 'can:curriculum.teacher_subject.view'])->group(function (): void {
    Route::get('/teacher-subjects', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/teacher-subjects').$query, 301);
    });
    Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store'])->middleware('can:curriculum.teacher_subject.create');
    Route::delete('/teacher-subjects/{teacherSubject}', [TeacherSubjectController::class, 'destroy'])->middleware('can:curriculum.teacher_subject.delete');
});
