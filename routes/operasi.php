<?php

use App\Http\Controllers\Operasi\AuxiliaryReadingController;
use App\Http\Controllers\Operasi\BeritaAcaraController;
use App\Http\Controllers\Operasi\DailyReportController;
use App\Http\Controllers\Operasi\DocumentTemplateController;
use App\Http\Controllers\Operasi\FeederReadingController;
use App\Http\Controllers\Operasi\FuelReceiptController;
use App\Http\Controllers\Operasi\LaporanController;
use App\Http\Controllers\Operasi\LogsheetController;
use App\Http\Controllers\Operasi\MasterController;
use App\Http\Controllers\Operasi\StarStopController;
use Illuminate\Support\Facades\Route;

/**
 * Modul OPERASI. Every route is additionally guarded inside its controller by a
 * permission check (operasi.*) and a unit-scope check, so membership of this
 * group is not on its own a grant.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('operasi')
    ->name('operasi.')
    ->group(function (): void {
        Route::get('input/laporan-harian', [DailyReportController::class, 'index'])
            ->name('input.daily-report.index');
        Route::post('input/laporan-harian', [DailyReportController::class, 'store'])
            ->name('input.daily-report.store');

        Route::get('input/star-stop', [StarStopController::class, 'index'])
            ->name('input.star-stop.index');
        Route::post('input/star-stop', [StarStopController::class, 'store'])
            ->name('input.star-stop.store');
        Route::delete('input/star-stop/{engineStatusLog}', [StarStopController::class, 'destroy'])
            ->name('input.star-stop.destroy');

        Route::get('input/feeder', [FeederReadingController::class, 'index'])
            ->name('input.feeder.index');
        Route::post('input/feeder', [FeederReadingController::class, 'store'])
            ->name('input.feeder.store');

        Route::get('input/pasokan-cadangan', [AuxiliaryReadingController::class, 'index'])
            ->name('input.auxiliary.index');
        Route::post('input/pasokan-cadangan', [AuxiliaryReadingController::class, 'store'])
            ->name('input.auxiliary.store');

        Route::get('input/penerimaan-bbm', [FuelReceiptController::class, 'index'])
            ->name('input.fuel-receipt.index');
        Route::post('input/penerimaan-bbm', [FuelReceiptController::class, 'store'])
            ->name('input.fuel-receipt.store');
        Route::delete('input/penerimaan-bbm/{fuelReceipt}', [FuelReceiptController::class, 'destroy'])
            ->name('input.fuel-receipt.destroy');

        Route::get('input/logsheet', [LogsheetController::class, 'index'])->name('input.logsheet.index');
        Route::post('input/logsheet', [LogsheetController::class, 'store'])->name('input.logsheet.store');
        Route::post('input/logsheet/submit', [LogsheetController::class, 'submit'])->name('input.logsheet.submit');

        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/{report}/excel', [LaporanController::class, 'spreadsheet'])->name('laporan.spreadsheet');
        Route::get('laporan/{report}', [LaporanController::class, 'show'])->name('laporan.show');

        Route::get('berita-acara', [BeritaAcaraController::class, 'index'])->name('berita-acara.index');
        Route::post('berita-acara', [BeritaAcaraController::class, 'store'])->name('berita-acara.store');
        Route::get('berita-acara/{type}', [BeritaAcaraController::class, 'show'])->name('berita-acara.show');
        Route::get('berita-acara/{type}/pdf', [BeritaAcaraController::class, 'pdf'])->name('berita-acara.pdf');

        Route::get('document-template', [DocumentTemplateController::class, 'index'])->name('document-template.index');
        Route::put('document-template/{type}', [DocumentTemplateController::class, 'update'])->name('document-template.update');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
