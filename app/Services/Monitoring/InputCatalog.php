<?php

namespace App\Services\Monitoring;

use App\Support\HarLembar\HarLembars;
use App\Support\HarTabel\HarTabels;
use App\Support\K3FormulirRegistry;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikJadwal;
use App\Support\PdmForms\PdmForms;

/**
 * Every input of every module the Monitoring checks for completeness, read
 * straight from its table (no model needed): how a unit fills it in a month
 * and the page that opens it.
 *
 * Kinds:
 * - month   — rows with year & month (jadwal, monthly forms): filled or not;
 * - year    — yearly plans (year only): filled or not;
 * - date    — rows dated inside the month (`date` column): filled or not;
 * - daily   — one entry per day (`date` column): days filled ÷ days due;
 * - daily_day — like daily, but the day is an integer `day` column with year & month;
 * - daily_engine — one entry per machine per day: (machine, day) filled ÷ machines × days due;
 * - period  — rows of a report period (report_period_id → report_periods);
 * - count   — distinct values of `distinct` with year & month ÷ `expected` (e.g. 3 Berita Acara).
 * `where` adds equality filters (e.g. a form key in a shared table).
 */
class InputCatalog
{
    /** Group key => label, in display order. */
    public const GROUPS = [
        'operasi' => 'Operasi — Akses 1',
        'operasi_pengusahaan' => 'Operasi — Pengusahaan',
        'operator' => 'Operator',
        'har' => 'Pemeliharaan — Akses 1',
        'har_pengusahaan' => 'Pemeliharaan — Pengusahaan',
        'k3' => 'K3 & Keamanan — Akses 1',
        'k3_pengusahaan' => 'K3 & Keamanan — Pengusahaan',
        'logistik' => 'Logistik & Gudang',
        'pdm' => 'PdM & Maturity Level',
    ];

    /**
     * @return list<array{key: string, group: string, label: string, table: string, kind: string, date?: string, day?: string, distinct?: string, expected?: int, where?: array<string, int|string>, route: string, params?: array<string, string>, period: 'harian'|'bulanan'|'tahunan'}>
     */
    public function entries(): array
    {
        $entries = [
            // Operasi — Akses 1 (Koordinator Operasi): jadwal & input.
            ...$this->monthly('operasi', [
                ['operasi_flm_jadwals', 'Jadwal FLM', 'operasi.jadwal.flm.index'],
                ['operasi_5s5r_jadwals', 'Jadwal Program 5S 5R', 'operasi.jadwal.program-5s-5r.index'],
                ['operasi_meeting_shift_jadwals', 'Jadwal Meeting Shift', 'operasi.jadwal.meeting-shift.index'],
                ['operasi_inventaris_jadwals', 'Jadwal Inventarisasi Tools', 'operasi.jadwal.inventarisasi-tools.index'],
                ['operasi_commissioning_tests', 'Jadwal Commissioning Test Mesin', 'operasi.jadwal.commissioning-test.index'],
                ['kondisi_abnormals', 'Kondisi Abnormal & Gangguan', 'operasi.input.kondisi-abnormal.index'],
                ['operasi_material_peralatans', 'Material & Peralatan', 'operasi.input.material-peralatan.index'],
                ['operasi_permit_to_works', 'Permit to Work', 'operasi.input.permit-to-work.index'],
                ['operasi_flm_monitorings', 'Monitoring FLM', 'operasi.input.flm-monitoring.index'],
                ['operasi_patrol_check_mesins', 'Patrol Check Mesin', 'operasi.input.patrol-check-mesin.index'],
                ['operasi_program_5s5r_items', 'Program 5S 5R Pengoperasian', 'operasi.input.program-5s5r.index'],
            ]),
            ...$this->yearly('operasi', [
                ['operasi_pembuatan_iks', 'Jadwal Pembuatan IK', 'operasi.jadwal.pembuatan-ik.index'],
                ['operasi_data_tekniss', 'Jadwal Pembuatan Data Teknis', 'operasi.jadwal.pembuatan-data-teknis.index'],
                ['operasi_blackstart_jadwals', 'Jadwal Pemeriksaan Blackstart', 'operasi.jadwal.blackstart.index'],
                ['operasi_comm_peralatans', 'Jadwal Commissioning Peralatan', 'operasi.jadwal.commissioning-test-peralatan.index'],
                ['operasi_performance_test_mesins', 'Jadwal Performance Test Mesin', 'operasi.jadwal.performance-test.index'],
            ]),
            $this->entry('operasi', 'operasi_checklist_commissioning_mesins', 'Checklist Commissioning Mesin', 'operasi.input.checklist-commissioning-mesin.index', 'date', ['date' => 'tanggal']),
            $this->entry('operasi', 'operasi_instruksi_kerjas', 'Instruksi Kerja (IK)', 'operasi.input.instruksi-kerja.index', 'date', ['date' => 'tanggal']),

            // Operasi — Pengusahaan (TL & Staf Operasi).
            $this->entry('operasi_pengusahaan', 'daily_engine_reports', 'Input Harian (per mesin)', 'operasi.pengusahaan.daily-report.index', 'daily_engine', ['date' => 'report_date']),
            $this->entry('operasi_pengusahaan', 'daily_feeder_readings', 'Feeder', 'operasi.pengusahaan.feeder.index', 'daily', ['date' => 'report_date']),
            $this->entry('operasi_pengusahaan', 'daily_auxiliary_readings', 'Pasokan Cadangan', 'operasi.pengusahaan.auxiliary.index', 'daily', ['date' => 'report_date']),
            $this->entry('operasi_pengusahaan', 'operasi_resource_pembangkits', 'Resource Pembangkit', 'operasi.pengusahaan.resource-pembangkit.index', 'daily_day', ['day' => 'tanggal']),
            $this->entry('operasi_pengusahaan', 'engine_status_logs', 'Star-Stop Mesin', 'operasi.pengusahaan.star-stop.index', 'date', ['date' => 'report_date']),
            $this->entry('operasi_pengusahaan', 'fuel_receipts', 'Penerimaan BBM', 'operasi.pengusahaan.fuel-receipt.index', 'date', ['date' => 'report_date']),
            $this->entry('operasi_pengusahaan', 'document_records', 'Berita Acara BBM & Pelumas (3 BA)', 'operasi.pengusahaan.berita-acara.index', 'count', ['distinct' => 'type', 'expected' => 3]),

            // Operator.
            $this->entry('operator', 'operator_logsheets', 'Logsheet Operator (per mesin)', 'operator.logsheet.index', 'daily_engine', ['date' => 'log_date']),
            $this->entry('operator', 'operator_mutasis', 'Lembar Mutasi Operator', 'operator.mutasi.index', 'daily', ['date' => 'tanggal']),
            $this->entry('operator', 'employee_presences', 'Presensi (hari ada absen)', 'operator.absensi.index', 'daily', ['date' => 'work_date']),
            $this->entry('operator', 'work_schedules', 'Jadwal Shift', 'operator.absensi.index', 'month'),

            // Pemeliharaan — Akses 1.
            ...$this->monthly('har', [
                ['har_jadwal_harians', 'Jadwal Kegiatan Harian', 'har.jadwal.harian.index'],
                ['har_jadwal_p0_p5s', 'Jadwal P0 - P5', 'har.jadwal.p0-p5.index'],
                ['har_jadwal_piket_on_calls', 'Jadwal Piket On Call', 'har.jadwal.piket-on-call.index'],
                ['har_jadwal_patrol_checks', 'Jadwal Patrol Check', 'har.jadwal.patrol-check.index'],
                ['har_jadwal_meeting_pemeliharaans', 'Jadwal Meeting Pemeliharaan', 'har.jadwal.meeting-pemeliharaan.index'],
                ['har_unsafe_conditions', 'Unsafe Action & Condition', 'har.input.unsafe-condition.index'],
                ['har_program_5s5r_items', 'Program 5S 5R', 'har.input.program-5s5r.index'],
                ['har_daily_meetings', 'Daily Meeting', 'har.formulir.daily-meeting.index'],
                ['har_logbook_mutasis', 'Logbook Mutasi', 'har.formulir.logbook-mutasi.index'],
            ]),
            ...$this->yearly('har', [['har_jadwal_pembuatan_iks', 'Jadwal Pembuatan IK', 'har.jadwal.pembuatan-ik.index']]),
            ...array_map(fn ($lembar): array => $this->entry('har', 'har_lembar_rows', $lembar->title(), "har.{$lembar->menu()}.lembar.index", 'month', ['where' => ['lembar' => $lembar->key()], 'params' => ['lembar' => $lembar->key()]]), HarLembars::all()),
            ...array_map(fn ($tabel): array => $this->entry('har', 'har_tabel_rows', $tabel->title(), "har.input.{$tabel->key()}.index", 'month', ['where' => ['tabel' => $tabel->key()]]), HarTabels::all()),
            $this->entry('har', 'work_orders', 'Work Order', 'har.input.work-order.index', 'period'),
            $this->entry('har', 'service_requests', 'Service Request', 'har.input.service-request.index', 'period'),
            $this->entry('har', 'maintenance_activities', 'Aktivitas Pemeliharaan', 'har.input.activity.index', 'period'),
            $this->entry('har', 'har_instruksi_kerjas', 'Instruksi Kerja (IK)', 'har.input.instruksi-kerja.index', 'date', ['date' => 'tanggal']),
            $this->entry('har', 'har_laporan_gangguans', 'Formulir Laporan Gangguan', 'har.formulir.laporan-gangguan.index', 'date', ['date' => 'tanggal_laporan']),

            // Pemeliharaan — Pengusahaan (TL & Staf): pengukuran & laporan kegiatan.
            ...array_map(fn (array $m): array => $this->entry('har_pengusahaan', $m[0], $m[1], "har.pengusahaan.{$m[2]}.index", 'date', ['date' => 'test_date']), [
                ['har_prelube_tests', 'Checklist Prelube Test', 'prelube-test'],
                ['har_hydrotests', 'Checklist Hydrotest', 'hydrotest'],
                ['har_timing_injection_pumps', 'Timing Injection Pump', 'timing-injection-pump'],
                ['har_crankshaft_deflections', 'Defleksi Crankshaft', 'crankshaft-deflection'],
                ['har_counter_weights', 'Baut Counter Weight', 'counter-weight'],
                ['har_axial_conrods', 'Axial Conrod & Baut Conrod', 'axial-conrod'],
                ['har_clearance_valves', 'Clearance Valve', 'clearance-valve'],
                ['har_combustion_pressures', 'Tekanan Pembakaran', 'combustion-pressure'],
                ['har_injector_pressures', 'Tekanan Pengabutan Injektor', 'injector-pressure'],
                ['har_motor_currents', 'Arus Kerja Elektro Motor', 'motor-current'],
                ['har_vibrations', 'Vibrasi', 'vibration'],
                ['har_lube_qualities', 'Kualitas Pelumas', 'lube-quality'],
                ['har_battery_voltages', 'Tegangan Baterai', 'battery-voltage'],
            ]),
            $this->entry('har_pengusahaan', 'har_laporan_kegiatans', 'Laporan Kegiatan', 'har.pengusahaan.laporan-kegiatan.index', 'month'),
            $this->entry('har_pengusahaan', 'maintenance_schedules', 'Rencana & Realisasi', 'har.pengusahaan.rencana-realisasi.index', 'month'),

            // K3 & Keamanan — Akses 1.
            ...$this->monthly('k3', [
                ['k3_instruksi_kerjas', 'Jadwal Instruksi Kerja', 'k3.jadwal.instruksi-kerja.index'],
                ['k3_kegiatan_rutins', 'Jadwal Kegiatan Rutin', 'k3.jadwal.kegiatan-rutin.index'],
                ['k3_jadwal_on_calls', 'Jadwal On Call', 'k3.jadwal.on-call.index'],
                ['k3_patrol_check_jadwals', 'Jadwal Patrol Check', 'k3.jadwal.patrol-check.index'],
                ['k3_pekerjaan_rutins', 'Jadwal Pekerjaan Rutin', 'k3.jadwal.pekerjaan-rutin.index'],
                ['accident_reports', 'Laporan Kecelakaan', 'k3.input.accident.index'],
                ['k3_air_limbahs', 'Air Limbah', 'k3.input.air-limbah.index'],
                ['fire_extinguisher_checks', 'Pemeriksaan APAR', 'k3.input.apar-check.index'],
                ['k3_apd_inventories', 'Inventaris APD', 'k3.input.apd-inventory.index'],
                ['k3_cctv_lists', 'CCTV', 'k3.input.cctv.index'],
                ['k3_dokumen_iks', 'Dokumen IK', 'k3.input.dokumen-ik.index'],
                ['k3_emergency_facility_checks', 'Emergency Facility', 'k3.input.emergency-facility.index'],
                ['k3_fire_alarm_inspections', 'Fire Alarm', 'k3.input.fire-alarm.index'],
                ['k3_hydrant_inspections', 'Hydrant', 'k3.input.hydrant.index'],
                ['inspections', 'Inspeksi', 'k3.input.inspection.index'],
                ['k3_kesiapan_apds', 'Kesiapan APD', 'k3.input.kesiapan-apd.index'],
                ['k3_kondisi_k3s', 'Kondisi K3', 'k3.input.kondisi-k3.index'],
                ['k3_rambu_inspections', 'Inspeksi Rambu', 'k3.input.rambu.index'],
                ['k3_activity_plans', 'Time Frame', 'k3.input.time-frame.index'],
                ['k3_metode_pengujian_peralatans', 'Formulir Metode Pengujian', 'k3.formulir.metode-pengujian.index'],
            ]),
            ...$this->yearly('k3', [['k3_jadwal_pembuatan_iks', 'Jadwal Pembuatan IK', 'k3.jadwal.pembuatan-ik.index']]),
            $this->entry('k3', 'security_patrols', 'Patrol Keamanan', 'k3.input.patrol.index', 'date', ['date' => 'patrol_date']),
            ...array_map(fn (string $form): array => $this->entry('k3', 'k3_formulir_records', 'Formulir '.K3FormulirRegistry::get($form)['title'], 'k3.formulir.record.index', 'month', ['where' => ['form' => $form], 'params' => ['form' => $form]]), K3FormulirRegistry::keys()),

            // K3 & Keamanan — Pengusahaan.
            ...$this->monthly('k3_pengusahaan', [
                ['k3_pengusahaan_time_frames', 'Time Frame Kinerja', 'k3.pengusahaan.time-frame.index'],
                ['k3_pengusahaan_kecelakaan_instalasis', 'Kecelakaan Instalasi', 'k3.pengusahaan.kecelakaan-instalasi.index'],
                ['k3_pengusahaan_kecelakaan_masyarakats', 'Kecelakaan Masyarakat', 'k3.pengusahaan.kecelakaan-masyarakat.index'],
                ['k3_pengusahaan_jam_kerjas', 'Jam Kerja', 'k3.pengusahaan.jam-kerja.index'],
                ['k3_pengusahaan_jam_kerja_bulanans', 'Jam Kerja Bulanan', 'k3.pengusahaan.jam-kerja-bulanan.index'],
                ['k3_pengusahaan_apar_apabs', 'APAR & APAB', 'k3.pengusahaan.apar-apab.index'],
                ['k3_pengusahaan_apats', 'APAT', 'k3.pengusahaan.apat.index'],
                ['k3_pengusahaan_hydrants', 'Hydrant', 'k3.pengusahaan.hydrant.index'],
                ['k3_pengusahaan_fire_alarms', 'Fire Alarm', 'k3.pengusahaan.fire-alarm.index'],
                ['k3_pengusahaan_alat_tanggap_darurats', 'Alat Tanggap Darurat', 'k3.pengusahaan.alat-tanggap-darurat.index'],
                ['k3_pengusahaan_emergency_facilities', 'Emergency Facility', 'k3.pengusahaan.emergency-facility.index'],
                ['k3_pengusahaan_pemeriksaan_p3ks', 'Pemeriksaan P3K', 'k3.pengusahaan.pemeriksaan-p3k.index'],
                ['k3_pengusahaan_inventaris_apds', 'Inventaris APD', 'k3.pengusahaan.inventaris-apd.index'],
                ['k3_pengusahaan_inspeksi_rambus', 'Inspeksi Rambu', 'k3.pengusahaan.inspeksi-rambu.index'],
                ['k3_pengusahaan_inspeksi_tempat_kerjas', 'Inspeksi Tempat Kerja', 'k3.pengusahaan.inspeksi-tempat-kerja.index'],
                ['k3_pengusahaan_patrol_securities', 'Patrol Security', 'k3.pengusahaan.patrol-security.index'],
                ['k3_pengusahaan_apel_keamanans', 'Apel Keamanan', 'k3.pengusahaan.apel-keamanan.index'],
                ['k3_pengusahaan_laporan_cctvs', 'Laporan CCTV', 'k3.pengusahaan.laporan-cctv.index'],
                ['k3_pengusahaan_buku_tamus', 'Buku Tamu', 'k3.pengusahaan.buku-tamu.index'],
                ['k3_pengusahaan_metode_pengujians', 'Metode Pengujian', 'k3.pengusahaan.metode-pengujian.index'],
            ]),
            ...$this->yearly('k3_pengusahaan', [['k3_pengusahaan_evaluasi_pengujians', 'Evaluasi Pengujian', 'k3.pengusahaan.evaluasi-pengujian.index']]),

            // Logistik & Gudang: grid sheets, forms and rekomendasi.
            ...array_map(fn (string $sheet): array => $this->entry(
                'logistik', 'logistik_jadwal_rows', LogistikJadwal::SHEETS[$sheet]['title'],
                LogistikJadwal::SHEETS[$sheet]['menu'] === 'jadwal' ? 'logistik.jadwal.sheet.index' : 'logistik.input.sheet.index',
                LogistikJadwal::SHEETS[$sheet]['layout'] === 'ik' ? 'year' : 'month',
                ['where' => ['jadwal' => $sheet], 'params' => ['jadwal' => $sheet]],
            ), array_keys(LogistikJadwal::SHEETS)),
            ...array_map(fn ($form): array => $this->entry('logistik', 'logistik_form_rows', $form->title(), 'logistik.input.form.index', 'month', ['where' => ['form' => $form->key()], 'params' => ['form' => $form->key()]]), LogistikForms::all()),
            $this->entry('logistik', 'logistik_rekomendasis', 'Rekomendasi', 'logistik.input.rekomendasi.index', 'month'),

            // PdM & Maturity Level.
            ...$this->monthly('pdm', [
                ['pdm_jadwal_harians', 'Jadwal Kegiatan PdM', 'pdm.jadwal.harian.index'],
                ['pdm_jadwal_patrol_checks', 'Jadwal Patrol Check PdM', 'pdm.jadwal.patrol-check.index'],
                ['pdm_jadwal_5s5rs', 'Jadwal Program 5S 5R', 'pdm.jadwal.program-5s-5r.index'],
                ['pdm_jadwal_meetings', 'Jadwal Meeting PdM', 'pdm.jadwal.meeting.index'],
                ['pdm_kesiapan_apds', 'Kesiapan APD', 'pdm.input.kesiapan-apd.index'],
                ['pdm_sample_monitorings', 'Monitoring Sample', 'pdm.input.sample-monitoring.index'],
                ['pdm_permit_to_works', 'Permit to Work', 'pdm.input.permit-to-work.index'],
                ['pdm_realisasi_prediktifs', 'Realisasi Prediktif', 'pdm.input.realisasi-prediktif.index'],
            ]),
            ...array_map(fn ($form): array => $this->entry('pdm', 'pdm_form_documents', $form->title(), 'pdm.input.forms.index', 'month', ['where' => ['form' => $form->key()], 'params' => ['form' => $form->key()]]), PdmForms::all()),
        ];

        return array_values($entries);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $items  table, label, route
     * @return list<array<string, mixed>>
     */
    private function monthly(string $group, array $items): array
    {
        return array_map(fn (array $i): array => $this->entry($group, $i[0], $i[1], $i[2], 'month'), $items);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $items  table, label, route
     * @return list<array<string, mixed>>
     */
    private function yearly(string $group, array $items): array
    {
        return array_map(fn (array $i): array => $this->entry($group, $i[0], $i[1], $i[2], 'year'), $items);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function entry(string $group, string $table, string $label, string $route, string $kind, array $options = []): array
    {
        $where = $options['where'] ?? [];

        return [
            'key' => $group.':'.$table.($where === [] ? '' : ':'.implode(',', $where)),
            'group' => $group,
            'label' => $label,
            'table' => $table,
            'kind' => $kind,
            'route' => $route,
            'period' => match ($kind) {
                'daily', 'daily_day', 'daily_engine' => 'harian',
                'year' => 'tahunan',
                default => 'bulanan',
            },
            ...$options,
        ];
    }
}
