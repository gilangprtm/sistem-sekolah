<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\InventoryApiController;
use App\Http\Controllers\Api\InventoryDashboardApiController;
use App\Http\Controllers\Api\InventoryTypeApiController;
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

    // Categories
    Route::middleware(['auth:sanctum', 'can:inventory.category.view'])->prefix('categories')->group(function () {
        Route::get('/', [CategoryApiController::class, 'index']);
        Route::post('/', [CategoryApiController::class, 'store'])->middleware('can:inventory.category.create');
        Route::get('/{category}', [CategoryApiController::class, 'show']);
        Route::patch('/{category}', [CategoryApiController::class, 'update'])->middleware('can:inventory.category.update');
        Route::delete('/{category}', [CategoryApiController::class, 'destroy'])->middleware('can:inventory.category.delete');
    });

    // Inventory types
    Route::middleware(['auth:sanctum', 'can:inventory.type.view'])->prefix('inventory-types')->group(function () {
        Route::get('/', [InventoryTypeApiController::class, 'index']);
        Route::post('/', [InventoryTypeApiController::class, 'store'])->middleware('can:inventory.type.create');
        Route::get('/{inventoryType}', [InventoryTypeApiController::class, 'show']);
        Route::patch('/{inventoryType}', [InventoryTypeApiController::class, 'update'])->middleware('can:inventory.type.update');
        Route::delete('/{inventoryType}', [InventoryTypeApiController::class, 'destroy'])->middleware('can:inventory.type.delete');
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
