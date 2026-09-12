<?php

use App\Http\Controllers\Operator\AbsensiController;
use App\Http\Controllers\Operator\LogsheetController;
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
    });
