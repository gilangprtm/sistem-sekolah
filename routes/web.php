<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('/assistant', 'assistant')->name('assistant');
});

require __DIR__.'/dashboard.php';
require __DIR__.'/users.php';
require __DIR__.'/roles.php';
require __DIR__.'/inventory.php';
require __DIR__.'/categories.php';
require __DIR__.'/inventory-types.php';
