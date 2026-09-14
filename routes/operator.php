<?php

use App\Http\Controllers\Operator\AbsensiController;
use App\Http\Controllers\Operator\AbsensiReportController;
use App\Http\Controllers\Operator\LogsheetController;
use App\Http\Controllers\Operator\LogsheetReportController;
use App\Services\Operasi\LogsheetAggregator;
use Illuminate\Support\Facades\Route;

/**
 * Modul OPERATOR — layer input lapangan oleh operator (logsheet harian &
 * jadwal/absensi shift). Modul tersendiri, terpisah dari OPERASI. Setiap route
 * dijaga di controller-nya dengan permission (operator.*) + cek scope unit.
 *
 * OPERASI dapat menarik data dari modul ini nanti (lihat
 * {@see LogsheetAggregator}) — hook disiapkan, belum aktif.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('operator')
    ->name('operator.')
    ->group(function (): void {
        Route::get('logsheet', [LogsheetController::class, 'index'])->name('logsheet.index');
        Route::post('logsheet', [LogsheetController::class, 'store'])->name('logsheet.store');
        Route::post('logsheet/submit', [LogsheetController::class, 'submit'])->name('logsheet.submit');

        Route::get('absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::post('absensi', [AbsensiController::class, 'store'])->name('absensi.store');
        Route::post('absensi/generate', [AbsensiController::class, 'generate'])->name('absensi.generate');

        Route::get('laporan', [LogsheetReportController::class, 'index'])->name('laporan.index');

        Route::get('laporan/logsheet', [LogsheetReportController::class, 'edit'])->name('laporan.logsheet.edit');
        Route::post('laporan/logsheet', [LogsheetReportController::class, 'store'])->name('laporan.logsheet.store');
        Route::post('laporan/logsheet/muat-ulang', [LogsheetReportController::class, 'regenerate'])->name('laporan.logsheet.regenerate');
        Route::get('laporan/logsheet/pdf', [LogsheetReportController::class, 'pdf'])->name('laporan.logsheet.pdf');

        Route::get('laporan/absensi', [AbsensiReportController::class, 'edit'])->name('laporan.absensi.edit');
        Route::post('laporan/absensi', [AbsensiReportController::class, 'store'])->name('laporan.absensi.store');
        Route::post('laporan/absensi/muat-ulang', [AbsensiReportController::class, 'regenerate'])->name('laporan.absensi.regenerate');
        Route::get('laporan/absensi/pdf', [AbsensiReportController::class, 'pdf'])->name('laporan.absensi.pdf');
    });
