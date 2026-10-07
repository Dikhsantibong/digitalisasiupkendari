<?php

use App\Http\Controllers\Operasi\BlackstartController;
use App\Http\Controllers\Operasi\ChecklistCommissioningMesinController;
use App\Http\Controllers\Operasi\DataTeknisController;
use App\Http\Controllers\Operasi\DocumentTemplateController;
use App\Http\Controllers\Operasi\FlmController;
use App\Http\Controllers\Operasi\FlmMonitoringController;
use App\Http\Controllers\Operasi\InputHubController;
use App\Http\Controllers\Operasi\InstruksiKerjaController;
use App\Http\Controllers\Operasi\InventarisController;
use App\Http\Controllers\Operasi\JadwalCommissioningTestController;
use App\Http\Controllers\Operasi\JadwalCommissioningTestPeralatanController;
use App\Http\Controllers\Operasi\JadwalController;
use App\Http\Controllers\Operasi\JadwalPerformanceTestController;
use App\Http\Controllers\Operasi\KondisiAbnormalController;
use App\Http\Controllers\Operasi\LaporanController;
use App\Http\Controllers\Operasi\LaporanDocumentController;
use App\Http\Controllers\Operasi\LaporanPengusahaanController;
use App\Http\Controllers\Operasi\MasterController;
use App\Http\Controllers\Operasi\MaterialPeralatanController;
use App\Http\Controllers\Operasi\MeetingShiftController;
use App\Http\Controllers\Operasi\PatrolCheckMesinController;
use App\Http\Controllers\Operasi\PembuatanIkController;
use App\Http\Controllers\Operasi\PengusahaanAuxiliaryReadingController;
use App\Http\Controllers\Operasi\PengusahaanBaFisikPelumasController;
use App\Http\Controllers\Operasi\PengusahaanBeritaAcaraController;
use App\Http\Controllers\Operasi\PengusahaanDailyReportController;
use App\Http\Controllers\Operasi\PengusahaanFeederReadingController;
use App\Http\Controllers\Operasi\PengusahaanFuelReceiptController;
use App\Http\Controllers\Operasi\PengusahaanInventarisController;
use App\Http\Controllers\Operasi\PengusahaanJamMesinController;
use App\Http\Controllers\Operasi\PengusahaanJamSiapOpsController;
use App\Http\Controllers\Operasi\PengusahaanKinerjaController;
use App\Http\Controllers\Operasi\PengusahaanKinerjaTermalController;
use App\Http\Controllers\Operasi\PengusahaanKwhController;
use App\Http\Controllers\Operasi\PengusahaanMesinHarianController;
use App\Http\Controllers\Operasi\PengusahaanPemakaianBbmController;
use App\Http\Controllers\Operasi\PengusahaanPemakaianPelumasController;
use App\Http\Controllers\Operasi\PengusahaanPersediaanController;
use App\Http\Controllers\Operasi\PengusahaanResourcePembangkitController;
use App\Http\Controllers\Operasi\PengusahaanRincianBbmController;
use App\Http\Controllers\Operasi\PengusahaanRincianPelumasController;
use App\Http\Controllers\Operasi\PengusahaanStandMeterController;
use App\Http\Controllers\Operasi\PengusahaanStarStopController;
use App\Http\Controllers\Operasi\PengusahaanTugBbmController;
use App\Http\Controllers\Operasi\PengusahaanTugPelumasController;
use App\Http\Controllers\Operasi\PermitToWorkController;
use App\Http\Controllers\Operasi\Program5s5rController;
use App\Http\Controllers\Operasi\Program5s5rInputController;
use App\Http\Controllers\Operasi\UnsafeConditionController;
use App\Http\Controllers\PengusahaanController;
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

        Route::get('input/instruksi-kerja', [InstruksiKerjaController::class, 'index'])->name('input.instruksi-kerja.index');
        Route::post('input/instruksi-kerja', [InstruksiKerjaController::class, 'store'])->name('input.instruksi-kerja.store');
        Route::get('input/instruksi-kerja/pdf', [InstruksiKerjaController::class, 'pdf'])->name('input.instruksi-kerja.pdf');
        Route::delete('input/instruksi-kerja/{instruksiKerja}', [InstruksiKerjaController::class, 'destroy'])->whereNumber('instruksiKerja')->name('input.instruksi-kerja.destroy');

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

        // Akses 2 — Pengusahaan Operasi (TL & Staf Operasi): data harian pembangkit
        // and Berita Acara. Operators keep their operasi.lapangan.* page permission.
        Route::get('pengusahaan/laporan-harian', [PengusahaanDailyReportController::class, 'index'])->name('pengusahaan.daily-report.index');
        Route::post('pengusahaan/laporan-harian', [PengusahaanDailyReportController::class, 'store'])->name('pengusahaan.daily-report.store');
        Route::get('pengusahaan/laporan-harian/pdf', [PengusahaanDailyReportController::class, 'pdf'])->name('pengusahaan.daily-report.pdf');

        Route::get('pengusahaan/star-stop', [PengusahaanStarStopController::class, 'index'])->name('pengusahaan.star-stop.index');
        Route::post('pengusahaan/star-stop', [PengusahaanStarStopController::class, 'store'])->name('pengusahaan.star-stop.store');
        Route::delete('pengusahaan/star-stop/{engineStatusLog}', [PengusahaanStarStopController::class, 'destroy'])->name('pengusahaan.star-stop.destroy');

        Route::get('pengusahaan/feeder', [PengusahaanFeederReadingController::class, 'index'])->name('pengusahaan.feeder.index');
        Route::post('pengusahaan/feeder', [PengusahaanFeederReadingController::class, 'store'])->name('pengusahaan.feeder.store');

        Route::get('pengusahaan/pasokan-cadangan', [PengusahaanAuxiliaryReadingController::class, 'index'])->name('pengusahaan.auxiliary.index');
        Route::post('pengusahaan/pasokan-cadangan', [PengusahaanAuxiliaryReadingController::class, 'store'])->name('pengusahaan.auxiliary.store');

        Route::get('pengusahaan/penerimaan-bbm', [PengusahaanFuelReceiptController::class, 'index'])->name('pengusahaan.fuel-receipt.index');
        Route::post('pengusahaan/penerimaan-bbm', [PengusahaanFuelReceiptController::class, 'store'])->name('pengusahaan.fuel-receipt.store');
        Route::delete('pengusahaan/penerimaan-bbm/{fuelReceipt}', [PengusahaanFuelReceiptController::class, 'destroy'])->name('pengusahaan.fuel-receipt.destroy');

        Route::get('pengusahaan/resource-pembangkit', [PengusahaanResourcePembangkitController::class, 'index'])->name('pengusahaan.resource-pembangkit.index');
        Route::post('pengusahaan/resource-pembangkit', [PengusahaanResourcePembangkitController::class, 'store'])->name('pengusahaan.resource-pembangkit.store');
        Route::get('pengusahaan/resource-pembangkit/pdf', [PengusahaanResourcePembangkitController::class, 'pdf'])->name('pengusahaan.resource-pembangkit.pdf');

        Route::get('pengusahaan/stand-meter', [PengusahaanStandMeterController::class, 'index'])->name('pengusahaan.stand-meter.index');
        Route::post('pengusahaan/stand-meter', [PengusahaanStandMeterController::class, 'store'])->name('pengusahaan.stand-meter.store');
        Route::get('pengusahaan/stand-meter/pdf', [PengusahaanStandMeterController::class, 'pdf'])->name('pengusahaan.stand-meter.pdf');

        Route::get('pengusahaan/pemakaian-pelumas', [PengusahaanPemakaianPelumasController::class, 'index'])->name('pengusahaan.pemakaian-pelumas.index');
        Route::post('pengusahaan/pemakaian-pelumas', [PengusahaanPemakaianPelumasController::class, 'store'])->name('pengusahaan.pemakaian-pelumas.store');
        Route::get('pengusahaan/pemakaian-pelumas/pdf', [PengusahaanPemakaianPelumasController::class, 'pdf'])->name('pengusahaan.pemakaian-pelumas.pdf');
        Route::get('pengusahaan/pelumas-tambah-ganti', [PengusahaanPemakaianPelumasController::class, 'tambahGanti'])->name('pengusahaan.pelumas-tambah-ganti.index');
        Route::get('pengusahaan/pelumas-tambah-ganti/pdf', [PengusahaanPemakaianPelumasController::class, 'tambahGantiPdf'])->name('pengusahaan.pelumas-tambah-ganti.pdf');

        Route::get('pengusahaan/tug-pelumas', [PengusahaanTugPelumasController::class, 'index'])->name('pengusahaan.tug-pelumas.index');
        Route::post('pengusahaan/tug-pelumas', [PengusahaanTugPelumasController::class, 'store'])->name('pengusahaan.tug-pelumas.store');
        Route::get('pengusahaan/tug-pelumas/pdf', [PengusahaanTugPelumasController::class, 'pdf'])->name('pengusahaan.tug-pelumas.pdf');

        Route::get('pengusahaan/pemakaian-bbm', [PengusahaanPemakaianBbmController::class, 'index'])->name('pengusahaan.pemakaian-bbm.index');
        Route::post('pengusahaan/pemakaian-bbm', [PengusahaanPemakaianBbmController::class, 'store'])->name('pengusahaan.pemakaian-bbm.store');
        Route::get('pengusahaan/pemakaian-bbm/pdf', [PengusahaanPemakaianBbmController::class, 'pdf'])->name('pengusahaan.pemakaian-bbm.pdf');

        Route::get('pengusahaan/tug-bbm', [PengusahaanTugBbmController::class, 'index'])->name('pengusahaan.tug-bbm.index');
        Route::post('pengusahaan/tug-bbm', [PengusahaanTugBbmController::class, 'store'])->name('pengusahaan.tug-bbm.store');
        Route::get('pengusahaan/tug-bbm/pdf', [PengusahaanTugBbmController::class, 'pdf'])->name('pengusahaan.tug-bbm.pdf');

        // Persediaan Bahan Bakar / Persediaan Pelumas share one controller.
        foreach (['bbm', 'pelumas'] as $persediaanJenis) {
            Route::get("pengusahaan/persediaan-{$persediaanJenis}", [PengusahaanPersediaanController::class, 'index'])->defaults('jenis', $persediaanJenis)->name("pengusahaan.persediaan-{$persediaanJenis}.index");
            Route::post("pengusahaan/persediaan-{$persediaanJenis}", [PengusahaanPersediaanController::class, 'store'])->defaults('jenis', $persediaanJenis)->name("pengusahaan.persediaan-{$persediaanJenis}.store");
            Route::get("pengusahaan/persediaan-{$persediaanJenis}/pdf", [PengusahaanPersediaanController::class, 'pdf'])->defaults('jenis', $persediaanJenis)->name("pengusahaan.persediaan-{$persediaanJenis}.pdf");
        }

        Route::get('pengusahaan/ba-fisik-pelumas', [PengusahaanBaFisikPelumasController::class, 'index'])->name('pengusahaan.ba-fisik-pelumas.index');
        Route::post('pengusahaan/ba-fisik-pelumas', [PengusahaanBaFisikPelumasController::class, 'store'])->name('pengusahaan.ba-fisik-pelumas.store');
        Route::get('pengusahaan/ba-fisik-pelumas/pdf', [PengusahaanBaFisikPelumasController::class, 'pdf'])->name('pengusahaan.ba-fisik-pelumas.pdf');

        // Perincian & Rekap Bahan Bakar share one controller and one stored record.
        foreach (['perincian-bbm', 'rekap-bbm'] as $rincianBbm) {
            Route::get("pengusahaan/{$rincianBbm}", [PengusahaanRincianBbmController::class, 'index'])->defaults('jenis', $rincianBbm)->name("pengusahaan.{$rincianBbm}.index");
            Route::post("pengusahaan/{$rincianBbm}", [PengusahaanRincianBbmController::class, 'store'])->defaults('jenis', $rincianBbm)->name("pengusahaan.{$rincianBbm}.store");
            Route::get("pengusahaan/{$rincianBbm}/pdf", [PengusahaanRincianBbmController::class, 'pdf'])->defaults('jenis', $rincianBbm)->name("pengusahaan.{$rincianBbm}.pdf");
        }

        // Perincian Minyak Pelumas & Rekap Pelumas share one controller.
        foreach (['perincian-pelumas', 'rekap-pelumas'] as $rincian) {
            Route::get("pengusahaan/{$rincian}", [PengusahaanRincianPelumasController::class, 'index'])->defaults('jenis', $rincian)->name("pengusahaan.{$rincian}.index");
            Route::post("pengusahaan/{$rincian}", [PengusahaanRincianPelumasController::class, 'store'])->defaults('jenis', $rincian)->name("pengusahaan.{$rincian}.store");
            Route::get("pengusahaan/{$rincian}/pdf", [PengusahaanRincianPelumasController::class, 'pdf'])->defaults('jenis', $rincian)->name("pengusahaan.{$rincian}.pdf");
        }

        // Jam Operasi / Jam Pemeliharaan / Jam Gangguan share one controller; Jam Siap Ops is derived from them.
        foreach (['operasi', 'pemeliharaan', 'gangguan'] as $jamJenis) {
            Route::get("pengusahaan/jam-{$jamJenis}", [PengusahaanJamMesinController::class, 'index'])->defaults('jenis', $jamJenis)->name("pengusahaan.jam-{$jamJenis}.index");
            Route::post("pengusahaan/jam-{$jamJenis}", [PengusahaanJamMesinController::class, 'store'])->defaults('jenis', $jamJenis)->name("pengusahaan.jam-{$jamJenis}.store");
            Route::get("pengusahaan/jam-{$jamJenis}/pdf", [PengusahaanJamMesinController::class, 'pdf'])->defaults('jenis', $jamJenis)->name("pengusahaan.jam-{$jamJenis}.pdf");
        }
        Route::get('pengusahaan/jam-siap-ops', [PengusahaanJamSiapOpsController::class, 'index'])->name('pengusahaan.jam-siap-ops.index');
        Route::get('pengusahaan/jam-siap-ops/pdf', [PengusahaanJamSiapOpsController::class, 'pdf'])->name('pengusahaan.jam-siap-ops.pdf');

        // Beban Tertinggi, Jumlah Kali Gangguan & Tara Kalor share one controller.
        foreach (['beban-tinggi', 'kali-gangguan', 'tara-kalor'] as $mesinHarian) {
            Route::get("pengusahaan/{$mesinHarian}", [PengusahaanMesinHarianController::class, 'index'])->defaults('jenis', $mesinHarian)->name("pengusahaan.{$mesinHarian}.index");
            Route::post("pengusahaan/{$mesinHarian}", [PengusahaanMesinHarianController::class, 'store'])->defaults('jenis', $mesinHarian)->name("pengusahaan.{$mesinHarian}.store");
            Route::get("pengusahaan/{$mesinHarian}/pdf", [PengusahaanMesinHarianController::class, 'pdf'])->defaults('jenis', $mesinHarian)->name("pengusahaan.{$mesinHarian}.pdf");
        }

        // Rekap: filled from the other sheets, editable.
        Route::get('pengusahaan/kinerja', [PengusahaanKinerjaController::class, 'index'])->name('pengusahaan.kinerja.index');
        Route::post('pengusahaan/kinerja', [PengusahaanKinerjaController::class, 'store'])->name('pengusahaan.kinerja.store');
        Route::get('pengusahaan/kinerja/pdf', [PengusahaanKinerjaController::class, 'pdf'])->name('pengusahaan.kinerja.pdf');
        Route::get('pengusahaan/inventaris-mesin', [PengusahaanInventarisController::class, 'index'])->name('pengusahaan.inventaris-mesin.index');
        Route::post('pengusahaan/inventaris-mesin', [PengusahaanInventarisController::class, 'store'])->name('pengusahaan.inventaris-mesin.store');
        Route::get('pengusahaan/inventaris-mesin/pdf', [PengusahaanInventarisController::class, 'pdf'])->name('pengusahaan.inventaris-mesin.pdf');
        Route::get('pengusahaan/kinerja-termal', [PengusahaanKinerjaTermalController::class, 'index'])->name('pengusahaan.kinerja-termal.index');
        Route::post('pengusahaan/kinerja-termal', [PengusahaanKinerjaTermalController::class, 'store'])->name('pengusahaan.kinerja-termal.store');
        Route::get('pengusahaan/kinerja-termal/pdf', [PengusahaanKinerjaTermalController::class, 'pdf'])->name('pengusahaan.kinerja-termal.pdf');

        // kWh: Stand kWh Harian is the input; TP and Energi are read from it.
        Route::get('pengusahaan/stand-kwh', [PengusahaanKwhController::class, 'harian'])->name('pengusahaan.stand-kwh.index');
        Route::post('pengusahaan/stand-kwh', [PengusahaanKwhController::class, 'store'])->name('pengusahaan.stand-kwh.store');
        Route::get('pengusahaan/stand-kwh/pdf', [PengusahaanKwhController::class, 'harianPdf'])->name('pengusahaan.stand-kwh.pdf');
        Route::get('pengusahaan/kwh-transfer-pricing', [PengusahaanKwhController::class, 'transferPricing'])->name('pengusahaan.kwh-tp.index');
        Route::get('pengusahaan/kwh-transfer-pricing/pdf', [PengusahaanKwhController::class, 'transferPricingPdf'])->name('pengusahaan.kwh-tp.pdf');
        Route::get('pengusahaan/kwh-rekap', [PengusahaanKwhController::class, 'rekap'])->name('pengusahaan.kwh-rekap.index');
        Route::get('pengusahaan/kwh-rekap/pdf', [PengusahaanKwhController::class, 'rekapPdf'])->name('pengusahaan.kwh-rekap.pdf');
        // SFC (liter ÷ kWh produksi) and SFC Netto (liter ÷ kWh netto).
        foreach (['sfc', 'sfc-netto'] as $sfc) {
            Route::get("pengusahaan/{$sfc}", [PengusahaanKwhController::class, 'sfc'])->defaults('jenis', $sfc)->name("pengusahaan.{$sfc}.index");
            Route::get("pengusahaan/{$sfc}/pdf", [PengusahaanKwhController::class, 'sfcPdf'])->defaults('jenis', $sfc)->name("pengusahaan.{$sfc}.pdf");
        }
        foreach (['dibangkit', 'pemakaian-sendiri'] as $energi) {
            Route::get("pengusahaan/energi-{$energi}", [PengusahaanKwhController::class, 'energi'])->defaults('jenis', $energi)->name("pengusahaan.energi-{$energi}.index");
            Route::get("pengusahaan/energi-{$energi}/pdf", [PengusahaanKwhController::class, 'energiPdf'])->defaults('jenis', $energi)->name("pengusahaan.energi-{$energi}.pdf");
        }

        Route::get('pengusahaan/berita-acara', [PengusahaanBeritaAcaraController::class, 'index'])->name('pengusahaan.berita-acara.index');
        Route::post('pengusahaan/berita-acara', [PengusahaanBeritaAcaraController::class, 'store'])->name('pengusahaan.berita-acara.store');
        Route::post('pengusahaan/berita-acara/lampiran', [PengusahaanBeritaAcaraController::class, 'uploadAttachment'])->name('pengusahaan.berita-acara.attachment.upload');
        Route::delete('pengusahaan/berita-acara/lampiran', [PengusahaanBeritaAcaraController::class, 'destroyAttachment'])->name('pengusahaan.berita-acara.attachment.destroy');
        Route::get('pengusahaan/berita-acara/{type}', [PengusahaanBeritaAcaraController::class, 'show'])->name('pengusahaan.berita-acara.show');
        Route::get('pengusahaan/berita-acara/{type}/preview', [PengusahaanBeritaAcaraController::class, 'preview'])->name('pengusahaan.berita-acara.preview');
        Route::get('pengusahaan/berita-acara/{type}/pdf', [PengusahaanBeritaAcaraController::class, 'pdf'])->name('pengusahaan.berita-acara.pdf');

        // Akses 2 — Pengusahaan (TL & Staf): hub per menu section.
        Route::get('pengusahaan/{section}', [PengusahaanController::class, 'index'])->name('pengusahaan.index')
            ->defaults('module', 'operasi')->whereIn('section', array_keys(PengusahaanController::SECTIONS));
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        // Laporan Pengusahaan Pembangkit (Akses 2) — before laporan/{report} so "pengusahaan" is never taken as a report code.
        Route::get('laporan/pengusahaan', [LaporanPengusahaanController::class, 'edit'])->name('laporan.pengusahaan.edit');
        Route::post('laporan/pengusahaan', [LaporanPengusahaanController::class, 'store'])->name('laporan.pengusahaan.store');
        Route::post('laporan/pengusahaan/muat-ulang', [LaporanPengusahaanController::class, 'regenerate'])->name('laporan.pengusahaan.regenerate');
        Route::get('laporan/pengusahaan/pdf', [LaporanPengusahaanController::class, 'pdf'])->name('laporan.pengusahaan.pdf');
        Route::get('laporan/{report}/excel', [LaporanController::class, 'spreadsheet'])->name('laporan.spreadsheet');
        Route::get('laporan/{report}/dokumen', [LaporanDocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/{report}/dokumen', [LaporanDocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/{report}/dokumen/muat-ulang', [LaporanDocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/{report}/dokumen/pdf', [LaporanDocumentController::class, 'pdf'])->name('laporan.document.pdf');
        Route::get('laporan/{report}', [LaporanController::class, 'show'])->name('laporan.show');

        Route::get('document-template', [DocumentTemplateController::class, 'index'])->name('document-template.index');
        Route::put('document-template/{type}', [DocumentTemplateController::class, 'update'])->name('document-template.update');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
