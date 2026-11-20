<?php

use App\Http\Controllers\Keuangan\CashCardController;
use App\Http\Controllers\Keuangan\DocumentController;
use App\Http\Controllers\Keuangan\SupplierController;
use Illuminate\Support\Facades\Route;

/**
 * Modul ADMINISTRASI & KEUANGAN per UL (user, 2026-11-20): Laporan Realisasi
 * Cash Card (+ bukti kas kecil & lampiran) and the Master Supplier. Every
 * route is guarded in its controller (keuangan.* + the UL scope).
 */
Route::middleware(['auth', 'verified'])
    ->prefix('keuangan')
    ->name('keuangan.')
    ->group(function (): void {
        Route::get('cash-card', [CashCardController::class, 'index'])->name('cash-card.index');
        Route::post('cash-card', [CashCardController::class, 'store'])->name('cash-card.store');
        Route::get('cash-card/pdf', [CashCardController::class, 'pdf'])->name('cash-card.pdf');
        Route::get('cash-card/bukti', [CashCardController::class, 'bukti'])->name('cash-card.bukti');

        // Laporan Administrasi Keuangan as an editable document (Teks / Excel / Pratinjau PDF).
        Route::get('dokumen', [DocumentController::class, 'show'])->name('document.show');
        Route::get('dokumen/pdf', [DocumentController::class, 'pdf'])->name('document.pdf');
        Route::post('dokumen', [DocumentController::class, 'store'])->name('document.store');
        Route::post('dokumen/muat-ulang', [DocumentController::class, 'regenerate'])->name('document.regenerate');

        Route::get('supplier', [SupplierController::class, 'index'])->name('supplier.index');
        Route::post('supplier', [SupplierController::class, 'store'])->name('supplier.store');
        Route::post('supplier/{supplier}', [SupplierController::class, 'update'])->whereNumber('supplier')->name('supplier.update');
        Route::delete('supplier/{supplier}', [SupplierController::class, 'destroy'])->whereNumber('supplier')->name('supplier.destroy');
    });
