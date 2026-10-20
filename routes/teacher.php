<?php

use App\Http\Controllers\Teacher\HomeroomController;
use App\Http\Controllers\Teacher\HomeroomSeatingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:teacher.homeroom.view'])
    ->prefix('guru')
    ->group(function (): void {
        Route::get('/wali-kelas', HomeroomController::class)->name('teacher.homeroom');
        Route::put('/wali-kelas/tempat-duduk', [HomeroomSeatingController::class, 'update'])
            ->middleware('can:teacher.homeroom.seating.manage')
            ->name('teacher.homeroom.seating.update');
    });
