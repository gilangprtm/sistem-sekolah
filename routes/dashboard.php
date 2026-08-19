<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/{screen}', [DashboardController::class, 'index'])->name('dashboard.screen');
    Route::inertia('/chat', 'chat')->name('chat');
    Route::inertia('/mail', 'mail')->name('mail');
});

require __DIR__.'/settings.php';
