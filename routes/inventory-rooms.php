<?php

use App\Http\Controllers\InventoryRoomController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:inventory.room.view'])
    ->prefix('inventaris/inventory-rooms')
    ->group(function (): void {
        Route::get('/', [InventoryRoomController::class, 'index'])->name('inventory-rooms.index');
        Route::get('/create', [InventoryRoomController::class, 'create'])
            ->middleware('can:inventory.room.create')
            ->name('inventory-rooms.create');
        Route::post('/', [InventoryRoomController::class, 'store'])
            ->middleware('can:inventory.room.create')
            ->name('inventory-rooms.store');
        Route::get('/{inventoryRoom}/edit', [InventoryRoomController::class, 'edit'])
            ->middleware('can:inventory.room.update')
            ->name('inventory-rooms.edit');
        Route::patch('/{inventoryRoom}', [InventoryRoomController::class, 'update'])
            ->middleware('can:inventory.room.update')
            ->name('inventory-rooms.update');
        Route::delete('/{inventoryRoom}', [InventoryRoomController::class, 'destroy'])
            ->middleware('can:inventory.room.delete')
            ->name('inventory-rooms.destroy');
    });

Route::middleware(['auth', 'verified', 'can:inventory.room.view'])->group(function (): void {
    Route::get('/inventory-rooms', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/inventaris/inventory-rooms').$query, 301);
    });
    Route::get('/inventory-rooms/create', fn (): RedirectResponse => response()->redirectTo(route('inventory-rooms.create'), 301))
        ->middleware('can:inventory.room.create');
    Route::get('/inventory-rooms/{inventoryRoom}/edit', fn (int $inventoryRoom): RedirectResponse => response()->redirectTo(route('inventory-rooms.edit', $inventoryRoom), 301))
        ->middleware('can:inventory.room.update');

    Route::post('/inventory-rooms', [InventoryRoomController::class, 'store'])
        ->middleware('can:inventory.room.create');
    Route::patch('/inventory-rooms/{inventoryRoom}', [InventoryRoomController::class, 'update'])
        ->middleware('can:inventory.room.update');
    Route::delete('/inventory-rooms/{inventoryRoom}', [InventoryRoomController::class, 'destroy'])
        ->middleware('can:inventory.room.delete');
});
