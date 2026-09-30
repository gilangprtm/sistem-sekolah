<?php

use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:student.view'])->group(function (): void {
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->middleware('can:student.create')->name('students.create');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->middleware('can:student.update')->name('students.edit');
    Route::post('/students', [StudentController::class, 'store'])->middleware('can:student.create')->name('students.store');
    Route::patch('/students/{student}', [StudentController::class, 'update'])->middleware('can:student.update')->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('can:student.delete')->name('students.destroy');
});
