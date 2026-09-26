<?php

use App\Http\Controllers\K3\AccidentController;
use App\Http\Controllers\K3\AirLimbahController;
use App\Http\Controllers\K3\ApdInventoryController;
use App\Http\Controllers\K3\AttachmentController;
use App\Http\Controllers\K3\CctvListController;
use App\Http\Controllers\K3\CertificateController;
use App\Http\Controllers\K3\DocumentController;
use App\Http\Controllers\K3\EmergencyFacilityCheckController;
use App\Http\Controllers\K3\EmergencyFacilityController;
use App\Http\Controllers\K3\FireAlarmInspectionController;
use App\Http\Controllers\K3\FireExtinguisherCheckController;
use App\Http\Controllers\K3\FormulirController;
use App\Http\Controllers\K3\FormulirRecordController;
use App\Http\Controllers\K3\HydrantInspectionController;
use App\Http\Controllers\K3\InputExportController;
use App\Http\Controllers\K3\InputHubController;
use App\Http\Controllers\K3\InspectionController;
use App\Http\Controllers\K3\InstruksiKerjaController;
use App\Http\Controllers\K3\JadwalController;
use App\Http\Controllers\K3\JadwalOnCallController;
use App\Http\Controllers\K3\KegiatanRutinController;
use App\Http\Controllers\K3\KesiapanApdController;
use App\Http\Controllers\K3\KondisiK3Controller;
use App\Http\Controllers\K3\LaporanController;
use App\Http\Controllers\K3\LaporanPengusahaanController;
use App\Http\Controllers\K3\MasterController;
use App\Http\Controllers\K3\MetodePengujianPeralatanController;
use App\Http\Controllers\K3\MonitoringController;
use App\Http\Controllers\K3\PatrolCheckController;
use App\Http\Controllers\K3\PatrolController;
use App\Http\Controllers\K3\PekerjaanRutinController;
use App\Http\Controllers\K3\RambuInspectionController;
use App\Http\Controllers\K3\TimeFrameController;
use App\Http\Controllers\PengusahaanController;
use App\Support\K3FormulirRegistry;
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
        Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
        Route::get('jadwal/kegiatan-rutin', [KegiatanRutinController::class, 'index'])->name('jadwal.kegiatan-rutin.index');
        Route::post('jadwal/kegiatan-rutin', [KegiatanRutinController::class, 'store'])->name('jadwal.kegiatan-rutin.store');
        Route::get('jadwal/kegiatan-rutin/pdf', [KegiatanRutinController::class, 'pdf'])->name('jadwal.kegiatan-rutin.pdf');
        Route::get('jadwal/instruksi-kerja', [InstruksiKerjaController::class, 'index'])->name('jadwal.instruksi-kerja.index');
        Route::post('jadwal/instruksi-kerja', [InstruksiKerjaController::class, 'store'])->name('jadwal.instruksi-kerja.store');
        Route::get('jadwal/instruksi-kerja/pdf', [InstruksiKerjaController::class, 'pdf'])->name('jadwal.instruksi-kerja.pdf');
        Route::get('jadwal/patrol-check', [PatrolCheckController::class, 'index'])->name('jadwal.patrol-check.index');
        Route::post('jadwal/patrol-check', [PatrolCheckController::class, 'store'])->name('jadwal.patrol-check.store');
        Route::get('jadwal/patrol-check/pdf', [PatrolCheckController::class, 'pdf'])->name('jadwal.patrol-check.pdf');
        Route::get('jadwal/pekerjaan-rutin', [PekerjaanRutinController::class, 'index'])->name('jadwal.pekerjaan-rutin.index');
        Route::post('jadwal/pekerjaan-rutin', [PekerjaanRutinController::class, 'store'])->name('jadwal.pekerjaan-rutin.store');
        Route::get('jadwal/pekerjaan-rutin/pdf', [PekerjaanRutinController::class, 'pdf'])->name('jadwal.pekerjaan-rutin.pdf');
        Route::get('jadwal/on-call', [JadwalOnCallController::class, 'index'])->name('jadwal.on-call.index');
        Route::post('jadwal/on-call', [JadwalOnCallController::class, 'store'])->name('jadwal.on-call.store');
        Route::get('jadwal/on-call/pdf', [JadwalOnCallController::class, 'pdf'])->name('jadwal.on-call.pdf');
        Route::get('input', [InputHubController::class, 'index'])->name('input.index');
        Route::get('input/export/{input}/pdf', [InputExportController::class, 'pdf'])->name('input.export.pdf');
        Route::get('input/export/{input}/data', [InputExportController::class, 'data'])->name('input.export.data');
        Route::get('formulir', [FormulirController::class, 'index'])->name('formulir.index');
        Route::get('formulir/metode-pengujian', [MetodePengujianPeralatanController::class, 'index'])->name('formulir.metode-pengujian.index');
        Route::post('formulir/metode-pengujian', [MetodePengujianPeralatanController::class, 'store'])->name('formulir.metode-pengujian.store');
        Route::get('formulir/metode-pengujian/pdf', [MetodePengujianPeralatanController::class, 'pdf'])->name('formulir.metode-pengujian.pdf');
        Route::get('formulir/{form}', [FormulirRecordController::class, 'index'])->whereIn('form', K3FormulirRegistry::keys())->name('formulir.record.index');
        Route::post('formulir/{form}', [FormulirRecordController::class, 'store'])->whereIn('form', K3FormulirRegistry::keys())->name('formulir.record.store');
        Route::get('formulir/{form}/pdf', [FormulirRecordController::class, 'pdf'])->whereIn('form', K3FormulirRegistry::keys())->name('formulir.record.pdf');

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

        Route::get('input/hydrant', [HydrantInspectionController::class, 'index'])->name('input.hydrant.index');
        Route::post('input/hydrant', [HydrantInspectionController::class, 'store'])->name('input.hydrant.store');

        Route::get('input/cctv', [CctvListController::class, 'index'])->name('input.cctv.index');
        Route::post('input/cctv', [CctvListController::class, 'store'])->name('input.cctv.store');

        Route::get('input/fire-alarm', [FireAlarmInspectionController::class, 'index'])->name('input.fire-alarm.index');
        Route::post('input/fire-alarm', [FireAlarmInspectionController::class, 'store'])->name('input.fire-alarm.store');

        Route::get('input/rambu', [RambuInspectionController::class, 'index'])->name('input.rambu.index');
        Route::post('input/rambu', [RambuInspectionController::class, 'store'])->name('input.rambu.store');

        Route::get('input/emergency-facility', [EmergencyFacilityCheckController::class, 'index'])->name('input.emergency-facility.index');
        Route::post('input/emergency-facility', [EmergencyFacilityCheckController::class, 'store'])->name('input.emergency-facility.store');

        Route::get('input/kesiapan-apd', [KesiapanApdController::class, 'index'])->name('input.kesiapan-apd.index');
        Route::post('input/kesiapan-apd', [KesiapanApdController::class, 'store'])->name('input.kesiapan-apd.store');

        Route::get('input/kondisi-k3', [KondisiK3Controller::class, 'index'])->name('input.kondisi-k3.index');
        Route::post('input/kondisi-k3', [KondisiK3Controller::class, 'store'])->name('input.kondisi-k3.store');
        Route::post('input/kondisi-k3/upload', [KondisiK3Controller::class, 'upload'])->name('input.kondisi-k3.upload');

        Route::get('input/apd-inventory', [ApdInventoryController::class, 'index'])->name('input.apd-inventory.index');
        Route::post('input/apd-inventory', [ApdInventoryController::class, 'store'])->name('input.apd-inventory.store');

        Route::get('input/air-limbah', [AirLimbahController::class, 'index'])->name('input.air-limbah.index');
        Route::post('input/air-limbah', [AirLimbahController::class, 'store'])->name('input.air-limbah.store');
        Route::get('input/air-limbah/pdf', [AirLimbahController::class, 'pdf'])->name('input.air-limbah.pdf');

        Route::get('input/certificate', [CertificateController::class, 'index'])->name('input.certificate.index');
        Route::post('input/certificate', [CertificateController::class, 'store'])->name('input.certificate.store');

        Route::get('input/attachment', [AttachmentController::class, 'index'])->name('input.attachment.index');
        Route::post('input/attachment', [AttachmentController::class, 'store'])->name('input.attachment.store');
        Route::delete('input/attachment/{attachment}', [AttachmentController::class, 'destroy'])->name('input.attachment.destroy');

        Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');

        // Akses 2 — Pengusahaan (TL & Staf): hub per menu section.
        Route::get('pengusahaan/{section}', [PengusahaanController::class, 'index'])->name('pengusahaan.index')
            ->defaults('module', 'k3')->whereIn('section', array_keys(PengusahaanController::SECTIONS));
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/dokumen', [DocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/dokumen', [DocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/dokumen/muat-ulang', [DocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/dokumen/pdf', [DocumentController::class, 'pdf'])->name('laporan.document.pdf');
        Route::get('laporan/pengusahaan', [LaporanPengusahaanController::class, 'edit'])->name('laporan.pengusahaan.edit');
        Route::post('laporan/pengusahaan', [LaporanPengusahaanController::class, 'store'])->name('laporan.pengusahaan.store');
        Route::post('laporan/pengusahaan/muat-ulang', [LaporanPengusahaanController::class, 'regenerate'])->name('laporan.pengusahaan.regenerate');
        Route::get('laporan/pengusahaan/pdf', [LaporanPengusahaanController::class, 'pdf'])->name('laporan.pengusahaan.pdf');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
