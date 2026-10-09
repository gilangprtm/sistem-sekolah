<?php

use App\Http\Controllers\KantinBarangController;
use App\Http\Controllers\KantinSaldoController;
use App\Http\Controllers\KantinPosController;
use Illuminate\Support\Facades\Route;

Route::post('/kantin/pos/identify', [KantinPosController::class, 'identify'])->middleware('throttle:20,1')->name('kantin.pos.identify');
Route::get('/kantin/pos', [KantinPosController::class, 'index'])->name('kantin.pos.index');

Route::middleware(['auth', 'verified'])->prefix('kantin')->group(function (): void {
    Route::middleware('can:kantin.barang.view')->group(function (): void {
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

    Route::middleware(['can:kantin.saldo.view', 'can:kantin.saldo.history.view'])->group(function (): void {
        Route::get('/saldo', [KantinSaldoController::class, 'index'])->name('kantin.saldo.index');
    });

    Route::middleware('can:kantin.saldo.topup')->get('/saldo/students', [KantinSaldoController::class, 'students'])
        ->name('kantin.saldo.students');
    Route::middleware('can:kantin.saldo.topup')->post('/saldo/top-up', [KantinSaldoController::class, 'storeTopUp'])
        ->name('kantin.saldo.top-up');
});
