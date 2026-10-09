<?php

use App\Http\Controllers\Api\AssistantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/assistant/chat', [AssistantController::class, 'chat'])->name('assistant.chat');
});

require __DIR__.'/dashboard.php';
require __DIR__.'/users.php';
require __DIR__.'/students.php';
require __DIR__.'/student-app.php';
require __DIR__.'/teachers.php';
require __DIR__.'/academic-years.php';
require __DIR__.'/subjects.php';
require __DIR__.'/teacher-subjects.php';
require __DIR__.'/rombels.php';
require __DIR__.'/academic-period-rombels.php';
require __DIR__.'/management-class.php';
require __DIR__.'/schedules.php';
require __DIR__.'/roles.php';
require __DIR__.'/inventory.php';
require __DIR__.'/categories.php';
require __DIR__.'/inventory-types.php';
require __DIR__.'/inventory-rooms.php';
require __DIR__.'/kantin.php';
