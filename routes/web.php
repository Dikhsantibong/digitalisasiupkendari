<?php

use App\Enums\ReportModule;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanUlController;
use App\Http\Controllers\ModuleResumeController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportWorkflowController;
use App\Http\Controllers\SignatureVerificationController;
use App\Http\Controllers\SlaController;
use App\Services\Reports\ModuleResume;
use App\Services\Reports\SlaCatalog;
use App\Services\Reports\UlReport;
use Illuminate\Support\Facades\Route;

// No landing page: guests go straight to the login page, signed-in users to their dashboard.
Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

// Public QR barcode signature verification (no authentication needed when scanned on paper/PDF).
Route::get('verifikasi-ttd/{ref}/{token}', [SignatureVerificationController::class, 'show'])
    ->where('ref', 'w?[0-9]+')
    ->where('token', '[a-f0-9]+')
    ->name('signature.verify');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Portal Pemantauan (kantor induk UP Kendari & Manager UL): lihat input, status & laporan final.
    Route::prefix('pantau')->name('portal.')->group(function (): void {
        Route::get('/', [PortalController::class, 'index'])->name('index');
        Route::get('ringkasan', [PortalController::class, 'overview'])->name('overview');
        Route::get('laporan', [PortalController::class, 'laporan'])->name('laporan');
        Route::get('data-input', [PortalController::class, 'input'])->name('input');
    });

    // Monitoring (Super Admin / monitoring.view): kelengkapan input & verifikasi laporan unit yang dapat diakses.
    Route::prefix('monitoring')->name('monitoring.')->group(function (): void {
        Route::get('/', [MonitoringController::class, 'index'])->name('index');
        Route::get('kelengkapan-input', [MonitoringController::class, 'input'])->name('input');
        Route::get('verifikasi-laporan', [MonitoringController::class, 'laporan'])->name('laporan');
    });

    // Notifikasi milik akun yang login (lonceng, halaman Notifikasi, preferensi, push ke perangkat).
    Route::prefix('notifikasi')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('feed', [NotificationController::class, 'feed'])->name('feed');
        Route::post('baca-semua', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::patch('pengaturan', [NotificationController::class, 'updateSettings'])->name('settings.update');
        Route::post('uji', [NotificationController::class, 'test'])->middleware('throttle:6,1')->name('test');
        Route::post('push', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('push', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::get('{notification}', [NotificationController::class, 'open'])->whereUuid('notification')->name('open');
        Route::post('{notification}/baca', [NotificationController::class, 'markRead'])->whereUuid('notification')->name('read');
    });

    // Persetujuan: laporan di giliran akun (Koordinator / TL / Manager UL), pengajuan operator, laporan "Mengetahui".
    Route::get('persetujuan', [ApprovalController::class, 'index'])->name('approvals.index');

    // Resume Bulanan per modul (pratinjau, PDF; Excel diunduh dari pratinjau), dibuka dari menu Laporan tiap modul.
    Route::prefix('laporan/resume/{module}')
        ->name('laporan.resume.')
        ->whereIn('module', array_keys(ModuleResume::MODULES))
        ->group(function (): void {
            Route::get('/', [ModuleResumeController::class, 'show'])->name('show');
            Route::get('pdf', [ModuleResumeController::class, 'pdf'])->name('pdf');
        });

    // Laporan UL (Admin UL & Super Admin): Pengusahaan Pembangkit, Logistik, K3L dari seluruh unit di bawah UL + PPT bulanan — PRD.md §8.13.
    Route::prefix('laporan-ul')->name('laporan-ul.')->group(function (): void {
        Route::get('/', [LaporanUlController::class, 'index'])->name('index');
        Route::get('ppt-data', [LaporanUlController::class, 'pptData'])->name('ppt-data');
        Route::prefix('{kind}')->whereIn('kind', array_keys(UlReport::KINDS))->group(function (): void {
            // Dokumen (Teks / Excel / Pratinjau PDF), seperti Laporan Project.
            Route::get('/', [LaporanUlController::class, 'show'])->name('show');
            Route::get('pdf', [LaporanUlController::class, 'pdf'])->name('pdf');
            Route::post('dokumen', [LaporanUlController::class, 'store'])->name('store');
            Route::post('dokumen/muat-ulang', [LaporanUlController::class, 'regenerate'])->name('regenerate');
            // Persetujuan berjenjang: Admin UL ajukan → TL per bidang setujui → Manager UL sahkan.
            Route::post('ajukan', [LaporanUlController::class, 'submit'])->name('workflow.submit');
            Route::post('setujui', [LaporanUlController::class, 'approve'])->name('workflow.approve');
            Route::post('sahkan', [LaporanUlController::class, 'ratify'])->name('workflow.ratify');
            Route::post('tolak', [LaporanUlController::class, 'reject'])->name('workflow.reject');
        });
    });

    // SLA bulanan per divisi (dihitung dari inputan), PDF; Excel diunduh dari halaman — PRD.md §8.12.
    // Laporan SLA gabungan (sampul + seluruh divisi + rekapitulasi dalam 1 file).
    Route::get('sla/gabungan/pdf', [SlaController::class, 'combinedPdf'])->name('sla.combined.pdf');
    Route::get('sla/gabungan/data', [SlaController::class, 'combinedData'])->name('sla.combined.data');
    Route::prefix('sla/{divisi}')
        ->name('sla.')
        ->whereIn('divisi', array_keys(SlaCatalog::DIVISI))
        ->group(function (): void {
            Route::get('/', [SlaController::class, 'show'])->name('show');
            Route::get('pdf', [SlaController::class, 'pdf'])->name('pdf');
        });

    // Workflow Laporan Pembangkit (ajukan → verifikasi Koordinator → setujui TL Pemeliharaan → sahkan Manager UL → final),
    // authorised inside ReportWorkflowService.
    Route::prefix('laporan-workflow/{module}')
        ->name('report-workflow.')
        ->whereIn('module', array_column(ReportModule::cases(), 'value'))
        ->group(function (): void {
            Route::post('ajukan', [ReportWorkflowController::class, 'submit'])->name('submit');
            Route::post('verifikasi', [ReportWorkflowController::class, 'verify'])->name('verify');
            Route::post('setujui', [ReportWorkflowController::class, 'approve'])->name('approve');
            Route::post('sahkan', [ReportWorkflowController::class, 'ratify'])->name('ratify');
            Route::post('tolak', [ReportWorkflowController::class, 'reject'])->name('reject');
        });
});

require __DIR__.'/admin.php';
require __DIR__.'/operator.php';
require __DIR__.'/operasi.php';
require __DIR__.'/har.php';
require __DIR__.'/k3.php';
require __DIR__.'/logistik.php';
require __DIR__.'/pdm.php';
require __DIR__.'/settings.php';
