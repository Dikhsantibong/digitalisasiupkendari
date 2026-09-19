<?php

use App\Http\Controllers\Pdm\DocumentController;
use App\Http\Controllers\Pdm\FormInputController;
use App\Http\Controllers\Pdm\InputHubController;
use App\Http\Controllers\Pdm\JadwalController;
use App\Http\Controllers\Pdm\JadwalHarianController;
use App\Http\Controllers\Pdm\JadwalMeetingController;
use App\Http\Controllers\Pdm\JadwalPatrolCheckController;
use App\Http\Controllers\Pdm\KesiapanApdController;
use App\Http\Controllers\Pdm\LaporanController;
use App\Http\Controllers\Pdm\PermitToWorkController;
use App\Http\Controllers\Pdm\Program5s5rController;
use App\Http\Controllers\Pdm\RealisasiPrediktifController;
use App\Http\Controllers\Pdm\SampleMonitoringController;
use Illuminate\Support\Facades\Route;

/**
 * Modul PdM & MATURITY LEVEL. Every route is additionally guarded inside its
 * controller by a permission check (pdm.*) and, where relevant, a unit-scope
 * check, so membership of this group is not on its own a grant.
 *
 * Only the Jadwal, Input, and Laporan hub pages exist for now; the individual
 * jadwal and laporan sub-pages are added later (isinya menyusul).
 */
Route::middleware(['auth', 'verified'])
    ->prefix('pdm')
    ->name('pdm.')
    ->group(function (): void {
        Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
        Route::get('jadwal/harian', [JadwalHarianController::class, 'index'])->name('jadwal.harian.index');
        Route::post('jadwal/harian', [JadwalHarianController::class, 'store'])->name('jadwal.harian.store');
        Route::get('jadwal/harian/pdf', [JadwalHarianController::class, 'pdf'])->name('jadwal.harian.pdf');
        Route::get('jadwal/patrol-check', [JadwalPatrolCheckController::class, 'index'])->name('jadwal.patrol-check.index');
        Route::post('jadwal/patrol-check', [JadwalPatrolCheckController::class, 'store'])->name('jadwal.patrol-check.store');
        Route::get('jadwal/patrol-check/pdf', [JadwalPatrolCheckController::class, 'pdf'])->name('jadwal.patrol-check.pdf');
        Route::get('jadwal/program-5s-5r', [Program5s5rController::class, 'index'])->name('jadwal.program-5s-5r.index');
        Route::post('jadwal/program-5s-5r', [Program5s5rController::class, 'store'])->name('jadwal.program-5s-5r.store');
        Route::get('jadwal/program-5s-5r/pdf', [Program5s5rController::class, 'pdf'])->name('jadwal.program-5s-5r.pdf');
        Route::get('jadwal/meeting', [JadwalMeetingController::class, 'index'])->name('jadwal.meeting.index');
        Route::post('jadwal/meeting', [JadwalMeetingController::class, 'store'])->name('jadwal.meeting.store');
        Route::get('jadwal/meeting/pdf', [JadwalMeetingController::class, 'pdf'])->name('jadwal.meeting.pdf');
        Route::get('input', [InputHubController::class, 'index'])->name('input.index');
        Route::get('input/kesiapan-apd', [KesiapanApdController::class, 'index'])->name('input.kesiapan-apd.index');
        Route::post('input/kesiapan-apd', [KesiapanApdController::class, 'store'])->name('input.kesiapan-apd.store');
        Route::get('input/kesiapan-apd/pdf', [KesiapanApdController::class, 'pdf'])->name('input.kesiapan-apd.pdf');
        Route::get('input/sample-monitoring', [SampleMonitoringController::class, 'index'])->name('input.sample-monitoring.index');
        Route::post('input/sample-monitoring', [SampleMonitoringController::class, 'store'])->name('input.sample-monitoring.store');
        Route::get('input/sample-monitoring/pdf', [SampleMonitoringController::class, 'pdf'])->name('input.sample-monitoring.pdf');
        Route::get('input/permit-to-work', [PermitToWorkController::class, 'index'])->name('input.permit-to-work.index');
        Route::post('input/permit-to-work', [PermitToWorkController::class, 'store'])->name('input.permit-to-work.store');
        Route::get('input/permit-to-work/pdf', [PermitToWorkController::class, 'pdf'])->name('input.permit-to-work.pdf');
        Route::get('input/realisasi-prediktif', [RealisasiPrediktifController::class, 'index'])->name('input.realisasi-prediktif.index');
        Route::post('input/realisasi-prediktif', [RealisasiPrediktifController::class, 'store'])->name('input.realisasi-prediktif.store');
        Route::get('input/realisasi-prediktif/pdf', [RealisasiPrediktifController::class, 'pdf'])->name('input.realisasi-prediktif.pdf');
        Route::get('input/forms/{form}', [FormInputController::class, 'index'])->name('input.forms.index');
        Route::post('input/forms/{form}', [FormInputController::class, 'store'])->name('input.forms.store');
        Route::get('input/forms/{form}/pdf', [FormInputController::class, 'pdf'])->name('input.forms.pdf');
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/dokumen', [DocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/dokumen', [DocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/dokumen/muat-ulang', [DocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/dokumen/pdf', [DocumentController::class, 'pdf'])->name('laporan.document.pdf');
    });
