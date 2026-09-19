<?php

use App\Enums\ReportModule;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportWorkflowController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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
