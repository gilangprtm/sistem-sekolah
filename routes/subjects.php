<?php

use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:subject.view'])->group(function (): void {
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->middleware('can:subject.create')->name('subjects.store');
    Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->middleware('can:subject.update')->name('subjects.update');
    Route::post('/subjects/{subject}/archive', [SubjectController::class, 'archive'])->middleware('can:subject.delete')->name('subjects.archive');
});
