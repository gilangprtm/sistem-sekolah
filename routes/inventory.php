<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:inventory.view'])->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [InventoryController::class, 'create'])->middleware('can:inventory.create')->name('inventory.create');
    Route::post('/inventory', [InventoryController::class, 'store'])->middleware('can:inventory.create')->name('inventory.store');

    // Dashboard HARUS sebelum {item} agar tidak tertangkap model binding
    Route::get('/inventory/dashboard', [InventoryDashboardController::class, 'index'])
        ->middleware('can:inventory.dashboard.view')
        ->name('inventory.dashboard');

    Route::get('/inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::patch('/inventory/{item}', [InventoryController::class, 'update'])->middleware('can:inventory.unit.condition.update')->name('inventory.update');
    Route::delete('/inventory/{item}', [InventoryController::class, 'destroy'])->middleware('can:inventory.delete')->name('inventory.destroy');
    Route::post('/inventory/{item}/units', [InventoryController::class, 'addUnits'])->middleware('can:inventory.unit.create')->name('inventory.units.store');
    Route::patch('/inventory/{item}/units/{unit}', [InventoryController::class, 'updateCondition'])->middleware('can:inventory.unit.condition.update')->name('inventory.units.condition');
});
