<?php

use App\Http\Controllers\InventoryTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:inventory.type.view'])->group(function () {
    Route::get('/inventory-types', [InventoryTypeController::class, 'index'])->name('inventory-types.index');
    Route::post('/inventory-types', [InventoryTypeController::class, 'store'])->middleware('can:inventory.type.create')->name('inventory-types.store');
    Route::patch('/inventory-types/{inventoryType}', [InventoryTypeController::class, 'update'])->middleware('can:inventory.type.update')->name('inventory-types.update');
    Route::delete('/inventory-types/{inventoryType}', [InventoryTypeController::class, 'destroy'])->middleware('can:inventory.type.delete')->name('inventory-types.destroy');
});
