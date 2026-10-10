<?php

use App\Http\Controllers\StudentApp\DashboardController;
use App\Http\Controllers\StudentApp\ProfileController;
use App\Http\Controllers\StudentApp\ScheduleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'role:Siswa'])
    ->prefix('student')
    ->name('student-app.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/schedule', ScheduleController::class)->name('schedule');
        Route::get('/chatbot', fn () => Inertia::render('student-app/chatbot'))->name('chatbot');
        Route::get('/profile', ProfileController::class)->name('profile');
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    });
