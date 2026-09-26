<?php

namespace App\Services\Operasi;

use App\Enums\ScheduleGroupType;
use App\Models\HarUnsafeCondition;
use App\Models\KondisiAbnormal;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiBlackstartJadwal;
use App\Models\OperasiCommissioningTest;
use App\Models\OperasiCommPeralatan;
use App\Models\OperasiDataTeknis;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiInventarisJadwal;
use App\Models\OperasiMeetingShiftJadwal;
use App\Models\OperasiPembuatanIk;
use App\Models\OperasiPerformanceTestMesin;
use App\Models\Unit;
use App\Services\Operator\AbsensiDocumentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Gathers the unit-wide data behind the "IV. Laporan Operasi" points of the
 * Laporan Operasi Pembangkit document: every jadwal (shift operator, FLM, 5S5R,
 * meeting shift, inventarisasi, IK, data teknis, blackstart, performance &
 * commissioning test) and input (unsafe condition, kondisi abnormal) for the
 * period, plus the resume statistik derived from them. A point whose input is
 * empty comes back as an empty list so the document can mark it with a red line.
 */
class OperasiReportSections
{
    public function __construct(private readonly AbsensiDocumentBuilder $absensi) {}

    /**
     * @param  array<string, mixed>  $engineReport  the MonthlyEngineReport payload (for "Input data aplikasi pembangkit")
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year, array $engineReport = []): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $flm = $this->flm($unit->id, $month, $year, $daysInMonth);
        $program5s5r = $this->dayPlanRows(Operasi5s5rJadwal::query(), $unit->id, $month, $year, fn (Operasi5s5rJadwal $r): string => (string) $r->pelaksana);
        $meetingShift = $this->meetingShift($unit->id, $month, $year);
        $inventarisasi = $this->dayPlanRows(
            OperasiInventarisJadwal::query(), $unit->id, $month, $year,
            fn (OperasiInventarisJadwal $r): string => trim(($r->uraian ?? '').($r->shift ? ' — '.$r->shift : '')),
        );
        $dataTeknis = $this->yearPlanRows(OperasiDataTeknis::query(), $unit->id, $year);
        $blackstart = $this->blackstart($unit->id, $month, $year);
        $engineDaysFilled = collect($engineReport['rows'] ?? [])
            ->filter(fn (array $row): bool => ($row['kwh_produksi_stand_akhir'] ?? null) !== null)
            ->count();

        return [
            'days_in_month' => $daysInMonth,
            'shift_operator' => $this->shiftOperator($unit, $month, $year),
            'flm' => $flm,
            'program_5s5r' => $program5s5r,
            'meeting_shift' => $meetingShift,
            'inventarisasi' => $inventarisasi,
            'pembuatan_ik' => $this->yearPlanRows(OperasiPembuatanIk::query(), $unit->id, $year),
            'data_teknis' => $dataTeknis,
            'blackstart' => $blackstart,
            'performance_test' => $this->performanceTest($unit->id, $month, $year),
            'data_teknis_bulan_ini' => array_values(array_filter($dataTeknis, fn (array $row): bool => ! empty($row['months'][(string) $month]))),
            'commissioning' => $this->commissioning($unit->id, $month, $year),
            'unsafe_conditions' => $this->unsafeConditions($unit->id, $month, $year),
            'kondisi_abnormal' => $this->kondisiAbnormal($unit->id, $month, $year),
            'resume' => $this->resume([
                'Jadwal FLM' => $this->totals(array_filter($flm, fn (array $r): bool => $r['row_type'] === 'mark'), 'target', 'realisasi'),
                'Jadwal Program 5S 5R' => $this->totals($program5s5r, 'target', 'realisasi_count'),
                'Jadwal Meeting Shift' => $this->totals(array_filter($meetingShift, fn (array $r): bool => $r['row_type'] !== 'shift'), 'target', 'realisasi'),
                'Jadwal Inventarisasi Tools & Material Operasi' => $this->totals($inventarisasi, 'target', 'realisasi_count'),
                'Pemeriksaan Instalasi Blackstart' => $this->totals($blackstart, 'rencana_bulan', 'realisasi_bulan'),
                'Input Data Aplikasi Pembangkit (hari terisi)' => $engineDaysFilled > 0 ? [$daysInMonth, $engineDaysFilled] : null,
            ]),
        ];
    }

    /**
     * Operator shift roster (from the Operator module), only employees with at
     * least one scheduled day.
     *
     * @return array{days: list<array<string, mixed>>, employees: list<array<string, mixed>>}
     */
    private function shiftOperator(Unit $unit, int $month, int $year): array
    {
        $roster = $this->absensi->build($unit, $month, $year, ScheduleGroupType::Shift);

        return [
            'days' => $roster['days'],
            'employees' => array_values(array_filter($roster['employees'], fn (array $e): bool => $e['cells'] !== [])),
        ];
    }

    /**
     * @return list<array{section: string, shift: string, label: string, row_type: string, days: array<string, mixed>, target: int, realisasi: int, percentage: int|null}>
     */
    private function flm(int $unitId, int $month, int $year, int $daysInMonth): array
    {
        return OperasiFlmJadwal::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (OperasiFlmJadwal $r) use ($daysInMonth): array {
                $days = (array) ($r->days ?? []);
                $realisasi = 0;
                if ($r->row_type !== 'shift') {
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $value = trim((string) ($days[(string) $d] ?? ''));
                        if ($value !== '') {
                            $realisasi += $r->row_type === 'minutes' ? (int) $value : 1;
                        }
                    }
                }

                return [
                    'section' => $r->section === 'non_rutin' ? 'NON RUTIN' : 'RUTIN',
                    'shift' => (string) ($r->shift ?? ''),
                    'label' => (string) $r->label,
                    'row_type' => (string) $r->row_type,
                    'days' => $days,
                    'target' => (int) $r->target,
                    'realisasi' => $realisasi,
                    'percentage' => $r->row_type === 'shift' ? null : ($r->target > 0 ? (int) round($realisasi / $r->target * 100) : 0),
                ];
            })->all();
    }

    /**
     * @return list<array{label: string, row_type: string, days: array<string, mixed>, target: int, realisasi: int|null, performance: int|null}>
     */
    private function meetingShift(int $unitId, int $month, int $year): array
    {
        return OperasiMeetingShiftJadwal::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (OperasiMeetingShiftJadwal $r): array {
                $days = (array) ($r->days ?? []);
                $realisasi = count(array_filter($days, fn ($v): bool => trim((string) $v) !== ''));

                return [
                    'label' => (string) $r->label,
                    'row_type' => (string) $r->row_type,
                    'days' => $days,
                    'target' => (int) $r->target,
                    'realisasi' => $r->row_type === 'shift' ? null : $realisasi,
                    'performance' => $r->row_type === 'shift' ? null : ($r->target > 0 ? (int) round($realisasi / $r->target * 100) : 0),
                ];
            })->all();
    }

    /**
     * Daily rencana/realisasi matrices (5S5R, inventarisasi): `rencana` and
     * `realisasi` hold the day numbers.
     *
     * @param  Builder<Operasi5s5rJadwal|OperasiInventarisJadwal>  $query
     * @param  callable(Operasi5s5rJadwal|OperasiInventarisJadwal): string  $label
     * @return list<array{pelaksana: string, rencana: list<int>, realisasi: list<int>, rencana_count: int, target: int, realisasi_count: int, performance: int}>
     */
    private function dayPlanRows($query, int $unitId, int $month, int $year, callable $label): array
    {
        return $query->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($r) use ($label): array {
                $rencana = array_map('intval', (array) ($r->rencana ?? []));
                $realisasi = array_map('intval', (array) ($r->realisasi ?? []));

                return [
                    'pelaksana' => $label($r),
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'rencana_count' => count($rencana),
                    'target' => (int) $r->target,
                    'realisasi_count' => count($realisasi),
                    'performance' => $r->target > 0 ? (int) round(count($realisasi) / $r->target * 100) : 0,
                ];
            })->all();
    }

    /**
     * Yearly month plans (pembuatan IK, data teknis): `months` maps month → mark.
     *
     * @param  Builder<OperasiPembuatanIk|OperasiDataTeknis>  $query
     * @return list<array{nama: string, pic: string, months: array<string, mixed>, jumlah: int}>
     */
    private function yearPlanRows($query, int $unitId, int $year): array
    {
        return $query->where('unit_id', $unitId)->where('year', $year)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($r): array {
                $months = array_filter((array) ($r->months ?? []), fn ($v): bool => trim((string) $v) !== '');

                return [
                    'nama' => (string) ($r->nama ?? ''),
                    'pic' => (string) ($r->pic ?? ''),
                    'months' => $months,
                    'jumlah' => count($months),
                ];
            })->all();
    }

    /**
     * Blackstart inspections for the year; `weeks` holds this month's weeks
     * (rencana/realisasi keys are "month-week").
     *
     * @return list<array<string, mixed>>
     */
    private function blackstart(int $unitId, int $month, int $year): array
    {
        return OperasiBlackstartJadwal::query()
            ->where('unit_id', $unitId)->where('year', $year)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (OperasiBlackstartJadwal $r, int $index) use ($month): array {
                $rencana = (array) ($r->rencana ?? []);
                $realisasi = (array) ($r->realisasi ?? []);
                $weeks = [];
                for ($week = 1; $week <= 4; $week++) {
                    $weeks[$week] = [
                        'rencana' => in_array("{$month}-{$week}", $rencana),
                        'realisasi' => in_array("{$month}-{$week}", $realisasi),
                    ];
                }

                return [
                    'no' => $r->no_urut ?: $index + 1,
                    'uraian' => (string) $r->uraian,
                    'pic' => (string) ($r->pic ?? ''),
                    'weeks' => $weeks,
                    'rencana_bulan' => count(array_filter($weeks, fn (array $w): bool => $w['rencana'])),
                    'realisasi_bulan' => count(array_filter($weeks, fn (array $w): bool => $w['realisasi'])),
                    'rencana_tahun' => count($rencana),
                    'realisasi_tahun' => count($realisasi),
                    'keterangan' => (string) ($r->keterangan ?? ''),
                ];
            })->all();
    }

    /**
     * Load (performance) tests at 50/75/100% for this month's weeks.
     *
     * @return list<array<string, mixed>>
     */
    private function performanceTest(int $unitId, int $month, int $year): array
    {
        $records = OperasiPerformanceTestMesin::query()
            ->where('unit_id', $unitId)->where('year', $year)
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($records->isNotEmpty()) {
            return $records->map(function (OperasiPerformanceTestMesin $r, int $index) use ($month): array {
                $weeks = [];
                for ($week = 1; $week <= 4; $week++) {
                    foreach (['50', '75', '100'] as $load) {
                        $weeks[$week][$load] = in_array("{$month}-{$week}", (array) ($r->{'beban_'.$load} ?? []));
                    }
                }

                return [
                    'no' => $r->no_urut ?: $index + 1,
                    'nama' => (string) $r->nama_mesin,
                    'weeks' => $weeks,
                    'jumlah_tahun' => count((array) ($r->beban_50 ?? [])) + count((array) ($r->beban_75 ?? [])) + count((array) ($r->beban_100 ?? [])),
                    'keterangan' => (string) ($r->keterangan ?? ''),
                ];
            })->all();
        }

        return OperasiCommPeralatan::query()
            ->where('unit_id', $unitId)->where('year', $year)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (OperasiCommPeralatan $r, int $index) use ($month): array {
                $weeks = [];
                for ($week = 1; $week <= 4; $week++) {
                    foreach (['50', '75', '100'] as $load) {
                        $weeks[$week][$load] = in_array("{$month}-{$week}", (array) ($r->{'beban_'.$load} ?? []));
                    }
                }

                return [
                    'no' => $r->no_urut ?: $index + 1,
                    'nama' => (string) $r->nama_peralatan,
                    'weeks' => $weeks,
                    'jumlah_tahun' => count((array) ($r->beban_50 ?? [])) + count((array) ($r->beban_75 ?? [])) + count((array) ($r->beban_100 ?? [])),
                    'keterangan' => (string) ($r->keterangan ?? ''),
                ];
            })->all();
    }

    /**
     * @return list<array{section: string, items: list<array<string, mixed>>}>
     */
    private function commissioning(int $unitId, int $month, int $year): array
    {
        return OperasiCommissioningTest::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->groupBy('section')
            ->map(fn ($items, $section): array => [
                'section' => (string) $section,
                'items' => $items->map(fn (OperasiCommissioningTest $r): array => [
                    'no' => $r->no_urut,
                    'kegiatan' => (string) $r->kegiatan,
                    'status' => $r->status,
                    'pic' => (string) ($r->pic ?? ''),
                    'paraf' => (string) ($r->paraf ?? ''),
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unsafeConditions(int $unitId, int $month, int $year): array
    {
        return HarUnsafeCondition::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->get()
            ->map(fn (HarUnsafeCondition $u): array => [
                'kategori' => $u->kategori,
                'temuan' => $u->temuan,
                'lokasi' => $u->lokasi,
                'tindak_lanjut' => $u->tindak_lanjut,
                'rekomendasi' => $u->rekomendasi,
            ])->all();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array{abnormal: int, durasi_abnormal: float, gangguan: int, durasi_gangguan: float}}
     */
    private function kondisiAbnormal(int $unitId, int $month, int $year): array
    {
        $records = KondisiAbnormal::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('no_urut')->get()
            ->filter(fn (KondisiAbnormal $k): bool => trim((string) $k->uraian_kondisi) !== '' || $k->is_abnormal || $k->is_gangguan);

        return [
            'rows' => $records->values()->map(fn (KondisiAbnormal $k, int $index): array => [
                'no' => $index + 1,
                'uraian' => (string) $k->uraian_kondisi,
                'tanggal' => $k->tanggal?->format('d/m/Y'),
                'is_abnormal' => (int) $k->is_abnormal === 1,
                'durasi_abnormal' => (float) $k->durasi_abnormal,
                'is_gangguan' => (int) $k->is_gangguan === 1,
                'durasi_gangguan' => (float) $k->durasi_gangguan,
            ])->all(),
            'totals' => [
                'abnormal' => $records->filter(fn (KondisiAbnormal $k): bool => (int) $k->is_abnormal === 1)->count(),
                'durasi_abnormal' => (float) $records->sum('durasi_abnormal'),
                'gangguan' => $records->filter(fn (KondisiAbnormal $k): bool => (int) $k->is_gangguan === 1)->count(),
                'durasi_gangguan' => (float) $records->sum('durasi_gangguan'),
            ],
        ];
    }

    /**
     * Summed [target, realisasi] of the rows, or null when there are none.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: int, 1: int}|null
     */
    private function totals(array $rows, string $targetKey, string $realisasiKey): ?array
    {
        if ($rows === []) {
            return null;
        }

        return [
            (int) array_sum(array_column($rows, $targetKey)),
            (int) array_sum(array_column($rows, $realisasiKey)),
        ];
    }

    /**
     * Resume statistik rows; only activities that have data this period.
     *
     * @param  array<string, array{0: int, 1: int}|null>  $activities
     * @return list<array{no: int, deskripsi: string, target: int, realisasi: int, analisa: string}>
     */
    private function resume(array $activities): array
    {
        $rows = [];
        foreach (array_filter($activities) as $deskripsi => [$target, $realisasi]) {
            $rows[] = [
                'no' => count($rows) + 1,
                'deskripsi' => $deskripsi,
                'target' => $target,
                'realisasi' => $realisasi,
                'analisa' => $target > 0 ? round($realisasi / $target * 100).'%' : '-',
            ];
        }

        return $rows;
    }
}
