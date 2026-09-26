<?php

use App\Http\Controllers\Operasi\AuxiliaryReadingController;
use App\Http\Controllers\Operasi\BeritaAcaraController;
use App\Http\Controllers\Operasi\BlackstartController;
use App\Http\Controllers\Operasi\ChecklistCommissioningMesinController;
use App\Http\Controllers\Operasi\DailyReportController;
use App\Http\Controllers\Operasi\DataTeknisController;
use App\Http\Controllers\Operasi\DocumentTemplateController;
use App\Http\Controllers\Operasi\FeederReadingController;
use App\Http\Controllers\Operasi\FlmController;
use App\Http\Controllers\Operasi\FlmMonitoringController;
use App\Http\Controllers\Operasi\FuelReceiptController;
use App\Http\Controllers\Operasi\InputHubController;
use App\Http\Controllers\Operasi\InventarisController;
use App\Http\Controllers\Operasi\JadwalCommissioningTestController;
use App\Http\Controllers\Operasi\JadwalCommissioningTestPeralatanController;
use App\Http\Controllers\Operasi\JadwalController;
use App\Http\Controllers\Operasi\JadwalPerformanceTestController;
use App\Http\Controllers\Operasi\KondisiAbnormalController;
use App\Http\Controllers\Operasi\LaporanController;
use App\Http\Controllers\Operasi\LaporanDocumentController;
use App\Http\Controllers\Operasi\MasterController;
use App\Http\Controllers\Operasi\MaterialPeralatanController;
use App\Http\Controllers\Operasi\MeetingShiftController;
use App\Http\Controllers\Operasi\PatrolCheckMesinController;
use App\Http\Controllers\Operasi\PembuatanIkController;
use App\Http\Controllers\Operasi\PermitToWorkController;
use App\Http\Controllers\Operasi\Program5s5rController;
use App\Http\Controllers\Operasi\Program5s5rInputController;
use App\Http\Controllers\Operasi\ResourcePembangkitController;
use App\Http\Controllers\Operasi\StarStopController;
use App\Http\Controllers\Operasi\UnsafeConditionController;
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
        Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
        Route::get('jadwal/flm', [FlmController::class, 'index'])->name('jadwal.flm.index');
        Route::post('jadwal/flm', [FlmController::class, 'store'])->name('jadwal.flm.store');
        Route::get('jadwal/flm/pdf', [FlmController::class, 'pdf'])->name('jadwal.flm.pdf');
        Route::get('jadwal/program-5s-5r', [Program5s5rController::class, 'index'])->name('jadwal.program-5s-5r.index');
        Route::post('jadwal/program-5s-5r', [Program5s5rController::class, 'store'])->name('jadwal.program-5s-5r.store');
        Route::get('jadwal/program-5s-5r/pdf', [Program5s5rController::class, 'pdf'])->name('jadwal.program-5s-5r.pdf');
        Route::get('jadwal/meeting-shift', [MeetingShiftController::class, 'index'])->name('jadwal.meeting-shift.index');
        Route::post('jadwal/meeting-shift', [MeetingShiftController::class, 'store'])->name('jadwal.meeting-shift.store');
        Route::get('jadwal/meeting-shift/pdf', [MeetingShiftController::class, 'pdf'])->name('jadwal.meeting-shift.pdf');
        Route::get('jadwal/inventarisasi-tools', [InventarisController::class, 'index'])->name('jadwal.inventarisasi-tools.index');
        Route::post('jadwal/inventarisasi-tools', [InventarisController::class, 'store'])->name('jadwal.inventarisasi-tools.store');
        Route::get('jadwal/inventarisasi-tools/pdf', [InventarisController::class, 'pdf'])->name('jadwal.inventarisasi-tools.pdf');
        Route::get('jadwal/pembuatan-ik', [PembuatanIkController::class, 'index'])->name('jadwal.pembuatan-ik.index');
        Route::post('jadwal/pembuatan-ik', [PembuatanIkController::class, 'store'])->name('jadwal.pembuatan-ik.store');
        Route::get('jadwal/pembuatan-ik/pdf', [PembuatanIkController::class, 'pdf'])->name('jadwal.pembuatan-ik.pdf');
        Route::get('jadwal/pembuatan-data-teknis', [DataTeknisController::class, 'index'])->name('jadwal.pembuatan-data-teknis.index');
        Route::post('jadwal/pembuatan-data-teknis', [DataTeknisController::class, 'store'])->name('jadwal.pembuatan-data-teknis.store');
        Route::get('jadwal/pembuatan-data-teknis/pdf', [DataTeknisController::class, 'pdf'])->name('jadwal.pembuatan-data-teknis.pdf');
        Route::get('jadwal/blackstart', [BlackstartController::class, 'index'])->name('jadwal.blackstart.index');
        Route::post('jadwal/blackstart', [BlackstartController::class, 'store'])->name('jadwal.blackstart.store');
        Route::get('jadwal/blackstart/pdf', [BlackstartController::class, 'pdf'])->name('jadwal.blackstart.pdf');
        Route::get('jadwal/commissioning-test', [JadwalCommissioningTestController::class, 'index'])->name('jadwal.commissioning-test.index');
        Route::post('jadwal/commissioning-test', [JadwalCommissioningTestController::class, 'store'])->name('jadwal.commissioning-test.store');
        Route::get('jadwal/commissioning-test/pdf', [JadwalCommissioningTestController::class, 'pdf'])->name('jadwal.commissioning-test.pdf');
        Route::get('jadwal/commissioning-test-peralatan', [JadwalCommissioningTestPeralatanController::class, 'index'])->name('jadwal.commissioning-test-peralatan.index');
        Route::post('jadwal/commissioning-test-peralatan', [JadwalCommissioningTestPeralatanController::class, 'store'])->name('jadwal.commissioning-test-peralatan.store');
        Route::get('jadwal/commissioning-test-peralatan/pdf', [JadwalCommissioningTestPeralatanController::class, 'pdf'])->name('jadwal.commissioning-test-peralatan.pdf');
        Route::get('jadwal/performance-test', [JadwalPerformanceTestController::class, 'index'])->name('jadwal.performance-test.index');
        Route::post('jadwal/performance-test', [JadwalPerformanceTestController::class, 'store'])->name('jadwal.performance-test.store');
        Route::get('jadwal/performance-test/pdf', [JadwalPerformanceTestController::class, 'pdf'])->name('jadwal.performance-test.pdf');
        Route::get('input', [InputHubController::class, 'index'])->name('input.index');

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

        Route::get('input/kondisi-abnormal', [KondisiAbnormalController::class, 'index'])
            ->name('input.kondisi-abnormal.index');
        Route::post('input/kondisi-abnormal', [KondisiAbnormalController::class, 'store'])
            ->name('input.kondisi-abnormal.store');
        Route::get('input/kondisi-abnormal/pdf', [KondisiAbnormalController::class, 'pdf'])
            ->name('input.kondisi-abnormal.pdf');

        Route::get('input/material-peralatan', [MaterialPeralatanController::class, 'index'])
            ->name('input.material-peralatan.index');
        Route::post('input/material-peralatan', [MaterialPeralatanController::class, 'store'])
            ->name('input.material-peralatan.store');
        Route::delete('input/material-peralatan/{materialPeralatan}', [MaterialPeralatanController::class, 'destroy'])
            ->name('input.material-peralatan.destroy');
        Route::get('input/material-peralatan/pdf', [MaterialPeralatanController::class, 'pdf'])
            ->name('input.material-peralatan.pdf');

        Route::get('input/resource-pembangkit', [ResourcePembangkitController::class, 'index'])
            ->name('input.resource-pembangkit.index');
        Route::post('input/resource-pembangkit', [ResourcePembangkitController::class, 'store'])
            ->name('input.resource-pembangkit.store');
        Route::get('input/resource-pembangkit/pdf', [ResourcePembangkitController::class, 'pdf'])
            ->name('input.resource-pembangkit.pdf');

        Route::get('input/permit-to-work', [PermitToWorkController::class, 'index'])
            ->name('input.permit-to-work.index');
        Route::post('input/permit-to-work', [PermitToWorkController::class, 'store'])
            ->name('input.permit-to-work.store');
        Route::get('input/permit-to-work/pdf', [PermitToWorkController::class, 'pdf'])
            ->name('input.permit-to-work.pdf');

        Route::get('input/monitoring-flm', [FlmMonitoringController::class, 'index'])
            ->name('input.flm-monitoring.index');
        Route::post('input/monitoring-flm', [FlmMonitoringController::class, 'store'])
            ->name('input.flm-monitoring.store');
        Route::get('input/monitoring-flm/pdf', [FlmMonitoringController::class, 'pdf'])
            ->name('input.flm-monitoring.pdf');

        Route::get('input/patrol-check-mesin', [PatrolCheckMesinController::class, 'index'])
            ->name('input.patrol-check-mesin.index');
        Route::post('input/patrol-check-mesin', [PatrolCheckMesinController::class, 'store'])
            ->name('input.patrol-check-mesin.store');
        Route::get('input/patrol-check-mesin/pdf', [PatrolCheckMesinController::class, 'pdf'])
            ->name('input.patrol-check-mesin.pdf');

        Route::get('input/checklist-commissioning-mesin', [ChecklistCommissioningMesinController::class, 'index'])
            ->name('input.checklist-commissioning-mesin.index');
        Route::post('input/checklist-commissioning-mesin', [ChecklistCommissioningMesinController::class, 'store'])
            ->name('input.checklist-commissioning-mesin.store');
        Route::get('input/checklist-commissioning-mesin/pdf', [ChecklistCommissioningMesinController::class, 'pdf'])
            ->name('input.checklist-commissioning-mesin.pdf');

        Route::get('input/unsafe-condition', [UnsafeConditionController::class, 'index'])
            ->name('input.unsafe-condition.index');
        Route::post('input/unsafe-condition', [UnsafeConditionController::class, 'store'])
            ->name('input.unsafe-condition.store');
        Route::post('input/unsafe-condition/{unsafeCondition}', [UnsafeConditionController::class, 'update'])
            ->name('input.unsafe-condition.update');
        Route::delete('input/unsafe-condition/{unsafeCondition}', [UnsafeConditionController::class, 'destroy'])
            ->name('input.unsafe-condition.destroy');
        Route::get('input/unsafe-condition/pdf', [UnsafeConditionController::class, 'pdf'])
            ->name('input.unsafe-condition.pdf');

        Route::get('input/program-5s5r', [Program5s5rInputController::class, 'index'])
            ->name('input.program-5s5r.index');
        Route::post('input/program-5s5r', [Program5s5rInputController::class, 'store'])
            ->name('input.program-5s5r.store');
        Route::get('input/program-5s5r/pdf', [Program5s5rInputController::class, 'pdf'])
            ->name('input.program-5s5r.pdf');

        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/{report}/excel', [LaporanController::class, 'spreadsheet'])->name('laporan.spreadsheet');
        Route::get('laporan/{report}/dokumen', [LaporanDocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/{report}/dokumen', [LaporanDocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/{report}/dokumen/muat-ulang', [LaporanDocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/{report}/dokumen/pdf', [LaporanDocumentController::class, 'pdf'])->name('laporan.document.pdf');
        Route::get('laporan/{report}', [LaporanController::class, 'show'])->name('laporan.show');

        Route::get('berita-acara', [BeritaAcaraController::class, 'index'])->name('berita-acara.index');
        Route::post('berita-acara', [BeritaAcaraController::class, 'store'])->name('berita-acara.store');
        Route::get('berita-acara/{type}', [BeritaAcaraController::class, 'show'])->name('berita-acara.show');
        Route::get('berita-acara/{type}/preview', [BeritaAcaraController::class, 'preview'])->name('berita-acara.preview');
        Route::get('berita-acara/{type}/pdf', [BeritaAcaraController::class, 'pdf'])->name('berita-acara.pdf');

        Route::get('document-template', [DocumentTemplateController::class, 'index'])->name('document-template.index');
        Route::put('document-template/{type}', [DocumentTemplateController::class, 'update'])->name('document-template.update');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
