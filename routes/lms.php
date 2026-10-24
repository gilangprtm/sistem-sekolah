<?php

use App\Http\Controllers\LmsCourseDesignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('lms')
    ->name('lms.')
    ->group(function (): void {
        Route::middleware('can:viewAny,App\\Models\\LmsCourse')->group(function (): void {
            Route::get('/perancangan-course', [LmsCourseDesignController::class, 'index'])->name('design');
            Route::post('/perancangan-course', [LmsCourseDesignController::class, 'store'])->middleware('can:create,App\\Models\\LmsCourse')->name('design.store');
            Route::get('/perancangan-course/{course}/edit', [LmsCourseDesignController::class, 'edit'])->name('design.edit');
            Route::patch('/perancangan-course/{course}', [LmsCourseDesignController::class, 'update'])->name('design.update');
            Route::patch('/perancangan-course/{course}/content', [LmsCourseDesignController::class, 'updateContent'])->name('design.content.update');
            Route::get('/perancangan-course/{course}/media/{media}', [LmsCourseDesignController::class, 'viewMedia'])->name('design.media.view');
        });
        Route::inertia('/course-management', 'lms/index', ['section' => 'management'])->name('management');
        Route::inertia('/course-saya', 'lms/index', ['section' => 'my-courses'])->name('my-courses');
    });
