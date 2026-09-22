<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:category.view'])->group(function () {
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('can:category.create')->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware('can:category.update')->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('can:category.delete')->name('categories.destroy');
});
