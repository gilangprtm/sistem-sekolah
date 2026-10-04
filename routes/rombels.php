<?php

use App\Http\Controllers\RombelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.rombel.view'])->group(function (): void {
    Route::get('/rombels', [RombelController::class, 'index'])->name('rombels.index');
    Route::post('/rombels', [RombelController::class, 'store'])->middleware('can:curriculum.rombel.create')->name('rombels.store');
    Route::patch('/rombels/{rombel}', [RombelController::class, 'update'])->middleware('can:curriculum.rombel.update')->name('rombels.update');
    Route::post('/rombels/{rombel}/archive', [RombelController::class, 'archive'])->middleware('can:curriculum.rombel.delete')->name('rombels.archive');
});
