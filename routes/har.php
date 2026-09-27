<?php

use App\Http\Controllers\Har\ActivityController;
use App\Http\Controllers\Har\AttachmentController;
use App\Http\Controllers\Har\AxialConrodController;
use App\Http\Controllers\Har\BatteryVoltageController;
use App\Http\Controllers\Har\ClearanceValveController;
use App\Http\Controllers\Har\CombustionPressureController;
use App\Http\Controllers\Har\CounterWeightController;
use App\Http\Controllers\Har\CrankshaftDeflectionController;
use App\Http\Controllers\Har\DailyMeetingController;
use App\Http\Controllers\Har\DocumentController;
use App\Http\Controllers\Har\FormulirController;
use App\Http\Controllers\Har\HydrotestController;
use App\Http\Controllers\Har\InjectorPressureController;
use App\Http\Controllers\Har\InputHubController;
use App\Http\Controllers\Har\JadwalController;
use App\Http\Controllers\Har\JadwalHarianController;
use App\Http\Controllers\Har\JadwalMeetingPemeliharaanController;
use App\Http\Controllers\Har\JadwalP0P5Controller;
use App\Http\Controllers\Har\JadwalPatrolCheckController;
use App\Http\Controllers\Har\JadwalPembuatanIkController;
use App\Http\Controllers\Har\JadwalPiketOnCallController;
use App\Http\Controllers\Har\LaporanController;
use App\Http\Controllers\Har\LaporanGangguanController;
use App\Http\Controllers\Har\LaporanPengusahaanController;
use App\Http\Controllers\Har\LembarController;
use App\Http\Controllers\Har\LogbookMutasiController;
use App\Http\Controllers\Har\LubeQualityController;
use App\Http\Controllers\Har\MasterController;
use App\Http\Controllers\Har\MotorCurrentController;
use App\Http\Controllers\Har\PatrolCheckParameterController;
use App\Http\Controllers\Har\PrelubeTestController;
use App\Http\Controllers\Har\Program5s5rController;
use App\Http\Controllers\Har\ScheduleController;
use App\Http\Controllers\Har\ServiceRequestController;
use App\Http\Controllers\Har\TabelController;
use App\Http\Controllers\Har\TimingInjectionPumpController;
use App\Http\Controllers\Har\UnsafeConditionController;
use App\Http\Controllers\Har\VibrationController;
use App\Http\Controllers\Har\WorkOrderController;
use App\Http\Controllers\PengusahaanController;
use App\Support\HarLembar\HarLembars;
use App\Support\HarTabel\HarTabels;
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
        Route::get('jadwal/harian', [JadwalHarianController::class, 'index'])->name('jadwal.harian.index');
        Route::post('jadwal/harian', [JadwalHarianController::class, 'store'])->name('jadwal.harian.store');
        Route::get('jadwal/harian/pdf', [JadwalHarianController::class, 'pdf'])->name('jadwal.harian.pdf');
        Route::get('jadwal/p0-p5', [JadwalP0P5Controller::class, 'index'])->name('jadwal.p0-p5.index');
        Route::post('jadwal/p0-p5', [JadwalP0P5Controller::class, 'store'])->name('jadwal.p0-p5.store');
        Route::get('jadwal/p0-p5/pdf', [JadwalP0P5Controller::class, 'pdf'])->name('jadwal.p0-p5.pdf');
        Route::get('jadwal/piket-on-call', [JadwalPiketOnCallController::class, 'index'])->name('jadwal.piket-on-call.index');
        Route::post('jadwal/piket-on-call', [JadwalPiketOnCallController::class, 'store'])->name('jadwal.piket-on-call.store');
        Route::get('jadwal/piket-on-call/pdf', [JadwalPiketOnCallController::class, 'pdf'])->name('jadwal.piket-on-call.pdf');
        Route::get('jadwal/patrol-check', [JadwalPatrolCheckController::class, 'index'])->name('jadwal.patrol-check.index');
        Route::post('jadwal/patrol-check', [JadwalPatrolCheckController::class, 'store'])->name('jadwal.patrol-check.store');
        Route::get('jadwal/patrol-check/pdf', [JadwalPatrolCheckController::class, 'pdf'])->name('jadwal.patrol-check.pdf');
        Route::get('jadwal/meeting-pemeliharaan', [JadwalMeetingPemeliharaanController::class, 'index'])->name('jadwal.meeting-pemeliharaan.index');
        Route::post('jadwal/meeting-pemeliharaan', [JadwalMeetingPemeliharaanController::class, 'store'])->name('jadwal.meeting-pemeliharaan.store');
        Route::get('jadwal/meeting-pemeliharaan/pdf', [JadwalMeetingPemeliharaanController::class, 'pdf'])->name('jadwal.meeting-pemeliharaan.pdf');
        Route::get('jadwal/pembuatan-ik', [JadwalPembuatanIkController::class, 'index'])->name('jadwal.pembuatan-ik.index');
        Route::post('jadwal/pembuatan-ik', [JadwalPembuatanIkController::class, 'store'])->name('jadwal.pembuatan-ik.store');
        Route::get('jadwal/pembuatan-ik/pdf', [JadwalPembuatanIkController::class, 'pdf'])->name('jadwal.pembuatan-ik.pdf');
        Route::get('jadwal/lembar/{lembar}', [LembarController::class, 'index'])->name('jadwal.lembar.index')->whereIn('lembar', HarLembars::keysFor('jadwal'));
        Route::post('jadwal/lembar/{lembar}', [LembarController::class, 'store'])->name('jadwal.lembar.store')->whereIn('lembar', HarLembars::keysFor('jadwal'));
        Route::get('jadwal/lembar/{lembar}/pdf', [LembarController::class, 'pdf'])->name('jadwal.lembar.pdf')->whereIn('lembar', HarLembars::keysFor('jadwal'));
        Route::get('input', [InputHubController::class, 'index'])->name('input.index');
        Route::get('formulir', [FormulirController::class, 'index'])->name('formulir.index');
        Route::get('formulir/daily-meeting', [DailyMeetingController::class, 'index'])->name('formulir.daily-meeting.index');
        Route::post('formulir/daily-meeting', [DailyMeetingController::class, 'store'])->name('formulir.daily-meeting.store');
        Route::delete('formulir/daily-meeting/{dailyMeeting}', [DailyMeetingController::class, 'destroy'])->name('formulir.daily-meeting.destroy');
        Route::get('formulir/daily-meeting/{dailyMeeting}/pdf', [DailyMeetingController::class, 'pdf'])->name('formulir.daily-meeting.pdf');
        Route::get('formulir/logbook-mutasi', [LogbookMutasiController::class, 'index'])->name('formulir.logbook-mutasi.index');
        Route::post('formulir/logbook-mutasi', [LogbookMutasiController::class, 'store'])->name('formulir.logbook-mutasi.store');
        Route::delete('formulir/logbook-mutasi/{logbookMutasi}', [LogbookMutasiController::class, 'destroy'])->name('formulir.logbook-mutasi.destroy');
        Route::get('formulir/logbook-mutasi/{logbookMutasi}/pdf', [LogbookMutasiController::class, 'pdf'])->name('formulir.logbook-mutasi.pdf');
        Route::get('formulir/laporan-gangguan', [LaporanGangguanController::class, 'index'])->name('formulir.laporan-gangguan.index');
        Route::post('formulir/laporan-gangguan', [LaporanGangguanController::class, 'store'])->name('formulir.laporan-gangguan.store');
        Route::delete('formulir/laporan-gangguan/{laporanGangguan}', [LaporanGangguanController::class, 'destroy'])->name('formulir.laporan-gangguan.destroy');
        Route::get('formulir/laporan-gangguan/{laporanGangguan}/pdf', [LaporanGangguanController::class, 'pdf'])->name('formulir.laporan-gangguan.pdf');
        // Akses 2 — Pengusahaan (TL & Staf): formulir teknis pemeliharaan per mesin & tanggal uji.
        foreach ([
            'prelube-test' => PrelubeTestController::class,
            'hydrotest' => HydrotestController::class,
            'timing-injection-pump' => TimingInjectionPumpController::class,
            'crankshaft-deflection' => CrankshaftDeflectionController::class,
            'counter-weight' => CounterWeightController::class,
            'axial-conrod' => AxialConrodController::class,
            'clearance-valve' => ClearanceValveController::class,
            'combustion-pressure' => CombustionPressureController::class,
            'injector-pressure' => InjectorPressureController::class,
            'motor-current' => MotorCurrentController::class,
            'vibration' => VibrationController::class,
            'lube-quality' => LubeQualityController::class,
            'battery-voltage' => BatteryVoltageController::class,
        ] as $slug => $controller) {
            Route::get("pengusahaan/{$slug}", [$controller, 'index'])->name("pengusahaan.{$slug}.index");
            Route::post("pengusahaan/{$slug}", [$controller, 'store'])->name("pengusahaan.{$slug}.store");
            Route::get("pengusahaan/{$slug}/pdf", [$controller, 'pdf'])->name("pengusahaan.{$slug}.pdf");
            Route::delete("pengusahaan/{$slug}/{record}", [$controller, 'destroy'])->name("pengusahaan.{$slug}.destroy")->whereNumber('record');
        }

        Route::get('input/work-order', [WorkOrderController::class, 'index'])->name('input.work-order.index');
        Route::post('input/work-order', [WorkOrderController::class, 'store'])->name('input.work-order.store');

        Route::get('input/service-request', [ServiceRequestController::class, 'index'])->name('input.service-request.index');
        Route::post('input/service-request', [ServiceRequestController::class, 'store'])->name('input.service-request.store');

        Route::get('input/activity', [ActivityController::class, 'index'])->name('input.activity.index');
        Route::post('input/activity', [ActivityController::class, 'store'])->name('input.activity.store');
        Route::put('input/activity/{activity}', [ActivityController::class, 'update'])->name('input.activity.update');
        Route::delete('input/activity/{activity}', [ActivityController::class, 'destroy'])->name('input.activity.destroy');

        Route::get('input/attachment', [AttachmentController::class, 'index'])->name('input.attachment.index');
        Route::post('input/attachment', [AttachmentController::class, 'store'])->name('input.attachment.store');
        Route::delete('input/attachment/{attachment}', [AttachmentController::class, 'destroy'])->name('input.attachment.destroy');

        Route::get('input/schedule', [ScheduleController::class, 'index'])->name('input.schedule.index');
        Route::post('input/schedule', [ScheduleController::class, 'store'])->name('input.schedule.store');

        Route::get('input/unsafe-condition', [UnsafeConditionController::class, 'index'])->name('input.unsafe-condition.index');
        Route::post('input/unsafe-condition', [UnsafeConditionController::class, 'store'])->name('input.unsafe-condition.store');
        Route::post('input/unsafe-condition/{unsafeCondition}', [UnsafeConditionController::class, 'update'])->name('input.unsafe-condition.update');
        Route::delete('input/unsafe-condition/{unsafeCondition}', [UnsafeConditionController::class, 'destroy'])->name('input.unsafe-condition.destroy');
        Route::get('input/unsafe-condition/pdf', [UnsafeConditionController::class, 'pdf'])->name('input.unsafe-condition.pdf');

        // Tabel input bebas (App\Support\HarTabel): Rekap Laporan Gangguan & Laporan Kondisi Abnormal dan Gangguan.
        foreach (HarTabels::all() as $tabel) {
            Route::get("input/{$tabel->key()}", [TabelController::class, 'index'])->name("input.{$tabel->key()}.index")->defaults('tabel', $tabel->key());
            Route::post("input/{$tabel->key()}", [TabelController::class, 'store'])->name("input.{$tabel->key()}.store")->defaults('tabel', $tabel->key());
            Route::get("input/{$tabel->key()}/pdf", [TabelController::class, 'pdf'])->name("input.{$tabel->key()}.pdf")->defaults('tabel', $tabel->key());
        }

        Route::get('input/program-5s5r', [Program5s5rController::class, 'index'])->name('input.program-5s5r.index');
        Route::post('input/program-5s5r', [Program5s5rController::class, 'store'])->name('input.program-5s5r.store');
        Route::get('input/program-5s5r/pdf', [Program5s5rController::class, 'pdf'])->name('input.program-5s5r.pdf');
        Route::get('input/patrol-check-parameter', [PatrolCheckParameterController::class, 'index'])->name('input.patrol-check-parameter.index');
        Route::post('input/patrol-check-parameter', [PatrolCheckParameterController::class, 'store'])->name('input.patrol-check-parameter.store');
        Route::get('input/patrol-check-parameter/pdf', [PatrolCheckParameterController::class, 'pdf'])->name('input.patrol-check-parameter.pdf');

        Route::get('input/lembar/{lembar}', [LembarController::class, 'index'])->name('input.lembar.index')->whereIn('lembar', HarLembars::keysFor('input'));
        Route::post('input/lembar/{lembar}', [LembarController::class, 'store'])->name('input.lembar.store')->whereIn('lembar', HarLembars::keysFor('input'));
        Route::get('input/lembar/{lembar}/pdf', [LembarController::class, 'pdf'])->name('input.lembar.pdf')->whereIn('lembar', HarLembars::keysFor('input'));

        // Akses 2 — Pengusahaan (TL & Staf): hub per menu section.
        Route::get('pengusahaan/{section}', [PengusahaanController::class, 'index'])->name('pengusahaan.index')
            ->defaults('module', 'har')->whereIn('section', array_keys(PengusahaanController::SECTIONS));
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/bulanan', [LaporanController::class, 'monthly'])->name('laporan.monthly');

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
