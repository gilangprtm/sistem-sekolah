<?php

use App\Http\Controllers\RombelPeriodUsageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:curriculum.rombel_usage.view'])->group(function (): void {
    Route::get('/academic-period-rombels', [RombelPeriodUsageController::class, 'index'])->name('academic-period-rombels.index');
    Route::post('/academic-period-rombels', [RombelPeriodUsageController::class, 'store'])->middleware('can:curriculum.rombel_usage.create')->name('academic-period-rombels.store');
    Route::delete('/academic-period-rombels/{rombelPeriodUsage}', [RombelPeriodUsageController::class, 'destroy'])->middleware('can:curriculum.rombel_usage.delete')->name('academic-period-rombels.destroy');
});
