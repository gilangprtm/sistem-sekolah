<?php

use App\Http\Controllers\StudentApp\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('student')
    ->name('student-app.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
