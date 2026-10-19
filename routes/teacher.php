<?php

use App\Http\Controllers\Teacher\HomeroomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:teacher.homeroom.view'])
    ->prefix('guru')
    ->group(function (): void {
        Route::get('/wali-kelas', HomeroomController::class)->name('teacher.homeroom');
    });
