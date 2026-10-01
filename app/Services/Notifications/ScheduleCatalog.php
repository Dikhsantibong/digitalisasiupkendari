<?php

namespace App\Services\Notifications;

use App\Enums\PermissionName;
use App\Models\HarJadwalHarian;
use App\Models\HarJadwalMeetingPemeliharaan;
use App\Models\HarJadwalP0P5;
use App\Models\HarJadwalPatrolCheck;
use App\Models\HarJadwalPembuatanIk;
use App\Models\HarJadwalPiketOnCall;
use App\Models\K3InstruksiKerja;
use App\Models\K3JadwalOnCall;
use App\Models\K3JadwalPembuatanIk;
use App\Models\K3KegiatanRutin;
use App\Models\K3PatrolCheckJadwal;
use App\Models\K3PekerjaanRutin;
use App\Models\LogistikJadwalRow;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiBlackstartJadwal;
use App\Models\OperasiCommPeralatan;
use App\Models\OperasiDataTeknis;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiInventarisJadwal;
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
     *     module: string,
     *     label: string,
     *     model: class-string<Model>,
     *     route: string,
     *     route_params?: array<string, string>,
     *     period: 'month'|'year',
     *     match?: 'month'|'week',
     *     cells: list<string>,
     *     codes?: list<string>,
     *     title: callable(Model): string,
     *     where?: callable(Builder<Model>): void,
     *     person?: string,
     *     with?: list<string>,
     * }>
     */
    public function tables(): array
    {
        $tables = [
            // Operasi
            ['module' => 'operasi', 'label' => '5S 5R', 'model' => Operasi5s5rJadwal::class, 'route' => 'operasi.jadwal.program-5s-5r.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->pelaksana],
            ['module' => 'operasi', 'label' => 'Inventarisasi Tools', 'model' => OperasiInventarisJadwal::class, 'route' => 'operasi.jadwal.inventarisasi-tools.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['module' => 'operasi', 'label' => 'FLM', 'model' => OperasiFlmJadwal::class, 'route' => 'operasi.jadwal.flm.index', 'period' => 'month', 'cells' => ['days'], 'where' => fn (Builder $q) => $q->where('row_type', 'shift'), 'title' => fn ($r): string => (string) $r->label],
            ['module' => 'operasi', 'label' => 'Meeting Shift', 'model' => OperasiMeetingShiftJadwal::class, 'route' => 'operasi.jadwal.meeting-shift.index', 'period' => 'month', 'cells' => ['days'], 'where' => fn (Builder $q) => $q->where('row_type', 'shift'), 'title' => fn ($r): string => (string) $r->label],
            ['module' => 'operasi', 'label' => 'Pembuatan IK', 'model' => OperasiPembuatanIk::class, 'route' => 'operasi.jadwal.pembuatan-ik.index', 'period' => 'year', 'match' => 'month', 'cells' => ['months'], 'title' => fn ($r): string => (string) $r->nama],
            ['module' => 'operasi', 'label' => 'Data Teknis', 'model' => OperasiDataTeknis::class, 'route' => 'operasi.jadwal.pembuatan-data-teknis.index', 'period' => 'year', 'match' => 'month', 'cells' => ['months'], 'title' => fn ($r): string => (string) $r->nama],
            ['module' => 'operasi', 'label' => 'Blackstart', 'model' => OperasiBlackstartJadwal::class, 'route' => 'operasi.jadwal.blackstart.index', 'period' => 'year', 'match' => 'week', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['module' => 'operasi', 'label' => 'Commissioning Peralatan', 'model' => OperasiCommPeralatan::class, 'route' => 'operasi.jadwal.commissioning-test-peralatan.index', 'period' => 'year', 'match' => 'week', 'cells' => ['beban_50', 'beban_75', 'beban_100'], 'title' => fn ($r): string => (string) $r->nama_peralatan],
            ['module' => 'operasi', 'label' => 'Performance Test', 'model' => OperasiPerformanceTestMesin::class, 'route' => 'operasi.jadwal.performance-test.index', 'period' => 'year', 'match' => 'week', 'cells' => ['beban_50', 'beban_75', 'beban_100'], 'title' => fn ($r): string => (string) $r->nama_mesin],

            // Pemeliharaan
            ['module' => 'har', 'label' => 'Kegiatan Harian', 'model' => HarJadwalHarian::class, 'route' => 'har.jadwal.harian.index', 'period' => 'month', 'cells' => ['jadwal'], 'title' => fn ($r): string => (string) $r->kegiatan],
            ['module' => 'har', 'label' => 'P0 - P5', 'model' => HarJadwalP0P5::class, 'route' => 'har.jadwal.p0-p5.index', 'period' => 'month', 'cells' => ['rencana'], 'with' => ['machine'], 'title' => fn ($r): string => (string) ($r->machine?->name ?? 'Mesin')],
            ['module' => 'har', 'label' => 'Meeting Pemeliharaan', 'model' => HarJadwalMeetingPemeliharaan::class, 'route' => 'har.jadwal.meeting-pemeliharaan.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['module' => 'har', 'label' => 'Patrol Check', 'model' => HarJadwalPatrolCheck::class, 'route' => 'har.jadwal.patrol-check.index', 'period' => 'month', 'cells' => ['rencana'], 'with' => ['employee'], 'person' => 'employee_id', 'title' => fn ($r): string => (string) ($r->employee?->name ?? 'Petugas')],
            ['module' => 'har', 'label' => 'Piket On Call', 'model' => HarJadwalPiketOnCall::class, 'route' => 'har.jadwal.piket-on-call.index', 'period' => 'month', 'cells' => ['piket'], 'person' => 'employee_id', 'title' => fn ($r): string => (string) $r->nama],
            ['module' => 'har', 'label' => 'Pembuatan IK', 'model' => HarJadwalPembuatanIk::class, 'route' => 'har.jadwal.pembuatan-ik.index', 'period' => 'year', 'match' => 'month', 'cells' => ['rencana_bulan'], 'title' => fn ($r): string => (string) $r->instruksi_kerja],

            // K3 & Keamanan
            ['module' => 'k3', 'label' => 'Instruksi Kerja', 'model' => K3InstruksiKerja::class, 'route' => 'k3.jadwal.instruksi-kerja.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->kegiatan],
            ['module' => 'k3', 'label' => 'Kegiatan Rutin', 'model' => K3KegiatanRutin::class, 'route' => 'k3.jadwal.kegiatan-rutin.index', 'period' => 'month', 'cells' => ['jadwal'], 'title' => fn ($r): string => (string) $r->kegiatan],
            ['module' => 'k3', 'label' => 'On Call', 'model' => K3JadwalOnCall::class, 'route' => 'k3.jadwal.on-call.index', 'period' => 'month', 'cells' => ['schedule'], 'person' => 'employee_id', 'title' => fn ($r): string => (string) $r->nama],
            ['module' => 'k3', 'label' => 'Patrol Check', 'model' => K3PatrolCheckJadwal::class, 'route' => 'k3.jadwal.patrol-check.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['module' => 'k3', 'label' => 'Pekerjaan Rutin', 'model' => K3PekerjaanRutin::class, 'route' => 'k3.jadwal.pekerjaan-rutin.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['module' => 'k3', 'label' => 'Pembuatan IK', 'model' => K3JadwalPembuatanIk::class, 'route' => 'k3.jadwal.pembuatan-ik.index', 'period' => 'year', 'match' => 'month', 'cells' => ['rencana_bulan'], 'title' => fn ($r): string => (string) $r->instruksi_kerja],

            // PdM & MATLEV
            ['module' => 'pdm', 'label' => 'Kegiatan PdM', 'model' => PdmJadwalHarian::class, 'route' => 'pdm.jadwal.harian.index', 'period' => 'month', 'cells' => ['jadwal'], 'where' => fn (Builder $q) => $q->where('is_category_header', false), 'title' => fn ($r): string => (string) $r->kegiatan],
            ['module' => 'pdm', 'label' => 'Patrol Check', 'model' => PdmJadwalPatrolCheck::class, 'route' => 'pdm.jadwal.patrol-check.index', 'period' => 'month', 'cells' => ['jadwal'], 'where' => fn (Builder $q) => $q->where('is_category_header', false), 'person' => 'employee_id', 'title' => fn ($r): string => (string) $r->nama],
            ['module' => 'pdm', 'label' => '5S 5R', 'model' => PdmJadwal5s5r::class, 'route' => 'pdm.jadwal.program-5s-5r.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
            ['module' => 'pdm', 'label' => 'Meeting', 'model' => PdmJadwalMeeting::class, 'route' => 'pdm.jadwal.meeting.index', 'period' => 'month', 'cells' => ['rencana'], 'title' => fn ($r): string => (string) $r->uraian],
        ];

        // Logistik & Gudang: the Jadwal sheets whose codes plan a date (R rencana, D rencana & realisasi).
        foreach (LogistikJadwal::SHEETS as $sheet => $definition) {
            if ($definition['menu'] !== 'jadwal' || ! in_array($definition['layout'], ['kegiatan', 'pelaksana', 'ik'], true)) {
                continue;
            }

            $yearly = $definition['layout'] === 'ik';
            $tables[] = [
                'module' => 'logistik',
                'label' => $definition['title'],
                'model' => LogistikJadwalRow::class,
                'route' => 'logistik.jadwal.sheet.index',
                'route_params' => ['jadwal' => $sheet],
                'period' => $yearly ? 'year' : 'month',
                'match' => 'month',
                'cells' => ['days'],
                'codes' => ['R', 'D'],
                'where' => fn (Builder $q) => $q->where('jadwal', $sheet)->whereNotNull('nama')->where('nama', '!=', ''),
                'title' => fn ($r): string => (string) $r->nama,
            ];
        }

        return $tables;
    }
}
