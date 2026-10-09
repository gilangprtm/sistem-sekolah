<?php

use App\Http\Controllers\RombelController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.rombel.view'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::get('/rombels', [RombelController::class, 'index'])->name('rombels.index');
        Route::post('/rombels', [RombelController::class, 'store'])->middleware('can:curriculum.rombel.create')->name('rombels.store');
        Route::patch('/rombels/{rombel}', [RombelController::class, 'update'])->middleware('can:curriculum.rombel.update')->name('rombels.update');
        Route::post('/rombels/{rombel}/archive', [RombelController::class, 'archive'])->middleware('can:curriculum.rombel.delete')->name('rombels.archive');
    });

Route::middleware(['auth', 'verified', 'can:curriculum.rombel.view'])->group(function (): void {
    Route::get('/rombels', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/rombels').$query, 301);
    });
    Route::post('/rombels', [RombelController::class, 'store'])->middleware('can:curriculum.rombel.create');
    Route::patch('/rombels/{rombel}', [RombelController::class, 'update'])->middleware('can:curriculum.rombel.update');
    Route::post('/rombels/{rombel}/archive', [RombelController::class, 'archive'])->middleware('can:curriculum.rombel.delete');
});
