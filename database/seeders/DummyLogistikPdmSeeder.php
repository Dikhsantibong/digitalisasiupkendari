<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikJadwal;
use App\Support\PdmForms\PdmForms;
use App\Support\PdmKesiapanApdForm;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds comprehensive, realistic dummy data for:
 * - Modul Logistik & Gudang (13 grid sheets in logistik_jadwal_rows, 6 table forms in logistik_form_rows, logistik_rekomendasis)
 * - Modul PdM & Maturity Level (6-month trend for pdm_realisasi_prediktifs, 4 jadwal tables + meta, kesiapan APD, sample monitoring, PTW, APD inventory, and all 7 PdmForms in pdm_form_documents + pdm_form_items)
 */
class DummyLogistikPdmSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $year = (int) $now->year;
        $curMonth = (int) $now->month;
        $prevMonth = $curMonth === 1 ? 12 : $curMonth - 1;
        $prevYear = $curMonth === 1 ? $year - 1 : $year;

        $units = Unit::query()->where('is_active', true)->orderBy('id')->get();
        $fallbackUser = User::query()->first();

        foreach ($units as $unit) {
            $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('id')->get();
            if ($machines->isEmpty()) {
                continue;
            }

            $koordLog = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::KoordinatorLogistik->value)->first();
            $officeLog = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::OfficeLogistik->value)->first();
            $koordPdm = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::KoordinatorPdm->value)->first();
            $picPdm = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::PicPdm->value)->first();
            $officePdm = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::OfficePdm->value)->first();
            $tlHar = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::TeamLeaderPemeliharaan->value)->first();
            $pl = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::ProjectLeader->value)->first();
            $mgrUl = Employee::query()
                ->where('position', EmployeePosition::ManagerUl->value)
                ->where(function ($q) use ($unit) {
                    $q->where('service_unit_id', $unit->service_unit_id)->orWhere('unit_id', $unit->id);
                })
                ->first();

            $logUserId = $officeLog?->user?->id ?? $koordLog?->user?->id ?? $fallbackUser?->id;
            $pdmUserId = $picPdm?->user?->id ?? $officePdm?->user?->id ?? $koordPdm?->user?->id ?? $fallbackUser?->id;

            $signers = [
                'mgr_id' => $mgrUl?->id,
                'mgr_name' => $mgrUl?->name ?? 'Zulkifli',
                'pl_id' => $pl?->id,
                'pl_name' => $pl?->name ?? 'Project Leader',
                'tl_har_id' => $tlHar?->id,
                'tl_har_name' => $tlHar?->name ?? 'Muh Isyak',
                'koord_log_name' => $koordLog?->name ?? 'Koordinator Logistik',
                'office_log_name' => $officeLog?->name ?? 'Officer Logistik',
                'koord_pdm_id' => $koordPdm?->id,
                'koord_pdm_name' => $koordPdm?->name ?? 'Koordinator PDM',
                'pic_pdm_id' => $picPdm?->id ?? $officePdm?->id,
                'pic_pdm_name' => $picPdm?->name ?? $officePdm?->name ?? 'PIC PDM',
            ];

            // 1. Yearly Logistik IK (month = 0)
            $this->seedYearlyLogistikIk($unit, $year, $signers['office_log_name'], $logUserId);

            // 2. 6-month trend for PdM Realisasi Prediktif (months 6..11 of current year)
            $trendMonths = [];
            for ($i = 5; $i >= 0; $i--) {
                $dt = Carbon::create($year, $curMonth, 1)->subMonths($i);
                $trendMonths[] = [(int) $dt->year, (int) $dt->month];
            }
            foreach ($trendMonths as [$tYear, $tMonth]) {
                $this->seedPdmRealisasiPrediktif($unit, $machines, $tYear, $tMonth, $pdmUserId);
            }

            // 3. Full monthly Logistik & PdM for prevMonth and curMonth
            $targetMonths = [
                [$prevYear, $prevMonth, Carbon::create($prevYear, $prevMonth, 1)->daysInMonth],
                [$year, $curMonth, min((int) $now->day, Carbon::create($year, $curMonth, 1)->daysInMonth)],
            ];

            foreach ($targetMonths as [$mYear, $mMonth, $daysDue]) {
                $this->seedMonthlyLogistik($unit, $mYear, $mMonth, $daysDue, $signers, $logUserId);
                $this->seedMonthlyPdm($unit, $machines, $mYear, $mMonth, $daysDue, $signers, $pdmUserId);
            }
        }
    }

    private function seedYearlyLogistikIk(Unit $unit, int $year, string $officerName, ?int $userId): void
    {
        $nowTs = now()->toDateTimeString();
        DB::table('logistik_jadwal_rows')
            ->where('unit_id', $unit->id)
            ->where('jadwal', 'ik')
            ->where('year', $year)
            ->delete();

        $ikTitles = [
            'IK Penerimaan, Pemeriksaan Fisik & Penyimpanan Material Gudang',
            'IK Pelaksanaan Stock Opname Bulanan & Rekonsiliasi Kartu Gantung (Bin Card)',
            'IK Pengelolaan, Penataan Rak (FIFO/FEFO) & 5S5R Gudang',
            'IK Penanganan Material Dead Stock, Obsolete & Material Bekas Pakai (Return)',
            'IK Pengeluaran Sparepart & Pencatatan Transaksi Gudang pada Aplikasi',
        ];
        $rows = [];
        foreach ($ikTitles as $idx => $title) {
            $m1 = ($idx % 6) + 1;
            $m2 = $m1 + 6;
            $rows[] = [
                'unit_id' => $unit->id,
                'jadwal' => 'ik',
                'year' => $year,
                'month' => 0,
                'section' => null,
                'nama' => $title,
                'pic' => $officerName,
                'days' => json_encode([(string) $m1 => 'D', (string) $m2 => 'D']),
                'target' => 2,
                'keterangan' => 'Dokumen IK Logistik telah diterbitkan dan digunakan',
                'evidence' => json_encode([]),
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('logistik_jadwal_rows')->insert($rows);
    }

    private function seedMonthlyLogistik(
        Unit $unit,
        int $year,
        int $month,
        int $daysDue,
        array $signers,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        $officerName = $signers['office_log_name'];

        // 1. All monthly sheets in LogistikJadwal::SHEETS (excluding 'ik' which is yearly)
        DB::table('logistik_jadwal_rows')
            ->where('unit_id', $unit->id)
            ->where('jadwal', '!=', 'ik')
            ->where('year', $year)
            ->where('month', $month)
            ->delete();

        $allJadwalRows = [];
        foreach (array_keys(LogistikJadwal::SHEETS) as $sheetKey) {
            if ($sheetKey === 'ik') {
                continue;
            }
            $columns = LogistikJadwal::columns($sheetKey, $month, $year);
            $defaults = LogistikJadwal::defaultRows($sheetKey, $columns, $officerName);
            $layout = LogistikJadwal::layout($sheetKey);

            foreach ($defaults as $idx => $defRow) {
                $daysMap = $defRow['days'] ?? [];
                if ($layout === 'kegiatan' || $layout === 'pelaksana') {
                    // Convert planned 'R' on days <= daysDue to 'D' (dilaksanakan)
                    $filled = [];
                    foreach ($daysMap as $col => $code) {
                        $filled[(string) $col] = ((int) $col <= $daysDue) ? 'D' : 'R';
                    }
                    if ($filled === []) {
                        foreach ([2, 9, 16, 23] as $d) {
                            if ($d <= $daysDue) {
                                $filled[(string) $d] = 'D';
                            }
                        }
                    }
                    $daysMap = $filled;
                } elseif ($layout === 'shift') {
                    $filled = [];
                    foreach ($columns as $col) {
                        $d = (int) $col['col'];
                        if ($d <= $daysDue) {
                            $filled[(string) $d] = $col['is_red'] ? 'OF' : 'P';
                        }
                    }
                    $daysMap = $filled;
                } elseif ($layout === 'patrol') {
                    $filled = [];
                    for ($d = 1; $d <= $daysDue; $d++) {
                        $filled[(string) $d] = 'N';
                    }
                    $daysMap = $filled;
                } elseif ($layout === 'checklist') {
                    $filled = [];
                    for ($slot = 1; $slot <= LogistikJadwal::CHECKLIST_SLOTS; $slot++) {
                        $filled[(string) $slot] = 'D';
                    }
                    $daysMap = $filled;
                } elseif ($layout === 'aplikasi') {
                    $filled = [];
                    for ($d = 1; $d <= $daysDue; $d++) {
                        $filled[(string) $d] = 'D';
                    }
                    $daysMap = $filled;
                } elseif ($layout === 'maturity') {
                    $daysMap = ['3' => 'L'];
                }

                $allJadwalRows[] = [
                    'unit_id' => $unit->id,
                    'jadwal' => $sheetKey,
                    'year' => $year,
                    'month' => $month,
                    'section' => $defRow['section'],
                    'nama' => $defRow['nama'] ?: $officerName,
                    'pic' => $defRow['pic'] ?: $officerName,
                    'days' => json_encode($daysMap),
                    'target' => $defRow['target'] ?? count($daysMap),
                    'keterangan' => $defRow['keterangan'] ?: 'Terlaksana sesuai jadwal',
                    'evidence' => json_encode($defRow['evidence'] ?? []),
                    'sort_order' => $idx + 1,
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($allJadwalRows, 500) as $chunk) {
            DB::table('logistik_jadwal_rows')->insert($chunk);
        }

        // 2. All 6 forms in LogistikForms::all()
        DB::table('logistik_form_rows')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->delete();

        $formRows = [];
        $checkDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 8));

        // 2.1 kondisi-stok (used by LogistikDashboard)
        $stockItems = [
            ['kode_material' => "MAT-{$unit->id}-001", 'stock_code' => 'SC-001', 'nama' => 'Filter Minyak Pelumas (Lube Oil Filter)', 'satuan' => 'BH', 'harga_satuan' => 450000, 'stok_awal' => 16, 'material_masuk' => 8, 'material_keluar' => 6, 'pemakaian_rata' => 4, 'safety_stock' => 4, 'ilt' => 2],
            ['kode_material' => "MAT-{$unit->id}-002", 'stock_code' => 'SC-002', 'nama' => 'Filter Bahan Bakar HSD (Fuel Filter)', 'satuan' => 'BH', 'harga_satuan' => 320000, 'stok_awal' => 20, 'material_masuk' => 10, 'material_keluar' => 8, 'pemakaian_rata' => 5, 'safety_stock' => 5, 'ilt' => 2],
            ['kode_material' => "MAT-{$unit->id}-003", 'stock_code' => 'SC-003', 'nama' => 'Gasket Cylinder Head Cover Set', 'satuan' => 'SET', 'harga_satuan' => 680000, 'stok_awal' => 10, 'material_masuk' => 4, 'material_keluar' => 2, 'pemakaian_rata' => 2, 'safety_stock' => 2, 'ilt' => 1],
            ['kode_material' => "MAT-{$unit->id}-004", 'stock_code' => 'SC-004', 'nama' => 'V-Belt Alternator & Pompa Air Pendingin', 'satuan' => 'BH', 'harga_satuan' => 275000, 'stok_awal' => 8, 'material_masuk' => 2, 'material_keluar' => 2, 'pemakaian_rata' => 1, 'safety_stock' => 2, 'ilt' => 1],
            // 1 item below ROP so LogistikDashboard "Stok Kritis / Di Bawah ROP" is populated realistically
            ['kode_material' => "MAT-{$unit->id}-005", 'stock_code' => 'SC-005', 'nama' => 'O-Ring Liner & Seal Kit Injector', 'satuan' => 'SET', 'harga_satuan' => 520000, 'stok_awal' => 4, 'material_masuk' => 0, 'material_keluar' => 2, 'pemakaian_rata' => 2, 'safety_stock' => 2, 'ilt' => 1],
        ];
        $kondisiStokForm = LogistikForms::find('kondisi-stok');
        foreach ($stockItems as $idx => $item) {
            $computed = $kondisiStokForm ? $kondisiStokForm->compute([
                ...$item,
                'open' => 0,
                'close' => 1,
                'keterangan' => $idx === 4 ? 'Usulan PR pengadaan ulang (Stok di bawah ROP)' : 'Stok aman di rak gudang',
            ]) : $item;
            $formRows[] = [
                'unit_id' => $unit->id,
                'form' => 'kondisi-stok',
                'year' => $year,
                'month' => $month,
                'section' => 'main',
                'data' => json_encode($computed),
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }

        // 2.2 Other 4 LogistikForms (pendukung, inventaris-lainnya, peralatan, permit-to-work)
        foreach (LogistikForms::all() as $formDef) {
            $fKey = $formDef->key();
            if ($fKey === 'kondisi-stok') {
                continue;
            }
            foreach ($formDef->sections() as $sec) {
                $secKey = $sec['key'];
                $defaultSecRows = $sec['rows'] ?? [];
                if ($defaultSecRows === []) {
                    $defaultSecRows = match ($fKey) {
                        'permit-to-work' => [[
                            'nomor' => sprintf('PTW-LOG/%s/%02d/%d/01', $unit->code, $month, $year),
                            'pekerjaan' => 'Stock Opname & Penataan Material Berat Rak Gudang',
                            'pelaksana' => $officerName,
                            'tanggal_mulai' => $checkDate,
                            'tanggal_selesai' => $checkDate,
                            'open' => 0,
                            'close' => 1,
                            'keterangan' => 'Selesai dengan aman',
                        ]],
                        default => [[
                            'nama' => 'Peralatan & Sarana Kerja Gudang',
                            'satuan' => 'SET',
                            'jumlah' => 2,
                            'baik' => 2,
                            'rusak' => 0,
                            'open' => 0,
                            'close' => 1,
                            'keterangan' => 'Lengkap dan berfungsi baik',
                        ]],
                    };
                }

                foreach (array_slice($defaultSecRows, 0, 8) as $idx => $rData) {
                    $enriched = [
                        ...$rData,
                        'jumlah' => $rData['jumlah'] ?? 2,
                        'baik' => $rData['baik'] ?? 2,
                        'rusak' => $rData['rusak'] ?? 0,
                        'open' => $rData['open'] ?? 0,
                        'close' => $rData['close'] ?? 1,
                        'keterangan' => $rData['keterangan'] ?? 'Lengkap & Baik',
                    ];
                    $computed = $formDef->compute($enriched);
                    $formRows[] = [
                        'unit_id' => $unit->id,
                        'form' => $fKey,
                        'year' => $year,
                        'month' => $month,
                        'section' => $secKey,
                        'data' => json_encode($computed),
                        'sort_order' => $idx + 1,
                        'input_by' => $userId,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
            }
        }
        DB::table('logistik_form_rows')->insert($formRows);

        // 3. logistik_rekomendasis
        DB::table('logistik_rekomendasis')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $rekomItems = [
            ['Manajemen Kartu Gantung (Bin Card) & Labeling Material', 'Seluruh rak telah dilengkapi bin card dan label kode material', 'Update berkala setiap transaksi masuk/keluar'],
            ['Kondisi Kebersihan & Penerangan Gudang (5S/5R)', 'Penerangan lampu LED gudang terang dan lantai bersih', 'Pertahankan jadwal kebersihan rutin'],
            ['Pengelolaan Material Fast Moving & Kritis (ROP)', 'Terdapat 1 item seal kit mendekati batas ROP', 'Pengajuan PR pengadaan sparepart fast moving'],
        ];
        foreach ($rekomItems as $idx => [$uraian, $existing, $tl]) {
            DB::table('logistik_rekomendasis')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'uraian' => $uraian,
                'kondisi_existing' => $existing,
                'tindak_lanjut' => $tl,
                'keterangan' => 'Tindak lanjut berjalan sesuai rencana',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }
    }

    private function seedPdmRealisasiPrediktif(
        Unit $unit,
        $machines,
        int $year,
        int $month,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        DB::table('pdm_realisasi_prediktifs')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->delete();

        $rows = [];
        foreach ($machines as $idx => $m) {
            $days = [5, 19];
            $rows[] = [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'uraian' => 'Pengukuran Vibrasi, Thermography & Pengambilan Sampel Pelumas/Air Pendingin',
                'mesin' => $m->name,
                'rencana' => json_encode($days),
                'realisasi' => json_encode($days),
                'durasi' => 2.0,
                'keterangan' => 'Terlaksana 100% sesuai jadwal prediktif',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('pdm_realisasi_prediktifs')->insert($rows);
    }

    private function seedMonthlyPdm(
        Unit $unit,
        $machines,
        int $year,
        int $month,
        int $daysDue,
        array $signers,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        $checkDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 9));
        $weeklyDays = array_values(array_filter([4, 11, 18, 25], fn (int $d) => $d <= $daysDue)) ?: [1];
        $allDays = range(1, $daysDue);

        // 1. pdm_jadwal_meta for all 4 types
        foreach (['harian', 'patrol_check', 'program_5s_5r', 'meeting'] as $type) {
            DB::table('pdm_jadwal_meta')->updateOrInsert(
                ['unit_id' => $unit->id, 'type' => $type, 'year' => $year, 'month' => $month],
                [
                    'doc_number' => sprintf('FM-PDM-%s/%s/%02d/%d', strtoupper($type), $unit->code, $month, $year),
                    'revision' => '00',
                    'effective_date' => "{$year}-01-02",
                    'page_number' => '1 dari 1',
                    'tempat_tanggal' => "Kendari, {$checkDate}",
                    'catatan' => 'Terlaksana rutin sesuai jadwal PdM pembangkit.',
                    'mengetahui_employee_id' => $signers['mgr_id'],
                    'mengetahui_nama' => $signers['mgr_name'],
                    'mengetahui_jabatan' => 'Manager UL',
                    'disetujui_employee_id' => $signers['tl_har_id'],
                    'disetujui_nama' => $signers['tl_har_name'],
                    'disetujui_jabatan' => 'Team Leader Pemeliharaan',
                    'dibuat_employee_id' => $signers['koord_pdm_id'],
                    'dibuat_nama' => $signers['koord_pdm_name'],
                    'dibuat_jabatan' => 'Koordinator PDM',
                    'updated_at' => $nowTs,
                    'created_at' => $nowTs,
                ],
            );
        }

        // 2. pdm_jadwal_harians
        DB::table('pdm_jadwal_harians')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $weeklyDayList = array_values(array_map('intval', $weeklyDays));
        DB::table('pdm_jadwal_harians')->insert([
            [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'kategori' => 'Mingguan',
                'is_category_header' => false,
                'no_urut' => '1',
                'kegiatan' => 'Pengukuran Vibrasi & Suhu Bearing Mesin Pembangkit',
                'target' => (string) count($weeklyDayList),
                'rencana_count' => count($weeklyDayList),
                'realisasi_count' => count($weeklyDayList),
                'jadwal' => json_encode($weeklyDayList),
                'keterangan' => 'Terlaksana 100%',
                'sort_order' => 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ],
            [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'kategori' => 'Bulanan',
                'is_category_header' => false,
                'no_urut' => '2',
                'kegiatan' => 'Pengambilan & Pengujian Sampel Minyak Pelumas dan Air Pendingin',
                'target' => '2',
                'rencana_count' => 2,
                'realisasi_count' => 2,
                'jadwal' => json_encode([5, 19]),
                'keterangan' => 'Terlaksana 100%',
                'sort_order' => 2,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ],
        ]);

        // 3. pdm_jadwal_patrol_checks
        DB::table('pdm_jadwal_patrol_checks')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $patrolSchedule = $daysDue >= 1 ? range(1, $daysDue) : [];
        DB::table('pdm_jadwal_patrol_checks')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'kategori' => 'Personil PdM',
            'is_category_header' => false,
            'no_urut' => '1',
            'employee_id' => $signers['pic_pdm_id'],
            'nama' => $signers['pic_pdm_name'],
            'no_hp' => '081245678901',
            'target' => (string) $daysDue,
            'jadwal' => json_encode($patrolSchedule),
            'keterangan' => 'Piket Patrol Check PdM Harian',
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 4. pdm_jadwal_5s5rs
        DB::table('pdm_jadwal_5s5rs')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $map5s = $weeklyDayList;
        foreach (['Ringkas (Seiri)', 'Rapi (Seiton)', 'Resik (Seiso)', 'Rawat (Seiketsu)', 'Rajin (Shitsuke)'] as $idx => $uraian) {
            DB::table('pdm_jadwal_5s5rs')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'uraian' => "Pelaksanaan {$uraian} Alat Ukur & Ruang Laboratorium PdM",
                'rencana' => json_encode($map5s),
                'realisasi' => json_encode($map5s),
                'target' => count($weeklyDays),
                'keterangan' => 'Alat ukur bersih, terkalibrasi & tertata rapi',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 5. pdm_jadwal_meetings
        DB::table('pdm_jadwal_meetings')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('pdm_jadwal_meetings')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'uraian' => 'Meeting Evaluasi Kondisi Mesin (CBM / PdM) & Rekomendasi Pemeliharaan',
            'rencana' => json_encode($map5s),
            'realisasi' => json_encode($map5s),
            'target' => count($weeklyDays),
            'catatan' => 'Koordinasi hasil ukur vibrasi dan kualitas oli bersama tim HAR & Operasi',
            'keterangan' => 'Terlaksana rutin',
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 6. pdm_kesiapan_apds + pdm_apd_inventories
        DB::table('pdm_kesiapan_apds')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('pdm_apd_inventories')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $kesiapanRows = [];
        foreach (PdmKesiapanApdForm::defaultRows() as $idx => $defRow) {
            $kesiapanRows[] = [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'kelompok' => $defRow['kelompok'],
                'no_urut' => $defRow['no_urut'],
                'inspeksi' => $defRow['inspeksi'],
                'jumlah' => 4,
                'satuan' => $defRow['satuan'],
                'kelayakan_apd' => 'Layak',
                'peralatan_jumlah' => 'Memenuhi',
                'peralatan_kelayakan' => 'Layak',
                'sop_pnp' => 'Memenuhi',
                'sop_vendor' => 'Memenuhi',
                'p3k_kotak' => 'Ada',
                'p3k_isi' => 'Ada',
                'cara_kerja' => 'Ergonomi',
                'keterangan' => 'Siap pakai untuk pengujian PdM',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('pdm_kesiapan_apds')->insert($kesiapanRows);

        DB::table('pdm_apd_inventories')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'grup' => 'APD PdM',
            'subkategori' => 'Pelindung Diri Pengujian',
            'no_urut' => 1,
            'nama' => 'Safety Helmet, Earmuff & Sarung Tangan Tahan Panas',
            'jumlah' => 4,
            'satuan' => 'Set',
            'kondisi' => 'Baik',
            'lokasi' => 'Ruang Instrumen PdM',
            'keterangan' => 'Lengkap',
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 7. pdm_sample_monitorings + items
        $oldSmIds = DB::table('pdm_sample_monitorings')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->pluck('id');
        if ($oldSmIds->isNotEmpty()) {
            DB::table('pdm_sample_monitoring_items')->whereIn('pdm_sample_monitoring_id', $oldSmIds)->delete();
            DB::table('pdm_sample_monitorings')->whereIn('id', $oldSmIds)->delete();
        }
        $smId = DB::table('pdm_sample_monitorings')->insertGetId([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'lokasi' => $unit->name,
            'pic_monitoring' => $signers['pic_pdm_name'],
            'rekap_targets' => json_encode(['pelumas' => $machines->count(), 'air_pendingin' => $machines->count(), 'bbm' => 2]),
            'catatan' => 'Seluruh sampel minyak pelumas, air pendingin, dan BBM telah diuji sesuai jadwal.',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);
        $smItems = [];
        foreach ($machines as $idx => $m) {
            $smItems[] = [
                'pdm_sample_monitoring_id' => $smId,
                'unit_id' => $unit->id,
                'section' => 'pelumas',
                'data' => json_encode([
                    'mesin' => $m->name,
                    'tanggal_ambil' => $checkDate,
                    'tanggal_uji' => $checkDate,
                    'visc_100' => '14.2 cSt',
                    'tbn' => '9.4 mgKOH/g',
                    'water' => '0.02%',
                    'status' => 'Normal',
                ]),
                'sort_order' => $idx + 1,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('pdm_sample_monitoring_items')->insert($smItems);

        // 8. pdm_permit_to_works
        DB::table('pdm_permit_to_works')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('pdm_permit_to_works')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'uraian' => 'Pengukuran Vibrasi, Thermography Panel & Pengambilan Sampel Oli Mesin Beroperasi',
            'tanggal' => $checkDate,
            'status' => 'closed',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 9. All 7 PdmForms in pdm_form_documents + pdm_form_items
        $oldDocIds = DB::table('pdm_form_documents')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->pluck('id');
        if ($oldDocIds->isNotEmpty()) {
            DB::table('pdm_form_items')->whereIn('pdm_form_document_id', $oldDocIds)->delete();
            DB::table('pdm_form_documents')->whereIn('id', $oldDocIds)->delete();
        }

        foreach (PdmForms::all() as $formDef) {
            $fKey = $formDef->key();
            $targets = $formDef->perMachine() ? $machines->all() : [null];

            foreach ($targets as $targetMachine) {
                $subject = $targetMachine ? (string) $targetMachine->id : '';
                $header = $formDef->defaults($unit, $month, $year, $targetMachine);
                $header['tanggal'] = $header['tanggal'] ?? $checkDate;
                $header['tanggal_ukur'] = $header['tanggal_ukur'] ?? $checkDate;
                $header['tanggal_pengujian'] = $header['tanggal_pengujian'] ?? $checkDate;
                $header['kesimpulan'] = $header['kesimpulan'] ?? 'Kondisi peralatan dan parameter hasil pengujian dalam batas normal (Zona A).';
                $header['rekomendasi'] = $header['rekomendasi'] ?? 'Lanjutkan operasi normal dan monitoring berkala sesuai jadwal PdM.';

                $docId = DB::table('pdm_form_documents')->insertGetId([
                    'unit_id' => $unit->id,
                    'form' => $fKey,
                    'year' => $year,
                    'month' => $month,
                    'subject' => $subject,
                    'header' => json_encode($header),
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]);

                $itemRows = [];
                $sortIdx = 1;
                foreach ($formDef->sections() as $sec) {
                    $secKey = $sec['key'];
                    $defaultRows = $sec['rows'] ?? [];
                    if ($defaultRows === []) {
                        $defaultRows = [['uraian' => $sec['title'] ?? 'Pemeriksaan Rutin PdM', 'status' => 'Normal', 'keterangan' => 'Baik']];
                    }
                    foreach (array_slice($defaultRows, 0, 10) as $rData) {
                        $filledRow = [...$rData];
                        foreach ($sec['columns'] ?? [] as $col) {
                            $cKey = $col['key'];
                            if (! isset($filledRow[$cKey]) || $filledRow[$cKey] === null || $filledRow[$cKey] === '') {
                                $filledRow[$cKey] = match ($col['type'] ?? 'text') {
                                    'check' => ($cKey === 'ya' || $cKey === 'normal' || $cKey === 'baik' || str_starts_with($cKey, 'p')) ? '1' : '',
                                    'number' => '2.45',
                                    'date' => $checkDate,
                                    default => 'Normal',
                                };
                            }
                        }
                        $itemRows[] = [
                            'pdm_form_document_id' => $docId,
                            'unit_id' => $unit->id,
                            'section' => $secKey,
                            'data' => json_encode($filledRow),
                            'sort_order' => $sortIdx++,
                            'created_at' => $nowTs,
                            'updated_at' => $nowTs,
                        ];
                    }
                }
                if ($itemRows !== []) {
                    DB::table('pdm_form_items')->insert($itemRows);
                }
            }
        }
    }
}
