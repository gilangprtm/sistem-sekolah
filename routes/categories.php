<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:inventory.category.view'])->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('can:inventory.category.create')->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware('can:inventory.category.update')->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:inventory.category.delete')->name('categories.destroy');
});
