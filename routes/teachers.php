<?php

use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:teacher.view'])->group(function (): void {
    Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::get('/teachers/create', [TeacherController::class, 'create'])->middleware('can:teacher.create')->name('teachers.create');
    Route::get('/teachers/{teacher}/edit', [TeacherController::class, 'edit'])->middleware('can:teacher.update')->name('teachers.edit');
    Route::post('/teachers', [TeacherController::class, 'store'])->middleware('can:teacher.create')->name('teachers.store');
    Route::patch('/teachers/{teacher}', [TeacherController::class, 'update'])->middleware('can:teacher.update')->name('teachers.update');
    Route::delete('/teachers/{teacher}', [TeacherController::class, 'destroy'])->middleware('can:teacher.delete')->name('teachers.destroy');
});
