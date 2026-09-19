<?php

namespace App\Services\K3;

use App\Enums\CertificateStatus;
use App\Http\Controllers\K3\AirLimbahController;
use App\Http\Controllers\K3\ApdInventoryController;
use App\Http\Controllers\K3\EmergencyFacilityCheckController;
use App\Models\AccidentReport;
use App\Models\EmergencyEquipment;
use App\Models\EmergencyFacilityCheck;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\K3AirLimbah;
use App\Models\K3ApdInventory;
use App\Models\K3Attachment;
use App\Models\K3CctvList;
use App\Models\K3EmergencyFacilityCheck;
use App\Models\K3FireAlarmInspection;
use App\Models\K3HydrantInspection;
use App\Models\K3RambuInspection;
use App\Models\PatrolLocation;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use App\Support\Indonesian;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One tabular view of every K3 input page (resources/js/pages/k3/input), shared
 * by the input's PDF & Excel exports and by the matching points of the Laporan
 * K3 Lingkungan Pembangkit — so the export, the report and the input page always
 * show the same data. The rows are exactly what the input page shows: saved
 * rows, or — before anything is saved — the master list / default template the
 * page pre-fills. `has_data` is true when the table has at least one row;
 * `saved` tells whether any of it was actually saved for the period.
 *
 * A table: `title`, `meta` (label/value lines under the title), `columns`
 * (label, Excel width, align l|c|r), `rows` (kind data|group|total with `cells`;
 * a cell is a string or `['image' => uri, 'text' => caption]`), `dense` for the
 * day matrices printed in a small font.
 *
 * @phpstan-type Column array{label: string, width?: int, align?: string}
 * @phpstan-type Row array{kind: string, cells: list<string|array{image: string, text: string}>}
 * @phpstan-type Table array{key: string, title: string, document_number: string, unit: string, period: string, meta: list<array{0: string, 1: string}>, columns: list<Column>, rows: list<Row>, has_data: bool, saved: bool, dense: bool}
 */
class K3InputTables
{
    /**
     * Export key => page title, in the order of the K3 input hub.
     */
    public const INPUTS = [
        'time-frame' => 'Time Frame K3L',
        'accidents' => 'Laporan Kecelakaan Kerja',
        'inspections' => 'Inspeksi Checklist K3',
        'apar-checks' => 'Patrol Check APAR/APAB',
        'emergency' => 'Kesiapan Fasilitas Darurat',
        'patrols' => 'Patroli Keamanan',
        'hydrant' => 'Inspeksi Hydrant',
        'cctv' => 'Daftar CCTV',
        'fire-alarm' => 'Inspeksi Fire Alarm',
        'rambu' => 'Inspeksi Rambu-Rambu K3 & B3',
        'emergency-facility' => 'Pemeriksaan Emergency Facility',
        'apd-inventory' => 'Daftar Inventaris APD',
        'air-limbah' => 'Logbook Pemantauan dan Pemanfaatan Air Limbah',
        'certificates' => 'Daftar Monitoring Sertifikasi Peralatan',
        'attachments' => 'Lampiran Dokumentasi K3',
    ];

    /** config('k3.document.numbers') key per export. */
    private const DOCUMENT_NUMBERS = [
        'time-frame' => 'time_frame',
        'accidents' => 'accidents',
        'inspections' => 'inspections',
        'apar-checks' => 'apar',
        'emergency-facility' => 'emergency_tools',
        'apd-inventory' => 'apd',
        'certificates' => 'certificates',
    ];

    /** @var list<array{category: string, jenis: string, kapasitas: string}> */
    public const DEFAULT_CERTIFICATES = [
        ['category' => 'Crane', 'jenis' => 'Overhead Traveling Crane', 'kapasitas' => '5 Ton'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit Containerized', 'kapasitas' => '25.000 Liter'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 1 HSD', 'kapasitas' => '100.000 Liter'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 2 HSD', 'kapasitas' => '1.500.000 Liter'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 1 MFO', 'kapasitas' => '1.500.000 Liter'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 2 MFO', 'kapasitas' => '1.500.000 Liter'],
        ['category' => 'Tangki Timbun', 'jenis' => 'Unit 3 MFO', 'kapasitas' => '1.500.000 Liter'],
        ['category' => 'Instalasi Penyalur Petir', 'jenis' => 'Power House', 'kapasitas' => '-'],
        ['category' => 'Instalasi Penyalur Petir', 'jenis' => 'Tangki HSD 02', 'kapasitas' => '-'],
    ];

    public function __construct(private readonly K3MonitoringService $monitoring) {}

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::INPUTS);
    }

    /**
     * The table(s) of one input for the period — inspections give one table
     * per checklist form (only `$options['form_code']` when given).
     *
     * @param  array{form_code?: string|null, week?: int|null}  $options
     * @return list<Table>
     */
    public function tables(string $key, Unit $unit, int $month, int $year, array $options = []): array
    {
        return match ($key) {
            'time-frame' => [$this->timeFrame($unit, $month, $year)],
            'accidents' => [$this->accidents($unit, $month, $year)],
            'inspections' => $this->inspections($unit, $month, $year, $options['form_code'] ?? null),
            'apar-checks' => [$this->aparChecks($unit, $month, $year)],
            'emergency' => [$this->emergency($unit, $month, $year, $options['week'] ?? null)],
            'patrols' => [$this->patrols($unit, $month, $year)],
            'hydrant' => [$this->hydrant($unit, $month, $year)],
            'cctv' => [$this->cctv($unit, $month, $year)],
            'fire-alarm' => [$this->fireAlarm($unit, $month, $year)],
            'rambu' => [$this->rambu($unit, $month, $year)],
            'emergency-facility' => [$this->emergencyFacility($unit, $month, $year)],
            'apd-inventory' => [$this->apdInventory($unit, $month, $year)],
            'air-limbah' => [$this->airLimbah($unit, $month, $year)],
            'certificates' => [$this->certificates($unit)],
            'attachments' => [$this->attachments($unit, $month, $year, embedImages: false)],
            default => throw new \InvalidArgumentException("Unknown K3 input export [{$key}]."),
        };
    }

    /**
     * Attachments with the photos inlined as data URIs (dompdf cannot fetch URLs).
     *
     * @return Table
     */
    public function attachmentsWithImages(Unit $unit, int $month, int $year): array
    {
        return $this->attachments($unit, $month, $year, embedImages: true);
    }

    /**
     * Every table the Laporan K3 uses, keyed by export key (inspections keep a list).
     *
     * @return array<string, Table|list<Table>>
     */
    public function forReport(Unit $unit, int $month, int $year): array
    {
        $tables = [];
        foreach (array_keys(self::INPUTS) as $key) {
            if ($key === 'attachments') {
                continue;
            }
            $list = $this->tables($key, $unit, $month, $year);
            $tables[$key] = $key === 'inspections' ? $list : $list[0];
        }

        return $tables;
    }

    /**
     * @return Table
     */
    private function timeFrame(Unit $unit, int $month, int $year): array
    {
        $days = $this->daysInMonth($month, $year);
        $plans = K3ActivityPlan::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()->keyBy('k3_activity_type_id');
        $types = K3ActivityType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get();

        $columns = [$this->col('No', 4, 'c'), $this->col('Uraian Kegiatan K3 & Keamanan', 34), $this->col('PIC', 10, 'c'), $this->col('R/Rl', 5, 'c')];
        for ($d = 1; $d <= $days; $d++) {
            $columns[] = $this->col((string) $d, 3, 'c');
        }
        array_push($columns, $this->col('Jumlah', 7, 'c'), $this->col('Capaian', 8, 'c'), $this->col('Keterangan', 22));

        $rows = [];
        $hasData = false;
        foreach ($types->values() as $index => $type) {
            $plan = $plans->get($type->id);
            $rencana = $this->markedDays($plan?->plan_days);
            $realisasi = $this->markedDays($plan?->real_days);
            $hasData = $hasData || $rencana !== [] || $realisasi !== [];
            $capaian = $rencana !== [] ? round(count($realisasi) / count($rencana) * 100).'%' : ($realisasi !== [] ? '100%' : '-');

            $plannedRow = [(string) ($index + 1), (string) $type->name, (string) ($plan?->pic ?? $type->default_pic ?? ''), 'R'];
            $realRow = ['', '', '', 'Rl'];
            for ($d = 1; $d <= $days; $d++) {
                $plannedRow[] = in_array($d, $rencana, true) ? 'R' : '';
                $realRow[] = in_array($d, $realisasi, true) ? 'Rl' : '';
            }
            array_push($plannedRow, (string) count($rencana), $capaian, (string) ($plan?->keterangan ?? ''));
            array_push($realRow, (string) count($realisasi), '', '');

            $rows[] = $this->row($plannedRow);
            $rows[] = $this->row($realRow);
        }

        return $this->table('time-frame', $unit, $month, $year, $columns, $rows, $hasData, dense: true, meta: [['Keterangan', 'R = Rencana · Rl = Realisasi']]);
    }

    /**
     * @return Table
     */
    private function accidents(Unit $unit, int $month, int $year): array
    {
        $reports = AccidentReport::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('incident_date')->orderBy('id')->get();

        $rows = $reports->values()->map(fn (AccidentReport $a, int $i): array => $this->row([
            (string) ($i + 1),
            $a->category->label(),
            $this->date($a->incident_date),
            (string) ($a->fungsi ?? ''),
            (string) ($a->lokasi ?? ''),
            (string) ($a->luka_ringan ?? 0),
            (string) ($a->luka_berat ?? 0),
            (string) ($a->meninggal ?? 0),
            (string) ($a->kerugian_material ?? ''),
            $a->is_nihil ? 'Nihil' : '',
            (string) ($a->keterangan ?? ''),
        ]))->all();

        return $this->table('accidents', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Kategori', 20), $this->col('Tanggal', 12, 'c'), $this->col('Fungsi', 16),
            $this->col('Lokasi', 18), $this->col('Luka Ringan', 9, 'c'), $this->col('Luka Berat', 9, 'c'), $this->col('Meninggal', 9, 'c'),
            $this->col('Kerugian Material', 18), $this->col('Nihil', 7, 'c'), $this->col('Keterangan', 24),
        ], $rows, $rows !== []);
    }

    /**
     * @return list<Table>
     */
    private function inspections(Unit $unit, int $month, int $year, ?string $formCode): array
    {
        $formCodes = $formCode !== null && $formCode !== ''
            ? [$formCode]
            : InspectionChecklist::query()->where('unit_id', $unit->id)->where('is_active', true)
                ->orderBy('form_code')->distinct()->pluck('form_code')->all();

        $tables = [];
        foreach ($formCodes as $code) {
            $inspection = Inspection::query()->with('results')
                ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->where('form_code', $code)
                ->first();
            $stored = $inspection?->results->keyBy('item_ref') ?? collect();

            $items = InspectionChecklist::query()
                ->where('unit_id', $unit->id)->where('form_code', $code)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('id')->pluck('item_text');

            $rows = $items->values()->map(function (string $item, int $i) use ($stored): array {
                $result = $stored->get($item);

                return $this->row([
                    (string) ($i + 1), $item, (string) ($result?->kondisi ?? ''), (string) ($result?->tindak_lanjut ?? ''),
                    (string) ($result?->nilai ?? ''), (string) ($result?->catatan ?? ''),
                ]);
            })->all();

            $table = $this->table('inspections', $unit, $month, $year, [
                $this->col('No', 4, 'c'), $this->col('Item Pemeriksaan', 60), $this->col('Kondisi', 12, 'c'),
                $this->col('Tindak Lanjut', 24), $this->col('Nilai', 8, 'c'), $this->col('Catatan', 24),
            ], $rows, $inspection !== null && $inspection->results->isNotEmpty(), meta: [
                ['Form', strtoupper(str_replace('-', ' ', (string) $code))],
                ['Tanggal Inspeksi', $this->date($inspection?->inspection_date)],
                ['Ketua Tim', (string) ($inspection?->ketua_tim ?? '')],
                ['Tim Pemeriksa', (string) ($inspection?->inspector_team ?? '')],
                ['Keterangan', (string) ($inspection?->keterangan ?? '')],
            ]);
            $table['title'] .= ' — '.strtoupper(str_replace('-', ' ', (string) $code));
            $tables[] = $table;
        }

        if ($tables === []) {
            $tables[] = $this->table('inspections', $unit, $month, $year, [$this->col('Item Pemeriksaan', 60)], [], false);
        }

        return $tables;
    }

    /**
     * @return Table
     */
    private function aparChecks(Unit $unit, int $month, int $year): array
    {
        $checks = FireExtinguisherCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->get()->keyBy('fire_extinguisher_id');

        $rows = FireExtinguisher::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('location')->get()
            ->values()->map(function (FireExtinguisher $ext, int $i) use ($checks): array {
                $check = $checks->get($ext->id);
                $status = $this->monitoring->statusFor($check?->exp_date)['status'];

                return $this->row([
                    (string) ($i + 1), (string) ($ext->rfid ?? ''), (string) ($ext->location ?? ''), (string) ($ext->merk ?? ''),
                    (string) ($ext->jenis ?? ''), $ext->berat_kg !== null ? (string) (float) $ext->berat_kg : '',
                    $this->date($check?->tgl_periksa), (string) ($check?->kondisi_tabung ?? ''), (string) ($check?->kondisi_nozzle ?? ''),
                    (string) ($check?->indikator_tekanan ?? ''), (string) ($check?->kondisi_pin_segel ?? ''),
                    $this->date($check?->exp_date), $check === null ? '' : CertificateStatus::from($status)->label(), (string) ($check?->keterangan ?? ''),
                ]);
            })->all();

        return $this->table('apar-checks', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('No. Tabung / RFID', 12, 'c'), $this->col('Lokasi', 22), $this->col('Merk', 12, 'c'),
            $this->col('Jenis / Media', 12, 'c'), $this->col('Berat (kg)', 8, 'c'), $this->col('Tgl Periksa', 11, 'c'),
            $this->col('Kondisi Tabung', 10, 'c'), $this->col('Kondisi Nozzle', 10, 'c'), $this->col('Indikator Tekanan', 10, 'c'),
            $this->col('Pin / Segel', 10, 'c'), $this->col('Exp Date', 11, 'c'), $this->col('Status', 10, 'c'), $this->col('Keterangan', 20),
        ], $rows, $checks->isNotEmpty());
    }

    /**
     * @return Table
     */
    private function emergency(Unit $unit, int $month, int $year, ?int $week): array
    {
        $checks = EmergencyFacilityCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->where(fn ($q) => $week === null ? $q->whereNull('week') : $q->where('week', $week))
            ->get()->keyBy('emergency_equipment_id');

        $rows = EmergencyEquipment::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->values()->map(function (EmergencyEquipment $equipment, int $i) use ($checks): array {
                $check = $checks->get($equipment->id);
                $total = (int) ($check?->jml_total ?? 0);

                return $this->row([
                    (string) ($i + 1), (string) ($equipment->group_name ?? ''), (string) $equipment->name,
                    (string) $total, (string) (int) ($check?->jml_ready ?? 0), (string) (int) ($check?->jml_not_ready ?? 0),
                    $total > 0 ? round((int) $check->jml_ready / $total * 100, 1).'%' : '-',
                    (string) ($check?->kendala ?? ''), (string) ($check?->tindak_lanjut ?? ''),
                ]);
            })->all();

        return $this->table('emergency', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Kelompok', 18), $this->col('Peralatan', 30), $this->col('Total', 8, 'c'),
            $this->col('Ready', 8, 'c'), $this->col('Not Ready', 9, 'c'), $this->col('% Kesiapan', 10, 'c'),
            $this->col('Kendala', 24), $this->col('Tindak Lanjut', 24),
        ], $rows, $checks->isNotEmpty(), meta: [['Periode Pemeriksaan', $week === null ? 'Bulanan' : 'Minggu '.$week]]);
    }

    /**
     * @return Table
     */
    private function patrols(Unit $unit, int $month, int $year): array
    {
        $days = $this->daysInMonth($month, $year);
        $patrols = SecurityPatrol::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('patrol_date', [Carbon::create($year, $month, 1)->toDateString(), Carbon::create($year, $month, $days)->toDateString()])
            ->get()->groupBy('patrol_location_id');

        $columns = [$this->col('No', 4, 'c'), $this->col('Titik / Pos Patroli', 28)];
        for ($d = 1; $d <= $days; $d++) {
            $columns[] = $this->col((string) $d, 3, 'c');
        }
        $columns[] = $this->col('Total Scan', 8, 'c');

        $rows = [];
        $dayTotals = array_fill(1, $days, 0);
        foreach (PatrolLocation::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get()->values() as $i => $location) {
            $byDay = ($patrols->get($location->id) ?? collect())->keyBy(fn (SecurityPatrol $p): int => (int) $p->patrol_date->day);
            $cells = [(string) ($i + 1), trim($location->code.' — '.$location->name, ' —')];
            $total = 0;
            for ($d = 1; $d <= $days; $d++) {
                $scan = $byDay->get($d)?->total_scan;
                $cells[] = $scan === null ? '' : (string) $scan;
                $total += (int) $scan;
                $dayTotals[$d] += (int) $scan;
            }
            $cells[] = (string) $total;
            $rows[] = $this->row($cells);
        }

        if ($rows !== []) {
            $rows[] = $this->row(['', 'TOTAL', ...array_map(fn (int $n): string => $n > 0 ? (string) $n : '', array_values($dayTotals)), (string) array_sum($dayTotals)], 'total');
        }

        return $this->table('patrols', $unit, $month, $year, $columns, $rows, $patrols->isNotEmpty(), dense: true);
    }

    /**
     * @return Table
     */
    private function hydrant(Unit $unit, int $month, int $year): array
    {
        $rows = K3HydrantInspection::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->values()->map(fn (K3HydrantInspection $h, int $i): array => $this->row([
                (string) ($i + 1), (string) $h->lokasi, (string) ($h->jenis ?? ''), $this->date($h->tanggal),
                (string) ($h->hose ?? ''), (string) ($h->nozzle ?? ''), (string) ($h->box ?? ''), (string) ($h->tekanan ?? ''),
                (string) ($h->keterangan ?? ''),
            ]))->all();

        return $this->table('hydrant', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Lokasi', 26), $this->col('Jenis', 14, 'c'), $this->col('Tanggal', 12, 'c'),
            $this->col('Hose', 10, 'c'), $this->col('Nozzle', 10, 'c'), $this->col('Box', 10, 'c'), $this->col('Tekanan (Bar)', 10, 'c'),
            $this->col('Keterangan', 28),
        ], $rows, $rows !== []);
    }

    /**
     * @return Table
     */
    private function cctv(Unit $unit, int $month, int $year): array
    {
        $rows = K3CctvList::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->values()->map(fn (K3CctvList $c, int $i): array => $this->row([
                (string) ($i + 1), $this->date($c->tanggal), (string) ($c->no_cctv ?? ''), (string) ($c->titik_lokasi ?? ''),
                strtoupper((string) ($c->status ?? '')), (string) ($c->keterangan ?? ''),
            ]))->all();

        return $this->table('cctv', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Tanggal', 12, 'c'), $this->col('No CCTV', 12, 'c'), $this->col('Titik Lokasi CCTV', 36),
            $this->col('Status', 12, 'c'), $this->col('Keterangan', 36),
        ], $rows, $rows !== []);
    }

    /**
     * @return Table
     */
    private function fireAlarm(Unit $unit, int $month, int $year): array
    {
        $rows = K3FireAlarmInspection::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->values()->map(fn (K3FireAlarmInspection $f, int $i): array => $this->row([
                (string) ($i + 1), (string) $f->lokasi, $this->date($f->tanggal_periksa), (string) ($f->kondisi ?? ''),
                (string) ($f->panel_indikator ?? ''), (string) ($f->keterangan ?? ''),
            ]))->all();

        return $this->table('fire-alarm', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Lokasi', 34), $this->col('Tanggal Periksa', 14, 'c'), $this->col('Kondisi', 14, 'c'),
            $this->col('Panel Indikator', 16, 'c'), $this->col('Keterangan', 36),
        ], $rows, $rows !== []);
    }

    /**
     * @return Table
     */
    private function rambu(Unit $unit, int $month, int $year): array
    {
        $rows = K3RambuInspection::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->values()->map(fn (K3RambuInspection $r, int $i): array => $this->row([
                (string) ($i + 1), (string) $r->rambu, (string) ($r->lokasi ?? ''), (string) ($r->kondisi ?? ''), (string) ($r->keterangan ?? ''),
            ]))->all();

        return $this->table('rambu', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Rambu-Rambu K3', 36), $this->col('Lokasi', 30), $this->col('Kondisi', 14, 'c'),
            $this->col('Keterangan', 36),
        ], $rows, $rows !== []);
    }

    /**
     * @return Table
     */
    private function emergencyFacility(Unit $unit, int $month, int $year): array
    {
        $records = K3EmergencyFacilityCheck::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        $saved = $records->isNotEmpty();
        if (! $saved) {
            $records = collect(EmergencyFacilityCheckController::DEFAULTS)
                ->flatMap(fn (array $items, string $grup): array => array_map(fn (string $nama): object => (object) [
                    'grup' => $grup, 'nama_peralatan' => $nama, 'lokasi' => '', 'jml_total' => 0, 'jml_ready' => 0,
                    'jml_not_ready' => 0, 'kendala' => '', 'tindak_lanjut' => '',
                ], $items));
        }

        $rows = [];
        $number = 0;
        foreach ($records->groupBy('grup') as $group => $items) {
            $rows[] = $this->row([(string) $group], 'group');
            foreach ($items as $r) {
                $total = (int) $r->jml_total;
                $rows[] = $this->row([
                    (string) ++$number, (string) $r->nama_peralatan, (string) ($r->lokasi ?? ''), (string) $total,
                    (string) (int) $r->jml_ready, (string) (int) $r->jml_not_ready,
                    $total > 0 ? round((int) $r->jml_ready / $total * 100).'%' : '-',
                    (string) ($r->kendala ?? ''), (string) ($r->tindak_lanjut ?? ''),
                ]);
            }
        }

        return $this->table('emergency-facility', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Nama Peralatan', 30), $this->col('Lokasi', 20), $this->col('Total', 8, 'c'),
            $this->col('Ready', 8, 'c'), $this->col('Not Ready', 9, 'c'), $this->col('% Kesiapan', 10, 'c'),
            $this->col('Kendala', 24), $this->col('Tindak Lanjut', 24),
        ], $rows, $saved);
    }

    /**
     * @return Table
     */
    private function apdInventory(Unit $unit, int $month, int $year): array
    {
        $records = K3ApdInventory::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        $saved = $records->isNotEmpty();
        if (! $saved) {
            $records = collect(ApdInventoryController::DEFAULTS)->map(fn (array $d): object => (object) ($d + [
                'jumlah' => 0, 'lokasi' => '', 'keterangan' => '',
            ]));
        }

        $rows = [];
        $number = 0;
        foreach ($records->groupBy('grup') as $group => $items) {
            $rows[] = $this->row([(string) $group], 'group');
            foreach ($items as $r) {
                $rows[] = $this->row([
                    (string) ++$number, (string) ($r->subkategori ?? ''), (string) $r->nama, (string) (int) $r->jumlah,
                    (string) ($r->satuan ?? ''), (string) ($r->lokasi ?? ''), (string) ($r->keterangan ?? ''),
                ]);
            }
        }

        return $this->table('apd-inventory', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Subkategori', 18), $this->col('Nama APD', 30), $this->col('Jumlah', 8, 'c'),
            $this->col('Satuan', 8, 'c'), $this->col('Lokasi Penyimpanan', 24), $this->col('Keterangan', 28),
        ], $rows, $saved);
    }

    /**
     * @return Table
     */
    private function airLimbah(Unit $unit, int $month, int $year): array
    {
        $records = K3AirLimbah::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        $saved = $records->isNotEmpty();
        if (! $saved) {
            $records = collect(AirLimbahController::DEFAULT_AREAS)->map(fn (string $area, int $i): object => (object) [
                'no_urut' => $i + 1, 'area_penyiraman' => $area, 'tanggal' => null, 'waktu_penyiraman' => '16.00 WITA',
                'metode_pemanfaatan' => 'Penyiraman Tanaman', 'debit_awal' => 0, 'debit_akhir' => 1, 'debit_jumlah' => 1,
                'frekuensi' => '1', 'pic' => 'K3L', 'keterangan' => '',
            ]);
        }

        $number = fn ($v): string => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        $rows = $records->values()->map(fn (object $r, int $i): array => $this->row([
            (string) ($r->no_urut ?: $i + 1), (string) $r->area_penyiraman, $this->date($r->tanggal), (string) ($r->waktu_penyiraman ?? ''),
            (string) ($r->metode_pemanfaatan ?? ''), $number($r->debit_awal), $number($r->debit_akhir), $number($r->debit_jumlah),
            (string) ($r->frekuensi ?? ''), (string) ($r->pic ?? ''), (string) ($r->keterangan ?? ''),
        ]))->all();

        if ($rows !== []) {
            $rows[] = $this->row(['', 'TOTAL DEBIT PEMANFAATAN', '', '', '', '', '', $number($records->sum('debit_jumlah')), '', '', ''], 'total');
        }

        return $this->table('air-limbah', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Area Penyiraman', 26), $this->col('Tanggal', 12, 'c'), $this->col('Waktu', 12, 'c'),
            $this->col('Metode Pemanfaatan', 20), $this->col('Debit Awal (m³)', 10, 'c'), $this->col('Debit Akhir (m³)', 10, 'c'),
            $this->col('Jumlah (m³)', 10, 'c'), $this->col('Frekuensi (kali/bln)', 10, 'c'), $this->col('PIC', 10, 'c'), $this->col('Keterangan', 20),
        ], $rows, $saved);
    }

    /**
     * Certificates are a unit register, not per period.
     *
     * @return Table
     */
    private function certificates(Unit $unit): array
    {
        $records = EquipmentCertificate::query()
            ->where('unit_id', $unit->id)->with('category:id,code,name')->orderBy('jenis')->get();

        $saved = $records->isNotEmpty();
        if (! $saved) {
            $records = collect(self::DEFAULT_CERTIFICATES)->map(fn (array $c): object => (object) [
                'category' => (object) ['name' => $c['category']],
                'jenis' => $c['jenis'],
                'kapasitas' => $c['kapasitas'],
                'lokasi' => $unit->name,
                'merk_manufacture' => '',
                'no_seri' => '',
                'regulasi' => 'Permenaker / Disnaker',
                'ijin_awal_nomor' => '',
                'ijin_awal_tanggal' => null,
                'uji_terakhir_nomor' => '',
                'uji_terakhir_tanggal' => null,
                'uji_ulang_tanggal' => null,
                'batasan_uji' => '',
                'masa_berlaku_tahun' => '',
                'keterangan' => 'Belum Ada Data',
            ]);
        }

        $rows = $records->values()->map(function (object $c, int $i): array {
            $ujiUlang = $c instanceof EquipmentCertificate ? $c->uji_ulang_tanggal : null;
            $status = $ujiUlang ? CertificateStatus::from($this->monitoring->statusFor($ujiUlang)['status'])->label() : 'Belum Ada Data';

            return $this->row([
                (string) ($i + 1), (string) ($c->category?->name ?? $c->category?->code ?? ''), (string) $c->jenis,
                (string) ($c->kapasitas ?? ''), (string) ($c->lokasi ?? ''), (string) ($c->merk_manufacture ?? ''), (string) ($c->no_seri ?? ''),
                (string) ($c->regulasi ?? ''), trim(($c->ijin_awal_nomor ?? '').' '.$this->date($c->ijin_awal_tanggal ?? null)),
                trim(($c->uji_terakhir_nomor ?? '').' '.$this->date($c->uji_terakhir_tanggal ?? null)), $this->date($c->uji_ulang_tanggal ?? null),
                (string) ($c->batasan_uji ?? ''), (string) ($c->masa_berlaku_tahun ?? ''), $status, (string) ($c->keterangan ?? ''),
            ]);
        })->all();

        $now = Carbon::now();

        return $this->table('certificates', $unit, (int) $now->month, (int) $now->year, [
            $this->col('No', 4, 'c'), $this->col('Kategori', 14), $this->col('Jenis Peralatan', 22), $this->col('Kapasitas', 10, 'c'),
            $this->col('Lokasi', 14), $this->col('Merk / Pabrikan', 14), $this->col('No. Seri', 12, 'c'), $this->col('Regulasi', 12, 'c'),
            $this->col('Ijin Awal (No/Tgl)', 16, 'c'), $this->col('Uji Terakhir (No/Tgl)', 16, 'c'), $this->col('Uji Ulang', 11, 'c'),
            $this->col('Batasan Uji', 10, 'c'), $this->col('Masa (Thn)', 8, 'c'), $this->col('Status', 10, 'c'), $this->col('Keterangan', 18),
        ], $rows, $saved, meta: [['Per Tanggal', $this->date($now)]]);
    }

    /**
     * @return Table
     */
    private function attachments(Unit $unit, int $month, int $year, bool $embedImages): array
    {
        $disk = Storage::disk('public');
        $rows = K3Attachment::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('id')->get()
            ->values()->map(function (K3Attachment $a, int $i) use ($disk, $embedImages): array {
                $image = $disk->url($a->file_path);
                if ($embedImages) {
                    $image = $disk->exists($a->file_path) && str_starts_with((string) $disk->mimeType($a->file_path), 'image/')
                        ? 'data:'.$disk->mimeType($a->file_path).';base64,'.base64_encode((string) $disk->get($a->file_path))
                        : '';
                }

                return $this->row([
                    (string) ($i + 1), (string) $a->title, (string) ($a->category ?? ''),
                    $image === '' ? '' : ['image' => $image, 'text' => (string) $a->title],
                ]);
            })->all();

        return $this->table('attachments', $unit, $month, $year, [
            $this->col('No', 4, 'c'), $this->col('Judul', 36), $this->col('Kategori', 20), $this->col('Foto / Dokumen', 50, 'c'),
        ], $rows, $rows !== []);
    }

    /**
     * @param  list<Column>  $columns
     * @param  list<Row>  $rows
     * @param  list<array{0: string, 1: string}>  $meta
     * @return Table
     */
    private function table(string $key, Unit $unit, int $month, int $year, array $columns, array $rows, bool $saved, bool $dense = false, array $meta = []): array
    {
        $numberKey = self::DOCUMENT_NUMBERS[$key] ?? null;

        return [
            'key' => $key,
            'title' => strtoupper(self::INPUTS[$key]),
            'document_number' => $numberKey !== null ? (string) (config('k3.document.numbers')[$numberKey] ?? '') : '',
            'unit' => $unit->name,
            'period' => $key === 'certificates' ? '' : Indonesian::monthName($month).' '.$year,
            'meta' => array_values(array_filter($meta, fn (array $line): bool => $line[1] !== '')),
            'columns' => $columns,
            'rows' => $rows,
            'has_data' => array_filter($rows, fn (array $row): bool => $row['kind'] === 'data') !== [],
            'saved' => $saved,
            'dense' => $dense,
        ];
    }

    /**
     * @return Column
     */
    private function col(string $label, int $width, string $align = 'l'): array
    {
        return ['label' => $label, 'width' => $width, 'align' => $align];
    }

    /**
     * @param  list<string|array{image: string, text: string}>  $cells
     * @return Row
     */
    private function row(array $cells, string $kind = 'data'): array
    {
        return ['kind' => $kind, 'cells' => $cells];
    }

    /**
     * Days marked in a {day: mark} map (see TimeFrameController::daysToMap).
     *
     * @return list<int>
     */
    private function markedDays(?array $map): array
    {
        return array_values(array_map('intval', array_keys(array_filter($map ?? [], fn ($mark): bool => trim((string) $mark) !== ''))));
    }

    private function date(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ($value instanceof CarbonInterface ? $value : Carbon::parse((string) $value))->format('d/m/Y');
    }

    private function daysInMonth(int $month, int $year): int
    {
        return (int) Carbon::create($year, $month, 1)->daysInMonth;
    }
}
