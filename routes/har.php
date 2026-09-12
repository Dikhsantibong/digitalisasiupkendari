<?php

use App\Http\Controllers\Har\ActivityController;
use App\Http\Controllers\Har\AttachmentController;
use App\Http\Controllers\Har\CostController;
use App\Http\Controllers\Har\DocumentController;
use App\Http\Controllers\Har\LaporanController;
use App\Http\Controllers\Har\MasterController;
use App\Http\Controllers\Har\ScheduleController;
use App\Http\Controllers\Har\ServiceRequestController;
use App\Http\Controllers\Har\WorkOrderController;
use Illuminate\Support\Facades\Route;

/**
 * Modul PEMELIHARAAN (HAR). Every route is additionally guarded inside its
 * controller by a permission check (har.*) and, where relevant, a unit-scope
 * check, so membership of this group is not on its own a grant.
 */
Route::middleware(['auth', 'verified'])
    ->prefix('har')
    ->name('har.')
    ->group(function (): void {
        Route::get('input/work-order', [WorkOrderController::class, 'index'])->name('input.work-order.index');
        Route::post('input/work-order', [WorkOrderController::class, 'store'])->name('input.work-order.store');

        Route::get('input/service-request', [ServiceRequestController::class, 'index'])->name('input.service-request.index');
        Route::post('input/service-request', [ServiceRequestController::class, 'store'])->name('input.service-request.store');

        Route::get('input/activity', [ActivityController::class, 'index'])->name('input.activity.index');
        Route::post('input/activity', [ActivityController::class, 'store'])->name('input.activity.store');
        Route::put('input/activity/{activity}', [ActivityController::class, 'update'])->name('input.activity.update');
        Route::delete('input/activity/{activity}', [ActivityController::class, 'destroy'])->name('input.activity.destroy');

        Route::get('input/cost', [CostController::class, 'index'])->name('input.cost.index');
        Route::post('input/cost', [CostController::class, 'store'])->name('input.cost.store');

        Route::get('input/attachment', [AttachmentController::class, 'index'])->name('input.attachment.index');
        Route::post('input/attachment', [AttachmentController::class, 'store'])->name('input.attachment.store');
        Route::delete('input/attachment/{attachment}', [AttachmentController::class, 'destroy'])->name('input.attachment.destroy');

        Route::get('input/schedule', [ScheduleController::class, 'index'])->name('input.schedule.index');
        Route::post('input/schedule', [ScheduleController::class, 'store'])->name('input.schedule.store');

        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/bulanan', [LaporanController::class, 'monthly'])->name('laporan.monthly');
        Route::get('laporan/executive', [LaporanController::class, 'executive'])->name('laporan.executive');

        Route::get('laporan/dokumen', [DocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/dokumen', [DocumentController::class, 'store'])->name('laporan.document.store');
        Route::get('laporan/dokumen/pdf', [DocumentController::class, 'pdf'])->name('laporan.document.pdf');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
