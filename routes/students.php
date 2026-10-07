<?php

use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:student.card.view'])->group(function (): void {
    Route::get('/students/cards', [StudentController::class, 'cards'])->name('students.cards.index');
});

Route::middleware(['auth', 'verified', 'can:student.card.print'])->group(function (): void {
    Route::get('/students/cards/print', [StudentController::class, 'cardsPrint'])->name('students.cards.print');
});

Route::middleware(['auth', 'verified', 'can:student.card.view'])->group(function (): void {
    Route::get('/students/cards/{student}', [StudentController::class, 'card'])->name('students.cards.show');
});

Route::middleware(['auth', 'verified', 'can:student.view'])->group(function (): void {
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/create', [StudentController::class, 'create'])->middleware('can:student.create')->name('students.create');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->middleware('can:student.update')->name('students.edit');
    Route::post('/students', [StudentController::class, 'store'])->middleware('can:student.create')->name('students.store');
    Route::patch('/students/{student}', [StudentController::class, 'update'])->middleware('can:student.update')->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->middleware('can:student.delete')->name('students.destroy');
});
