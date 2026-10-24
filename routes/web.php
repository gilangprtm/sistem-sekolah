<?php

use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\CounselingNoteController;
use App\Http\Controllers\StudentViolationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/assistant/chat', [AssistantController::class, 'chat'])->name('assistant.chat');
});

Route::middleware(['auth', 'verified'])->prefix('kesiswaan/pelanggaran')->name('student-affairs.violations.')->group(function (): void {
    Route::get('/', [StudentViolationController::class, 'index'])->middleware('can:viewAny,App\\Models\\StudentViolation')->name('index');
    Route::get('/siswa', [StudentViolationController::class, 'students'])->middleware('can:viewAny,App\\Models\\StudentViolation')->name('students');
    Route::get('/jenis', [StudentViolationController::class, 'categories'])->middleware('can:viewAny,App\\Models\\StudentViolation')->name('categories');
    Route::get('/{student_violation}', [StudentViolationController::class, 'show'])->middleware('can:view,student_violation')->name('show');
    Route::post('/', [StudentViolationController::class, 'store'])->middleware('can:create,App\\Models\\StudentViolation')->name('store');
    Route::put('/{student_violation}', [StudentViolationController::class, 'update'])->middleware('can:update,student_violation')->name('update');
    Route::delete('/{student_violation}', [StudentViolationController::class, 'destroy'])->middleware('can:delete,student_violation')->name('destroy');
});

Route::middleware(['auth', 'verified'])->prefix('konseling/catatan')->name('counseling.notes.')->group(function (): void {
    Route::get('/', [CounselingNoteController::class, 'index'])->middleware('can:viewAny,App\\Models\\CounselingNote')->name('index');
    Route::get('/siswa', [CounselingNoteController::class, 'students'])->middleware('can:viewAny,App\\Models\\CounselingNote')->name('students');
    Route::get('/{counseling_note}', [CounselingNoteController::class, 'show'])->middleware('can:view,counseling_note')->name('show');
    Route::post('/', [CounselingNoteController::class, 'store'])->middleware('can:create,App\\Models\\CounselingNote')->name('store');
    Route::put('/{counseling_note}', [CounselingNoteController::class, 'update'])->middleware('can:update,counseling_note')->name('update');
    Route::delete('/{counseling_note}', [CounselingNoteController::class, 'destroy'])->middleware('can:delete,counseling_note')->name('destroy');
});

require __DIR__.'/dashboard.php';
require __DIR__.'/users.php';
require __DIR__.'/students.php';
require __DIR__.'/student-app.php';
require __DIR__.'/teachers.php';
require __DIR__.'/teacher.php';
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
require __DIR__.'/uas.php';
require __DIR__.'/lms.php';
