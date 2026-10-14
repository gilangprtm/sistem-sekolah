<?php

use App\Http\Controllers\UasLinkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.uas.view'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::get('/ujian', [UasLinkController::class, 'index'])->name('uas.index');
        Route::post('/ujian', [UasLinkController::class, 'store'])->middleware('can:curriculum.uas.create')->name('uas.store');
        Route::patch('/ujian/{uasLink}', [UasLinkController::class, 'update'])->middleware('can:curriculum.uas.update')->name('uas.update');
        Route::delete('/ujian/{uasLink}', [UasLinkController::class, 'destroy'])->middleware('can:curriculum.uas.delete')->name('uas.destroy');

        Route::get('/uas', fn (Request $request) => redirect()->route('uas.index', $request->query()))->name('uas.legacy.index');
        Route::post('/uas', [UasLinkController::class, 'store'])->middleware('can:curriculum.uas.create')->name('uas.legacy.store');
        Route::patch('/uas/{uasLink}', [UasLinkController::class, 'update'])->middleware('can:curriculum.uas.update')->name('uas.legacy.update');
        Route::delete('/uas/{uasLink}', [UasLinkController::class, 'destroy'])->middleware('can:curriculum.uas.delete')->name('uas.legacy.destroy');
    });
