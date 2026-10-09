<?php

use App\Http\Controllers\ScheduleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.view'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::get('/schedule/generate', [ScheduleController::class, 'generate'])->name('schedule.generate');
    });

Route::middleware(['auth', 'verified', 'can:curriculum.schedule.manage'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::post('/schedule', [ScheduleController::class, 'save'])->name('schedule.save');
        Route::post('/schedule/move', [ScheduleController::class, 'move'])->name('schedule.move');
        Route::delete('/schedule', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
    });

Route::middleware(['auth', 'verified', 'can:curriculum.view'])->group(function (): void {
    Route::get('/schedule', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/schedule').$query, 301);
    });
    Route::get('/schedule/generate', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/schedule/generate').$query, 301);
    });
    Route::get('/jadwal-pelajaran', fn (Request $request): RedirectResponse => response()->redirectTo(
        url('/kurikulum/schedule').($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301
    ))->name('schedule.legacy.indonesian');
    Route::get('/schedules', fn (Request $request): RedirectResponse => response()->redirectTo(
        url('/kurikulum/schedule').($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301
    ))->name('schedule.legacy.plural');
    Route::get('/schedules/generate', fn (Request $request): RedirectResponse => response()->redirectTo(
        url('/kurikulum/schedule/generate').($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301
    ))->name('schedule.legacy.plural.generate');
});

Route::middleware(['auth', 'verified', 'can:curriculum.schedule.manage'])->group(function (): void {
    Route::post('/schedule', [ScheduleController::class, 'save']);
    Route::post('/schedule/move', [ScheduleController::class, 'move']);
    Route::delete('/schedule', [ScheduleController::class, 'destroy']);
});
