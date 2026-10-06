<?php

use App\Http\Controllers\ManagementClassController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.view'])->group(function (): void {
    Route::get('/management-class', [ManagementClassController::class, 'index'])->name('management-class.index');
    Route::get('/management-class/manage', [ManagementClassController::class, 'manage'])->middleware('can:curriculum.update')->name('management-class.manage');
    Route::post('/management-class/classes', [ManagementClassController::class, 'storeClass'])->middleware('can:curriculum.update')->name('management-class.classes.store');
    Route::post('/management-class/classes/update', [ManagementClassController::class, 'updateClass'])->middleware('can:curriculum.update')->name('management-class.classes.update');
    Route::post('/management-class/classes/promote', [ManagementClassController::class, 'promoteClass'])->middleware('can:curriculum.update')->name('management-class.classes.promote');
    Route::post('/management-class/students', [ManagementClassController::class, 'storeStudent'])->middleware('can:curriculum.update')->name('management-class.students.store');
    Route::post('/management-class/homerooms', [ManagementClassController::class, 'storeHomeroom'])->middleware('can:curriculum.update')->name('management-class.homerooms.store');
});
