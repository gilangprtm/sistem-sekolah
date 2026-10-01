<?php

use App\Http\Controllers\TeacherSubjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.teacher_subject.view'])->group(function (): void {
    Route::get('/teacher-subjects', [TeacherSubjectController::class, 'index'])->name('teacher-subjects.index');
    Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store'])->middleware('can:curriculum.teacher_subject.create')->name('teacher-subjects.store');
    Route::delete('/teacher-subjects/{teacherSubject}', [TeacherSubjectController::class, 'destroy'])->middleware('can:curriculum.teacher_subject.delete')->name('teacher-subjects.destroy');
});
