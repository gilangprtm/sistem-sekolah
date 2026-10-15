<?php

use App\Http\Controllers\StudentApp\DashboardController;
use App\Http\Controllers\StudentApp\ExamAttemptController;
use App\Http\Controllers\StudentApp\KantinController;
use App\Http\Controllers\StudentApp\ProfileController;
use App\Http\Controllers\StudentApp\ScheduleController;
use App\Http\Controllers\StudentApp\UasController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'role:Siswa'])
    ->prefix('student')
    ->name('student-app.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/schedule', ScheduleController::class)->name('schedule');
        Route::get('/uas', UasController::class)->name('uas');
        Route::get('/uas/{uasLink}/open', [UasController::class, 'open'])->name('uas.open');
        Route::get('/uas/{uasLink}/attempt', [ExamAttemptController::class, 'show'])->name('uas.attempt');
        Route::post('/uas/{uasLink}/attempt', [ExamAttemptController::class, 'start'])->name('uas.attempt.start');
        Route::post('/uas/attempts/{attempt}/events', [ExamAttemptController::class, 'event'])->name('uas.attempt.event');
        Route::get('/chatbot', fn () => Inertia::render('student-app/chatbot'))->name('chatbot');
        Route::get('/kantin', KantinController::class)->name('kantin');
        Route::get('/profile', ProfileController::class)->name('profile');
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('profile.password');
        Route::put('/profile/kantin-pin', [ProfileController::class, 'updateKantinPin'])
            ->middleware('throttle:6,1')
            ->name('profile.kantin-pin');
        Route::delete('/profile/kantin-pin', [ProfileController::class, 'resetKantinPin'])
            ->middleware('throttle:6,1')
            ->name('profile.kantin-pin.reset');
    });
