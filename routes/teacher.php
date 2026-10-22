<?php

use App\DocumentModule;
use App\Http\Controllers\Teacher\AgendaController;
use App\Http\Controllers\Teacher\AgendaReportController;
use App\Http\Controllers\Teacher\AttendanceReportController;
use App\Http\Controllers\Teacher\DocumentController;
use App\Http\Controllers\Teacher\HomeroomController;
use App\Http\Controllers\Teacher\HomeroomSeatingController;
use App\Http\Controllers\Teacher\StudentAttendanceController;
use Illuminate\Support\Facades\Route;

foreach (DocumentModule::cases() as $module) {
    Route::middleware(['auth', 'verified'])->prefix($module->prefix())->name($module->value.'.documents.')->group(function (): void {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
        Route::post('/folders', [DocumentController::class, 'storeFolder'])->name('folders.store');
        Route::delete('/folders/{folder}', [DocumentController::class, 'destroyFolder'])->name('folders.destroy');
        Route::post('/files', [DocumentController::class, 'storeDocument'])->name('store');
        Route::get('/files/{document}/view', [DocumentController::class, 'view'])->name('view');
        Route::get('/files/{document}/download', [DocumentController::class, 'download'])->name('download');
        Route::delete('/files/{document}', [DocumentController::class, 'destroy'])->name('destroy');
    });
}

Route::get('/guru/agenda/cetak', AgendaReportController::class)
    ->middleware(['auth', 'verified', 'can:teacher.agenda.manage'])->name('teacher.agenda.report');
Route::get('/guru/agenda/unduh', [AgendaReportController::class, 'download'])
    ->middleware(['auth', 'verified', 'can:teacher.agenda.manage'])->name('teacher.agenda.download');
Route::get('/guru/kehadiran/unduh', AttendanceReportController::class)
    ->middleware(['auth', 'verified', 'can:teacher.homeroom.view'])
    ->name('teacher.attendance.report.download');

Route::put('/guru/kehadiran', [StudentAttendanceController::class, 'update'])
    ->middleware(['auth', 'verified', 'can:teacher.attendance.manage'])
    ->name('teacher.attendance.update');

Route::middleware(['auth', 'verified', 'can:teacher.agenda.manage'])
    ->prefix('guru/agenda')->name('teacher.agenda.')->group(function (): void {
        Route::get('/', [AgendaController::class, 'index'])->name('index');
        Route::get('/create', [AgendaController::class, 'create'])->name('create');
        Route::post('/', [AgendaController::class, 'store'])->name('store');
        Route::get('/{agenda}/edit', [AgendaController::class, 'edit'])->name('edit');
        Route::put('/{agenda}', [AgendaController::class, 'update'])->name('update');
    });

Route::middleware(['auth', 'verified', 'can:teacher.homeroom.view'])
    ->prefix('guru')
    ->group(function (): void {
        Route::get('/wali-kelas', HomeroomController::class)->name('teacher.homeroom');
        Route::put('/wali-kelas/tempat-duduk', [HomeroomSeatingController::class, 'update'])
            ->middleware('can:teacher.homeroom.seating.manage')
            ->name('teacher.homeroom.seating.update');
    });
