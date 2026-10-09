<?php

use App\Http\Controllers\ManagementClassController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.view'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::get('/management-class', [ManagementClassController::class, 'index'])->name('management-class.index');
        Route::get('/management-class/manage', [ManagementClassController::class, 'manage'])->middleware('can:curriculum.update')->name('management-class.manage');
        Route::post('/management-class/classes', [ManagementClassController::class, 'storeClass'])->middleware('can:curriculum.update')->name('management-class.classes.store');
        Route::post('/management-class/classes/update', [ManagementClassController::class, 'updateClass'])->middleware('can:curriculum.update')->name('management-class.classes.update');
        Route::post('/management-class/classes/promote', [ManagementClassController::class, 'promoteClass'])->middleware('can:curriculum.update')->name('management-class.classes.promote');
        Route::post('/management-class/students', [ManagementClassController::class, 'storeStudent'])->middleware('can:curriculum.update')->name('management-class.students.store');
        Route::post('/management-class/homerooms', [ManagementClassController::class, 'storeHomeroom'])->middleware('can:curriculum.update')->name('management-class.homerooms.store');
    });

Route::middleware(['auth', 'verified', 'can:curriculum.view'])->group(function (): void {
    Route::get('/management-class', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/management-class').$query, 301);
    });
    Route::get('/management-class/manage', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/management-class/manage').$query, 301);
    })->middleware('can:curriculum.update');
    Route::post('/management-class/classes', [ManagementClassController::class, 'storeClass'])->middleware('can:curriculum.update');
    Route::post('/management-class/classes/update', [ManagementClassController::class, 'updateClass'])->middleware('can:curriculum.update');
    Route::post('/management-class/classes/promote', [ManagementClassController::class, 'promoteClass'])->middleware('can:curriculum.update');
    Route::post('/management-class/students', [ManagementClassController::class, 'storeStudent'])->middleware('can:curriculum.update');
    Route::post('/management-class/homerooms', [ManagementClassController::class, 'storeHomeroom'])->middleware('can:curriculum.update');
});
