<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

require __DIR__.'/dashboard.php';
require __DIR__.'/users.php';
require __DIR__.'/roles.php';
require __DIR__.'/inventory.php';
