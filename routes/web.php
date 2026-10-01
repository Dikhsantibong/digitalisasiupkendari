<?php

use App\Enums\ReportModule;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Monitoring\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportWorkflowController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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
