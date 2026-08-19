<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

// Auth demo pages (v1 / v2 showcase screens — not the main Fortify auth flow)
Route::get('/auth/v1/login', fn () => Inertia::render('auth/v1/login'))->name('auth.v1.login');
Route::get('/auth/v1/register', fn () => Inertia::render('auth/v1/register'))->name('auth.v1.register');
Route::get('/auth/v2/login', fn () => Inertia::render('auth/v2/login'))->name('auth.v2.login');
Route::get('/auth/v2/register', fn () => Inertia::render('auth/v2/register'))->name('auth.v2.register');

require __DIR__.'/dashboard.php';
