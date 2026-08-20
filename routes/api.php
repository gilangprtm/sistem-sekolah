<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\Api\InventoryDashboardApiController;
use App\Http\Controllers\Api\RoleApiController;
use App\Http\Controllers\Api\UserApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Auth publik
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    // Users (Super Admin)
    Route::middleware(['auth:sanctum', 'can:users.manage'])->prefix('users')->group(function () {
        Route::get('/', [UserApiController::class, 'index']);
        Route::post('/', [UserApiController::class, 'store']);
        Route::get('/{user}', [UserApiController::class, 'show']);
        Route::patch('/{user}', [UserApiController::class, 'update']);
        Route::delete('/{user}', [UserApiController::class, 'destroy']);
        Route::post('/{user}/roles', [UserApiController::class, 'assignRoles']);
        Route::delete('/{user}/roles/{role}', [UserApiController::class, 'removeRole']);
    });

    // Roles (Super Admin)
    Route::middleware(['auth:sanctum', 'can:roles.manage'])->prefix('roles')->group(function () {
        Route::get('/', [RoleApiController::class, 'index']);
        Route::post('/', [RoleApiController::class, 'store']);
        Route::get('/{role}', [RoleApiController::class, 'show']);
        Route::patch('/{role}', [RoleApiController::class, 'update']);
        Route::delete('/{role}', [RoleApiController::class, 'destroy']);
        Route::post('/{role}/permissions', [RoleApiController::class, 'assignPermissions']);
        Route::delete('/{role}/permissions/{permission}', [RoleApiController::class, 'removePermission']);
    });

    // Inventory (permission inventory.view / inventory.manage)
    Route::middleware(['auth:sanctum', 'can:inventory.view'])->prefix('inventory')->group(function () {
        Route::get('/', [InventoryApiController::class, 'index']);
        Route::get('/dashboard', [InventoryDashboardApiController::class, 'index'])
            ->middleware('can:inventory.dashboard.view');
        Route::post('/', [InventoryApiController::class, 'store'])
            ->middleware('can:inventory.create');
        Route::get('/{item}', [InventoryApiController::class, 'show']);
        Route::patch('/{item}', [InventoryApiController::class, 'update'])
            ->middleware('can:inventory.unit.condition.update');
        Route::delete('/{item}', [InventoryApiController::class, 'destroy'])
            ->middleware('can:inventory.delete');
        Route::post('/{item}/units', [InventoryApiController::class, 'addUnits'])
            ->middleware('can:inventory.unit.create');
        Route::patch('/{item}/units/{unit}', [InventoryApiController::class, 'updateUnitCondition'])
            ->middleware('can:inventory.unit.condition.update');
    });
});
