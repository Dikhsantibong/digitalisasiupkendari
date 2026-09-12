<?php

use App\Http\Controllers\K3\AccidentController;
use App\Http\Controllers\K3\AttachmentController;
use App\Http\Controllers\K3\CertificateController;
use App\Http\Controllers\K3\DocumentController;
use App\Http\Controllers\K3\EmergencyFacilityController;
use App\Http\Controllers\K3\FireExtinguisherCheckController;
use App\Http\Controllers\K3\InspectionController;
use App\Http\Controllers\K3\LaporanController;
use App\Http\Controllers\K3\MasterController;
use App\Http\Controllers\K3\MonitoringController;
use App\Http\Controllers\K3\PatrolController;
use App\Http\Controllers\K3\TimeFrameController;
use Illuminate\Support\Facades\Route;

/**
 * Modul K3 & KEAMANAN. Every route is additionally guarded inside its
 * controller by a permission check (k3.*) and, where relevant, a unit-scope
 * check, so membership of this group is not on its own a grant.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('k3')
    ->name('k3.')
    ->group(function (): void {
        Route::get('input/time-frame', [TimeFrameController::class, 'index'])->name('input.time-frame.index');
        Route::post('input/time-frame', [TimeFrameController::class, 'store'])->name('input.time-frame.store');

        Route::get('input/accident', [AccidentController::class, 'index'])->name('input.accident.index');
        Route::post('input/accident', [AccidentController::class, 'store'])->name('input.accident.store');

        Route::get('input/inspection', [InspectionController::class, 'index'])->name('input.inspection.index');
        Route::post('input/inspection', [InspectionController::class, 'store'])->name('input.inspection.store');

        Route::get('input/apar-check', [FireExtinguisherCheckController::class, 'index'])->name('input.apar-check.index');
        Route::post('input/apar-check', [FireExtinguisherCheckController::class, 'store'])->name('input.apar-check.store');

        Route::get('input/emergency', [EmergencyFacilityController::class, 'index'])->name('input.emergency.index');
        Route::post('input/emergency', [EmergencyFacilityController::class, 'store'])->name('input.emergency.store');

        Route::get('input/patrol', [PatrolController::class, 'index'])->name('input.patrol.index');
        Route::post('input/patrol', [PatrolController::class, 'store'])->name('input.patrol.store');

        Route::get('input/certificate', [CertificateController::class, 'index'])->name('input.certificate.index');
        Route::post('input/certificate', [CertificateController::class, 'store'])->name('input.certificate.store');

        Route::get('input/attachment', [AttachmentController::class, 'index'])->name('input.attachment.index');
        Route::post('input/attachment', [AttachmentController::class, 'store'])->name('input.attachment.store');
        Route::delete('input/attachment/{attachment}', [AttachmentController::class, 'destroy'])->name('input.attachment.destroy');

        Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/dokumen', [DocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/dokumen', [DocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/dokumen/muat-ulang', [DocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/dokumen/pdf', [DocumentController::class, 'pdf'])->name('laporan.document.pdf');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
