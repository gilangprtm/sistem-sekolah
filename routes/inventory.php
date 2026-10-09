<?php

use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryDashboardController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:inventory.view'])
    ->prefix('inventaris/inventory')
    ->group(function (): void {
        Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/export/excel', [InventoryController::class, 'exportExcel'])->name('inventory.export.excel');

        Route::get('/create', [InventoryController::class, 'create'])
            ->middleware('can:inventory.create')
            ->name('inventory.create');
        Route::post('/', [InventoryController::class, 'store'])
            ->middleware('can:inventory.create')
            ->name('inventory.store');

        // Dashboard HARUS sebelum {item} agar tidak tertangkap model binding.
        Route::get('/dashboard', [InventoryDashboardController::class, 'index'])
            ->middleware('can:inventory.dashboard.view')
            ->name('inventory.dashboard');

        Route::get('/{item}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::patch('/{item}', [InventoryController::class, 'update'])
            ->middleware('can:inventory.unit.condition.update')
            ->name('inventory.update');
        Route::delete('/{item}', [InventoryController::class, 'destroy'])
            ->middleware('can:inventory.delete')
            ->name('inventory.destroy');
        Route::post('/{item}/units', [InventoryController::class, 'addUnits'])
            ->middleware('can:inventory.unit.create')
            ->name('inventory.units.store');
        Route::patch('/{item}/units/{unit}', [InventoryController::class, 'updateCondition'])
            ->middleware('can:inventory.unit.condition.update')
            ->name('inventory.units.condition');
        Route::patch('/{item}/units/{unit}/room', [InventoryController::class, 'updateRoom'])
            ->middleware('can:inventory.room.assign')
            ->name('inventory.units.room');
    });

/*
 * Legacy web paths remain available during the URL migration. Read requests
 * redirect to the canonical Inventaris path; write requests retain their old
 * behavior so existing clients do not lose payloads on a redirect.
 */
Route::middleware(['auth', 'verified', 'can:inventory.view'])->group(function (): void {
    Route::get('/inventory', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/inventaris/inventory').$query, 301);
    });
    Route::get('/inventory/create', fn (): RedirectResponse => response()->redirectTo(route('inventory.create'), 301))
        ->middleware('can:inventory.create');
    Route::get('/inventory/dashboard', fn (): RedirectResponse => response()->redirectTo(route('inventory.dashboard'), 301))
        ->middleware('can:inventory.dashboard.view');
    Route::get('/inventory/export/excel', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(route('inventory.export.excel').$query, 301);
    });
    Route::get('/inventory/{item}', fn (int $item): RedirectResponse => response()->redirectTo(route('inventory.show', $item), 301));

    Route::post('/inventory', [InventoryController::class, 'store'])
        ->middleware('can:inventory.create');
    Route::patch('/inventory/{item}', [InventoryController::class, 'update'])
        ->middleware('can:inventory.unit.condition.update');
    Route::delete('/inventory/{item}', [InventoryController::class, 'destroy'])
        ->middleware('can:inventory.delete');
    Route::post('/inventory/{item}/units', [InventoryController::class, 'addUnits'])
        ->middleware('can:inventory.unit.create');
    Route::patch('/inventory/{item}/units/{unit}', [InventoryController::class, 'updateCondition'])
        ->middleware('can:inventory.unit.condition.update');
    Route::patch('/inventory/{item}/units/{unit}/room', [InventoryController::class, 'updateRoom'])
        ->middleware('can:inventory.room.assign');
});
