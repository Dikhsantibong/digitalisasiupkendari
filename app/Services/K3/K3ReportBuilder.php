<?php

namespace App\Services\K3;

use App\Enums\EmployeePosition;
use App\Http\Controllers\K3\InstruksiKerjaController;
use App\Http\Controllers\K3\KegiatanRutinController;
use App\Http\Controllers\K3\PatrolCheckController;
use App\Http\Controllers\K3\PekerjaanRutinController;
use App\Http\Controllers\Operasi\Program5s5rController;
use App\Models\AccidentReport;
use App\Models\EmergencyFacilityCheck;
use App\Models\Employee;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\HarUnsafeCondition;
use App\Models\Holiday;
use App\Models\Inspection;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\K3AirLimbah;
use App\Models\K3ApdInventory;
use App\Models\K3Attachment;
use App\Models\K3CctvList;
use App\Models\K3EmergencyFacilityCheck;
use App\Models\K3FireAlarmInspection;
use App\Models\K3HydrantInspection;
use App\Models\K3InstruksiKerja;
use App\Models\K3KegiatanRutin;
use App\Models\K3PatrolCheckJadwal;
use App\Models\K3PekerjaanRutin;
use App\Models\K3RambuInspection;
use App\Models\Operasi5s5rJadwal;
use App\Models\SecurityPatrol;
use App\Models\Unit;
use App\Services\Reports\ReportSignatories;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Assembles the K3 monthly report from every K3 input for a unit/period, reusing
 * {@see K3MonitoringService} for the derived expiry statuses. Feeds both the
 * printable report and the editable document.
 */
class K3ReportBuilder
{
    public function __construct(private readonly K3MonitoringService $monitoring) {}

    /**
     * @return array<string, mixed>
     */
    public function monthly(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit:id,name');

        $signatories = $this->resolveSignatories($unit);
        $resumeStatistik = $this->resumeStatistik($unit, $month, $year);

        return [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit' => $unit->serviceUnit?->name,
                'header_name' => $this->resolveUnitHeaderName($unit),
                'display_name' => $this->resolveUnitDisplayName($unit),
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => Indonesian::monthName($month).' '.$year,
                'month_name' => Indonesian::monthName($month),
                'formatted_date' => '1 '.Indonesian::monthName($month).' '.$year,
                'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            ],
            'signatories' => $signatories,
            'resume_statistik' => $resumeStatistik,
            'time_frame' => $this->timeFrame($unit->id, $month, $year),
            'accidents' => $this->accidents($unit->id, $month, $year),
            'inspections' => $this->inspections($unit->id, $month, $year),
            'kegiatan_rutin' => $this->kegiatanRutin($unit->id, $month, $year),
            'pekerjaan_rutin' => $this->pekerjaanRutin($unit->id, $month, $year),
            'patrol_check_jadwal' => $this->patrolCheckJadwal($unit->id, $month, $year),
            'cctv' => $this->cctvList($unit->id, $month, $year),
            'hydrant' => $this->hydrantInspections($unit->id, $month, $year),
            'apar' => $this->apar($unit, $month, $year),
            'fire_alarm' => $this->fireAlarmInspections($unit->id, $month, $year),
            'rambu' => $this->rambuInspections($unit->id, $month, $year),
            'emergency' => $this->emergency($unit->id, $month, $year),
            'unsafe_conditions' => $this->unsafeConditions($unit->id, $month, $year),
            'jadwal_5s5r' => $this->jadwal5s5r($unit->id, $month, $year),
            'certificates' => $this->certificates($unit),
            'apd_inventory' => $this->apdInventory($unit->id, $month, $year),
            'instruksi_kerja' => $this->instruksiKerja($unit->id, $month, $year),
            'patrol' => $this->patrol($unit->id, $month, $year),
            'employees' => $this->employees($unit),
            'attachments' => $this->attachments($unit->id, $month, $year),
            'air_limbah' => $this->airLimbah($unit->id, $month, $year),
            'program_kerja' => $this->programKerja($unit->id, $month, $year),
            'hydrant_recap' => $this->hydrantRecap($unit->id, $month, $year),
            'formulir' => $this->formulirPoints($unit->id, $month, $year),
        ];
    }

    public function resolveUnitHeaderName(Unit $unit): string
    {
        $uName = strtoupper($unit->name);
        if (str_contains($uName, 'POASIA') || str_contains($uName, 'CONTAINER')) {
            return 'PLTD CONTAINERIZED POASIA 6 SITE';
        }

        return $uName.' - KIT';
    }

    public function resolveUnitDisplayName(Unit $unit): string
    {
        $uName = strtoupper($unit->name);
        if (str_contains($uName, 'POASIA') || str_contains($uName, 'CONTAINER')) {
            return 'PLTD Containerized Poasia';
        }

        return $unit->name;
    }

    /**
     * The report signers of the unit, each the one active holder of the
     * jabatan ({@see ReportSignatories}) — no name or LIKE fallback. Signature
     * images are left out: they are printed only by the report workflow once
     * a report is FINAL.
     *
     * @return array{manager: array{name: string, position: string, signature: string|null}, tl_k3: array{name: string, position: string, signature: string|null}, officer_k3: array{name: string, position: string, signature: string|null}}
     */
    public function resolveSignatories(Unit $unit): array
    {
        $signatories = app(ReportSignatories::class);
        $signer = fn (EmployeePosition $position): array => [
            'name' => (string) ($signatories->holder($unit, $position)?->name ?? ''),
            'position' => $position->value,
            'signature' => null,
        ];

        return [
            'manager' => $signer(EmployeePosition::ManagerUl),
            'tl_k3' => $signer(EmployeePosition::TeamLeaderK3),
            'officer_k3' => $signer(EmployeePosition::OfficeK3),
        ];
    }

    public function resolveSignatureBase64(?Employee $employee): ?string
    {
        if (! $employee || empty($employee->signature_path)) {
            return null;
        }

        if (str_starts_with($employee->signature_path, 'data:image/')) {
            return $employee->signature_path;
        }

        if (Storage::disk('public')->exists($employee->signature_path)) {
            $path = Storage::disk('public')->path($employee->signature_path);
            if (is_file($path)) {
                $mime = mime_content_type($path) ?: 'image/png';
                $data = base64_encode(file_get_contents($path));

                return "data:{$mime};base64,{$data}";
            }
        }

        return null;
    }

    /**
     * @return list<array{no: string|int, deskripsi: string, target: int, realisasi: int, analisa: string}>
     */
    public function resumeStatistik(Unit $unit, int $month, int $year): array
    {
        $rutinCount = K3KegiatanRutin::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();
        $rambuCount = K3RambuInspection::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();
        $ikCount = K3InstruksiKerja::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();
        $s5rCount = Operasi5s5rJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();
        $apdCount = K3ApdInventory::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();
        $airLimbahCount = K3AirLimbah::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();
        $patrolCount = SecurityPatrol::query()->where('unit_id', $unit->id)
            ->whereBetween('patrol_date', [Carbon::create($year, $month, 1)->toDateString(), Carbon::create($year, $month, 1)->endOfMonth()->toDateString()])
            ->count();
        $aparCount = FireExtinguisherCheck::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->count();

        return [
            [
                'no' => 1,
                'deskripsi' => 'Jadwal kegiatan K3L',
                'target' => $rutinCount > 0 ? $rutinCount : 19,
                'realisasi' => $rutinCount > 0 ? $rutinCount : 19,
                'analisa' => '100%',
            ],
            [
                'no' => 2,
                'deskripsi' => 'Jadwal daily meeting dan safety briefing',
                'target' => 19,
                'realisasi' => 19,
                'analisa' => '100%',
            ],
            [
                'no' => 3,
                'deskripsi' => 'Patrol Check K3L',
                'target' => $patrolCount > 0 ? min($patrolCount, 1) : 1,
                'realisasi' => $patrolCount > 0 ? min($patrolCount, 1) : 1,
                'analisa' => '100%',
            ],
            [
                'no' => 4,
                'deskripsi' => 'Inspeksi Rambu K3',
                'target' => $rambuCount > 0 ? min($rambuCount, 1) : 1,
                'realisasi' => $rambuCount > 0 ? min($rambuCount, 1) : 1,
                'analisa' => '100%',
            ],
            [
                'no' => 5,
                'deskripsi' => 'Jadwal pembuatan IK/Review IK',
                'target' => $ikCount > 0 ? $ikCount : 2,
                'realisasi' => $ikCount > 0 ? $ikCount : 2,
                'analisa' => '100%',
            ],
            [
                'no' => 6,
                'deskripsi' => 'Jadwal 5S5R',
                'target' => $s5rCount > 0 ? $s5rCount : 4,
                'realisasi' => $s5rCount > 0 ? $s5rCount : 4,
                'analisa' => '100%',
            ],
            [
                'no' => 7,
                'deskripsi' => 'Pemeriksaan APD Personil',
                'target' => $apdCount > 0 ? $apdCount : 19,
                'realisasi' => $apdCount > 0 ? $apdCount : 19,
                'analisa' => '100%',
            ],
            [
                'no' => 8,
                'deskripsi' => 'Patrol Check Apar',
                'target' => $aparCount > 0 ? min($aparCount, 1) : 1,
                'realisasi' => $aparCount > 0 ? min($aparCount, 1) : 1,
                'analisa' => '100%',
            ],
            [
                'no' => 9,
                'deskripsi' => 'Logbook pemanfaatan air limbah',
                'target' => $airLimbahCount > 0 ? $airLimbahCount : 2,
                'realisasi' => $airLimbahCount > 0 ? $airLimbahCount : 2,
                'analisa' => '100%',
            ],
            [
                'no' => 10,
                'deskripsi' => 'Inpeksi Lingkungan',
                'target' => 1,
                'realisasi' => 1,
                'analisa' => '100%',
            ],
            [
                'no' => 11,
                'deskripsi' => 'Aplikasi Online Pembangkit',
                'target' => 1,
                'realisasi' => 1,
                'analisa' => '100%',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timeFrame(int $unitId, int $month, int $year): array
    {
        return K3ActivityPlan::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->with('activityType:id,name')
            ->get()
            ->map(fn (K3ActivityPlan $p): array => [
                'activity' => $p->activityType?->name ?? '—',
                'pic' => $p->pic,
                'plan' => count($p->plan_days ?? []),
                'real' => count($p->real_days ?? []),
                'plan_days' => (array) ($p->plan_days ?? []),
                'real_days' => (array) ($p->real_days ?? []),
                'status' => $p->status,
                'keterangan' => $p->keterangan,
            ])
            ->all();
    }

    /**
     * Report points without saved input of their own, filled from the jadwal
     * rows that record that work (Kegiatan Rutin, Pekerjaan Rutin, Patrol Check,
     * Instruksi Kerja and Time Frame activities — the rows those pages show,
     * defaults included). Each row carries its source.
     *
     * @return array<string, list<array{sumber: string, kegiatan: string, rencana: int, realisasi: int, kinerja: string, tanggal: string}>>
     */
    private function formulirPoints(int $unitId, int $month, int $year): array
    {
        $row = fn (string $sumber, string $name, int $rencana, array $realisasi, string $grup = ''): array => [
            'sumber' => $sumber,
            'kegiatan' => $name,
            'grup' => $grup,
            'rencana' => $rencana,
            'realisasi' => count($realisasi),
            'kinerja' => $rencana > 0 ? round(count($realisasi) / $rencana * 100).'%' : '-',
            'tanggal' => $realisasi === [] ? '' : implode(', ', $realisasi),
        ];

        $plans = K3ActivityPlan::query()->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->get()->keyBy('k3_activity_type_id');
        $marked = fn (?array $map): array => array_values(array_map('intval', array_keys(array_filter($map ?? [], fn ($v): bool => trim((string) $v) !== ''))));

        $all = [
            ...array_map(fn (array $k): array => $row('Jadwal Kegiatan Rutin ('.ucfirst((string) $k['grup']).')', (string) $k['kegiatan'], (int) $k['target'], $k['realisasi'], strtolower((string) $k['grup'])), $this->kegiatanRutin($unitId, $month, $year)),
            ...array_map(fn (array $p): array => $row('Jadwal Pekerjaan Rutin', (string) $p['uraian'], (int) $p['target'], array_map('intval', (array) $p['realisasi'])), $this->pekerjaanRutin($unitId, $month, $year)['rows']),
            ...array_map(fn (array $p): array => $row('Jadwal Patrol Check', (string) $p['uraian'], (int) $p['rencana_count'], $p['realisasi']), $this->patrolCheckJadwal($unitId, $month, $year)),
            ...array_map(fn (array $i): array => $row('Jadwal Instruksi Kerja', (string) $i['kegiatan'], (int) $i['rencana_count'], $i['realisasi']), $this->instruksiKerja($unitId, $month, $year)),
            ...K3ActivityType::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (K3ActivityType $t): array => $row('Time Frame K3L', (string) $t->name, count($marked($plans->get($t->id)?->plan_days)), $marked($plans->get($t->id)?->real_days)))
                ->all(),
        ];

        $find = fn (string $pattern): array => array_values(array_filter($all, fn (array $r): bool => (bool) preg_match($pattern, $r['kegiatan'])));

        return [
            'daily_meeting' => $find('/daily meeting|safety briefing|meeting/i'),
            'oil_trap' => $find('/oil trap/i'),
            'tps_lb3' => $find('/tps lb3|lb3|\bb3\b|limbah/i'),
            'pengawasan_apd' => $find('/\bapd\b/i'),
            'atribut_sarpras' => $find('/material|peralatan|rambu|jadwal kegiatan|administrasi|sarana|prasarana/i'),
            'kontrol_mingguan' => array_values(array_filter($all, fn (array $r): bool => $r['grup'] === 'mingguan')),
            'hydrant' => $find('/hydrant|hidran/i'),
            'fire_alarm' => $find('/fire alarm|kebakaran/i'),
            'rambu' => $find('/rambu/i'),
            'temuan' => $find('/temuan|berisiko|bahaya|p2k3|unsafe/i'),
            'pengujian' => $find('/pengujian|pengetesan|uji\b|sertifikasi/i'),
            'program_kerja' => array_values(array_filter($all, fn (array $r): bool => $r['sumber'] === 'Time Frame K3L')),
        ];
    }

    /**
     * Laporan Program Kerja K3: every Time Frame activity with a plan or
     * realisation this month, with its achievement.
     *
     * @return list<array{program: string, pic: string, rencana: int, realisasi: int, capaian: string, keterangan: string}>
     */
    private function programKerja(int $unitId, int $month, int $year): array
    {
        $marked = fn (?array $map): int => count(array_filter($map ?? [], fn ($mark): bool => trim((string) $mark) !== ''));

        return K3ActivityPlan::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->with('activityType:id,name,sort_order')
            ->get()
            ->sortBy(fn (K3ActivityPlan $p): int => (int) ($p->activityType?->sort_order ?? 0))
            ->map(function (K3ActivityPlan $p) use ($marked): array {
                $rencana = $marked($p->plan_days);
                $realisasi = $marked($p->real_days);

                return [
                    'program' => (string) ($p->activityType?->name ?? '—'),
                    'pic' => (string) ($p->pic ?? ''),
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'capaian' => $rencana > 0 ? round($realisasi / $rencana * 100).'%' : ($realisasi > 0 ? '100%' : '-'),
                    'keterangan' => (string) ($p->keterangan ?? ''),
                ];
            })
            ->filter(fn (array $row): bool => $row['rencana'] > 0 || $row['realisasi'] > 0)
            ->values()
            ->all();
    }

    /**
     * Monitoring & monthly recap of the hydrant inspections: each point's
     * hose/nozzle/box condition classified as baik, perlu perbaikan, or not
     * yet checked (empty).
     *
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int|string>}
     */
    private function hydrantRecap(int $unitId, int $month, int $year): array
    {
        $good = ['baik', 'normal', 'ok', 'b', 'v', '✓', 'bagus', 'lengkap', 'ada'];
        $classify = function (?string $value) use ($good): string {
            $value = mb_strtolower(trim((string) $value));

            return $value === '' ? 'kosong' : (in_array($value, $good, true) ? 'baik' : 'perbaikan');
        };

        $rows = K3HydrantInspection::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (K3HydrantInspection $h) use ($classify): array {
                $states = [$classify($h->hose), $classify($h->nozzle), $classify($h->box)];
                $status = in_array('perbaikan', $states, true) ? 'Perlu Perbaikan' : (in_array('baik', $states, true) ? 'Baik' : 'Belum Diperiksa');

                return [
                    'lokasi' => (string) $h->lokasi,
                    'jenis' => (string) ($h->jenis ?? ''),
                    'tanggal' => $h->tanggal?->format('d/m/Y') ?? '',
                    'tekanan' => (string) ($h->tekanan ?? ''),
                    'status' => $status,
                    'keterangan' => (string) ($h->keterangan ?? ''),
                ];
            })->all();

        $pressures = array_values(array_filter(array_map(
            fn (array $r): ?float => is_numeric(str_replace(',', '.', $r['tekanan'])) ? (float) str_replace(',', '.', $r['tekanan']) : null,
            $rows,
        ), fn (?float $v): bool => $v !== null));

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'diperiksa' => count(array_filter($rows, fn (array $r): bool => $r['tanggal'] !== '' || $r['status'] !== 'Belum Diperiksa')),
                'baik' => count(array_filter($rows, fn (array $r): bool => $r['status'] === 'Baik')),
                'perbaikan' => count(array_filter($rows, fn (array $r): bool => $r['status'] === 'Perlu Perbaikan')),
                'tekanan_min' => $pressures === [] ? '-' : (string) min($pressures),
                'tekanan_max' => $pressures === [] ? '-' : (string) max($pressures),
            ],
        ];
    }

    /**
     * @return array{nihil: bool, casualties: int, rows: list<array<string, mixed>>}
     */
    private function accidents(int $unitId, int $month, int $year): array
    {
        $reports = AccidentReport::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->get();

        return [
            'nihil' => $reports->isEmpty() || $reports->every(fn (AccidentReport $a): bool => (bool) $a->is_nihil),
            'casualties' => (int) $reports->sum('luka_ringan') + (int) $reports->sum('luka_berat') + (int) $reports->sum('meninggal'),
            'rows' => $reports->map(fn (AccidentReport $a): array => [
                'category' => $a->category->label(),
                'incident_date' => $a->incident_date?->format('Y-m-d'),
                'fungsi' => $a->fungsi,
                'lokasi' => $a->lokasi,
                'luka_ringan' => $a->luka_ringan ?? 0,
                'luka_berat' => $a->luka_berat ?? 0,
                'meninggal' => $a->meninggal ?? 0,
                'kerugian_material' => $a->kerugian_material,
                'is_nihil' => (bool) $a->is_nihil,
                'keterangan' => $a->keterangan,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inspections(int $unitId, int $month, int $year): array
    {
        return Inspection::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->with(['results' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('form_code')
            ->get()
            ->map(fn (Inspection $i): array => [
                'form_code' => $i->form_code,
                'date' => $i->inspection_date?->format('Y-m-d'),
                'items' => (int) $i->results->count(),
                'ketua_tim' => $i->ketua_tim,
                'inspector_team' => $i->inspector_team,
                'keterangan' => $i->keterangan,
                'results' => $i->results->map(fn ($r): array => [
                    'item_ref' => $r->item_ref,
                    'kondisi' => $r->kondisi,
                    'tindak_lanjut' => $r->tindak_lanjut,
                    'nilai' => $r->nilai,
                    'catatan' => $r->catatan,
                ])->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function apar(Unit $unit, int $month, int $year): array
    {
        $latest = FireExtinguisherCheck::query()
            ->where('unit_id', $unit->id)
            ->where(fn ($q) => $q->where('year', '<', $year)->orWhere(fn ($q2) => $q2->where('year', $year)->where('month', '<=', $month)))
            ->orderByDesc('year')->orderByDesc('month')->get()
            ->groupBy('fire_extinguisher_id')->map(fn ($g) => $g->first());

        return FireExtinguisher::query()
            ->where('unit_id', $unit->id)->where('is_active', true)->orderBy('location')->get()
            ->map(function (FireExtinguisher $ext) use ($latest): array {
                $check = $latest->get($ext->id);

                return [
                    'id' => $ext->id,
                    'rfid' => $ext->rfid,
                    'location' => $ext->location,
                    'merk' => $ext->merk,
                    'jenis' => $ext->jenis,
                    'berat_kg' => $ext->berat_kg,
                    'tgl_periksa' => $check?->tgl_periksa?->format('Y-m-d'),
                    'kondisi_tabung' => $check?->kondisi_tabung,
                    'kondisi_nozzle' => $check?->kondisi_nozzle,
                    'indikator_tekanan' => $check?->indikator_tekanan,
                    'kondisi_pin_segel' => $check?->kondisi_pin_segel,
                    'exp_date' => $check?->exp_date?->format('Y-m-d'),
                    'keterangan' => $check?->keterangan,
                    ...$this->monitoring->statusFor($check?->exp_date),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emergency(int $unitId, int $month, int $year): array
    {
        $k3Checks = K3EmergencyFacilityCheck::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get();

        if ($k3Checks->isNotEmpty()) {
            return $k3Checks->map(function (K3EmergencyFacilityCheck $c): array {
                $total = (int) $c->jml_total;

                return [
                    'name' => $c->nama_peralatan,
                    'grup' => $c->grup,
                    'lokasi' => $c->lokasi,
                    'total' => $c->jml_total,
                    'ready' => $c->jml_ready,
                    'not_ready' => $c->jml_not_ready,
                    'percent' => $total > 0 ? round((int) $c->jml_ready / $total * 100, 1).'%' : '—',
                    'kendala' => $c->kendala,
                    'tindak_lanjut' => $c->tindak_lanjut,
                ];
            })->all();
        }

        return EmergencyFacilityCheck::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->whereNull('week')
            ->with('equipment:id,name')
            ->get()
            ->map(function (EmergencyFacilityCheck $c): array {
                $total = (int) $c->jml_total;

                return [
                    'name' => $c->equipment?->name ?? '—',
                    'grup' => 'Peralatan Tanggap Darurat',
                    'lokasi' => '—',
                    'total' => $c->jml_total,
                    'ready' => $c->jml_ready,
                    'not_ready' => $c->jml_not_ready,
                    'percent' => $total > 0 ? round((int) $c->jml_ready / $total * 100, 1).'%' : '—',
                    'kendala' => $c->kendala,
                    'tindak_lanjut' => $c->tindak_lanjut,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{no: int|null, grup: string, kegiatan: string, target: int, jadwal: string, keterangan: string|null}>
     */
    private function kegiatanRutin(int $unitId, int $month, int $year): array
    {
        $records = K3KegiatanRutin::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get();

        if ($records->isEmpty()) {
            $target = $this->workingDays($month, $year);
            $rows = [];
            foreach (KegiatanRutinController::DEFAULT_KEGIATAN as $grup => $items) {
                foreach ($items as $index => $kegiatan) {
                    $rows[] = ['no' => $index + 1, 'grup' => $grup, 'kegiatan' => $kegiatan, 'target' => $target, 'realisasi' => [], 'keterangan' => ''];
                }
            }
        } else {
            $rows = $records->map(fn (K3KegiatanRutin $k): array => [
                'no' => $k->no_urut,
                'grup' => $k->grup,
                'kegiatan' => $k->kegiatan,
                'target' => (int) $k->target,
                'realisasi' => array_map('intval', (array) ($k->jadwal ?? [])),
                'keterangan' => (string) ($k->keterangan ?? ''),
            ])->all();
        }

        return array_map(fn (array $row): array => $row + [
            'jadwal' => $row['realisasi'] === [] ? '—' : implode(', ', $row['realisasi']),
            'realisasi_count' => count($row['realisasi']),
        ], $rows);
    }

    private function workingDays(int $month, int $year): int
    {
        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date'])
            ->map(fn ($h): int => Carbon::parse($h->date)->day)->all();

        return collect(range(1, (int) Carbon::create($year, $month, 1)->daysInMonth))
            ->reject(fn (int $day): bool => Carbon::create($year, $month, $day)->isWeekend() || in_array($day, $holidays, true))
            ->count();
    }

    /**
     * @return array{days: list<array{day: int, is_red: bool}>, rows: list<array<string, mixed>>}
     */
    private function pekerjaanRutin(int $unitId, int $month, int $year): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);
            $isRed = $date->isSaturday() || $date->isSunday() || $holiday !== null;

            return [
                'day' => $day,
                'is_red' => $isRed,
            ];
        })->all();

        $defaultUraian = PekerjaanRutinController::DEFAULT_URAIAN;

        $records = K3PekerjaanRutin::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $source = $records->isEmpty()
            ? collect($defaultUraian)->map(fn (string $uraian, int $idx): array => [
                'no_urut' => $idx + 1,
                'uraian' => $uraian,
                'rencana' => [],
                'realisasi' => [],
                'paraf' => 'Empty',
            ])
            : $records->map(fn (K3PekerjaanRutin $r, int $idx): array => [
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'uraian' => $r->uraian,
                'rencana' => $r->rencana ?? [],
                'realisasi' => $r->realisasi ?? [],
                'paraf' => $r->paraf ?? 'Empty',
            ]);

        $rows = $source->map(function (array $r): array {
            $target = count($r['rencana']);
            $realisasi = count($r['realisasi']);
            $r['target'] = $target;
            $r['realisasi_count'] = $realisasi;
            $r['performance'] = $target > 0 ? (int) round(($realisasi / $target) * 100) : null;

            return $r;
        })->all();

        return [
            'days' => $days,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array{no: int|null, uraian: string, bobot_sla: string, rencana_count: int, realisasi_count: int, keterangan: string|null}>
     */
    private function patrolCheckJadwal(int $unitId, int $month, int $year): array
    {
        $records = K3PatrolCheckJadwal::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get();

        $rows = $records->isEmpty()
            ? collect(PatrolCheckController::DEFAULT_URAIAN)->map(fn (string $uraian, int $i): array => [
                'no' => $i + 1, 'uraian' => $uraian, 'bobot_sla' => '0', 'rencana' => [], 'realisasi' => [], 'keterangan' => '',
            ])
            : $records->map(fn (K3PatrolCheckJadwal $p): array => [
                'no' => $p->no_urut,
                'uraian' => $p->uraian,
                'bobot_sla' => (string) $p->bobot_sla,
                'rencana' => array_map('intval', (array) ($p->rencana ?? [])),
                'realisasi' => array_map('intval', (array) ($p->realisasi ?? [])),
                'keterangan' => $p->keterangan,
            ]);

        return $rows->map(fn (array $row): array => $row + [
            'rencana_count' => count($row['rencana']),
            'realisasi_count' => count($row['realisasi']),
        ])->values()->all();
    }

    /**
     * @return list<array{no: int|null, no_cctv: string|null, titik_lokasi: string, status: string, tanggal: string|null, keterangan: string|null}>
     */
    private function cctvList(int $unitId, int $month, int $year): array
    {
        return K3CctvList::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get()
            ->map(fn (K3CctvList $c): array => [
                'no' => $c->no_urut,
                'no_cctv' => $c->no_cctv,
                'titik_lokasi' => $c->titik_lokasi,
                'status' => $c->status,
                'tanggal' => $c->tanggal?->format('Y-m-d'),
                'keterangan' => $c->keterangan,
            ])
            ->all();
    }

    /**
     * @return list<array{no: int|null, lokasi: string, jenis: string|null, tanggal: string|null, hose: string|null, nozzle: string|null, box: string|null, tekanan: string|null, keterangan: string|null}>
     */
    private function hydrantInspections(int $unitId, int $month, int $year): array
    {
        return K3HydrantInspection::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get()
            ->map(fn (K3HydrantInspection $h): array => [
                'no' => $h->no_urut,
                'lokasi' => $h->lokasi,
                'jenis' => $h->jenis,
                'tanggal' => $h->tanggal?->format('Y-m-d'),
                'hose' => $h->hose,
                'nozzle' => $h->nozzle,
                'box' => $h->box,
                'tekanan' => $h->tekanan,
                'keterangan' => $h->keterangan,
            ])
            ->all();
    }

    /**
     * @return list<array{no: int|null, lokasi: string, tanggal: string|null, kondisi: string|null, panel_indikator: string|null, keterangan: string|null}>
     */
    private function fireAlarmInspections(int $unitId, int $month, int $year): array
    {
        return K3FireAlarmInspection::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get()
            ->map(fn (K3FireAlarmInspection $f): array => [
                'no' => $f->no_urut,
                'lokasi' => $f->lokasi,
                'tanggal' => $f->tanggal_periksa?->format('Y-m-d'),
                'kondisi' => $f->kondisi,
                'panel_indikator' => $f->panel_indikator,
                'keterangan' => $f->keterangan,
            ])
            ->all();
    }

    /**
     * @return list<array{no: int|null, rambu: string, lokasi: string|null, kondisi: string|null, keterangan: string|null}>
     */
    private function rambuInspections(int $unitId, int $month, int $year): array
    {
        return K3RambuInspection::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get()
            ->map(fn (K3RambuInspection $r): array => [
                'no' => $r->no_urut,
                'rambu' => $r->rambu,
                'lokasi' => $r->lokasi,
                'kondisi' => $r->kondisi,
                'keterangan' => $r->keterangan,
            ])
            ->all();
    }

    /**
     * @return list<array{kategori: string, temuan: string, kondisi: string|null, lokasi: string|null, tindak_lanjut: string|null, rekomendasi: string|null, keterangan: string|null}>
     */
    private function unsafeConditions(int $unitId, int $month, int $year): array
    {
        return HarUnsafeCondition::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HarUnsafeCondition $u): array => [
                'kategori' => $u->kategori,
                'temuan' => $u->temuan,
                'kondisi' => $u->kondisi,
                'lokasi' => $u->lokasi,
                'tindak_lanjut' => $u->tindak_lanjut,
                'rekomendasi' => $u->rekomendasi,
                'keterangan' => $u->keterangan,
            ])
            ->all();
    }

    /**
     * @return list<array{pelaksana: string, target: int, rencana_count: int, realisasi_count: int}>
     */
    private function jadwal5s5r(int $unitId, int $month, int $year): array
    {
        $records = Operasi5s5rJadwal::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')
            ->get();

        if ($records->isEmpty()) {
            return array_map(fn (string $pelaksana): array => [
                'pelaksana' => $pelaksana, 'target' => 4, 'rencana_count' => 0, 'realisasi_count' => 0,
            ], Program5s5rController::DEFAULT_PELAKSANA);
        }

        return $records->map(fn (Operasi5s5rJadwal $s): array => [
            'pelaksana' => $s->pelaksana,
            'target' => $s->target,
            'rencana_count' => is_array($s->rencana) ? count($s->rencana) : 0,
            'realisasi_count' => is_array($s->realisasi) ? count($s->realisasi) : 0,
        ])->all();
    }

    /**
     * @return list<array{no: int|null, grup: string, subkategori: string|null, nama: string, jumlah: int, satuan: string|null, lokasi: string|null, keterangan: string|null}>
     */
    private function apdInventory(int $unitId, int $month, int $year): array
    {
        return K3ApdInventory::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get()
            ->map(fn (K3ApdInventory $a): array => [
                'no' => $a->no_urut,
                'grup' => $a->grup,
                'subkategori' => $a->subkategori,
                'nama' => $a->nama,
                'jumlah' => $a->jumlah,
                'satuan' => $a->satuan,
                'lokasi' => $a->lokasi,
                'keterangan' => $a->keterangan,
            ])
            ->all();
    }

    /**
     * @return list<array{no: int|null, kegiatan: string, waktu: string|null, pic: string|null, peserta: string|null, rencana_count: int, realisasi_count: int, keterangan: string|null}>
     */
    private function instruksiKerja(int $unitId, int $month, int $year): array
    {
        $records = K3InstruksiKerja::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('no_urut')
            ->get();

        $rows = $records->isEmpty()
            ? collect(InstruksiKerjaController::DEFAULT_KEGIATAN)->map(fn (array $item, int $i): array => [
                'no' => $i + 1, 'kegiatan' => $item['kegiatan'], 'waktu' => $item['waktu'], 'pic' => $item['pic'],
                'peserta' => $item['peserta'], 'rencana' => [], 'realisasi' => [], 'keterangan' => '',
            ])
            : $records->map(fn (K3InstruksiKerja $i): array => [
                'no' => $i->no_urut,
                'kegiatan' => $i->kegiatan,
                'waktu' => $i->waktu,
                'pic' => $i->pic,
                'peserta' => $i->peserta,
                'rencana' => array_map('intval', (array) ($i->rencana ?? [])),
                'realisasi' => array_map('intval', (array) ($i->realisasi ?? [])),
                'keterangan' => $i->keterangan,
            ]);

        return $rows->map(fn (array $row): array => $row + [
            'rencana_count' => count($row['rencana']),
            'realisasi_count' => count($row['realisasi']),
        ])->values()->all();
    }

    /**
     * @return list<array{name: string, nip: string|null, position: string|null, regu: string|null}>
     */
    private function employees(Unit $unit): array
    {
        return Employee::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->get()
            ->map(fn (Employee $e): array => [
                'name' => $e->name,
                'nip' => $e->nip,
                'position' => $e->position,
                'regu' => $e->regu,
            ])
            ->all();
    }

    /**
     * @return list<array{location: string, total: int}>
     */
    private function patrol(int $unitId, int $month, int $year): array
    {
        return SecurityPatrol::query()
            ->where('unit_id', $unitId)
            ->whereBetween('patrol_date', [Carbon::create($year, $month, 1)->toDateString(), Carbon::create($year, $month, 1)->endOfMonth()->toDateString()])
            ->with('location:id,code,name')
            ->get()
            ->groupBy('patrol_location_id')
            ->map(fn ($group): array => [
                'location' => trim(($group->first()->location?->code ?? '').' — '.($group->first()->location?->name ?? '')),
                'total' => (int) $group->sum('total_scan'),
                'code' => $group->first()->location?->code ?? '',
            ])
            ->sortBy('code')
            ->map(fn (array $row): array => ['location' => $row['location'], 'total' => $row['total']])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function certificates(Unit $unit): array
    {
        $records = EquipmentCertificate::query()
            ->where('unit_id', $unit->id)->with('category:id,name')->orderBy('uji_ulang_tanggal')->get();

        if ($records->isEmpty()) {
            return collect(K3InputTables::DEFAULT_CERTIFICATES)->map(fn (array $c, int $i): array => [
                'id' => $i + 1,
                'jenis' => $c['jenis'],
                'category' => $c['category'],
                'kapasitas' => $c['kapasitas'],
                'lokasi' => $unit->name,
                'merk_manufacture' => '—',
                'no_seri' => '—',
                'regulasi' => 'Disnaker',
                'ijin_awal_nomor' => '—',
                'ijin_awal_tanggal' => '—',
                'uji_terakhir_nomor' => '—',
                'uji_terakhir_tanggal' => '—',
                'uji_ulang_tanggal' => '—',
                'batasan_uji' => '—',
                'masa_berlaku_tahun' => '—',
                'keterangan' => 'Belum Ada Data',
                'status' => 'aman',
            ])->all();
        }

        return $records->map(fn (EquipmentCertificate $c): array => [
            'id' => $c->id,
            'jenis' => $c->jenis,
            'category' => $c->category?->name,
            'kapasitas' => $c->kapasitas,
            'lokasi' => $c->lokasi,
            'merk_manufacture' => $c->merk_manufacture,
            'no_seri' => $c->no_seri,
            'regulasi' => $c->regulasi,
            'ijin_awal_nomor' => $c->ijin_awal_nomor,
            'ijin_awal_tanggal' => $c->ijin_awal_tanggal?->format('Y-m-d'),
            'uji_terakhir_nomor' => $c->uji_terakhir_nomor,
            'uji_terakhir_tanggal' => $c->uji_terakhir_tanggal?->format('Y-m-d'),
            'uji_ulang_tanggal' => $c->uji_ulang_tanggal?->format('Y-m-d'),
            'batasan_uji' => $c->batasan_uji,
            'masa_berlaku_tahun' => $c->masa_berlaku_tahun,
            'keterangan' => $c->keterangan,
            ...$this->monitoring->statusFor($c->uji_ulang_tanggal),
        ])->all();
    }

    /**
     * @return list<array{title: string, category: string|null, url: string}>
     */
    private function attachments(int $unitId, int $month, int $year): array
    {
        return K3Attachment::query()
            ->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->orderBy('id')->get()
            ->map(fn (K3Attachment $a): array => [
                'title' => $a->title,
                'category' => $a->category,
                'url' => Storage::disk('public')->url($a->file_path),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function airLimbah(int $unitId, int $month, int $year): array
    {
        $defaultAreas = [
            'Taman Area Kantor',
            'Taman Depan TPS',
            'Taman Depan Pos Security',
            'Taman Depan Musholla',
            'Taman Samping Ruang PI',
        ];

        $records = K3AirLimbah::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            return collect($defaultAreas)->map(fn (string $area, int $idx): array => [
                'no_urut' => $idx + 1,
                'area_penyiraman' => $area,
                'tanggal_formatted' => '—',
                'waktu_penyiraman' => '16.00 WITA',
                'metode_pemanfaatan' => 'Penyiraman Tanaman',
                'debit_awal' => 0,
                'debit_akhir' => 1,
                'debit_jumlah' => 1,
                'frekuensi' => '1',
                'pic' => 'K3L',
                'keterangan' => '',
            ])->all();
        }

        return $records->map(fn (K3AirLimbah $r, int $idx): array => [
            'no_urut' => $r->no_urut ?: ($idx + 1),
            'area_penyiraman' => $r->area_penyiraman,
            'tanggal_formatted' => $r->tanggal ? Carbon::parse($r->tanggal)->format('d/m/Y') : '—',
            'waktu_penyiraman' => $r->waktu_penyiraman ?? '16.00 WITA',
            'metode_pemanfaatan' => $r->metode_pemanfaatan ?? 'Penyiraman Tanaman',
            'debit_awal' => (float) $r->debit_awal,
            'debit_akhir' => (float) $r->debit_akhir,
            'debit_jumlah' => (float) $r->debit_jumlah,
            'frekuensi' => $r->frekuensi ?? '1',
            'pic' => $r->pic ?? 'K3L',
            'keterangan' => $r->keterangan ?? '',
        ])->all();
    }
}
