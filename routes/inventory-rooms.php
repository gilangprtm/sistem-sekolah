<?php

use App\Http\Controllers\InventoryRoomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:inventory.room.view'])->group(function (): void {
    Route::get('/inventory-rooms', [InventoryRoomController::class, 'index'])->name('inventory-rooms.index');
    Route::get('/inventory-rooms/create', [InventoryRoomController::class, 'create'])
        ->middleware('can:inventory.room.create')
        ->name('inventory-rooms.create');
    Route::post('/inventory-rooms', [InventoryRoomController::class, 'store'])
        ->middleware('can:inventory.room.create')
        ->name('inventory-rooms.store');
    Route::get('/inventory-rooms/{inventoryRoom}/edit', [InventoryRoomController::class, 'edit'])
        ->middleware('can:inventory.room.update')
        ->name('inventory-rooms.edit');
    Route::patch('/inventory-rooms/{inventoryRoom}', [InventoryRoomController::class, 'update'])
        ->middleware('can:inventory.room.update')
        ->name('inventory-rooms.update');
    Route::delete('/inventory-rooms/{inventoryRoom}', [InventoryRoomController::class, 'destroy'])
        ->middleware('can:inventory.room.delete')
        ->name('inventory-rooms.destroy');
});
