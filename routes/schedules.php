<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.view'])->group(function (): void {
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/schedule/generate', [ScheduleController::class, 'generate'])->name('schedule.generate');
    Route::get('/jadwal-pelajaran', [ScheduleController::class, 'legacyIndex'])->name('schedule.legacy.indonesian');
    Route::get('/schedules', [ScheduleController::class, 'legacyIndex'])->name('schedule.legacy.plural');
    Route::get('/schedules/generate', [ScheduleController::class, 'legacyGenerate'])->name('schedule.legacy.plural.generate');
});

Route::middleware(['auth', 'verified', 'can:curriculum.schedule.manage'])->group(function (): void {
    Route::post('/schedule', [ScheduleController::class, 'save'])->name('schedule.save');
    Route::post('/schedule/move', [ScheduleController::class, 'move'])->name('schedule.move');
    Route::delete('/schedule', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
});
