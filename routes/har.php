<?php

use App\Http\Controllers\Har\ActivityController;
use App\Http\Controllers\Har\AttachmentController;
use App\Http\Controllers\Har\ClearanceValveController;
use App\Http\Controllers\Har\CombustionPressureController;
use App\Http\Controllers\Har\CostController;
use App\Http\Controllers\Har\CrankshaftDeflectionController;
use App\Http\Controllers\Har\DocumentController;
use App\Http\Controllers\Har\FormulirController;
use App\Http\Controllers\Har\HydrotestController;
use App\Http\Controllers\Har\InputHubController;
use App\Http\Controllers\Har\JadwalController;
use App\Http\Controllers\Har\LaporanController;
use App\Http\Controllers\Har\MasterController;
use App\Http\Controllers\Har\PrelubeTestController;
use App\Http\Controllers\Har\ScheduleController;
use App\Http\Controllers\Har\ServiceRequestController;
use App\Http\Controllers\Har\TimingInjectionPumpController;
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
        Route::get('jadwal', [JadwalController::class, 'index'])->name('jadwal.index');
        Route::get('input', [InputHubController::class, 'index'])->name('input.index');
        Route::get('formulir', [FormulirController::class, 'index'])->name('formulir.index');
        Route::get('formulir/prelube-test', [PrelubeTestController::class, 'index'])->name('formulir.prelube-test.index');
        Route::post('formulir/prelube-test', [PrelubeTestController::class, 'store'])->name('formulir.prelube-test.store');
        Route::get('formulir/prelube-test/pdf', [PrelubeTestController::class, 'pdf'])->name('formulir.prelube-test.pdf');
        Route::delete('formulir/prelube-test/{prelubeTest}', [PrelubeTestController::class, 'destroy'])->name('formulir.prelube-test.destroy');

        Route::get('formulir/hydrotest', [HydrotestController::class, 'index'])->name('formulir.hydrotest.index');
        Route::post('formulir/hydrotest', [HydrotestController::class, 'store'])->name('formulir.hydrotest.store');
        Route::get('formulir/hydrotest/pdf', [HydrotestController::class, 'pdf'])->name('formulir.hydrotest.pdf');
        Route::delete('formulir/hydrotest/{hydrotest}', [HydrotestController::class, 'destroy'])->name('formulir.hydrotest.destroy');

        Route::get('formulir/timing-injection-pump', [TimingInjectionPumpController::class, 'index'])->name('formulir.timing-injection-pump.index');
        Route::post('formulir/timing-injection-pump', [TimingInjectionPumpController::class, 'store'])->name('formulir.timing-injection-pump.store');
        Route::get('formulir/timing-injection-pump/pdf', [TimingInjectionPumpController::class, 'pdf'])->name('formulir.timing-injection-pump.pdf');
        Route::delete('formulir/timing-injection-pump/{timingInjectionPump}', [TimingInjectionPumpController::class, 'destroy'])->name('formulir.timing-injection-pump.destroy');

        Route::get('formulir/tekanan-pembakaran', [CombustionPressureController::class, 'index'])->name('formulir.combustion-pressure.index');
        Route::post('formulir/tekanan-pembakaran', [CombustionPressureController::class, 'store'])->name('formulir.combustion-pressure.store');
        Route::get('formulir/tekanan-pembakaran/pdf', [CombustionPressureController::class, 'pdf'])->name('formulir.combustion-pressure.pdf');
        Route::delete('formulir/tekanan-pembakaran/{combustionPressure}', [CombustionPressureController::class, 'destroy'])->name('formulir.combustion-pressure.destroy');

        Route::get('formulir/defleksi-crankshaft', [CrankshaftDeflectionController::class, 'index'])->name('formulir.crankshaft-deflection.index');
        Route::post('formulir/defleksi-crankshaft', [CrankshaftDeflectionController::class, 'store'])->name('formulir.crankshaft-deflection.store');
        Route::get('formulir/defleksi-crankshaft/pdf', [CrankshaftDeflectionController::class, 'pdf'])->name('formulir.crankshaft-deflection.pdf');
        Route::delete('formulir/defleksi-crankshaft/{crankshaftDeflection}', [CrankshaftDeflectionController::class, 'destroy'])->name('formulir.crankshaft-deflection.destroy');

        Route::get('formulir/clearance-valve', [ClearanceValveController::class, 'index'])->name('formulir.clearance-valve.index');
        Route::post('formulir/clearance-valve', [ClearanceValveController::class, 'store'])->name('formulir.clearance-valve.store');
        Route::get('formulir/clearance-valve/pdf', [ClearanceValveController::class, 'pdf'])->name('formulir.clearance-valve.pdf');
        Route::delete('formulir/clearance-valve/{clearanceValve}', [ClearanceValveController::class, 'destroy'])->name('formulir.clearance-valve.destroy');

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

        Route::get('laporan/dokumen', [DocumentController::class, 'edit'])->name('laporan.document.edit');
        Route::post('laporan/dokumen', [DocumentController::class, 'store'])->name('laporan.document.store');
        Route::post('laporan/dokumen/muat-ulang', [DocumentController::class, 'regenerate'])->name('laporan.document.regenerate');
        Route::get('laporan/dokumen/pdf', [DocumentController::class, 'pdf'])->name('laporan.document.pdf');

        Route::get('master/{resource}', [MasterController::class, 'index'])->name('master.index');
        Route::post('master/{resource}', [MasterController::class, 'store'])->name('master.store');
        Route::put('master/{resource}/{id}', [MasterController::class, 'update'])->name('master.update');
        Route::delete('master/{resource}/{id}', [MasterController::class, 'destroy'])->name('master.destroy');
    });
