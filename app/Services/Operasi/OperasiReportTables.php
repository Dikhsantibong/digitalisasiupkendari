<?php

namespace App\Services\Operasi;

use App\Enums\ReportModule;
use App\Http\Controllers\Operasi\BlackstartController;
use App\Http\Controllers\Operasi\DataTeknisController;
use App\Http\Controllers\Operasi\FlmController;
use App\Http\Controllers\Operasi\FlmMonitoringController;
use App\Http\Controllers\Operasi\InventarisController;
use App\Http\Controllers\Operasi\JadwalCommissioningTestController;
use App\Http\Controllers\Operasi\JadwalCommissioningTestPeralatanController;
use App\Http\Controllers\Operasi\JadwalPerformanceTestController;
use App\Http\Controllers\Operasi\KondisiAbnormalController;
use App\Http\Controllers\Operasi\MaterialPeralatanController;
use App\Http\Controllers\Operasi\MeetingShiftController;
use App\Http\Controllers\Operasi\PatrolCheckMesinController;
use App\Http\Controllers\Operasi\PembuatanIkController;
use App\Http\Controllers\Operasi\PengusahaanResourcePembangkitController;
use App\Http\Controllers\Operasi\PermitToWorkController;
use App\Http\Controllers\Operasi\Program5s5rController;
use App\Http\Controllers\Operasi\Program5s5rInputController;
use App\Models\OperasiInstruksiKerja;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\Unit;
use App\Services\Reports\Chart3d;
use App\Services\Reports\ReportWorkflowService;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * The jadwal & input tables of the Laporan Operasi Pembangkit — each the
 * jadwal/input PDF view itself (resources/views/operasi/{jadwal,input}),
 * rendered with exactly what that page shows and embedded as a scoped
 * fragment, so the report always prints the full table — plus the Resume
 * Statistik (computed from that same data, with 3D pie & bar charts) and the
 * Lembar Pengesahan & tanda tangan laporan from the report workflow.
 */
class OperasiReportTables
{
    /** Daftar Isi point => [controller, title]. */
    public const TABLES = [
        'flm' => [FlmController::class, 'Jadwal FLM'],
        'flm_monitoring' => [FlmMonitoringController::class, 'Laporan Monitoring FLM'],
        'program_5s5r' => [Program5s5rController::class, 'Jadwal Program 5S 5R'],
        'meeting_shift' => [MeetingShiftController::class, 'Jadwal Meeting Shift'],
        'inventarisasi' => [InventarisController::class, 'Jadwal Inventarisasi Tools dan Material Operasi'],
        'pembuatan_ik' => [PembuatanIkController::class, 'Jadwal Pembuatan IK'],
        'data_teknis' => [DataTeknisController::class, 'Jadwal Pembuatan Data Teknis KIT'],
        'blackstart' => [BlackstartController::class, 'Jadwal Pemeriksaan Instalasi Blackstart'],
        'performance_test' => [JadwalPerformanceTestController::class, 'Jadwal Pelaksanaan Performance Test'],
        'commissioning' => [JadwalCommissioningTestController::class, 'Jadwal Commissioning Test Mesin Pembangkit'],
        'kondisi_abnormal' => [KondisiAbnormalController::class, 'Laporan Kondisi Abnormal & Gangguan'],
        'material_peralatan' => [MaterialPeralatanController::class, 'Laporan Material dan Peralatan'],
        'permit_to_work' => [PermitToWorkController::class, 'Laporan Permit to Work Pembangkit'],
        'resource_pembangkit' => [PengusahaanResourcePembangkitController::class, 'Laporan Resource Pembangkit'],
    ];

    public function __construct(
        private readonly ScopedHtmlFragment $fragments,
        private readonly Chart3d $charts,
        private readonly ReportWorkflowService $workflows,
    ) {}

    /**
     * @param  int  $engineDaysFilled  days of the month with an engine daily report
     * @return array{tables: array<string, array{title: string, scope: string, body: string}>, styles: string, resume: array{rows: list<array{no: int, deskripsi: string, target: int, realisasi: int, analisa: string}>, summary: array{target: float, realisasi: float, analisa: string}, charts: array{pie: string, bar: string}}}
     */
    public function build(Unit $unit, int $month, int $year, int $engineDaysFilled = 0): array
    {
        $tables = [];
        $styles = [];
        $payloads = [];

        foreach (self::TABLES as $key => [$controller, $title]) {
            [$view, $data] = app($controller)->pdfView($unit, $month, $year);
            if ($key === 'performance_test' && empty($data['has_saved'])) {
                [$fallbackView, $fallbackData] = app(JadwalCommissioningTestPeralatanController::class)->pdfView($unit, $month, $year);
                if (! empty($fallbackData['has_saved'])) {
                    [$view, $data] = [$fallbackView, $fallbackData];
                }
            }
            $payloads[$key] = $data;
            $scope = 'op-v-'.Str::slug(str_replace('.', '-', $view));
            $fragment = $this->fragments->extract(View::make($view, $data)->render(), $scope);
            $styles[$scope] = $fragment['css'];
            $tables[$key] = ['title' => $title, 'scope' => $scope, 'body' => $fragment['body']];
        }

        // 12. Pembuatan Patrol Check: the Patrol Check Mesin input (Input Project) of every machine
        // saved for the month, each its own PDF view — empty body when none is saved.
        $patrol = $this->patrolChecks($unit, $month, $year);
        if ($patrol['css'] !== '') {
            $styles[$patrol['scope']] = $patrol['css'];
        }
        $tables['patrol_check'] = ['title' => 'Patrol Check Mesin', 'scope' => $patrol['scope'], 'body' => $patrol['body']];

        // 14. Laporan 5S5R: the Program 5S 5R Pengoperasian KIT input (per shift per hari)
        // when saved for the month.
        $program5s5rInput = $this->program5s5rInput($unit, $month, $year);
        if ($program5s5rInput['css'] !== '') {
            $styles[$program5s5rInput['scope']] = $program5s5rInput['css'];
        }
        $tables['program_5s5r_input'] = ['title' => 'Laporan 5S5R', 'scope' => $program5s5rInput['scope'], 'body' => $program5s5rInput['body']];

        return [
            'tables' => $tables,
            'styles' => implode("\n", $styles),
            'resume' => $this->resume($unit, $payloads, $month, $year, $engineDaysFilled),
        ];
    }

    /**
     * @return array{scope: string, css: string, body: string}
     */
    private function program5s5rInput(Unit $unit, int $month, int $year): array
    {
        $scope = 'op-v-operasi-input-program-5s5r-pdf';
        [$view, $data] = app(Program5s5rInputController::class)->pdfView($unit, $month, $year);
        if (empty($data['has_saved'])) {
            return ['scope' => $scope, 'css' => '', 'body' => ''];
        }

        $fragment = $this->fragments->extract(View::make($view, $data)->render(), $scope);

        return ['scope' => $scope, 'css' => $fragment['css'], 'body' => $fragment['body']];
    }

    /**
     * The saved Patrol Check Mesin sheets of the month (one per machine, by
     * machine name), each rendered from the input's own PDF view, one after
     * another.
     *
     * @return array{scope: string, css: string, body: string}
     */
    private function patrolChecks(Unit $unit, int $month, int $year): array
    {
        $scope = 'op-v-operasi-input-patrol-check-mesin-pdf';
        $css = '';
        $bodies = [];

        $machines = OperasiPatrolCheckMesin::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('nama_mesin')->orderBy('id')
            ->pluck('nama_mesin')->filter(fn (?string $name): bool => trim((string) $name) !== '')->unique()->values();

        foreach ($machines as $name) {
            [$view, $data] = app(PatrolCheckMesinController::class)->pdfView($unit, $year, $month, null, (string) $name);
            $fragment = $this->fragments->extract(View::make($view, $data)->render(), $scope);
            $css = $fragment['css'];
            $bodies[] = $fragment['body'];
        }

        return ['scope' => $scope, 'css' => $css, 'body' => implode('<div class="op-patrol-gap" style="height: 10px;"></div>', $bodies)];
    }

    /**
     * Resume Statistik Operasi: target & realisasi of every jadwal this month,
     * computed from the data its PDF shows, the averaged "I. Operasi
     * Pembangkit" row and the 3D charts.
     *
     * @param  array<string, array<string, mixed>>  $payloads  table key => pdfView data
     * @return array{rows: list<array{no: int, deskripsi: string, target: int, realisasi: int, analisa: string}>, summary: array{target: float, realisasi: float, analisa: string}, charts: array{pie: string, bar: string}}
     */
    private function resume(Unit $unit, array $payloads, int $month, int $year, int $engineDaysFilled): array
    {
        $rows = fn (string $key): array => array_values((array) ($payloads[$key]['rows'] ?? []));
        $hasSaved = fn (string $key): bool => (bool) ($payloads[$key]['has_saved'] ?? true);
        $sum = fn (array $list, callable $value): int => (int) array_sum(array_map($value, $list));
        $flm = fn (string $section): array => $hasSaved('flm')
            ? array_filter($rows('flm'), fn (array $r): bool => ($r['section'] ?? '') === $section && ($r['row_type'] ?? '') === 'mark')
            : [];
        $dayPlan = fn (string $key): array => [
            $sum($rows($key), fn (array $r): int => (int) (($r['target'] ?? 0) > 0 ? $r['target'] : ($r['rencana_count'] ?? 0))),
            $sum($rows($key), fn (array $r): int => (int) ($r['realisasi_count'] ?? 0)),
        ];
        $thisMonth = fn (string $key, string $field = 'months'): int => count(array_filter(
            $rows($key),
            fn (array $r): bool => trim((string) (($r[$field] ?? [])[(string) $month] ?? '')) !== '',
        ));
        $weeks = fn (array $keys): int => count(array_filter((array) $keys, fn ($k): bool => str_starts_with((string) $k, $month.'-')));
        $meeting = array_filter($rows('meeting_shift'), fn (array $r): bool => ($r['row_type'] ?? '') !== 'shift');
        $performanceRows = $hasSaved('performance_test') ? $rows('performance_test') : [];
        $performanceTarget = $sum($performanceRows, fn (array $r): int => $weeks([...($r['beban_50'] ?? []), ...($r['beban_75'] ?? []), ...($r['beban_100'] ?? [])]) > 0 ? 1 : 0);
        $performanceRealisasi = $sum($performanceRows, fn (array $r): int => $weeks($r['realisasi'] ?? []) > 0 ? 1 : 0);
        $ikRealisasi = OperasiInstruksiKerja::query()
            ->where('unit_id', $unit->id)
            ->where(function ($q) use ($year, $month): void {
                $q->where(function ($sub) use ($year, $month): void {
                    $sub->whereNotNull('tanggal')
                        ->whereYear('tanggal', $year)
                        ->whereMonth('tanggal', $month);
                })->orWhere(function ($sub) use ($year, $month): void {
                    $sub->whereNull('tanggal')
                        ->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                });
            })
            ->count();
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $activities = [
            'Jadwal First Line Maintenance (Rutin)' => [$sum($flm('rutin'), fn (array $r): int => (int) ($r['target'] ?? 0)), $sum($flm('rutin'), fn (array $r): int => (int) ($r['realisasi'] ?? 0))],
            'Jadwal First Line Maintenance (Non Rutin)' => [$sum($flm('non_rutin'), fn (array $r): int => (int) ($r['target'] ?? 0)), $sum($flm('non_rutin'), fn (array $r): int => (int) ($r['realisasi'] ?? 0))],
            'Jadwal 5S5R' => $dayPlan('program_5s5r'),
            'Jadwal Meeting Shift Operator' => [$sum($meeting, fn (array $r): int => (int) ($r['target'] ?? 0)), $sum($meeting, fn (array $r): int => (int) ($r['realisasi'] ?? 0))],
            'Jadwal Inventarisasi Tools & Material Operasi' => $dayPlan('inventarisasi'),
            'Jadwal Instruksi Kerja (IK)' => [$thisMonth('pembuatan_ik'), $ikRealisasi],
            'Jadwal Pembuatan Data Teknis' => [$thisMonth('data_teknis'), $thisMonth('data_teknis', 'realisasi')],
            'Jadwal Pemeriksaan Instalasi Blackstart' => [
                $sum($rows('blackstart'), fn (array $r): int => $weeks($r['rencana'] ?? [])),
                $sum($rows('blackstart'), fn (array $r): int => $weeks($r['realisasi'] ?? [])),
            ],
            'Jadwal Pelaksanaan Performance Test Mesin Pembangkit' => [$performanceTarget, $performanceRealisasi],
            'Input Data Aplikasi Pembangkit' => [$daysInMonth, $engineDaysFilled],
        ];

        $list = [];
        $percentages = [];
        foreach ($activities as $deskripsi => [$target, $realisasi]) {
            $list[] = [
                'no' => count($list) + 1,
                'deskripsi' => $deskripsi,
                'target' => $target,
                'realisasi' => $realisasi,
                'analisa' => $target > 0 ? round($realisasi / $target * 100).'%' : '-',
            ];
            if ($target > 0) {
                $percentages[] = $realisasi / $target * 100;
            }
        }

        $count = max(1, count($list));
        $short = array_map(fn (array $r): string => str_replace(['Jadwal ', 'Pelaksanaan ', ' Mesin Pembangkit', ' Tools & Material Operasi'], ['', '', '', ''], $r['deskripsi']), $list);

        return [
            'rows' => $list,
            'summary' => [
                'target' => round(array_sum(array_column($list, 'target')) / $count, 1),
                'realisasi' => round(array_sum(array_column($list, 'realisasi')) / $count, 1),
                'analisa' => $percentages !== [] ? round(array_sum($percentages) / count($percentages)).'%' : '-',
            ],
            'charts' => [
                'pie' => $this->charts->pie('Komposisi Realisasi Kegiatan Operasi', $short, array_column($list, 'realisasi')),
                'bar' => $this->charts->bars('Target vs Realisasi Kegiatan Operasi', $short, [
                    'Target' => array_column($list, 'target'),
                    'Realisasi' => array_column($list, 'realisasi'),
                ]),
            ],
        ];
    }

    /**
     * Lembar Pengesahan (Memeriksa Koordinator Operasi · Menyetujui TL
     * Pemeliharaan · Mengesahkan Manager UL) and the in-report block (Project
     * Leader · Office Operasi) of the unit, from the report workflow.
     *
     * @return array{tempat_tanggal: string, blocks: array{pengesahan: string, laporan: string}}
     */
    public function pengesahan(Unit $unit, int $month, int $year): array
    {
        return [
            'tempat_tanggal' => 'Kendari, '.Indonesian::longDate(Carbon::create($year, $month, 1)->addMonth()),
            'blocks' => $this->workflows->signatureBlocks(ReportModule::Operasi, $unit, $month, $year),
        ];
    }
}
