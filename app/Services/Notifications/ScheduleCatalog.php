<?php

namespace App\Services\Notifications;

use App\Enums\PermissionName;
use App\Models\HarJadwalHarian;
use App\Models\HarJadwalMeetingPemeliharaan;
use App\Models\HarJadwalP0P5;
use App\Models\HarJadwalPatrolCheck;
use App\Models\HarJadwalPembuatanIk;
use App\Models\HarJadwalPiketOnCall;
use App\Models\HarLembarRow;
use App\Models\K3ActivityPlan;
use App\Models\K3InstruksiKerja;
use App\Models\K3JadwalOnCall;
use App\Models\K3JadwalPembuatanIk;
use App\Models\K3KegiatanRutin;
use App\Models\K3PatrolCheckJadwal;
use App\Models\K3PekerjaanRutin;
use App\Models\LogistikJadwalRow;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiBlackstartJadwal;
use App\Models\OperasiCommissioningTest;
use App\Models\OperasiCommPeralatan;
use App\Models\OperasiDataTeknis;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiInventarisJadwal;
use App\Models\OperasiJadwalKegiatan;
use App\Models\OperasiMeetingShiftJadwal;
use App\Models\OperasiPembuatanIk;
use App\Models\OperasiPerformanceTestMesin;
use App\Models\PdmJadwal5s5r;
use App\Models\PdmJadwalHarian;
use App\Models\PdmJadwalMeeting;
use App\Models\PdmJadwalPatrolCheck;
use App\Support\LogistikJadwal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Where each module keeps its jadwal, read by {@see ScheduleReminders}.
 *
 * A table entry says how a row plans a date:
 * - `period` month: rows per unit & month; `cells` are the days (a list of
 *   days, or a map day → code) of the plan (rencana).
 * - `period` year + `match` month: yearly rows; `cells` hold months, reminded
 *   on the 1st of that month.
 * - `period` year + `match` week: yearly rows; `cells` hold "{month}-{week}"
 *   keys (M1–M4), reminded on the 1st, 8th, 15th and 22nd.
 * `person` names the employee column of a personal duty (piket, on call,
 * patrol): that employee's account gets its own reminder too.
 * `regu_cell` resolves cell codes ('A'–'D') to every active employee of that
 * regu in the unit; `regu_column` reads regu codes from a row attribute.
 * `cutoff` shifts a monthly table to the 16th-prev-month..15th cut-off window.
 * `requires_input` marks duties whose reminder repeats periodically until the
 * user records their input/realisation, and `action` maps to the direct input
 * form for field operators/technicians.
 */
class ScheduleCatalog
{
    /** Module key → [label, permission that receives the digest, permission that may open the pages, hub route]. */
    public const MODULES = [
        'operasi' => ['Operasi', PermissionName::OperasiInputWrite, PermissionName::OperasiInputView, 'operasi.jadwal.index'],
        'har' => ['Pemeliharaan', PermissionName::HarInputWrite, PermissionName::HarInputView, 'har.jadwal.index'],
        'k3' => ['K3 & Keamanan', PermissionName::K3InputWrite, PermissionName::K3InputView, 'k3.jadwal.index'],
        'pdm' => ['PdM & MATLEV', PermissionName::PdmInputWrite, PermissionName::PdmInputView, 'pdm.jadwal.index'],
        'logistik' => ['Logistik & Gudang', PermissionName::LogistikInputWrite, PermissionName::LogistikInputView, 'logistik.jadwal.index'],
    ];

    /**
     * @return list<array{
     *     key: string,
     *     module: string,
     *     label: string,
     *     model: class-string<Model>,
     *     route: string,
     *     route_params?: array<string, string>,
     *     period: 'month'|'year',
     *     match?: 'month'|'week'|'date'|'w_week'|'mw_week',
     *     cutoff?: bool,
     *     cells: list<string>,
     *     codes?: list<string>,
     *     exclude_codes?: list<string>,
     *     title: callable(Model): string,
     *     where?: callable(Builder<Model>): void,
     *     person?: string,
     *     regu_cell?: bool,
     *     regu_column?: string,
     *     requires_input?: bool,
     *     action?: array{route: string, params?: array<string, mixed>, permissions: list<PermissionName>, label: string, pass_day?: bool, pass_date?: bool},
     *     with?: list<string>,
     * }>
     */
    public function tables(): array
    {
        $tables = [
            // Operasi
            ['key' => 'operasi:kegiatan', 'module' => 'operasi', 'label' => 'Kegiatan Operasi', 'model' => OperasiJadwalKegiatan::class, 'route' => 'operasi.jadwal.kegiatan.index', 'period' => 'month', 'cells' => ['jadwal'], 'title' => fn ($r): string => (string) $r->kegiatan],
            [
                'key' => 'operasi:5s-5r',
                'module' => 'operasi',
                'label' => '5S 5R',
                'model' => Operasi5s5rJadwal::class,
                'route' => 'operasi.jadwal.program-5s-5r.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'person' => 'employee_id',
                'regu_column' => 'shift',
                'requires_input' => true,
                'action' => [
                    'route' => 'operasi.input.program-5s5r.index',
                    'permissions' => [PermissionName::OperasiLapanganProgram5s5r, PermissionName::OperasiInputWrite, PermissionName::OperasiInputView],
                    'label' => 'Isi Program 5S 5R',
                    'pass_day' => true,
                ],
                'title' => fn ($r): string => $r->displayPelaksana(),
            ],
            [
                'key' => 'operasi:inventarisasi-tools',
                'module' => 'operasi',
                'label' => 'Inventarisasi Tools',
                'model' => OperasiInventarisJadwal::class,
                'route' => 'operasi.jadwal.inventarisasi-tools.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'regu_column' => 'shift',
                'requires_input' => true,
                'action' => [
                    'route' => 'operasi.input.material-peralatan.index',
                    'permissions' => [PermissionName::OperasiLapanganMaterialPeralatan, PermissionName::OperasiInputWrite, PermissionName::OperasiInputView],
                    'label' => 'Cek Material & Peralatan',
                ],
                'title' => fn ($r): string => (string) $r->uraian,
            ],
            [
                'key' => 'operasi:flm',
                'module' => 'operasi',
                'label' => 'FLM',
                'model' => OperasiFlmJadwal::class,
                'route' => 'operasi.jadwal.flm.index',
                'period' => 'month',
                'cells' => ['days'],
                'where' => fn (Builder $q) => $q->where('row_type', 'shift'),
                'regu_cell' => true,
                'requires_input' => true,
                'action' => [
                    'route' => 'operasi.input.flm-monitoring.index',
                    'permissions' => [PermissionName::OperasiLapanganFlmMonitoring, PermissionName::OperasiInputWrite, PermissionName::OperasiInputView],
                    'label' => 'Isi Monitoring FLM',
                ],
                'title' => fn ($r): string => (string) $r->label,
            ],
            [
                'key' => 'operasi:meeting-shift',
                'module' => 'operasi',
                'label' => 'Meeting Shift',
                'model' => OperasiMeetingShiftJadwal::class,
                'route' => 'operasi.jadwal.meeting-shift.index',
                'period' => 'month',
                'cells' => ['days'],
                'where' => fn (Builder $q) => $q->where('row_type', 'shift'),
                'regu_cell' => true,
                'requires_input' => true,
                'action' => [
                    'route' => 'operator.mutasi.index',
                    'permissions' => [PermissionName::OperatorMutasiWrite, PermissionName::OperatorMutasiView],
                    'label' => 'Buka Lembar Mutasi',
                    'pass_date' => true,
                ],
                'title' => fn ($r): string => (string) $r->label,
            ],
            ['key' => 'operasi:pembuatan-ik', 'module' => 'operasi', 'label' => 'Pembuatan IK', 'model' => OperasiPembuatanIk::class, 'route' => 'operasi.jadwal.pembuatan-ik.index', 'period' => 'year', 'match' => 'month', 'cells' => ['months'], 'title' => fn ($r): string => (string) $r->nama],
            ['key' => 'operasi:data-teknis', 'module' => 'operasi', 'label' => 'Data Teknis', 'model' => OperasiDataTeknis::class, 'route' => 'operasi.jadwal.pembuatan-data-teknis.index', 'period' => 'year', 'match' => 'month', 'cells' => ['months'], 'title' => fn ($r): string => (string) $r->nama],
            ['key' => 'operasi:blackstart', 'module' => 'operasi', 'label' => 'Blackstart', 'model' => OperasiBlackstartJadwal::class, 'route' => 'operasi.jadwal.blackstart.index', 'period' => 'year', 'match' => 'week', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['key' => 'operasi:commissioning-test', 'module' => 'operasi', 'label' => 'Commissioning Test Mesin', 'model' => OperasiCommissioningTest::class, 'route' => 'operasi.jadwal.commissioning-test.index', 'period' => 'year', 'match' => 'week', 'cells' => ['beban_50', 'beban_75', 'beban_100'], 'with' => ['machine'], 'title' => fn ($r): string => $r->displayMachineName()],
            ['key' => 'operasi:commissioning-peralatan', 'module' => 'operasi', 'label' => 'Commissioning Peralatan', 'model' => OperasiCommPeralatan::class, 'route' => 'operasi.jadwal.commissioning-test-peralatan.index', 'period' => 'year', 'match' => 'week', 'cells' => ['beban_50', 'beban_75', 'beban_100'], 'title' => fn ($r): string => (string) $r->nama_peralatan],
            ['key' => 'operasi:performance-test', 'module' => 'operasi', 'label' => 'Performance Test', 'model' => OperasiPerformanceTestMesin::class, 'route' => 'operasi.jadwal.performance-test.index', 'period' => 'year', 'match' => 'week', 'cells' => ['beban_50', 'beban_75', 'beban_100'], 'title' => fn ($r): string => (string) $r->nama_mesin],

            // Pemeliharaan
            ['key' => 'har:harian', 'module' => 'har', 'label' => 'Kegiatan Harian', 'model' => HarJadwalHarian::class, 'route' => 'har.jadwal.harian.index', 'period' => 'month', 'cells' => ['jadwal'], 'title' => fn ($r): string => (string) $r->kegiatan],
            ['key' => 'har:p0-p5', 'module' => 'har', 'label' => 'P0 - P5', 'model' => HarJadwalP0P5::class, 'route' => 'har.jadwal.p0-p5.index', 'period' => 'month', 'cells' => ['rencana'], 'with' => ['machine'], 'title' => fn ($r): string => (string) ($r->machine?->name ?? 'Mesin')],
            [
                'key' => 'har:meeting-pemeliharaan',
                'module' => 'har',
                'label' => 'Meeting Pemeliharaan',
                'model' => HarJadwalMeetingPemeliharaan::class,
                'route' => 'har.jadwal.meeting-pemeliharaan.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'har.formulir.daily-meeting.index',
                    'permissions' => [PermissionName::HarLapanganDailyMeeting, PermissionName::HarInputWrite, PermissionName::HarInputView],
                    'label' => 'Buka Daily Meeting HAR',
                ],
                'title' => fn ($r): string => (string) $r->uraian,
            ],
            [
                'key' => 'har:patrol-check',
                'module' => 'har',
                'label' => 'Patrol Check',
                'model' => HarJadwalPatrolCheck::class,
                'route' => 'har.jadwal.patrol-check.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'with' => ['employee'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'har.input.lembar.index',
                    'params' => ['lembar' => 'patrol-check-pemeliharaan'],
                    'permissions' => [PermissionName::HarLapanganPatrolCheckPemeliharaan, PermissionName::HarInputWrite, PermissionName::HarInputView],
                    'label' => 'Isi Patrol Check HAR',
                ],
                'title' => fn ($r): string => (string) ($r->employee?->name ?? 'Petugas'),
            ],
            [
                'key' => 'har:piket-on-call',
                'module' => 'har',
                'label' => 'Piket On Call',
                'model' => HarJadwalPiketOnCall::class,
                'route' => 'har.jadwal.piket-on-call.index',
                'period' => 'month',
                'cells' => ['piket'],
                'person' => 'employee_id',
                'requires_input' => false,
                'title' => fn ($r): string => (string) $r->nama,
            ],
            ['key' => 'har:pembuatan-ik', 'module' => 'har', 'label' => 'Pembuatan IK', 'model' => HarJadwalPembuatanIk::class, 'route' => 'har.jadwal.pembuatan-ik.index', 'period' => 'year', 'match' => 'month', 'cells' => ['rencana_bulan'], 'title' => fn ($r): string => (string) $r->instruksi_kerja],
            ['key' => 'har:inventarisasi-tools', 'module' => 'har', 'label' => 'Inventarisasi Tools & Material', 'model' => HarLembarRow::class, 'route' => 'har.jadwal.lembar.index', 'route_params' => ['lembar' => 'inventarisasi-tools'], 'period' => 'month', 'match' => 'w_week', 'cells' => ['cells.main'], 'where' => fn (Builder $q) => $q->where('lembar', 'inventarisasi-tools'), 'title' => fn ($r): string => (string) ($r->fields['uraian'] ?? 'Tools')],
            ['key' => 'har:blackstart', 'module' => 'har', 'label' => 'Pemeriksaan Blackstart', 'model' => HarLembarRow::class, 'route' => 'har.jadwal.lembar.index', 'route_params' => ['lembar' => 'pemeriksaan-blackstart'], 'period' => 'year', 'match' => 'mw_week', 'cells' => ['cells.rencana'], 'where' => fn (Builder $q) => $q->where('lembar', 'pemeriksaan-blackstart'), 'title' => fn ($r): string => (string) ($r->fields['uraian'] ?? 'Blackstart')],

            // K3 & Keamanan
            ['key' => 'k3:instruksi-kerja', 'module' => 'k3', 'label' => 'Instruksi Kerja', 'model' => K3InstruksiKerja::class, 'route' => 'k3.jadwal.instruksi-kerja.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->kegiatan],
            ['key' => 'k3:kegiatan-rutin', 'module' => 'k3', 'label' => 'Kegiatan Rutin', 'model' => K3KegiatanRutin::class, 'route' => 'k3.jadwal.kegiatan-rutin.index', 'period' => 'month', 'cells' => ['jadwal'], 'title' => fn ($r): string => (string) $r->kegiatan],
            [
                'key' => 'k3:on-call',
                'module' => 'k3',
                'label' => 'On Call',
                'model' => K3JadwalOnCall::class,
                'route' => 'k3.jadwal.on-call.index',
                'period' => 'month',
                'match' => 'date',
                'cutoff' => true,
                'cells' => ['schedule'],
                'exclude_codes' => ['L', 'OFF', 'CT', 'A', 'KTA'],
                'person' => 'employee_id',
                'requires_input' => false,
                'title' => fn ($r): string => (string) $r->nama,
            ],
            [
                'key' => 'k3:patrol-check',
                'module' => 'k3',
                'label' => 'Patrol Check',
                'model' => K3PatrolCheckJadwal::class,
                'route' => 'k3.jadwal.patrol-check.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'k3.jadwal.patrol-check.index',
                    'permissions' => [PermissionName::K3InputWrite, PermissionName::K3InputView],
                    'label' => 'Isi Patrol Check K3L',
                ],
                'title' => fn ($r): string => (string) $r->uraian,
            ],
            [
                'key' => 'k3:pekerjaan-rutin',
                'module' => 'k3',
                'label' => 'Pekerjaan Rutin',
                'model' => K3PekerjaanRutin::class,
                'route' => 'k3.jadwal.pekerjaan-rutin.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'k3.jadwal.pekerjaan-rutin.index',
                    'permissions' => [PermissionName::K3InputWrite, PermissionName::K3InputView],
                    'label' => 'Isi Pekerjaan Rutin K3L',
                ],
                'title' => fn ($r): string => (string) $r->uraian,
            ],
            [
                'key' => 'k3:time-frame',
                'module' => 'k3',
                'label' => 'Time Frame K3',
                'model' => K3ActivityPlan::class,
                'route' => 'k3.input.time-frame.index',
                'period' => 'month',
                'cells' => ['plan_days'],
                'with' => ['activityType'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'k3.input.time-frame.index',
                    'permissions' => [PermissionName::K3InputWrite, PermissionName::K3InputView],
                    'label' => 'Isi Time Frame K3',
                ],
                'title' => fn ($r): string => (string) ($r->activityType?->name ?? 'Time Frame K3'),
            ],
            ['key' => 'k3:pembuatan-ik', 'module' => 'k3', 'label' => 'Pembuatan IK', 'model' => K3JadwalPembuatanIk::class, 'route' => 'k3.jadwal.pembuatan-ik.index', 'period' => 'year', 'match' => 'month', 'cells' => ['rencana_bulan'], 'title' => fn ($r): string => (string) $r->instruksi_kerja],

            // PdM & MATLEV
            ['key' => 'pdm:harian', 'module' => 'pdm', 'label' => 'Kegiatan PdM', 'model' => PdmJadwalHarian::class, 'route' => 'pdm.jadwal.harian.index', 'period' => 'month', 'cells' => ['jadwal'], 'where' => fn (Builder $q) => $q->where('is_category_header', false), 'title' => fn ($r): string => (string) $r->kegiatan],
            [
                'key' => 'pdm:patrol-check',
                'module' => 'pdm',
                'label' => 'Patrol Check',
                'model' => PdmJadwalPatrolCheck::class,
                'route' => 'pdm.jadwal.patrol-check.index',
                'period' => 'month',
                'cells' => ['jadwal'],
                'where' => fn (Builder $q) => $q->where('is_category_header', false),
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'pdm.input.forms.index',
                    'params' => ['form' => 'patrol-check-pdm'],
                    'permissions' => [PermissionName::PdmInputWrite, PermissionName::PdmInputView],
                    'label' => 'Isi Patrol Check PdM',
                ],
                'title' => fn ($r): string => (string) $r->nama,
            ],
            [
                'key' => 'pdm:5s-5r',
                'module' => 'pdm',
                'label' => '5S 5R',
                'model' => PdmJadwal5s5r::class,
                'route' => 'pdm.jadwal.program-5s-5r.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'pdm.input.forms.index',
                    'params' => ['form' => 'checklist-5s5r'],
                    'permissions' => [PermissionName::PdmInputWrite, PermissionName::PdmInputView],
                    'label' => 'Isi Inspeksi 5S5R PdM',
                ],
                'title' => fn ($r): string => (string) $r->uraian,
            ],
            [
                'key' => 'pdm:meeting',
                'module' => 'pdm',
                'label' => 'Meeting',
                'model' => PdmJadwalMeeting::class,
                'route' => 'pdm.jadwal.meeting.index',
                'period' => 'month',
                'cells' => ['rencana'],
                'person' => 'employee_id',
                'requires_input' => true,
                'action' => [
                    'route' => 'pdm.jadwal.meeting.index',
                    'permissions' => [PermissionName::PdmInputWrite, PermissionName::PdmInputView],
                    'label' => 'Isi Realisasi Meeting PdM',
                ],
                'title' => fn ($r): string => (string) $r->uraian,
            ],
        ];

        // Logistik & Gudang: the Jadwal sheets whose codes plan a date (R rencana, D rencana & realisasi, P shift pagi).
        foreach (LogistikJadwal::SHEETS as $sheet => $definition) {
            $layout = $definition['layout'];
            if ($definition['menu'] !== 'jadwal' || ! in_array($layout, ['kegiatan', 'pelaksana', 'shift', 'ik'], true)) {
                continue;
            }

            $yearly = $layout === 'ik';
            $entry = [
                'key' => "logistik:{$sheet}",
                'module' => 'logistik',
                'label' => $definition['title'],
                'model' => LogistikJadwalRow::class,
                'route' => 'logistik.jadwal.sheet.index',
                'route_params' => ['jadwal' => $sheet],
                'period' => $yearly ? 'year' : 'month',
                'match' => $yearly ? 'month' : 'day',
                'cells' => ['days'],
                'codes' => $layout === 'shift' ? ['P'] : ['R', 'D'],
                'where' => fn (Builder $q) => $q->where('jadwal', $sheet)->whereNotNull('nama')->where('nama', '!=', ''),
                'title' => fn ($r): string => (string) $r->nama,
            ];

            if ($layout === 'shift') {
                $entry['person'] = 'employee_id';
                $entry['requires_input'] = false;
            } elseif ($layout === 'pelaksana') {
                $entry['person'] = 'employee_id';
                $entry['requires_input'] = $sheet !== 'piket';
                if ($sheet === '5s5r') {
                    $entry['action'] = [
                        'route' => 'logistik.input.sheet.index',
                        'params' => ['jadwal' => 'inspeksi-5s5r'],
                        'permissions' => [PermissionName::LogistikInputWrite, PermissionName::LogistikInputView],
                        'label' => 'Isi Inspeksi 5S5R Logistik',
                    ];
                } elseif ($sheet === 'piket-patrol-check') {
                    $entry['action'] = [
                        'route' => 'logistik.input.sheet.index',
                        'params' => ['jadwal' => 'patrol-check'],
                        'permissions' => [PermissionName::LogistikInputWrite, PermissionName::LogistikInputView],
                        'label' => 'Isi Patrol Check Logistik',
                    ];
                } elseif ($sheet !== 'piket') {
                    $entry['action'] = [
                        'route' => 'logistik.jadwal.sheet.index',
                        'params' => ['jadwal' => $sheet],
                        'permissions' => [PermissionName::LogistikInputWrite, PermissionName::LogistikInputView],
                        'label' => 'Isi Realisasi',
                    ];
                }
            }

            $tables[] = $entry;
        }

        return $tables;
    }

    /**
     * Find one registered jadwal entry by its stable key.
     *
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        foreach ($this->tables() as $table) {
            if ($table['key'] === $key) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Extract regu letters ('A'..'D') from strings like "B", "Shift B", "Regu B", or "A/B".
     * Returns an empty list for non-regu placeholders such as "JADWAL".
     *
     * @return list<string>
     */
    public static function extractReguCodes(?string $raw): array
    {
        $text = strtoupper(trim((string) $raw));
        if ($text === '') {
            return [];
        }

        $text = trim((string) preg_replace('/\b(OPERATOR|SHIFT|REGU)\b/i', ' ', $text));
        if ($text === '') {
            return [];
        }

        if (preg_match('/^[A-D](?:\s*[\/,&+\s-]\s*[A-D])*$/', $text) !== 1) {
            return [];
        }

        preg_match_all('/[A-D]/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
