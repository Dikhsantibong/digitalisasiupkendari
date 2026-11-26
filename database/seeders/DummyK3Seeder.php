<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Models\ApdItem;
use App\Models\EmergencyEquipment;
use App\Models\Employee;
use App\Models\EquipmentCategory;
use App\Models\EquipmentCertificate;
use App\Models\FireExtinguisher;
use App\Models\InspectionChecklist;
use App\Models\K3ActivityType;
use App\Models\P3kBox;
use App\Models\PatrolLocation;
use App\Models\SecurityPost;
use App\Models\Unit;
use App\Models\User;
use App\Support\K3FormulirRegistry;
use App\Support\K3IkTemplates;
use App\Support\K3KesiapanApdForm;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds comprehensive, realistic dummy data for:
 * - Modul K3 & Keamanan — Akses 1 (Pembangkit: Jadwal, Input, Formulir)
 * - Modul K3 & Keamanan — Akses 2 (Pengusahaan: 21 tables + 15 child item tables + 2 meta tables)
 * - 6-month trend history (months 6..11 of 2026) for K3Dashboard charts
 */
class DummyK3Seeder extends Seeder
{
    public function run(): void
    {
        $this->call(K3MasterDataSeeder::class);

        $now = Carbon::now();
        $year = (int) $now->year;
        $curMonth = (int) $now->month;
        $prevMonth = $curMonth === 1 ? 12 : $curMonth - 1;
        $prevYear = $curMonth === 1 ? $year - 1 : $year;

        $units = Unit::query()->where('is_active', true)->orderBy('id')->get();
        $fallbackUser = User::query()->first();

        foreach ($units as $unit) {
            // Ensure unit has master fire extinguishers, emergency equipments, and patrol locations
            $this->ensureUnitK3Masters($unit);

            $activityTypes = K3ActivityType::query()
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            $eqCategories = EquipmentCategory::query()
                ->where('unit_id', $unit->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $koordK3 = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::KoordinatorK3->value)->first();
            $petugasK3 = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::OfficeK3->value)->first();
            $tlK3 = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::TeamLeaderK3->value)->first();
            $stafK3 = Employee::query()->where('unit_id', $unit->id)->where('position', 'Staf K3')->first();
            $pl = Employee::query()->where('unit_id', $unit->id)->where('position', EmployeePosition::ProjectLeader->value)->first();
            $mgrUl = Employee::query()
                ->where('position', EmployeePosition::ManagerUl->value)
                ->where(function ($q) use ($unit) {
                    $q->where('service_unit_id', $unit->service_unit_id)->orWhere('unit_id', $unit->id);
                })
                ->first();

            $inputUserId = $petugasK3?->user?->id ?? $koordK3?->user?->id ?? $fallbackUser?->id;
            $pengusahaanUserId = $tlK3?->user?->id ?? $stafK3?->user?->id ?? $inputUserId;

            $signers = [
                'mgr_id' => $mgrUl?->id,
                'mgr_name' => $mgrUl?->name ?? 'Zulkifli',
                'tl_id' => $tlK3?->id,
                'tl_name' => $tlK3?->name ?? 'Muh Amin',
                'staf_id' => $stafK3?->id,
                'staf_name' => $stafK3?->name ?? 'Rizky Pratama',
                'koord_name' => $koordK3?->name ?? 'Koordinator K3',
                'petugas_name' => $petugasK3?->name ?? 'Petugas K3',
                'petugas_id' => $petugasK3?->id ?? $koordK3?->id,
                'pl_name' => $pl?->name ?? 'Project Leader',
            ];

            // 1. 6-month trend history (months 6..11 of current year) for K3Dashboard
            $trendMonths = [];
            for ($i = 5; $i >= 0; $i--) {
                $dt = Carbon::create($year, $curMonth, 1)->subMonths($i);
                $trendMonths[] = [(int) $dt->year, (int) $dt->month];
            }
            foreach ($trendMonths as [$tYear, $tMonth]) {
                $this->seedK3TrendMonth($unit, $activityTypes, $tYear, $tMonth, $signers, $inputUserId);
            }

            // 2. Yearly K3 (Akses 1 & Pengusahaan) + Equipment Certificates
            $this->seedYearlyK3($unit, $eqCategories, $year, $signers, $inputUserId, $pengusahaanUserId);

            // 3. Full monthly K3 Akses 1 & Akses 2 Pengusahaan for prevMonth and curMonth
            $targetMonths = [
                [$prevYear, $prevMonth, Carbon::create($prevYear, $prevMonth, 1)->daysInMonth],
                [$year, $curMonth, min((int) $now->day, Carbon::create($year, $curMonth, 1)->daysInMonth)],
            ];

            foreach ($targetMonths as [$mYear, $mMonth, $daysDue]) {
                $daysInMonth = Carbon::create($mYear, $mMonth, 1)->daysInMonth;
                $this->seedMonthlyK3Akses1($unit, $mYear, $mMonth, $daysInMonth, $daysDue, $signers, $inputUserId);
                $this->seedMonthlyK3Pengusahaan($unit, $eqCategories, $mYear, $mMonth, $daysInMonth, $daysDue, $signers, $pengusahaanUserId);
            }
        }
    }

    private function ensureUnitK3Masters(Unit $unit): void
    {
        if (FireExtinguisher::query()->where('unit_id', $unit->id)->count() === 0) {
            $apars = [
                ['code' => "APAR-{$unit->id}-01", 'location' => 'Ruang Kontrol (CCR)', 'brand' => 'Servvo', 'type' => 'CO2', 'weight_kg' => 5.0],
                ['code' => "APAR-{$unit->id}-02", 'location' => 'Power House Mesin #1', 'brand' => 'Protect', 'type' => 'Dry Chemical Powder', 'weight_kg' => 9.0],
                ['code' => "APAR-{$unit->id}-03", 'location' => 'Area Tangki BBM HSD', 'brand' => 'Yamato', 'type' => 'Foam AFFF', 'weight_kg' => 9.0],
                ['code' => "APAR-{$unit->id}-04", 'location' => 'Gudang & Workshop HAR', 'brand' => 'Servvo', 'type' => 'Dry Chemical Powder', 'weight_kg' => 6.0],
            ];
            foreach ($apars as $idx => $a) {
                FireExtinguisher::query()->create([
                    'unit_id' => $unit->id,
                    ...$a,
                    'sort_order' => $idx + 1,
                    'is_active' => true,
                ]);
            }
        }

        if (EmergencyEquipment::query()->where('unit_id', $unit->id)->count() === 0) {
            $eqs = [
                ['group_name' => 'APAR', 'name' => 'APAR Dry Powder & CO2', 'location' => 'Power House & CCR'],
                ['group_name' => 'Hydrant', 'name' => 'Hydrant Pillar & Box Outdoor', 'location' => 'Halaman Sentral & Area BBM'],
                ['group_name' => 'P3K', 'name' => 'Kotak P3K Tipe B', 'location' => 'CCR, Workshop & Pos Security'],
                ['group_name' => 'Eye Wash', 'name' => 'Emergency Eye Wash & Shower', 'location' => 'Area TPS LB3 & Tangki BBM'],
            ];
            foreach ($eqs as $idx => $e) {
                EmergencyEquipment::query()->create([
                    'unit_id' => $unit->id,
                    ...$e,
                    'sort_order' => $idx + 1,
                    'is_active' => true,
                ]);
            }
        }

        if (PatrolLocation::query()->where('unit_id', $unit->id)->count() === 0) {
            $locs = [
                ['code' => 'POS-01', 'name' => 'Gerbang Utama & Pos Jaga Security'],
                ['code' => 'POS-02', 'name' => 'Gedung Sentral / Power House'],
                ['code' => 'POS-03', 'name' => 'Area Tangki Timbun BBM & Rumah Pompa'],
                ['code' => 'POS-04', 'name' => 'Area Switchyard 20 kV & Gudang'],
            ];
            foreach ($locs as $idx => $l) {
                PatrolLocation::query()->create([
                    'unit_id' => $unit->id,
                    ...$l,
                    'sort_order' => $idx + 1,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function seedK3TrendMonth(
        Unit $unit,
        $activityTypes,
        int $year,
        int $month,
        array $signers,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        $inspDate = sprintf('%04d-%02d-05', $year, $month);

        // 1. accident_reports (4 categories, all Nihil)
        DB::table('accident_reports')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $accRows = [];
        foreach (['pak', 'pahk', 'instalasi', 'masyarakat'] as $cat) {
            $accRows[] = [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'incident_date' => null,
                'fungsi' => 'Pembangkitan',
                'lokasi' => $unit->name,
                'category' => $cat,
                'luka_ringan' => 0,
                'luka_berat' => 0,
                'meninggal' => 0,
                'kerugian_material' => 0,
                'is_nihil' => true,
                'keterangan' => 'Nihil Kecelakaan (Zero Accident)',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('accident_reports')->insert($accRows);

        // 2. inspections + inspection_results (keyed by unit's InspectionChecklist & P3kBox masters)
        $existingInspIds = DB::table('inspections')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->pluck('id');
        if ($existingInspIds->isNotEmpty()) {
            DB::table('inspection_results')->whereIn('inspection_id', $existingInspIds)->delete();
            DB::table('inspections')->whereIn('id', $existingInspIds)->delete();
        }

        $tempatKerjaItems = InspectionChecklist::query()
            ->where('unit_id', $unit->id)
            ->where('form_code', 'tempat-kerja')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $tkInspId = DB::table('inspections')->insertGetId([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'form_code' => 'tempat-kerja',
            'inspection_date' => $inspDate,
            'inspector_team' => json_encode([$signers['tl_name'], $signers['koord_name'], $signers['petugas_name']]),
            'ketua_tim' => $signers['tl_name'],
            'keterangan' => 'Seluruh area memenuhi standar keselamatan kerja',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        $tkResults = [];
        foreach ($tempatKerjaItems as $idx => $chk) {
            $tkResults[] = [
                'inspection_id' => $tkInspId,
                'item_ref' => (string) $chk->id,
                'kondisi' => 'Y',
                'tindak_lanjut' => null,
                'nilai' => null,
                'catatan' => 'Sesuai standar K3L & 5S/5R',
                'sort_order' => $idx + 1,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        foreach (array_chunk($tkResults, 200) as $chunk) {
            DB::table('inspection_results')->insert($chunk);
        }

        $p3kItems = InspectionChecklist::query()
            ->where('unit_id', $unit->id)
            ->where('form_code', 'isi-p3k')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $p3kBoxes = P3kBox::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $boxQtyMap = [];
        foreach ($p3kBoxes as $box) {
            $boxQtyMap[$box->code] = '2';
        }
        $boxQtyJson = json_encode($boxQtyMap);

        $p3kInspId = DB::table('inspections')->insertGetId([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'form_code' => 'isi-p3k',
            'inspection_date' => $inspDate,
            'inspector_team' => json_encode([$signers['koord_name'], $signers['petugas_name']]),
            'ketua_tim' => $signers['koord_name'],
            'keterangan' => 'Seluruh kotak P3K terisi lengkap',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        $p3kResults = [];
        foreach ($p3kItems as $idx => $chk) {
            $p3kResults[] = [
                'inspection_id' => $p3kInspId,
                'item_ref' => (string) $chk->id,
                'kondisi' => 'Ada',
                'tindak_lanjut' => null,
                'nilai' => $boxQtyJson,
                'catatan' => 'Lengkap',
                'sort_order' => $idx + 1,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        if ($p3kResults !== []) {
            DB::table('inspection_results')->insert($p3kResults);
        }

        // 3. k3_activity_plans
        DB::table('k3_activity_plans')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $planDaysJson = json_encode(['2' => '1', '9' => '1', '16' => '1', '23' => '1']);
        $actPlanRows = [];
        foreach ($activityTypes as $at) {
            $actPlanRows[] = [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'k3_activity_type_id' => $at->id,
                'pic' => $signers['petugas_name'],
                'plan_days' => $planDaysJson,
                'real_days' => $planDaysJson,
                'status' => 'Terlaksana',
                'keterangan' => 'Terlaksana 100% sesuai jadwal',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        if ($actPlanRows !== []) {
            DB::table('k3_activity_plans')->insert($actPlanRows);
        }
    }

    private function seedYearlyK3(
        Unit $unit,
        $eqCategories,
        int $year,
        array $signers,
        ?int $inputUserId,
        ?int $pengusahaanUserId,
    ): void {
        $nowTs = now()->toDateTimeString();

        // 0. Historical baseline incident (365 days ago) so K3Dashboard "Hari Tanpa Kecelakaan" shows 365 hari
        $histDate = Carbon::now()->subDays(365);
        DB::table('accident_reports')->where('unit_id', $unit->id)->where('year', (int) $histDate->year)->delete();
        DB::table('accident_reports')->insert([
            'unit_id' => $unit->id,
            'year' => (int) $histDate->year,
            'month' => (int) $histDate->month,
            'incident_date' => $histDate->toDateString(),
            'fungsi' => 'Pembangkitan',
            'lokasi' => $unit->name,
            'category' => 'pak',
            'luka_ringan' => 1,
            'luka_berat' => 0,
            'meninggal' => 0,
            'kerugian_material' => 0,
            'is_nihil' => false,
            'keterangan' => 'Pertolongan pertama luka gores ringan (First Aid Case) — telah ditangani tuntas',
            'input_by' => $inputUserId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 1. k3_jadwal_pembuatan_iks (yearly)
        DB::table('k3_jadwal_pembuatan_iks')->where('unit_id', $unit->id)->where('year', $year)->delete();
        $iks = [
            ['Penanganan Tumpahan BBM & Limbah B3 (Spill Kit)', [1, 7]],
            ['Pengoperasian Pompa Hydrant Diesel & Jockey Pump', [2, 8]],
            ['Tanggap Darurat Kebakaran Area Power House', [3, 9]],
            ['Pemeriksaan Rutin APAR, APAB, dan Fire Alarm', [4, 10]],
            ['Pelaksanaan Safety Briefing & Permit to Work (JSA)', [5, 11]],
        ];
        foreach ($iks as $idx => [$judul, $months]) {
            DB::table('k3_jadwal_pembuatan_iks')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'instruksi_kerja' => $judul,
                'pic_pembuat' => $signers['koord_name'],
                'rencana_bulan' => json_encode($months),
                'realisasi_bulan' => json_encode($months),
                'keterangan' => 'Dokumen IK K3 telah terbit dan disosialisasikan',
                'sort_order' => $idx + 1,
                'input_by' => $inputUserId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 2. equipment_certificates & k3_pengusahaan_certificates (aligned with unit's K3MasterDataSeeder certificates)
        $existingCerts = EquipmentCertificate::query()
            ->where('unit_id', $unit->id)
            ->with('category')
            ->orderBy('id')
            ->get();

        DB::table('k3_pengusahaan_certificates')->where('unit_id', $unit->id)->delete();

        $certsForEval = [];
        if ($existingCerts->isNotEmpty()) {
            foreach ($existingCerts as $idx => $cert) {
                $catName = $cert->category?->name ?? 'Peralatan K3';
                $reg = match ($catName) {
                    'Crane' => 'Permenaker No. 8/2020',
                    'Instalasi Penyalur Petir' => 'Permenaker No. 2/1989',
                    default => 'Permenaker No. 37/2016',
                };
                $merk = match ($catName) {
                    'Crane' => 'Konecranes',
                    'Instalasi Penyalur Petir' => 'Kurn R-150',
                    default => 'PT. Bukaka Teknik Utama',
                };
                $seri = sprintf('%s-%d-%02d', strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $catName), 0, 3) ?: 'EQ'), $unit->id, $idx + 1);
                $ijinAwalNo = sprintf('SLO-AWAL/%s/%02d/2021', $unit->code, $idx + 1);
                $ujiTerakhirNo = sprintf('SUKET-K3/%s/%02d/2025', $unit->code, $idx + 1);

                $cert->update([
                    'merk_manufacture' => $cert->merk_manufacture ?: $merk,
                    'no_seri' => $cert->no_seri ?: $seri,
                    'regulasi' => $cert->regulasi ?: $reg,
                    'ijin_awal_nomor' => $cert->ijin_awal_nomor ?: $ijinAwalNo,
                    'ijin_awal_tanggal' => $cert->ijin_awal_tanggal ?: '2021-06-15',
                    'uji_terakhir_nomor' => $cert->uji_terakhir_nomor ?: $ujiTerakhirNo,
                    'uji_terakhir_tanggal' => $cert->uji_terakhir_tanggal ?: '2025-06-15',
                    'uji_ulang_tanggal' => $cert->uji_ulang_tanggal ?: '2027-06-15',
                    'batasan_uji' => $cert->batasan_uji ?: '2 Tahun',
                    'masa_berlaku_tahun' => $cert->masa_berlaku_tahun ?: 2,
                    'keterangan' => $cert->keterangan ?: 'Sertifikat Laik Operasi (SLO / Suket K3) Aktif',
                    'input_by' => $inputUserId,
                ]);

                DB::table('k3_pengusahaan_certificates')->insert([
                    'unit_id' => $unit->id,
                    'equipment_category_id' => $cert->equipment_category_id,
                    'jenis' => $cert->jenis,
                    'kapasitas' => $cert->kapasitas,
                    'lokasi' => $cert->lokasi ?: $unit->name,
                    'merk_manufacture' => $cert->merk_manufacture ?: $merk,
                    'no_seri' => $cert->no_seri ?: $seri,
                    'regulasi' => $cert->regulasi ?: $reg,
                    'ijin_awal_nomor' => $cert->ijin_awal_nomor ?: $ijinAwalNo,
                    'ijin_awal_tanggal' => '2021-06-15',
                    'uji_terakhir_nomor' => $cert->uji_terakhir_nomor ?: $ujiTerakhirNo,
                    'uji_terakhir_tanggal' => '2025-06-15',
                    'uji_ulang_tanggal' => '2027-06-15',
                    'batasan_uji' => '2 Tahun',
                    'masa_berlaku_tahun' => 2,
                    'keterangan' => 'Sertifikat Laik Operasi (SLO / Suket K3) Aktif',
                    'input_by' => $pengusahaanUserId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]);

                $certsForEval[] = [
                    'kategori' => $catName,
                    'jenis' => $cert->jenis,
                    'kapasitas' => $cert->kapasitas ?? '-',
                ];
            }
        }

        // 3. k3_pengusahaan_evaluasi_pengujians + meta (yearly)
        DB::table('k3_pengusahaan_evaluasi_pengujians')->where('unit_id', $unit->id)->where('year', $year)->delete();
        DB::table('k3_pengusahaan_evaluasi_pengujian_meta')->updateOrInsert(
            ['unit_id' => $unit->id, 'year' => $year],
            [
                'nomor_dokumen' => sprintf('FM-K3-EVAL/%s/%d', $unit->code, $year),
                'tanggal_terbit' => "{$year}-01-05",
                'revisi' => '00',
                'halaman' => '1 dari 1',
                'catatan' => 'Seluruh peralatan wajib sertifikasi K3 dalam status aktif dan laik operasi.',
                'updated_at' => $nowTs,
                'created_at' => $nowTs,
            ],
        );
        $progresBulan = json_encode([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
        foreach ($certsForEval as $idx => $evalRow) {
            DB::table('k3_pengusahaan_evaluasi_pengujians')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => (string) ($idx + 1),
                'nama_kategori_alat' => $evalRow['kategori'],
                'jenis' => $evalRow['jenis'],
                'kapasitas' => $evalRow['kapasitas'],
                'temuan_sertifikat' => 'Tidak ada temuan (Laik Operasi)',
                'progres_bulan' => $progresBulan,
                'keterangan' => 'Sertifikat masih berlaku hingga 2027',
                'sort_order' => $idx + 1,
                'input_by' => $pengusahaanUserId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }
    }

    private function seedMonthlyK3Akses1(
        Unit $unit,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        array $signers,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        $weeklyDays = array_values(array_filter([3, 10, 17, 24], fn (int $d) => $d <= $daysDue)) ?: [1];
        $allDays = range(1, $daysDue);
        $checkDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 6));

        // 1. k3_instruksi_kerjas
        DB::table('k3_instruksi_kerjas')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_instruksi_kerjas')->insert([
            [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => 1,
                'kegiatan' => 'Sosialisasi IK Penggunaan APAR & Hydrant Darurat',
                'waktu' => '08:30 WITA',
                'pic' => $signers['koord_name'],
                'peserta' => 'Operator Shift, Harmes, Harlist & Security',
                'rencana' => json_encode($weeklyDays),
                'realisasi' => json_encode($weeklyDays),
                'keterangan' => 'Terlaksana pada saat Safety Briefing mingguan',
                'sort_order' => 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ],
        ]);

        // 2. k3_kegiatan_rutins
        DB::table('k3_kegiatan_rutins')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_kegiatan_rutins')->insert([
            [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'grup' => 'harian',
                'no_urut' => 1,
                'kegiatan' => 'Patrol Check K3L, Pemeriksaan Penggunaan APD & Housekeeping Area Sentral',
                'target' => $daysDue,
                'jadwal' => json_encode($allDays),
                'keterangan' => 'Rutin harian',
                'sort_order' => 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ],
            [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'grup' => 'mingguan',
                'no_urut' => 2,
                'kegiatan' => 'Safety Briefing, Pengecekan Pompa Hydrant & Kotak P3K',
                'target' => count($weeklyDays),
                'jadwal' => json_encode($weeklyDays),
                'keterangan' => 'Rutin mingguan',
                'sort_order' => 2,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ],
            [
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'grup' => 'bulanan',
                'no_urut' => 3,
                'kegiatan' => 'Inspeksi Menyeluruh APAR, Rambu K3, Fire Alarm & Penyusunan Laporan K3',
                'target' => 1,
                'jadwal' => json_encode([min($daysDue, 5)]),
                'keterangan' => 'Rutin bulanan',
                'sort_order' => 3,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ],
        ]);

        // 3. k3_jadwal_on_calls
        DB::table('k3_jadwal_on_calls')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $onCallMap = [];
        for ($d = 1; $d <= $daysDue; $d++) {
            $onCallMap[sprintf('%04d-%02d-%02d', $year, $month, $d)] = 'P';
        }
        DB::table('k3_jadwal_on_calls')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'employee_id' => $signers['petugas_id'],
            'nama' => $signers['petugas_name'],
            'kode_prk' => "PRK-K3-{$unit->code}",
            'jabatan' => 'Petugas K3',
            'schedule' => json_encode($onCallMap),
            'total' => $daysDue,
            'nilai' => 50000,
            'rupiah' => $daysDue * 50000,
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 4. k3_patrol_check_jadwals
        DB::table('k3_patrol_check_jadwals')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_patrol_check_jadwals')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'uraian' => 'Patrol Check K3 Area Power House, Tangki BBM, TPS LB3 & Oil Trap',
            'rencana' => json_encode($allDays),
            'realisasi' => json_encode($allDays),
            'bobot_sla' => 100,
            'keterangan' => 'Terlaksana rutin setiap hari',
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 5. k3_pekerjaan_rutins
        DB::table('k3_pekerjaan_rutins')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_pekerjaan_rutins')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'uraian' => 'Pemeriksaan Harian Kesiapan Sarana Tanggap Darurat & Kebersihan Lingkungan',
            'rencana' => json_encode($allDays),
            'realisasi' => json_encode($allDays),
            'paraf' => 'OK',
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 6. k3_air_limbahs
        DB::table('k3_air_limbahs')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_air_limbahs')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'area_penyiraman' => 'Taman & Ruang Terbuka Hijau Sentral',
            'tanggal' => $checkDate,
            'waktu_penyiraman' => '08:00 - 09:00 WITA',
            'metode_pemanfaatan' => 'Penyiraman tanaman setelah melalui Oil Trap & IPAL',
            'debit_awal' => 120.5,
            'debit_akhir' => 122.0,
            'debit_jumlah' => 1.5,
            'frekuensi' => '1 Kali/Hari',
            'pic' => $signers['petugas_name'],
            'keterangan' => 'pH air normal (7.1), bebas kandungan minyak',
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 7. fire_extinguisher_checks
        DB::table('fire_extinguisher_checks')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $apars = FireExtinguisher::query()->where('unit_id', $unit->id)->where('is_active', true)->get();
        foreach ($apars as $apar) {
            DB::table('fire_extinguisher_checks')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'fire_extinguisher_id' => $apar->id,
                'tgl_periksa' => $checkDate,
                'kondisi_tabung' => 'Baik',
                'kondisi_nozzle' => 'Baik',
                'indikator_tekanan' => 'Normal',
                'kondisi_pin_segel' => 'Baik',
                'exp_date' => sprintf('%04d-12-31', $year + 1),
                'keterangan' => 'Siap Pakai',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 8. k3_apd_inventories (pulled from unit's ApdItem & ApdCategory master data)
        DB::table('k3_apd_inventories')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $pelengkapCategories = [
            'Tangga',
            'Lampu Penerangan',
            'Sirkulator Udara/Kipas',
            'TOA',
            'Tongkat Rambu-Rambu',
            'Rompi Nyala',
            'Lampu Sorot',
        ];
        $unitApdItems = ApdItem::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        foreach ($unitApdItems as $idx => $item) {
            $catName = $item->category?->name ?? 'Perlengkapan K3';
            $grup = in_array($catName, $pelengkapCategories, true)
                ? 'Peralatan Keselamatan Kerja Pelengkap'
                : 'Peralatan Keselamatan Kerja Utama';
            $satuan = str_contains(strtolower($item->name), 'sarung tangan') || str_contains(strtolower($item->name), 'sepatu') || str_contains(strtolower($item->name), 'ear')
                ? 'psg'
                : 'buah';

            DB::table('k3_apd_inventories')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'grup' => $grup,
                'subkategori' => $catName,
                'no_urut' => (string) ($idx + 1),
                'nama' => $item->name,
                'jumlah' => 10,
                'satuan' => $satuan,
                'lokasi' => $item->location ?: $unit->name,
                'keterangan' => 'Kondisi baik & layak pakai',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 9. k3_cctv_lists
        DB::table('k3_cctv_lists')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $cctvs = [
            ['CCTV-01', 'Gerbang Utama & Pos Jaga Security'],
            ['CCTV-02', 'Ruang Mesin Utama (Power House)'],
            ['CCTV-03', 'Area Tangki Timbun BBM HSD'],
            ['CCTV-04', 'Area Switchyard 20 kV & Gudang'],
        ];
        foreach ($cctvs as $idx => [$noCctv, $lokasi]) {
            DB::table('k3_cctv_lists')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'tanggal' => $checkDate,
                'no_cctv' => $noCctv,
                'titik_lokasi' => $lokasi,
                'status' => 'on',
                'keterangan' => 'Aktif merekam 24 jam, tampilan jernih',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 10. k3_dokumen_iks
        DB::table('k3_dokumen_iks')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $k3IkTemplates = array_slice(K3IkTemplates::all(), 0, 2);
        foreach ($k3IkTemplates as $idx => $tpl) {
            DB::table('k3_dokumen_iks')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'sort_order' => $idx + 1,
                'sistem' => $tpl['sistem'] ?? 'KESELAMATAN & KESEHATAN KERJA (K3)',
                'judul' => $tpl['judul'] ?? 'INSTRUKSI KERJA TANGGAP DARURAT KEBAKARAN',
                'no_dokumen' => sprintf('IK-K3/%s/%02d/%d', $unit->code, $idx + 1, $year),
                'tanggal' => sprintf('%04d-%02d-02', $year, $month),
                'revisi' => '00',
                'halaman' => '1 dari 2',
                'sections' => json_encode($tpl['sections'] ?? []),
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 11. k3_emergency_facility_checks & emergency_facility_checks
        DB::table('k3_emergency_facility_checks')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('emergency_facility_checks')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $emEqs = EmergencyEquipment::query()->where('unit_id', $unit->id)->where('is_active', true)->get();
        foreach ($emEqs as $idx => $eq) {
            $qty = 4;
            DB::table('k3_emergency_facility_checks')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'grup' => $eq->group_name ?: 'A. SARANA TANGGAP DARURAT',
                'no_urut' => $idx + 1,
                'nama_peralatan' => $eq->name,
                'jml_total' => $qty,
                'jml_ready' => $qty,
                'jml_not_ready' => 0,
                'lokasi' => $eq->location ?: 'Power House & CCR',
                'kendala' => 'Nihil',
                'tindak_lanjut' => 'Pengecekan rutin berkala',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
            DB::table('emergency_facility_checks')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'week' => 1,
                'emergency_equipment_id' => $eq->id,
                'jml_total' => $qty,
                'jml_ready' => $qty,
                'jml_not_ready' => 0,
                'kendala' => 'Nihil',
                'tindak_lanjut' => 'Siap beroperasi 100%',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 12. k3_fire_alarm_inspections
        DB::table('k3_fire_alarm_inspections')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach (['Ruang Kontrol (CCR)', 'Power House Zona 1', 'Gudang & Workshop'] as $idx => $lok) {
            DB::table('k3_fire_alarm_inspections')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'lokasi' => $lok,
                'tanggal_periksa' => $checkDate,
                'kondisi' => 'Baik',
                'panel_indikator' => 'Normal',
                'keterangan' => 'Sensor smoke & heat detector responsif',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 13. k3_hydrant_inspections
        DB::table('k3_hydrant_inspections')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach (['Hydrant Box Outdoor #1 (Depan Power House)', 'Hydrant Box Outdoor #2 (Area Tangki BBM)'] as $idx => $lok) {
            DB::table('k3_hydrant_inspections')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'lokasi' => $lok,
                'jenis' => 'Hydrant Pillar & Box Outdoor',
                'tanggal' => $checkDate,
                'hose' => 'Baik',
                'nozzle' => 'Baik',
                'box' => 'Baik',
                'tekanan' => '7.2 Bar',
                'keterangan' => 'Tekanan pompa hydrant normal',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 14. k3_kesiapan_apds + meta (pulled across all 5 groups from unit's masters)
        DB::table('k3_kesiapan_apds')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_kesiapan_apd_meta')->updateOrInsert(
            ['unit_id' => $unit->id, 'year' => $year, 'month' => $month],
            ['catatan' => 'Seluruh APD, peralatan kerja, SOP, dan kotak P3K dalam kondisi lengkap dan layak.', 'updated_at' => $nowTs, 'created_at' => $nowTs],
        );
        foreach (K3KesiapanApdForm::defaultRows($unit) as $idx => $r) {
            DB::table('k3_kesiapan_apds')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'kelompok' => $r['kelompok'],
                'no_urut' => $r['no_urut'] ?? (string) ($idx + 1),
                'inspeksi' => $r['inspeksi'],
                'apd_jumlah' => $r['apd_jumlah'] ?? '-',
                'kelayakan_apd' => $r['kelayakan_apd'] ?? '-',
                'peralatan_jumlah' => $r['peralatan_jumlah'] ?? '-',
                'peralatan_kelayakan' => $r['peralatan_kelayakan'] ?? '-',
                'sop_pnp' => $r['sop_pnp'] ?? '-',
                'sop_vendor' => $r['sop_vendor'] ?? '-',
                'p3k_ada' => $r['p3k_ada'] ?? '-',
                'p3k_memenuhi' => $r['p3k_memenuhi'] ?? '-',
                'cara_kerja' => $r['cara_kerja'] ?? '-',
                'keterangan' => $r['keterangan'] ?? 'Lengkap & Baik',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 15. k3_kondisi_k3s + meta
        DB::table('k3_kondisi_k3s')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_kondisi_k3_meta')->updateOrInsert(
            ['unit_id' => $unit->id, 'year' => $year, 'month' => $month],
            ['catatan' => 'Temuan kondisi tidak aman telah ditindaklanjuti seluruhnya (Closed).', 'updated_at' => $nowTs, 'created_at' => $nowTs],
        );
        DB::table('k3_kondisi_k3s')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'periode' => "Minggu II {$month}/{$year}",
            'kategori' => 'Unsafe Condition',
            'temuan' => 'Garis kuning pembatas jalur pejalan kaki di Power House mulai pudar',
            'kondisi' => 'Pudar sebagian',
            'tindak_lanjut' => 'Pengecatan ulang garis demarkasi jalur hijau dan kuning',
            'rekomendasi' => 'Inspeksi rutin marka lantai setiap triwulan',
            'lokasi' => 'Power House',
            'keterangan' => 'Closed',
            'eviden_sebelum' => null,
            'eviden_sesudah' => null,
            'sort_order' => 1,
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 16. k3_rambu_inspections
        DB::table('k3_rambu_inspections')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $rambus = [
            ['Rambu Wajib Gunakan Earplug / Earmuff', 'Pintu Masuk Power House'],
            ['Rambu Dilarang Merokok & Nyala Api Terbuka', 'Area Tangki BBM HSD'],
            ['Rambu Jalur Evakuasi & Titik Kumpul (Assembly Point)', 'Halaman Depan Sentral'],
        ];
        foreach ($rambus as $idx => [$rambu, $lok]) {
            DB::table('k3_rambu_inspections')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'rambu' => $rambu,
                'lokasi' => $lok,
                'kondisi' => 'Baik',
                'keterangan' => 'Rambu terbaca jelas dan terpasang kokoh',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 17. k3_metode_pengujian_peralatans + meta (pulled from unit's EquipmentCertificate master records)
        DB::table('k3_metode_pengujian_peralatans')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_metode_pengujian_peralatan_meta')->updateOrInsert(
            ['unit_id' => $unit->id, 'year' => $year, 'month' => $month],
            [
                'catatan' => 'Seluruh peralatan telah diuji sesuai metode standar K3.',
                'document_number' => sprintf('FM-K3-MPP/%s/%02d/%d', $unit->code, $month, $year),
                'revision' => '00',
                'effective_date' => "{$year}-01-02",
                'manager_ul_id' => $signers['mgr_id'],
                'manager_ul_name' => $signers['mgr_name'],
                'manager_ul_title' => 'Manager UL',
                'tl_k3_id' => $signers['tl_id'],
                'tl_k3_name' => $signers['tl_name'],
                'tl_k3_title' => 'Team Leader K3 & Kam',
                'staff_k3_id' => $signers['staf_id'],
                'staff_k3_name' => $signers['staf_name'],
                'staff_k3_title' => 'Staf K3',
                'sign_place_date' => "Kendari, {$checkDate}",
                'format' => 'form',
                'updated_at' => $nowTs,
                'created_at' => $nowTs,
            ],
        );
        $unitCerts = EquipmentCertificate::query()
            ->where('unit_id', $unit->id)
            ->with('category')
            ->orderBy('id')
            ->get();
        foreach ($unitCerts as $idx => $cert) {
            DB::table('k3_metode_pengujian_peralatans')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => (string) ($idx + 1),
                'nama_peralatan' => $cert->category?->name ?? 'Peralatan K3',
                'no_pengesahan' => $cert->uji_terakhir_nomor ?: sprintf('SUKET-K3/%s/%02d/2025', $unit->code, $idx + 1),
                'nama_kategori_alat' => trim($cert->jenis.($cert->kapasitas && $cert->kapasitas !== '-' ? " ({$cert->kapasitas})" : '')),
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => 'Memenuhi',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Memenuhi',
                'uji_ketahanan' => 'Memenuhi',
                'sertifikasi_terakhir' => $cert->uji_terakhir_tanggal?->format('Y-m-d') ?? '2025-06-15',
                'sertifikasi_ulang' => $cert->uji_ulang_tanggal?->format('Y-m-d') ?? '2027-06-15',
                'keterangan' => 'Laik Operasi',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 18. security_patrols (daily for 1..daysDue)
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        DB::table('security_patrols')->where('unit_id', $unit->id)->whereBetween('patrol_date', [$startOfMonth, $endOfMonth])->delete();
        $patrolLocs = PatrolLocation::query()->where('unit_id', $unit->id)->where('is_active', true)->get();
        $patrolRows = [];
        $scanTimesJson = json_encode(['02:00', '08:00', '14:00', '20:00']);
        for ($d = 1; $d <= $daysDue; $d++) {
            $pDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
            foreach ($patrolLocs as $loc) {
                $patrolRows[] = [
                    'unit_id' => $unit->id,
                    'patrol_date' => $pDate,
                    'patrol_location_id' => $loc->id,
                    'scan_times' => $scanTimesJson,
                    'total_scan' => 4,
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($patrolRows, 500) as $chunk) {
            DB::table('security_patrols')->insert($chunk);
        }

        // 19. k3_attachments
        DB::table('k3_attachments')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_attachments')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'title' => "Dokumentasi Safety Briefing, Inspeksi APAR & Patrol K3 {$unit->name}",
            'file_path' => "k3/attachments/eviden-k3-{$unit->id}-{$year}-{$month}.jpg",
            'category' => 'inspeksi',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 20. k3_formulir_records (sarana-prasarana, kontrol-mingguan w1..w4, pemeliharaan-tps-lb3, pemeliharaan-oil-trap)
        DB::table('k3_formulir_records')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach (K3FormulirRegistry::keys() as $formKey) {
            $def = K3FormulirRegistry::get($formKey);
            if ($def === null) {
                continue;
            }
            $defaultSections = K3FormulirRegistry::defaultSections($def);
            $weeks = ($def['has_week'] ?? false) ? [1, 2, 3, 4] : [0];
            foreach ($weeks as $w) {
                DB::table('k3_formulir_records')->insert([
                    'unit_id' => $unit->id,
                    'form' => $formKey,
                    'year' => $year,
                    'month' => $month,
                    'week' => $w,
                    'data' => json_encode(['sections' => $defaultSections]),
                    'catatan' => 'Seluruh parameter pemeriksaan memenuhi standar K3 & Lingkungan.',
                    'document_number' => $def['default_doc_number'] ?? "FM-K3-{$formKey}",
                    'revision' => '00',
                    'effective_date' => "{$year}-01-02",
                    'manager_ul_id' => $signers['mgr_id'],
                    'manager_ul_name' => $signers['mgr_name'],
                    'manager_ul_title' => 'Manager UL',
                    'tl_k3_id' => $signers['tl_id'],
                    'tl_k3_name' => $signers['tl_name'],
                    'tl_k3_title' => 'Team Leader K3 & Kam',
                    'staff_k3_id' => $signers['staf_id'],
                    'staff_k3_name' => $signers['staf_name'],
                    'staff_k3_title' => 'Staf K3',
                    'sign_place_date' => "Kendari, {$checkDate}",
                    'format' => 'structured',
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]);
            }
        }
    }

    private function seedMonthlyK3Pengusahaan(
        Unit $unit,
        $eqCategories,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        array $signers,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        $docDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 8));

        // 1. k3_pengusahaan_time_frames (pulled from unit's K3ActivityType master data)
        DB::table('k3_pengusahaan_time_frames')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $unitActivityTypes = K3ActivityType::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
        foreach ($unitActivityTypes as $idx => $actType) {
            DB::table('k3_pengusahaan_time_frames')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'uraian_pelaporan' => $actType->name,
                'pic' => $actType->default_pic ?: $signers['tl_name'],
                'rencana' => json_encode([5, 19]),
                'realisasi' => json_encode([5, 19]),
                'keterangan' => 'Tepat Waktu (On Time)',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 2. k3_pengusahaan_jam_kerjas
        DB::table('k3_pengusahaan_jam_kerjas')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_pengusahaan_jam_kerjas')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_dokumen' => sprintf('FM-K3-JK/%s/%02d/%d', $unit->code, $month, $year),
            'tgl_berlaku' => "{$year}-01-02",
            'revisi' => '00',
            'halaman' => '1 dari 1',
            'karyawan_tetap' => 4,
            'karyawan_tetap_shift' => 0,
            'karyawan_tidak_tetap' => 8,
            'karyawan_tidak_tetap_shift' => 12,
            'hari_tetap' => 22,
            'jam_tetap' => 8,
            'lembur_tetap' => 16,
            'hari_tetap_shift' => 0,
            'jam_tetap_shift' => 0,
            'lembur_tetap_shift' => 0,
            'hari_tidak_tetap' => 22,
            'jam_tidak_tetap' => 8,
            'lembur_tidak_tetap' => 32,
            'hari_tidak_tetap_shift' => $daysDue,
            'jam_tidak_tetap_shift' => 8,
            'lembur_tidak_tetap_shift' => 48,
            'cuti_orang' => 0,
            'cuti_hari' => 0,
            'cuti_jam' => 0,
            'ijin_orang' => 0,
            'ijin_hari' => 0,
            'ijin_jam' => 0,
            'sakit_orang' => 0,
            'sakit_hari' => 0,
            'sakit_jam' => 0,
            'total_jam_kerja_orang' => 4224,
            'total_lembur' => 96,
            'total_absensi_jam' => 0,
            'total_jam_kerja_seluruh' => 4320,
            'catatan' => 'Zero Accident — Nihil kecelakaan kerja sepanjang bulan berjalan.',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 3. k3_pengusahaan_jam_kerja_bulanans
        DB::table('k3_pengusahaan_jam_kerja_bulanans')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $komulatifLalu = 185000 + ($month * 4000);
        DB::table('k3_pengusahaan_jam_kerja_bulanans')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_dokumen' => sprintf('FM-K3-JKB/%s/%02d/%d', $unit->code, $month, $year),
            'tgl_berlaku' => "{$year}-01-02",
            'revisi' => '00',
            'halaman' => '1 dari 1',
            'jam_kerja_komulatif_bulan_lalu' => $komulatifLalu,
            'karyawan_tetap' => 4,
            'karyawan_tetap_shift' => 0,
            'karyawan_tidak_tetap' => 8,
            'karyawan_tidak_tetap_shift' => 12,
            'jumlah_karyawan' => 24,
            'hari_kerja' => 22,
            'jam_kerja_standart' => 8,
            'jam_kerja_standart_karyawan' => 4224,
            'jam_kerja_lembur_karyawan' => 96,
            'jam_kerja_seluruh_karyawan' => 4320,
            'jam_absensi_karyawan' => 0,
            'jam_kerja_realisasi_karyawan' => 4320,
            'jam_kerja_komulatif_bulan_ini' => $komulatifLalu + 4320,
            'catatan' => 'Akumulasi jam kerja aman terus meningkat tanpa kecelakaan kerja.',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        // 4 & 5. k3_pengusahaan_kecelakaan_instalasis & masyarakats (+ items)
        foreach (['k3_pengusahaan_kecelakaan_instalasis' => 'k3_pengusahaan_kecelakaan_instalasi_items', 'k3_pengusahaan_kecelakaan_masyarakats' => 'k3_pengusahaan_kecelakaan_masyarakat_items'] as $parentTbl => $childTbl) {
            $oldIds = DB::table($parentTbl)->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->pluck('id');
            if ($oldIds->isNotEmpty()) {
                DB::table($childTbl)->whereIn('laporan_id', $oldIds)->delete();
                DB::table($parentTbl)->whereIn('id', $oldIds)->delete();
            }
            $lapId = DB::table($parentTbl)->insertGetId([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'is_nihil' => true,
                'lampiran_teks' => 'NIHIL KECELAKAAN',
                'nomor_keputusan' => '-',
                'catatan' => 'Nihil kejadian kecelakaan sepanjang periode pelaporan.',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
            DB::table($childTbl)->insert([
                'laporan_id' => $lapId,
                'no_urut' => 1,
                'tanggal_kejadian' => null,
                'fungsi' => 'Pembangkitan',
                'lokasi_kejadian' => $unit->name,
                'luka_ringan' => 0,
                'luka_berat' => 0,
                'meninggal' => 0,
                'kerugian_material' => 0,
                'keterangan' => 'NIHIL',
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 6. k3_pengusahaan_emergency_facilities (pulled from unit's EmergencyEquipment master data)
        DB::table('k3_pengusahaan_emergency_facilities')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $unitEmergencyEquipments = EmergencyEquipment::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        foreach (['M1', 'BULANAN'] as $periodeKey) {
            foreach ($unitEmergencyEquipments as $idx => $eq) {
                $tot = str_contains($eq->name, 'APAR') ? 10 : (str_contains($eq->name, 'P3K') ? 7 : 2);
                DB::table('k3_pengusahaan_emergency_facilities')->insert([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'periode' => $periodeKey,
                    'no_dokumen' => sprintf('FM-K3-EF/%s/%02d/%d', $unit->code, $month, $year),
                    'revisi' => '00',
                    'tanggal_dokumen' => $docDate,
                    'halaman' => '1 dari 1',
                    'grup' => $eq->group_name ?: 'Fasilitas Darurat',
                    'no_urut' => $idx + 1,
                    'nama_peralatan' => $eq->name,
                    'jml_total' => (string) $tot,
                    'jml_ready' => (string) $tot,
                    'jml_not_ready' => '0',
                    'persen_kesiapan' => '100%',
                    'lokasi' => $eq->location ?: $unit->name,
                    'kendala' => 'Nihil',
                    'tindak_lanjut' => 'Inspeksi rutin bulanan',
                    'sort_order' => $idx + 1,
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]);
            }
        }

        // 7. k3_pengusahaan_apar_apabs + items (pulled from unit's FireExtinguisher master data)
        $unitApars = FireExtinguisher::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('location')
            ->get();
        $aparItemsPayload = [];
        foreach ($unitApars as $idx => $apar) {
            $aparItemsPayload[] = [
                'no_urut' => $idx + 1,
                'no_rfid' => $apar->rfid,
                'lokasi' => $apar->location ?: $unit->name,
                'tgl_periksa' => $docDate,
                'merk_apar' => $apar->merk ?: 'Fire Venom',
                'jenis_apar' => $apar->jenis ?: 'Powder',
                'berat_kg' => (string) ($apar->berat_kg ?? 6),
                'kondisi_tabung' => 'baik',
                'kondisi_nozzle_selang' => 'baik',
                'indikator_tekanan' => 'ok',
                'kondisi_pin_segel' => 'baik',
                'keterangan' => 'Ex. 10/11/2027',
                'sort_order' => $idx + 1,
            ];
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_apar_apabs',
            'k3_pengusahaan_apar_apab_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-APAR/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'catatan' => 'Seluruh APAR dan APAB dalam kondisi tekanan normal dan siap pakai.',
                'input_by' => $userId,
            ],
            $aparItemsPayload,
        );

        // 8. k3_pengusahaan_apats + items
        $this->replaceParentAndItems(
            'k3_pengusahaan_apats',
            'k3_pengusahaan_apat_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-APAT/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'tanggal_inspeksi' => $docDate,
                'catatan' => 'Bak pasir, sekop, dan karung goni di area tangki BBM lengkap.',
                'input_by' => $userId,
            ],
            [
                ['no_urut' => 1, 'nama_alat' => 'Drum / Bak Pasir Kering', 'tanggal_inspeksi' => $docDate, 'kondisi' => 'Baik', 'jumlah' => 2, 'keterangan' => 'Pasir kering & bersih', 'sort_order' => 1],
                ['no_urut' => 2, 'nama_alat' => 'Sekop & Ember Pemadam', 'tanggal_inspeksi' => $docDate, 'kondisi' => 'Baik', 'jumlah' => 4, 'keterangan' => 'Siap pakai', 'sort_order' => 2],
            ],
        );

        // 9. k3_pengusahaan_fire_alarms + items
        $this->replaceParentAndItems(
            'k3_pengusahaan_fire_alarms',
            'k3_pengusahaan_fire_alarm_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-FA/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'tanggal_periksa' => $docDate,
                'catatan' => 'Panel MCFA, smoke detector, heat detector, dan alarm bell berfungsi normal.',
                'input_by' => $userId,
            ],
            [
                ['no_urut' => 1, 'lokasi' => 'Ruang Kontrol (CCR)', 'tanggal_periksa' => $docDate, 'kondisi' => 'Baik', 'panel_indikator' => 'Normal', 'keterangan' => 'Aktif', 'sort_order' => 1],
                ['no_urut' => 2, 'lokasi' => 'Power House Sentral', 'tanggal_periksa' => $docDate, 'kondisi' => 'Baik', 'panel_indikator' => 'Normal', 'keterangan' => 'Aktif', 'sort_order' => 2],
            ],
        );

        // 10. k3_pengusahaan_hydrants + items
        $this->replaceParentAndItems(
            'k3_pengusahaan_hydrants',
            'k3_pengusahaan_hydrant_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-HYD/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'tanggal_periksa' => $docDate,
                'catatan' => 'Jockey pump dan electric/diesel hydrant pump siap operasi.',
                'input_by' => $userId,
            ],
            [
                ['no_urut' => 1, 'lokasi' => 'Hydrant Box Outdoor #1', 'tanggal_periksa' => $docDate, 'hose' => 'Baik', 'nozzle' => 'Baik', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => '7.2 Bar (Normal)', 'keterangan' => 'Siap Pakai', 'sort_order' => 1],
                ['no_urut' => 2, 'lokasi' => 'Hydrant Box Outdoor #2', 'tanggal_periksa' => $docDate, 'hose' => 'Baik', 'nozzle' => 'Baik', 'box_hydrant' => 'Baik', 'kondisi_tekanan_air' => '7.0 Bar (Normal)', 'keterangan' => 'Siap Pakai', 'sort_order' => 2],
            ],
        );

        // 11. k3_pengusahaan_pemeriksaan_p3ks + items (pulled from unit's P3kBox & InspectionChecklist isi-p3k)
        $unitP3kLocations = P3kBox::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (P3kBox $b): string => $b->location ?: $b->code)
            ->values()
            ->all();
        if ($unitP3kLocations === []) {
            $unitP3kLocations = ['Ruang CCR', 'Workshop', 'Pos Security'];
        }
        $unitP3kChecklists = InspectionChecklist::query()
            ->where('unit_id', $unit->id)
            ->where('form_code', 'isi-p3k')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $p3kItemsPayload = [];
        foreach ($unitP3kChecklists as $idx => $chk) {
            $stdQty = $idx === 0 ? 20 : ($idx <= 4 ? 2 : 1);
            $kondisiMap = [];
            foreach ($unitP3kLocations as $locName) {
                $kondisiMap[$locName] = $stdQty;
            }
            $p3kItemsPayload[] = [
                'no_urut' => $idx + 1,
                'nama_isi' => $chk->item_text,
                'standar_jumlah' => $stdQty,
                'satuan' => 'buah',
                'kondisi_lokasi' => json_encode($kondisiMap),
                'keterangan' => 'Lengkap',
                'sort_order' => $idx + 1,
            ];
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_pemeriksaan_p3ks',
            'k3_pengusahaan_pemeriksaan_p3k_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-P3K/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'locations' => json_encode($unitP3kLocations),
                'catatan' => 'Seluruh isi kotak P3K lengkap dan belum melewati masa kedaluwarsa.',
                'input_by' => $userId,
            ],
            $p3kItemsPayload,
        );

        // 12. k3_pengusahaan_alat_tanggap_darurats + items (pulled from unit's EmergencyEquipment master data)
        $atdItemsPayload = [];
        foreach ($unitEmergencyEquipments as $idx => $eq) {
            $qty = str_contains($eq->name, 'APAR') ? count($aparItemsPayload) : (str_contains($eq->name, 'P3K') ? count($unitP3kLocations) : 2);
            $atdItemsPayload[] = [
                'no_urut' => $idx + 1,
                'jenis' => $eq->name,
                'siap_pakai' => $qty,
                'kadaluarsa' => 0,
                'kosong' => 0,
                'tgl_diisi_kembali' => null,
                'keterangan' => 'Siap Pakai',
                'sort_order' => $idx + 1,
            ];
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_alat_tanggap_darurats',
            'k3_pengusahaan_alat_tanggap_darurat_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-ATD/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'catatan' => 'Peralatan tanggap darurat siap digunakan.',
                'input_by' => $userId,
            ],
            $atdItemsPayload,
        );

        // 13. k3_pengusahaan_inventaris_apds + items (pulled from unit's ApdItem & ApdCategory master data)
        $pelengkapCategories = [
            'Tangga',
            'Lampu Penerangan',
            'Sirkulator Udara/Kipas',
            'TOA',
            'Tongkat Rambu-Rambu',
            'Rompi Nyala',
            'Lampu Sorot',
        ];
        $unitApdItems = ApdItem::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $invApdPayload = [];
        $seenGroups = ['I' => [], 'II' => []];
        foreach ($unitApdItems as $idx => $item) {
            $catName = $item->category?->name ?? 'Perlengkapan K3';
            $kat = in_array($catName, $pelengkapCategories, true) ? 'II' : 'I';
            $isFirstInGroup = ! isset($seenGroups[$kat][$catName]);
            if ($isFirstInGroup) {
                $seenGroups[$kat][$catName] = count($seenGroups[$kat]) + 1;
            }
            $invApdPayload[] = [
                'kategori' => $kat,
                'no_grup' => $seenGroups[$kat][$catName],
                'nama_grup' => $isFirstInGroup ? $catName.' :' : null,
                'nama_alat' => $item->name,
                'jumlah' => 10,
                'lokasi' => $item->location ?: $unit->name,
                'keterangan' => 'Baik',
                'sort_order' => $idx + 1,
            ];
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_inventaris_apds',
            'k3_pengusahaan_inventaris_apd_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-APD/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'catatan' => 'Stok dan distribusi APD personil pembangkit mencukupi.',
                'input_by' => $userId,
            ],
            $invApdPayload,
        );

        // 14. k3_pengusahaan_inspeksi_tempat_kerjas + items (pulled from unit's InspectionChecklist tempat-kerja)
        $unitTkChecklists = InspectionChecklist::query()
            ->where('unit_id', $unit->id)
            ->where('form_code', 'tempat-kerja')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $itkPayload = [];
        $catCounters = [];
        foreach ($unitTkChecklists as $idx => $chk) {
            $parts = explode(' — ', (string) $chk->item_text, 2);
            $cat = count($parts) === 2 ? trim($parts[0]) : 'UMUM';
            $itemText = count($parts) === 2 ? trim($parts[1]) : trim((string) $chk->item_text);
            $catCounters[$cat] = ($catCounters[$cat] ?? 0) + 1;
            $itkPayload[] = [
                'category' => $cat,
                'no_urut' => $catCounters[$cat],
                'item' => $itemText,
                'status' => 'Y',
                'comment' => 'Sesuai aturan 5R & SMK3',
                'sort_order' => $idx + 1,
            ];
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_inspeksi_tempat_kerjas',
            'k3_pengusahaan_inspeksi_tempat_kerja_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-ITK/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'tanggal_inspeksi' => $docDate,
                'departemen' => 'Operasi, Pemeliharaan & K3',
                'lokasi' => $unit->name,
                'tim_inspektur' => "{$signers['tl_name']}, {$signers['koord_name']}",
                'ketua_tim' => $signers['tl_name'],
                'inspektur' => $signers['petugas_name'],
                'catatan' => 'Lingkungan kerja aman, pencahayaan dan ventilasi memadai.',
                'input_by' => $userId,
            ],
            $itkPayload,
        );

        // 15. k3_pengusahaan_inspeksi_rambus + items
        $this->replaceParentAndItems(
            'k3_pengusahaan_inspeksi_rambus',
            'k3_pengusahaan_inspeksi_rambu_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-K3-RMB/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'tanggal_inspeksi' => $docDate,
                'catatan' => 'Seluruh rambu keselamatan terpasang dengan jelas.',
                'input_by' => $userId,
            ],
            [
                ['no_id' => 1, 'rambu_k3' => 'Wajib Menggunakan Pelindung Pendengaran (Earplug/Earmuff)', 'lokasi' => 'Power House', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Baik', 'sort_order' => 1],
                ['no_id' => 2, 'rambu_k3' => 'Dilarang Merokok — Area Mudah Terbakar', 'lokasi' => 'Area Tangki BBM', 'tingkat_pelanggaran' => 'Nihil', 'keterangan' => 'Baik', 'sort_order' => 2],
            ],
        );

        // 16. k3_pengusahaan_metode_pengujians + meta (pulled from unit's EquipmentCertificate master data)
        DB::table('k3_pengusahaan_metode_pengujians')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        DB::table('k3_pengusahaan_metode_pengujian_meta')->updateOrInsert(
            ['unit_id' => $unit->id, 'year' => $year, 'month' => $month],
            [
                'nomor_dokumen' => sprintf('FM-K3-MPP/%s/%02d/%d', $unit->code, $month, $year),
                'tanggal_terbit' => $docDate,
                'revisi' => '00',
                'halaman' => '1 dari 1',
                'catatan' => 'Metode pengujian peralatan sesuai regulasi K3 ketenagalistrikan.',
                'updated_at' => $nowTs,
                'created_at' => $nowTs,
            ],
        );
        $unitCerts = EquipmentCertificate::query()
            ->where('unit_id', $unit->id)
            ->with('category')
            ->orderBy('id')
            ->get();
        foreach ($unitCerts as $idx => $cert) {
            DB::table('k3_pengusahaan_metode_pengujians')->insert([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => (string) ($idx + 1),
                'nama_peralatan' => $cert->category?->name ?? 'Peralatan K3',
                'no_pengesahan' => $cert->uji_terakhir_nomor ?: sprintf('SUKET-K3/%s/%02d/2025', $unit->code, $idx + 1),
                'nama_kategori_alat' => trim($cert->jenis.($cert->kapasitas && $cert->kapasitas !== '-' ? " ({$cert->kapasitas})" : '')),
                'uji_visual' => 'Memenuhi',
                'uji_fungsi' => 'Memenuhi',
                'uji_beban' => 'Memenuhi',
                'uji_hydro' => '-',
                'ndt' => 'Memenuhi',
                'uji_ultrasonic_thickness' => 'Memenuhi',
                'uji_ketahanan' => 'Memenuhi',
                'sertifikasi_terakhir' => $cert->uji_terakhir_tanggal?->format('Y-m-d') ?? '2025-06-15',
                'sertifikasi_ulang' => $cert->uji_ulang_tanggal?->format('Y-m-d') ?? '2027-06-15',
                'keterangan' => 'Laik Operasi',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ]);
        }

        // 17. k3_pengusahaan_patrol_securities + items (pulled from unit's PatrolLocation master data)
        $scansMap = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $scansMap[(string) $d] = $d <= $daysDue ? 4 : 0;
        }
        $unitPatrolLocs = PatrolLocation::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();
        $patrolItemsPayload = [];
        foreach ($unitPatrolLocs as $idx => $loc) {
            $patrolItemsPayload[] = [
                'lokasi_kode' => $loc->code,
                'lokasi_nama' => $loc->name ?: $loc->code,
                'scans' => json_encode($scansMap),
                'total' => $daysDue * 4,
                'persentase' => 100.0,
                'sort_order' => $idx + 1,
            ];
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_patrol_securities',
            'k3_pengusahaan_patrol_security_items',
            $unit->id,
            $year,
            $month,
            [
                'judul' => "REKAPITULASI PATROLI KEAMANAN (GUARD TOUR) {$unit->name}",
                'catatan' => 'Patroli keamanan dilaksanakan 4 kali sehari pada seluruh titik pos pantau.',
                'input_by' => $userId,
            ],
            $patrolItemsPayload,
        );

        // 18. k3_pengusahaan_apel_keamanans + items (pulled from unit's SecurityPost master data)
        $unitSecurityPosts = SecurityPost::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
        if ($unitSecurityPosts === []) {
            $unitSecurityPosts = ['Regu A', 'Regu B', 'Regu C'];
        }
        $shiftTemplate = [
            ['shift' => 'Pagi', 'waktu_apel' => '08:00'],
            ['shift' => 'Sore', 'waktu_apel' => '16:00'],
            ['shift' => 'Malam', 'waktu_apel' => '22:00'],
        ];
        $apelItemsPayload = [];
        $apelOrder = 1;
        for ($d = 1; $d <= $daysDue; $d++) {
            $tglStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            foreach ($shiftTemplate as $sIdx => $sh) {
                $postName = $unitSecurityPosts[$sIdx % count($unitSecurityPosts)];
                $timLabel = preg_replace('/^Regu\s+/i', '', $postName) ?: $postName;
                $apelItemsPayload[] = [
                    'tanggal' => $tglStr,
                    'hari_ke' => $d,
                    'tim_regu' => $timLabel,
                    'shift' => $sh['shift'],
                    'waktu_apel' => $sh['waktu_apel'],
                    'jumlah_personil' => 2,
                    'kelengkapan_atribut' => 'Lengkap',
                    'paraf_komandan_regu' => '✓',
                    'keterangan' => 'Aman & Kondusif',
                    'sort_order' => $apelOrder++,
                ];
            }
        }
        $this->replaceParentAndItems(
            'k3_pengusahaan_apel_keamanans',
            'k3_pengusahaan_apel_keamanan_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-KAM-APEL/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'catatan' => 'Apel serah terima jaga regu satuan pengamanan terlaksana tertib.',
                'input_by' => $userId,
            ],
            $apelItemsPayload,
        );

        // 19. k3_pengusahaan_laporan_cctvs + items
        $this->replaceParentAndItems(
            'k3_pengusahaan_laporan_cctvs',
            'k3_pengusahaan_laporan_cctv_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-KAM-CCTV/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'catatan' => 'Seluruh kamera CCTV beroperasi normal dan NVR merekam penuh.',
                'input_by' => $userId,
            ],
            [
                ['no_urut' => 1, 'tanggal' => $docDate, 'lokasi_cctv' => 'Gerbang Utama & Pos Jaga Security', 'waktu_pantau' => '08:00 & 20:00', 'kondisi_pantau' => 'Normal / Aktif', 'keterangan' => 'Rekaman baik', 'sort_order' => 1],
                ['no_urut' => 2, 'tanggal' => $docDate, 'lokasi_cctv' => 'Power House & Area Tangki BBM', 'waktu_pantau' => '08:00 & 20:00', 'kondisi_pantau' => 'Normal / Aktif', 'keterangan' => 'Rekaman baik', 'sort_order' => 2],
            ],
        );

        // 20. k3_pengusahaan_buku_tamus + items
        $this->replaceParentAndItems(
            'k3_pengusahaan_buku_tamus',
            'k3_pengusahaan_buku_tamu_items',
            $unit->id,
            $year,
            $month,
            [
                'no_dokumen' => sprintf('FM-KAM-TAMU/%s/%02d/%d', $unit->code, $month, $year),
                'revisi' => '00',
                'tanggal_dokumen' => $docDate,
                'catatan' => 'Seluruh tamu wajib menukar kartu identitas, memakai visitor badge & APD.',
                'input_by' => $userId,
            ],
            [
                ['no_urut' => 1, 'tanggal' => $docDate, 'jumlah_kehadiran_tamu' => 5, 'tamu_pln' => 3, 'instansi' => 0, 'kontraktor' => 2, 'lainnya' => 0, 'keterangan' => 'Supervisi rutin & pengiriman logistik', 'sort_order' => 1],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $parentData
     * @param  list<array<string, mixed>>  $itemsData
     */
    private function replaceParentAndItems(
        string $parentTable,
        string $childTable,
        int $unitId,
        int $year,
        int $month,
        array $parentData,
        array $itemsData,
    ): void {
        $nowTs = now()->toDateTimeString();
        $oldIds = DB::table($parentTable)
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->pluck('id');
        if ($oldIds->isNotEmpty()) {
            DB::table($childTable)->whereIn('laporan_id', $oldIds)->delete();
            DB::table($parentTable)->whereIn('id', $oldIds)->delete();
        }

        $laporanId = DB::table($parentTable)->insertGetId([
            'unit_id' => $unitId,
            'year' => $year,
            'month' => $month,
            ...$parentData,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        $rows = [];
        foreach ($itemsData as $item) {
            $rows[] = [
                'laporan_id' => $laporanId,
                ...$item,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        if ($rows !== []) {
            DB::table($childTable)->insert($rows);
        }
    }
}
