<?php

use App\Http\Controllers\KantinBarangController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:kantin.barang.view'])->prefix('kantin')->group(function (): void {
    Route::get('/barang', [KantinBarangController::class, 'index'])->name('kantin.barang.index');
    Route::post('/barang', [KantinBarangController::class, 'store'])
        ->middleware('can:kantin.barang.create')
        ->name('kantin.barang.store');
    Route::patch('/barang/{kantinBarang}', [KantinBarangController::class, 'update'])
        ->middleware('can:kantin.barang.update')
        ->name('kantin.barang.update');
    Route::post('/barang/{kantinBarang}/archive', [KantinBarangController::class, 'archive'])
        ->middleware('can:kantin.barang.delete')
        ->name('kantin.barang.archive');
});
