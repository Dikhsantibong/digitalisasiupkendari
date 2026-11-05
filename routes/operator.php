<?php

use App\Http\Controllers\Operator\AbsensiController;
use App\Http\Controllers\Operator\LogsheetController;
use App\Http\Controllers\Operator\MutasiController;
use App\Http\Controllers\Operator\OpnameController;
use App\Http\Controllers\Operator\PengajuanController;
use App\Http\Controllers\Operator\PresensiController;
use App\Http\Controllers\Operator\ResumeController;
use App\Http\Controllers\Operator\StartStopController;
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
        Route::post('logsheet/lembar', [LogsheetController::class, 'storeSheet'])->name('logsheet.sheet');
        Route::get('logsheet/pdf', [LogsheetController::class, 'pdf'])->name('logsheet.pdf');

        // Start Stop Engine: start, CBG on/off and stop of each run per mesin; gangguan stops feed Jumlah Kali Gangguan.
        Route::get('start-stop', [StartStopController::class, 'index'])->name('start-stop.index');
        Route::post('start-stop', [StartStopController::class, 'sync'])->name('start-stop.sync');

        // Opname BBM, Opname Pelumas and Opname kWh: one daily sheet each per unit.
        foreach (['bbm', 'pelumas', 'kwh'] as $opname) {
            Route::get("opname-{$opname}", [OpnameController::class, 'index'])->defaults('jenis', $opname)->name("opname-{$opname}.index");
            Route::post("opname-{$opname}", [OpnameController::class, 'store'])->defaults('jenis', $opname)->name("opname-{$opname}.store");
        }

        Route::get('absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::post('absensi', [AbsensiController::class, 'store'])->name('absensi.store');
        Route::post('absensi/generate', [AbsensiController::class, 'generate'])->name('absensi.generate');
        Route::get('absensi/pdf', [AbsensiController::class, 'pdf'])->name('absensi.pdf');

        Route::get('mutasi', [MutasiController::class, 'index'])->name('mutasi.index');
        Route::post('mutasi', [MutasiController::class, 'store'])->name('mutasi.store');
        Route::post('mutasi/{mutasi}/terima', [MutasiController::class, 'terima'])->name('mutasi.terima');
        Route::get('mutasi/{mutasi}/pdf', [MutasiController::class, 'pdf'])->name('mutasi.pdf');

        Route::get('presensi', [PresensiController::class, 'index'])->name('presensi.index');
        Route::post('presensi/masuk', [PresensiController::class, 'checkIn'])->name('presensi.check-in');
        Route::post('presensi/pulang', [PresensiController::class, 'checkOut'])->name('presensi.check-out');

        // Pengajuan Absensi: izin, sakit, cuti & tukar shift (pengganti → persetujuan → Jadwal Shift).
        Route::get('pengajuan', [PengajuanController::class, 'index'])->name('pengajuan.index');
        Route::post('pengajuan', [PengajuanController::class, 'store'])->name('pengajuan.store');
        Route::post('pengajuan/{attendanceRequest}/jawab', [PengajuanController::class, 'respond'])->name('pengajuan.respond');
        Route::post('pengajuan/{attendanceRequest}/setujui', [PengajuanController::class, 'approve'])->name('pengajuan.approve');
        Route::post('pengajuan/{attendanceRequest}/tolak', [PengajuanController::class, 'reject'])->name('pengajuan.reject');
        Route::post('pengajuan/{attendanceRequest}/batal', [PengajuanController::class, 'cancel'])->name('pengajuan.cancel');
        Route::get('pengajuan/{attendanceRequest}/lampiran', [PengajuanController::class, 'attachment'])->name('pengajuan.attachment');

        // Resume Statistik: realisasi shift harian & pekerjaan per operator.
        Route::get('resume', [ResumeController::class, 'index'])->name('resume.index');
    });
