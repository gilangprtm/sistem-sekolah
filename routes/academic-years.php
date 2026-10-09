<?php

use App\Http\Controllers\AcademicYearController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.view'])
    ->prefix('kurikulum')
    ->group(function (): void {
        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->middleware('can:curriculum.create')->name('academic-years.store');
        Route::patch('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->middleware('can:curriculum.update')->name('academic-years.update');
        Route::post('/academic-years/{academicYear}/periods', [AcademicYearController::class, 'storePeriod'])->middleware('can:curriculum.create')->name('academic-periods.store');
        Route::patch('/academic-periods/{academicPeriod}', [AcademicYearController::class, 'updatePeriod'])->middleware('can:curriculum.update')->name('academic-periods.update');
    });

Route::middleware(['auth', 'verified', 'can:curriculum.view'])->group(function (): void {
    Route::get('/academic-years', function (Request $request): RedirectResponse {
        $query = $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return response()->redirectTo(url('/kurikulum/academic-years').$query, 301);
    });
    Route::post('/academic-years', [AcademicYearController::class, 'store'])->middleware('can:curriculum.create');
    Route::patch('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->middleware('can:curriculum.update');
    Route::post('/academic-years/{academicYear}/periods', [AcademicYearController::class, 'storePeriod'])->middleware('can:curriculum.create');
    Route::patch('/academic-periods/{academicPeriod}', [AcademicYearController::class, 'updatePeriod'])->middleware('can:curriculum.update');
});
