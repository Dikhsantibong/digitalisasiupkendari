<?php

use App\Http\Controllers\Logistik\DocumentController;
use App\Http\Controllers\Logistik\FormController;
use App\Http\Controllers\Logistik\InputHubController;
use App\Http\Controllers\Logistik\JadwalController;
use App\Http\Controllers\Logistik\JadwalSheetController;
use App\Http\Controllers\Logistik\LaporanController;
use App\Http\Controllers\Logistik\RekomendasiController;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikJadwal;
use Illuminate\Support\Facades\Route;

/**
 * Modul LOGISTIK & GUDANG. Every route is additionally guarded inside its
 * controller by a permission check (logistik.*) and, where relevant, a
 * unit-scope check, so membership of this group is not on its own a grant.
 *
 * Hub pages (Jadwal, Input, Laporan), the jadwal sheets, the inputs and the
 * editable Laporan Logistik & Gudang; the remaining sub-pages follow later.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('logistik')
    ->name('logistik.')
    ->group(function (): void {
        Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
        Route::get('jadwal/{jadwal}', [JadwalSheetController::class, 'index'])->name('jadwal.sheet.index')->whereIn('jadwal', LogistikJadwal::keysFor('jadwal'));
        Route::post('jadwal/{jadwal}', [JadwalSheetController::class, 'store'])->name('jadwal.sheet.store')->whereIn('jadwal', LogistikJadwal::keysFor('jadwal'));
        Route::get('jadwal/{jadwal}/pdf', [JadwalSheetController::class, 'pdf'])->name('jadwal.sheet.pdf')->whereIn('jadwal', LogistikJadwal::keysFor('jadwal'));
        Route::get('input', [InputHubController::class, 'index'])->name('input.index');
        Route::get('input/rekomendasi', [RekomendasiController::class, 'index'])->name('input.rekomendasi.index');
        Route::post('input/rekomendasi', [RekomendasiController::class, 'store'])->name('input.rekomendasi.store');
        Route::get('input/rekomendasi/pdf', [RekomendasiController::class, 'pdf'])->name('input.rekomendasi.pdf');
        Route::get('input/form/{form}', [FormController::class, 'index'])->name('input.form.index')->whereIn('form', LogistikForms::keys());
        Route::post('input/form/{form}', [FormController::class, 'store'])->name('input.form.store')->whereIn('form', LogistikForms::keys());
        Route::get('input/form/{form}/pdf', [FormController::class, 'pdf'])->name('input.form.pdf')->whereIn('form', LogistikForms::keys());
        Route::get('input/lembar/{jadwal}', [JadwalSheetController::class, 'index'])->name('input.sheet.index')->whereIn('jadwal', LogistikJadwal::keysFor('input'));
        Route::post('input/lembar/{jadwal}', [JadwalSheetController::class, 'store'])->name('input.sheet.store')->whereIn('jadwal', LogistikJadwal::keysFor('input'));
        Route::get('input/lembar/{jadwal}/pdf', [JadwalSheetController::class, 'pdf'])->name('input.sheet.pdf')->whereIn('jadwal', LogistikJadwal::keysFor('input'));
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/dokumen', [DocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/dokumen', [DocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/dokumen/muat-ulang', [DocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/dokumen/pdf', [DocumentController::class, 'pdf'])->name('laporan.document.pdf');
    });
