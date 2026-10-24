<?php

use App\Http\Controllers\LmsCourseDesignController;
use App\Http\Controllers\LmsCourseManagementController;
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
            Route::post('/perancangan-course/{course}/revise', [LmsCourseDesignController::class, 'revise'])->name('design.revise');
            Route::get('/perancangan-course/{course}/media/{media}', [LmsCourseDesignController::class, 'viewMedia'])->name('design.media.view');
        });
        Route::middleware('can:manage,App\\Models\\LmsCourse')->group(function (): void {
            Route::get('/course-management', [LmsCourseManagementController::class, 'index'])->name('management');
            Route::get('/course-management/{course}', [LmsCourseManagementController::class, 'show'])->name('management.show');
            Route::post('/course-management/{course}/activate', [LmsCourseManagementController::class, 'activate'])->name('management.activate');
            Route::post('/course-management/{course}/archive', [LmsCourseManagementController::class, 'archive'])->name('management.archive');
            Route::post('/course-management/{course}/restore', [LmsCourseManagementController::class, 'restore'])->name('management.restore');
            Route::get('/course-management/{course}/revisions/{revision}/media/{media}', [LmsCourseManagementController::class, 'media'])->name('management.media');
        });
        Route::inertia('/course-saya', 'lms/index', ['section' => 'my-courses'])->name('my-courses');
    });
