<?php

namespace Database\Seeders;

use App\Enums\MaintenanceScope;
use App\Enums\MaterialKelengkapan;
use App\Enums\SchedulePlanType;
use App\Enums\WorkOrderSource;
use App\Enums\WoWaitingReason;
use App\Models\Employee;
use App\Models\HarAxialConrod;
use App\Models\HarBatteryVoltage;
use App\Models\HarClearanceValve;
use App\Models\HarCombustionPressure;
use App\Models\HarCounterWeight;
use App\Models\HarCrankshaftDeflection;
use App\Models\HarDailyMeeting;
use App\Models\HarHydrotest;
use App\Models\HarInjectorPressure;
use App\Models\HarInstruksiKerja;
use App\Models\HarJadwalHarian;
use App\Models\HarJadwalMeetingPemeliharaan;
use App\Models\HarJadwalP0P5;
use App\Models\HarJadwalPatrolCheck;
use App\Models\HarJadwalPembuatanIk;
use App\Models\HarJadwalPiketOnCall;
use App\Models\HarLaporanGangguan;
use App\Models\HarLaporanKegiatan;
use App\Models\HarLembarMeta;
use App\Models\HarLembarRow;
use App\Models\HarLogbookMutasi;
use App\Models\HarLubeQuality;
use App\Models\HarMaterialPeralatan;
use App\Models\HarMotorCurrent;
use App\Models\HarPatrolCheckReading;
use App\Models\HarPrelubeTest;
use App\Models\HarProgram5s5rItem;
use App\Models\HarTabelRow;
use App\Models\HarTimingInjectionPump;
use App\Models\HarUnsafeCondition;
use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\MaintenanceActivity;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceCycle;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceType;
use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\SrCategory;
use App\Models\Unit;
use App\Models\WorkGroup;
use App\Models\WoStatus;
use App\Services\Har\HarBatteryVoltagePdfBuilder;
use App\Services\Har\HarMotorCurrentPdfBuilder;
use App\Support\HarIkTemplates;
use App\Support\HarLembar\InventarisasiToolsLembar;
use App\Support\HarLembar\PatrolCheckPemeliharaanLembar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds comprehensive dummy data for Modul Pemeliharaan (HAR) Akses 1 & Akses 2
 * (Pengusahaan) across all active units.
 */
class DummyHarSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure HAR master catalogs exist (MaintenanceType, MaintenanceCycle, WoStatus, WorkGroup, SrCategory)
        $this->call(HarSeeder::class);

        $types = MaintenanceType::query()->get()->keyBy('code');
        $cycles = MaintenanceCycle::query()->get()->keyBy('code');
        $statuses = WoStatus::query()->get()->keyBy('code');
        $groups = WorkGroup::query()->get()->keyBy('code');
        $srCategories = SrCategory::query()->get()->keyBy('code');

        $now = Carbon::now();
        $year = (int) $now->year;
        $currentMonth = (int) $now->month;
        $prevMonth = $currentMonth > 1 ? $currentMonth - 1 : 12;
        $monthsToSeed = array_values(array_unique([$prevMonth, $currentMonth]));

        $units = Unit::query()->where('is_active', true)->orderBy('id')->get();

        foreach ($units as $unit) {
            $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('id')->get();
            if ($machines->isEmpty()) {
                continue;
            }

            $employees = Employee::query()->where('unit_id', $unit->id)->where('is_active', true)->get();
            $tlHar = $employees->firstWhere('position', 'Team Leader Pemeliharaan');
            $stafHar = $employees->firstWhere('position', 'Staf Pemeliharaan');
            $koordHar = $employees->firstWhere('position', 'Koordinator Pemeliharaan');
            $officeHar = $employees->firstWhere('position', 'Office Pemeliharaan');
            $projectLeader = $employees->firstWhere('position', 'Project Leader');
            $harmesList = $employees->where('position', Employee::POSITION_HARMES)->values();
            $harlistList = $employees->where('position', Employee::POSITION_HARLIST)->values();

            $managerUl = $unit->service_unit_id
                ? Employee::query()->where('service_unit_id', $unit->service_unit_id)->where('position', 'Manager UL')->where('is_active', true)->first()
                : null;

            $inputUserId = $officeHar?->user_id ?? $koordHar?->user_id ?? $tlHar?->user_id;
            $pengusahaanUserId = $stafHar?->user_id ?? $tlHar?->user_id ?? $inputUserId;

            $signers = [
                'tl_id' => $tlHar?->id,
                'tl_name' => $tlHar?->name ?? 'Team Leader Pemeliharaan',
                'staf_id' => $stafHar?->id,
                'staf_name' => $stafHar?->name ?? 'Staf Pemeliharaan',
                'koord_id' => $koordHar?->id,
                'koord_name' => $koordHar?->name ?? 'Koordinator Pemeliharaan',
                'office_name' => $officeHar?->name ?? 'Office Pemeliharaan',
                'pl_name' => $projectLeader?->name ?? 'Project Leader',
                'mgr_id' => $managerUl?->id,
                'mgr_name' => $managerUl?->name ?? 'Manager UL',
                'harmes_name' => $harmesList->first()?->name ?? 'Teknisi Harmes',
                'harlist_name' => $harlistList->first()?->name ?? 'Teknisi Harlist',
            ];

            // 1. Seed 6-month trend history (months 6..9 of $year)
            for ($m = max(1, $currentMonth - 5); $m < $prevMonth; $m++) {
                $this->seedHarTrendMonth(
                    $unit,
                    $machines,
                    $types,
                    $cycles,
                    $statuses,
                    $groups,
                    $srCategories,
                    $year,
                    $m,
                    Carbon::create($year, $m, 1)->daysInMonth,
                    $signers,
                    $inputUserId,
                    $pengusahaanUserId,
                );
            }

            // 2. Seed Yearly HAR Jadwal & Yearly Lembar Blackstart
            $this->seedYearlyHar($unit, $year, $signers['koord_name'], $inputUserId);

            // 3. Seed Full Monthly HAR Akses 1 & Akses 2 ($prevMonth & $currentMonth)
            foreach ($monthsToSeed as $month) {
                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                $daysDue = ($month === $currentMonth) ? min((int) $now->day, $daysInMonth) : $daysInMonth;

                $this->seedMonthlyHarAkses1(
                    $unit,
                    $machines,
                    $harmesList,
                    $harlistList,
                    $types,
                    $cycles,
                    $statuses,
                    $groups,
                    $srCategories,
                    $year,
                    $month,
                    $daysInMonth,
                    $daysDue,
                    $signers,
                    $inputUserId,
                );

                $this->seedMonthlyHarPengusahaan(
                    $unit,
                    $machines,
                    $year,
                    $month,
                    $daysInMonth,
                    $daysDue,
                    $signers,
                    $pengusahaanUserId,
                );
            }
        }
    }

    private function seedHarTrendMonth(
        Unit $unit,
        $machines,
        $types,
        $cycles,
        $statuses,
        $groups,
        $srCategories,
        int $year,
        int $month,
        int $daysDue,
        array $signers,
        ?int $inputUserId,
        ?int $pengusahaanUserId,
    ): ReportPeriod {
        $nowTs = now()->toDateTimeString();
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $firstMachine = $machines->first();

        $period = ReportPeriod::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => $daysInMonth, 'total_hours' => $daysInMonth * 24],
        );

        DB::table('work_orders')->where('unit_id', $unit->id)->where('report_period_id', $period->id)->delete();
        ServiceRequest::query()->where('unit_id', $unit->id)->where('report_period_id', $period->id)->delete();
        HarUnsafeCondition::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

        $woSpecs = [
            ['PM', 'CLOSE', 'MECHD', 'P1', null, 2500000, 4800000, "PM Inspection Compresi Cylinder Head P1 {$firstMachine->name}"],
            ['PM', 'CLOSE', 'MECHD', 'P2', null, 3500000, 7200000, "PM Penggantian Filter Oli & BBM P2 {$firstMachine->name}"],
            ['PM', 'CLOSE', 'ELECD', 'P3', null, 1800000, 3200000, "PM Generator Panel & Megger Inspection P3 {$firstMachine->name}"],
            ['CM', 'CLOSE', 'MECHD', null, null, 4200000, 6500000, "Perbaikan Seal Pompa Jacket Water {$firstMachine->name}"],
            ['CM', 'INPRG', 'MECHD', null, WoWaitingReason::Material->value, 1500000, 2950000, "Penggantian O-Ring Cooler Pelumas {$firstMachine->name}"],
            ['PDM', 'CLOSE', 'ELECD', null, null, 1200000, 850000, "Pengukuran Vibrasi & Thermography Panel {$firstMachine->name}"],
        ];

        $woRows = [];
        foreach ($woSpecs as $idx => [$typeCode, $statusCode, $groupCode, $cycleCode, $waitReason, $srvCost, $matCost, $desc]) {
            $day = min($daysDue, 2 + ($idx * 2));
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $machine = $machines[$idx % $machines->count()];
            $woRows[] = [
                'unit_id' => $unit->id,
                'report_period_id' => $period->id,
                'wonum' => sprintf('WO%02d%02d%02d%02d', $unit->id, $year % 100, $month, $idx + 1),
                'description' => $desc,
                'maintenance_type_id' => $types[$typeCode]?->id ?? $types->first()?->id,
                'engine_id' => $machine->id,
                'assetnum' => $machine->serial_number,
                'work_group_id' => $groups[$groupCode]?->id ?? $groups->first()?->id,
                'owner_group' => $groupCode,
                'wo_status_id' => $statuses[$statusCode]?->id ?? $statuses->first()?->id,
                'cycle_id' => $cycleCode ? ($cycles[$cycleCode]?->id ?? null) : null,
                'report_date' => $dateStr,
                'sched_start' => $dateStr,
                'sched_finish' => $dateStr,
                'actual_finish' => $statusCode === 'CLOSE' ? $dateStr : null,
                'waiting_reason' => $waitReason,
                'priority_text' => $typeCode === 'CM' ? 'High' : 'Normal',
                'materials' => json_encode([['name' => 'Filter Element', 'qty' => 2, 'unit' => 'PCS']]),
                'service_cost' => $srvCost,
                'material_cost' => $matCost,
                'source' => WorkOrderSource::Manual->value,
                'input_by' => $inputUserId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('work_orders')->insert($woRows);

        for ($s = 1; $s <= 4; $s++) {
            $machine = $machines[($s - 1) % $machines->count()];
            $catCode = ($s % 2 === 1) ? 'FLM' : 'CM';
            ServiceRequest::query()->create([
                'unit_id' => $unit->id,
                'report_period_id' => $period->id,
                'sr_number' => sprintf('SR%02d%02d%02d%02d', $unit->id, $year % 100, $month, $s),
                'description' => "Pengecekan indikasi rembesan oli & kekencangan baut {$machine->name}",
                'sr_category_id' => $srCategories[$catCode]?->id ?? $srCategories->first()?->id,
                'status' => $s === 4 ? 'open' : 'close',
                'engine_id' => $machine->id,
                'source' => 'manual',
                'keterangan' => 'Telah ditindaklanjuti sesuai prosedur FLM/CM',
                'input_by' => $inputUserId,
            ]);
        }

        foreach (['har', 'operasi'] as $idx => $src) {
            HarUnsafeCondition::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'source' => $src,
                'periode' => 'MINGGU KE - '.($idx + 1),
                'kategori' => 'UNSAFE CONDITION',
                'temuan' => 'Ceceran oli ringan di sekitar lantai pondasi pompa transfer',
                'kondisi' => 'Lantai licin berpotensi terpeleset',
                'tindak_lanjut' => 'Pembersihan dengan oil absorbent dan pengencangan nipel pipa',
                'rekomendasi' => 'Monitoring rutin setiap shift',
                'lokasi' => 'Power House / Ruang Mesin',
                'keterangan' => 'close',
                'sort_order' => $idx + 1,
                'input_by' => $inputUserId,
            ]);
        }

        // Seed Vibration & Lube Quality for PdM 6-month trend
        $this->seedVibrationAndLubeQuality($unit, $machines, $year, $month, min($daysDue, 10), $signers, $pengusahaanUserId);

        return $period;
    }

    private function seedYearlyHar(Unit $unit, int $year, string $koordName, ?int $userId): void
    {
        HarJadwalPembuatanIk::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
        $ikItems = [
            'IK Pemeliharaan Rutin P0 - P5 Mesin Diesel',
            'IK Overhaul Cylinder Head & Penggantian Injector',
            'IK Pengukuran Crankshaft Web Deflection',
            'IK Pemeliharaan Generator & Pengujian Tahanan Isolasi (Megger)',
            'IK Pemeliharaan Panel Kubikel 20 kV & Sistem DC',
        ];
        foreach ($ikItems as $idx => $title) {
            HarJadwalPembuatanIk::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'instruksi_kerja' => $title,
                'pic_pembuat' => $koordName,
                'rencana_bulan' => [1, 3, 6, 9, 10, 11],
                'realisasi_bulan' => [1, 3, 6, 9, 10, 11],
                'keterangan' => 'Dokumen IK terbit & terdistribusi',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // Pemeriksaan Blackstart (yearly lembar: month = 0)
        HarLembarRow::query()
            ->where('unit_id', $unit->id)
            ->where('lembar', 'pemeriksaan-blackstart')
            ->where('year', $year)
            ->delete();

        $allWeeks = [];
        for ($m = 1; $m <= 11; $m++) {
            for ($w = 1; $w <= 4; $w++) {
                $allWeeks["m{$m}w{$w}"] = '1';
            }
        }
        $bsRows = [
            ['uraian' => 'ENGINE DIESEL GENSET (EDG)', 'pic' => $koordName],
            ['uraian' => 'SISTEM BATTERY & CHARGER', 'pic' => $koordName],
            ['uraian' => 'AIR COMPRESSOR START', 'pic' => $koordName],
            ['uraian' => 'PANEL KONTROL BLACKSTART', 'pic' => $koordName],
        ];
        foreach ($bsRows as $idx => $r) {
            HarLembarRow::query()->create([
                'unit_id' => $unit->id,
                'lembar' => 'pemeriksaan-blackstart',
                'year' => $year,
                'month' => 0,
                'subject' => '',
                'section' => 'data-teknis',
                'sort_order' => $idx,
                'fields' => $r,
                'cells' => ['rencana' => $allWeeks, 'realisasi' => $allWeeks],
                'input_by' => $userId,
            ]);
        }
        HarLembarMeta::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'lembar' => 'pemeriksaan-blackstart', 'year' => $year, 'month' => 0, 'subject' => ''],
            ['catatan' => 'Instalasi blackstart dalam kondisi siap operasi.', 'input_by' => $userId],
        );
    }

    private function seedMonthlyHarAkses1(
        Unit $unit,
        $machines,
        $harmesList,
        $harlistList,
        $types,
        $cycles,
        $statuses,
        $groups,
        $srCategories,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        array $signers,
        ?int $userId,
    ): void {
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // Work Orders, Service Requests & Unsafe Conditions
        $period = $this->seedHarTrendMonth(
            $unit,
            $machines,
            $types,
            $cycles,
            $statuses,
            $groups,
            $srCategories,
            $year,
            $month,
            $daysDue,
            $signers,
            $userId,
            $userId,
        );

        $workDays = [];
        for ($d = 1; $d <= $daysDue; $d++) {
            $dow = Carbon::create($year, $month, $d)->dayOfWeek;
            if (! in_array($dow, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                $workDays[] = $d;
            }
        }
        if ($workDays === []) {
            $workDays = [1];
        }
        $weeklyDays = array_values(array_filter([4, 11, 18, 25], fn (int $d): bool => $d <= $daysDue));
        if ($weeklyDays === []) {
            $weeklyDays = [1];
        }

        // 1. har_jadwal_harians
        HarJadwalHarian::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $harianItems = [
            ['no_urut' => '1', 'kegiatan' => 'Patrol check & pengecekan kebocoran sistem pelumas/BBM/pendingin mesin', 'target' => count($workDays), 'jadwal' => $workDays],
            ['no_urut' => '2', 'kegiatan' => 'Pemeriksaan tekanan compressor udara start & drain kondensat botol angin', 'target' => count($workDays), 'jadwal' => $workDays],
            ['no_urut' => '3', 'kegiatan' => 'Pembersihan strainer BBM dan filter udara turbocharger', 'target' => count($weeklyDays), 'jadwal' => $weeklyDays],
            ['no_urut' => '4', 'kegiatan' => 'Pemeriksaan level elektrolit & tegangan baterai 24/110 VDC', 'target' => count($weeklyDays), 'jadwal' => $weeklyDays],
            ['no_urut' => '5', 'kegiatan' => 'Pengambilan sampel oli & pengukuran vibrasi rutin bulanan', 'target' => 1, 'jadwal' => [min($daysDue, 10)]],
        ];
        foreach ($harianItems as $idx => $item) {
            HarJadwalHarian::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $item['no_urut'],
                'kegiatan' => $item['kegiatan'],
                'target' => $item['target'],
                'rencana_count' => $item['target'],
                'realisasi_count' => count($item['jadwal']),
                'jadwal' => $item['jadwal'],
                'keterangan' => 'Terlaksana sesuai jadwal',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // 2. har_jadwal_p0_p5s
        HarJadwalP0P5::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach ($machines->values() as $idx => $m) {
            $d1 = min($daysDue, 3 + ($idx % 3));
            $d2 = min($daysDue, 12 + ($idx % 3));
            $rencanaMap = [(string) $d1 => 'P1', (string) $d2 => 'P2'];
            $durasiMap = [(string) $d1 => '2', (string) $d2 => '4'];
            $warnaMap = [(string) $d1 => 'hijau', (string) $d2 => 'kuning'];

            HarJadwalP0P5::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'machine_id' => $m->id,
                'rencana' => $rencanaMap,
                'realisasi' => $rencanaMap,
                'durasi' => $durasiMap,
                'warna' => ['rencana' => $warnaMap, 'realisasi' => $warnaMap],
                'operating_hours' => '18500',
                'keterangan' => 'Pemeliharaan preventif P0-P5 terlaksana',
                'input_by' => $userId,
            ]);
        }

        // 3. har_jadwal_piket_on_calls & 4. har_jadwal_patrol_checks
        HarJadwalPiketOnCall::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        HarJadwalPatrolCheck::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

        $patrolDayMap = [];
        foreach ($workDays as $wd) {
            $patrolDayMap[(string) $wd] = '1';
        }

        $sortPiket = 1;
        foreach ($harmesList->take(3)->values() as $idx => $emp) {
            HarJadwalPiketOnCall::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'kategori' => 'HARMES',
                'employee_id' => $emp->id,
                'nama' => $emp->name,
                'no_hp' => '08124567890'.$idx,
                'target' => count($workDays),
                'piket' => $workDays,
                'sort_order' => $sortPiket++,
                'input_by' => $userId,
            ]);
            HarJadwalPatrolCheck::query()->create([
                'unit_id' => $unit->id,
                'employee_id' => $emp->id,
                'year' => $year,
                'month' => $month,
                'rencana' => $patrolDayMap,
                'realisasi' => $patrolDayMap,
                'input_by' => $userId,
            ]);
        }
        foreach ($harlistList->take(2)->values() as $idx => $emp) {
            HarJadwalPiketOnCall::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'kategori' => 'HARLIS',
                'employee_id' => $emp->id,
                'nama' => $emp->name,
                'no_hp' => '08134567890'.$idx,
                'target' => count($workDays),
                'piket' => $workDays,
                'sort_order' => $sortPiket++,
                'input_by' => $userId,
            ]);
            HarJadwalPatrolCheck::query()->create([
                'unit_id' => $unit->id,
                'employee_id' => $emp->id,
                'year' => $year,
                'month' => $month,
                'rencana' => $patrolDayMap,
                'realisasi' => $patrolDayMap,
                'input_by' => $userId,
            ]);
        }

        // 5. har_jadwal_meeting_pemeliharaans
        HarJadwalMeetingPemeliharaan::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $meetingDayMap = [];
        foreach ($weeklyDays as $wd) {
            $meetingDayMap[(string) $wd] = 1;
        }
        foreach (['TEAM PEMELIHARAAN', 'TEAM OPERASI'] as $idx => $uraian) {
            HarJadwalMeetingPemeliharaan::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'uraian' => $uraian,
                'rencana' => $meetingDayMap,
                'realisasi' => $meetingDayMap,
                'target' => count($weeklyDays),
                'keterangan' => 'Koordinasi mingguan HAR & Operasi',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // 6. HarLembars (inventarisasi-tools & patrol-check-pemeliharaan + readings)
        HarLembarRow::query()
            ->where('unit_id', $unit->id)
            ->whereIn('lembar', ['inventarisasi-tools', 'patrol-check-pemeliharaan'])
            ->where('year', $year)
            ->where('month', $month)
            ->delete();
        HarPatrolCheckReading::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

        $toolsRows = [];
        $nowTs = now()->toDateTimeString();
        foreach (array_slice((new InventarisasiToolsLembar)->defaultRows(), 0, 12) as $idx => $defaultRow) {
            $toolsRows[] = [
                'unit_id' => $unit->id,
                'lembar' => 'inventarisasi-tools',
                'year' => $year,
                'month' => $month,
                'subject' => '',
                'section' => $defaultRow['section'],
                'sort_order' => $idx,
                'fields' => json_encode($defaultRow['fields']),
                'cells' => json_encode(['main' => ['w1' => '1', 'w2' => '1', 'w3' => '1', 'w4' => '1']]),
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        DB::table('har_lembar_rows')->insert($toolsRows);

        $patrolCells = [];
        for ($d = 1; $d <= $daysDue; $d++) {
            $patrolCells["d{$d}"] = 'N';
        }
        $patrolLembarRows = [];
        $defaultPatrolRows = (new PatrolCheckPemeliharaanLembar)->defaultRows();
        foreach ($machines as $m) {
            foreach ($defaultPatrolRows as $sortIdx => $defaultRow) {
                $patrolLembarRows[] = [
                    'unit_id' => $unit->id,
                    'lembar' => 'patrol-check-pemeliharaan',
                    'year' => $year,
                    'month' => $month,
                    'subject' => (string) $m->id,
                    'section' => $defaultRow['section'],
                    'sort_order' => $sortIdx,
                    'fields' => json_encode($defaultRow['fields']),
                    'cells' => json_encode(['main' => $patrolCells]),
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
            HarLembarMeta::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'lembar' => 'patrol-check-pemeliharaan', 'year' => $year, 'month' => $month, 'subject' => (string) $m->id],
                ['catatan' => 'Seluruh sistem mesin dalam kondisi normal.', 'input_by' => $userId],
            );
            HarPatrolCheckReading::query()->create([
                'unit_id' => $unit->id,
                'machine_id' => $m->id,
                'year' => $year,
                'month' => $month,
                'day' => min($daysDue, 5),
                'values' => [
                    'beban' => 750.0,
                    'tek_pelumas_masuk_mesin' => 4.2,
                    'suhu_pelumas_masuk_mesin' => 68.0,
                    'suhu_air_pendingin_keluar_mesin' => 79.0,
                    'suhu_gas_buang_rata' => 385.0,
                ],
                'input_by' => $userId,
            ]);
        }
        foreach (array_chunk($patrolLembarRows, 500) as $chunk) {
            DB::table('har_lembar_rows')->insert($chunk);
        }

        // 7. HarTabels (laporan-gangguan & abnormal-gangguan)
        HarTabelRow::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $firstMachine = $machines->first();
        HarTabelRow::query()->create([
            'unit_id' => $unit->id,
            'tabel' => 'laporan-gangguan',
            'year' => $year,
            'month' => $month,
            'sort_order' => 0,
            'data' => [
                'mesin' => $firstMachine->name,
                'peralatan' => 'Cooling System / Radiator',
                'uraian_gangguan' => 'Suhu air pendingin naik saat beban puncak',
                'penyebab' => 'Kisi radiator kotor oleh debu',
                'tindakan_perbaikan' => 'Flushing dan penyemprotan kisi radiator dengan air bertekanan',
                'tanggal_mulai' => sprintf('%04d-%02d-07', $year, $month),
                'tanggal_selesai' => sprintf('%04d-%02d-07', $year, $month),
                'durasi_jam' => '1.0',
                'status' => 'Selesai',
                'keterangan' => 'Mesin kembali beroperasi normal',
            ],
            'input_by' => $userId,
        ]);
        HarTabelRow::query()->create([
            'unit_id' => $unit->id,
            'tabel' => 'abnormal-gangguan',
            'year' => $year,
            'month' => $month,
            'sort_order' => 0,
            'data' => [
                'tanggal' => sprintf('%04d-%02d-07', $year, $month),
                'mesin' => $firstMachine->name,
                'uraian_kondisi' => 'Rembesan halus pada gasket cover rocker arm',
                'kategori' => 'Abnormal',
                'tindak_lanjut' => 'Penggantian gasket rocker arm pada saat jadwal P1',
                'pic' => $signers['harmes_name'],
                'status' => 'Closed',
                'keterangan' => 'Tidak ada rembesan lagi',
            ],
            'input_by' => $userId,
        ]);

        // 8. Maintenance Activities, Tasks, Materials, Costs & Attachments
        MaintenanceActivity::query()->where('unit_id', $unit->id)->where('report_period_id', $period->id)->delete();
        DB::table('maintenance_costs')->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        MaintenanceAttachment::query()->where('unit_id', $unit->id)->where('report_period_id', $period->id)->delete();

        $actDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 12));
        $activity = MaintenanceActivity::query()->create([
            'unit_id' => $unit->id,
            'report_period_id' => $period->id,
            'activity_date' => $actDate,
            'engine_id' => $firstMachine->id,
            'maintenance_type_id' => $types['PM']?->id ?? $types->first()?->id,
            'work_result' => "Pemeliharaan Rutin P2 {$firstMachine->name}: penggantian filter oli, filter BBM, dan pengecekan kelistrikan selesai.",
            'no_lh05' => '-',
            'no_sr' => sprintf('SR%02d%02d%02d01', $unit->id, $year % 100, $month),
            'no_tug9' => sprintf('TUG9/%s/%02d/%d', $unit->code, $month, $year),
            'keterangan' => 'Selesai tepat waktu',
            'input_by' => $userId,
        ]);
        $activity->tasks()->createMany([
            ['task_description' => 'Penggantian elemen filter minyak pelumas & pembersihan housing', 'sort_order' => 1],
            ['task_description' => 'Pemeriksaan kekencangan baut terminal generator & megger test', 'sort_order' => 2],
        ]);
        $activity->materials()->createMany([
            ['material_name' => 'Filter Oli Utama (Lube Oil Filter)', 'part_number' => 'LF-9009', 'quantity' => 2, 'unit_of_measure' => 'PCS'],
            ['material_name' => 'Filter Bahan Bakar (Fuel Filter)', 'part_number' => 'FF-5320', 'quantity' => 2, 'unit_of_measure' => 'PCS'],
        ]);

        DB::table('maintenance_costs')->insert([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'service_cost' => 18200000,
            'material_cost' => 31200000,
            'use_manual' => false,
            'keterangan' => 'Biaya pemeliharaan rutin & korektif bulanan',
            'input_by' => $userId,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ]);

        MaintenanceAttachment::query()->create([
            'unit_id' => $unit->id,
            'report_period_id' => $period->id,
            'activity_id' => $activity->id,
            'engine_id' => $firstMachine->id,
            'title' => "Dokumentasi Pemeliharaan P2 {$firstMachine->name}",
            'photo_path' => "har/attachments/eviden-p2-{$unit->id}-{$year}-{$month}.jpg",
            'caption' => 'Foto pelaksanaan penggantian filter dan pembersihan cooler',
            'taken_date' => $actDate,
            'sort_order' => 1,
            'input_by' => $userId,
        ]);

        // 9. har_material_peralatans
        HarMaterialPeralatan::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $harMats = [
            ['kategori' => 'PERALATAN', 'kode_material' => 'HAR-TL-01', 'stok_code' => 'SC-HAR-01', 'nama_item' => 'Kunci Momen (Torque Wrench) 50-350 Nm', 'satuan' => 'SET', 'stok_awal' => 2, 'masuk' => 0, 'keluar' => 0, 'stok_akhir' => 2, 'harga_satuan' => 4500000],
            ['kategori' => 'PERALATAN', 'kode_material' => 'HAR-TL-02', 'stok_code' => 'SC-HAR-02', 'nama_item' => 'Insulation Tester (Megger) 1000V', 'satuan' => 'UNIT', 'stok_awal' => 1, 'masuk' => 0, 'keluar' => 0, 'stok_akhir' => 1, 'harga_satuan' => 6800000],
            ['kategori' => 'MATERIAL', 'kode_material' => 'HAR-MT-01', 'stok_code' => 'SC-HAR-03', 'nama_item' => 'Filter Pelumas Mesin Utama', 'satuan' => 'BH', 'stok_awal' => 12, 'masuk' => 6, 'keluar' => 4, 'stok_akhir' => 14, 'harga_satuan' => 450000],
            ['kategori' => 'MATERIAL', 'kode_material' => 'HAR-MT-02', 'stok_code' => 'SC-HAR-04', 'nama_item' => 'Gasket Cylinder Head Cover', 'satuan' => 'SET', 'stok_awal' => 8, 'masuk' => 4, 'keluar' => 2, 'stok_akhir' => 10, 'harga_satuan' => 380000],
        ];
        foreach ($harMats as $idx => $hm) {
            HarMaterialPeralatan::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                ...$hm,
                'pemakaian_rata_rata' => 2,
                'safety_stock' => 4,
                'ilt' => 2,
                'rop' => 8,
                'roq' => 6,
                'kelengkapan' => MaterialKelengkapan::Lengkap,
                'uraian_kondisi' => 'Lengkap dan siap pakai di workshop HAR',
                'checked_by' => $userId,
                'checked_at' => now(),
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // 10. har_program_5s5r_items
        HarProgram5s5rItem::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $programs = ['ringkas', 'rapi', 'resik', 'rawat', 'rajin'];
        foreach (range(1, 4) as $minggu) {
            foreach ($programs as $prog) {
                HarProgram5s5rItem::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'minggu' => $minggu,
                    'program' => $prog,
                    'detail' => 'Penataan dan pembersihan area workshop pemeliharaan, special tools, serta spare part',
                    'pic' => $signers['harmes_name'],
                    'kondisi_awal' => 'Baik',
                    'membersihkan' => true,
                    'merapikan' => true,
                    'membuang_sampah' => $prog === 'resik',
                    'mengecat' => false,
                    'lainnya' => false,
                    'progres' => '76-100',
                    'kondisi_akhir' => 'Baik',
                    'jumlah' => 5,
                    'keterangan' => 'Workshop bersih dan tools tertata sesuai shadow board',
                    'input_by' => $userId,
                ]);
            }
        }

        // 11. har_instruksi_kerjas (date_col = tanggal)
        HarInstruksiKerja::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->delete();

        $harIkDefaults = array_slice(HarIkTemplates::all(), 1, 2);
        foreach ($harIkDefaults as $idx => $tpl) {
            HarInstruksiKerja::query()->create([
                'unit_id' => $unit->id,
                'sort_order' => $idx + 1,
                'kop' => $tpl['kop'] ?? "INSTRUKSI KERJA PEMELIHARAAN {$unit->name}",
                'judul' => $tpl['judul'] ?? "PEMELIHARAAN PERIODIK {$firstMachine->name}",
                'mesin' => $firstMachine->name,
                'no_dokumen' => sprintf('IK-HAR/%s/%02d/%d', $unit->code, $idx + 1, $year),
                'tanggal' => sprintf('%04d-%02d-03', $year, $month),
                'revisi' => '00',
                'sections' => $tpl['sections'] ?? [
                    ['judul' => 'TUJUAN', 'bernomor' => true, 'gaya' => 'paragraf', 'lanjut' => false, 'pengantar' => 'Pedoman pelaksanaan pemeliharaan mesin secara aman dan tepat mutu.', 'butir' => []],
                ],
                'dibuat_jabatan' => 'Koordinator Pemeliharaan',
                'dibuat_nama' => $signers['koord_name'],
                'disetujui_jabatan' => 'Project Leader',
                'disetujui_nama' => $signers['pl_name'],
                'input_by' => $userId,
            ]);
        }

        // 12. har_laporan_gangguans (date_col = tanggal_laporan)
        HarLaporanGangguan::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal_laporan', [$startOfMonth, $endOfMonth])
            ->delete();

        $ggnDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 7));
        HarLaporanGangguan::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'nomor' => sprintf('LH05/%s/%02d/%d/001', $unit->code, $month, $year),
            'tanggal_laporan' => $ggnDate,
            'hal' => "Laporan Gangguan Sistem Pendingin {$firstMachine->name}",
            'form_code' => 'LH-05',
            'unit_kesatuan' => $unit->name,
            'machine_id' => $firstMachine->id,
            'merek' => $firstMachine->merk,
            'type' => $firstMachine->type,
            'no_seri' => $firstMachine->serial_number,
            'rh' => '24500 Jam',
            'jsb' => '1200 Jam',
            'jsmo' => '8500 Jam',
            'jsi_terakhir' => '350 Jam',
            'fungsi_pembangkit' => 'Base / Peak Load',
            'daya_terpasang' => (string) $firstMachine->capacity_kw,
            'daya_mampu' => (string) round((float) $firstMachine->capacity_kw * 0.9, 2),
            'tanggal_jam_kerusakan' => "{$ggnDate} / 14:15 WITA",
            'peralatan_rusak' => 'Kisi Radiator & Strainer Jacket Water',
            'gejala' => 'Temperatur air pendingin meningkat hingga 87°C saat beban puncak',
            'urutan_kejadian' => "1. Pukul 14:15 WITA alarm high temp menyala.\n2. Beban diturunkan bertahap dan dilakukan flushing kisi radiator.\n3. Pukul 15:15 WITA mesin kembali paralel normal.",
            'parameter_terkait' => 'Jacket Water Temp: 87°C (Normal: 78-82°C)',
            'analisa_penyebab' => 'Akumulasi debu pada kisi radiator menghambat pelepasan panas',
            'akibat' => 'Derating sementara 1 jam untuk pembersihan radiator',
            'tindak_lanjut_pendek' => 'Flushing kisi radiator dengan high pressure cleaner',
            'tindak_lanjut_panjang' => 'Peningkatan frekuensi pembersihan radiator setiap 2 minggu',
            'eviden' => null,
            'sort_order' => 1,
            'input_by' => $userId,
        ]);

        // 13. har_daily_meetings
        HarDailyMeeting::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $dmDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 4));
        HarDailyMeeting::query()->create([
            'unit_id' => $unit->id,
            'token' => Str::random(32),
            'year' => $year,
            'month' => $month,
            'tanggal' => $dmDate,
            'acara' => 'Daily Meeting Pemeliharaan, Evaluasi WO P1-P5 & Safety Briefing',
            'waktu' => '08:00 - 08:45 WITA',
            'tempat' => "Ruang Rapat {$unit->name}",
            'peserta' => [
                ['nama' => $signers['koord_name'], 'jabatan' => 'Koordinator Pemeliharaan', 'bagian' => 'HAR', 'ttd' => 'Hadir'],
                ['nama' => $signers['harmes_name'], 'jabatan' => 'Harmes', 'bagian' => 'HAR Mesin', 'ttd' => 'Hadir'],
                ['nama' => $signers['harlist_name'], 'jabatan' => 'Harlist', 'bagian' => 'HAR Listrik', 'ttd' => 'Hadir'],
            ],
            'eviden' => [],
            'absensi_dibuka' => true,
            'input_by' => $userId,
        ]);

        // 14. har_logbook_mutasis
        HarLogbookMutasi::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        HarLogbookMutasi::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'tanggal' => $dmDate,
            'absensi' => [
                ['nama' => $signers['harmes_name'], 'jabatan' => 'Harmes', 'keterangan' => 'Piket Pagi', 'paraf' => 'Hadir'],
                ['nama' => $signers['harlist_name'], 'jabatan' => 'Harlist', 'keterangan' => 'Piket Pagi', 'paraf' => 'Hadir'],
            ],
            'apd' => [
                ['item' => 'Safety Helmet', 'keterangan' => 'Lengkap & Baik'],
                ['item' => 'Safety Shoes', 'keterangan' => 'Lengkap & Baik'],
                ['item' => 'Earplug / Earmuff', 'keterangan' => 'Lengkap & Baik'],
            ],
            'rutin' => [
                ['uraian' => "Inspeksi harian & pengecekan level oli {$firstMachine->name}", 'keterangan' => 'Selesai'],
            ],
            'non_rutin' => [
                ['uraian' => 'Flushing kisi radiator & pembersihan strainer BBM', 'keterangan' => 'Selesai'],
            ],
            'kondisi_k3' => [
                ['uraian' => 'Area workshop bersih, bebas ceceran oli, APAR siap pakai', 'keterangan' => 'Aman'],
            ],
            'input_by' => $userId,
        ]);
    }

    private function seedMonthlyHarPengusahaan(
        Unit $unit,
        $machines,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        array $signers,
        ?int $userId,
    ): void {
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        $firstMachine = $machines->first();
        $testDate = sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 10));

        // 1. maintenance_schedules (6 rows per machine: rencana/realisasi × har/pelumas/air)
        MaintenanceSchedule::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach ($machines as $m) {
            foreach ([SchedulePlanType::Rencana, SchedulePlanType::Realisasi] as $planType) {
                MaintenanceSchedule::query()->create([
                    'unit_id' => $unit->id,
                    'engine_id' => $m->id,
                    'year' => $year,
                    'month' => $month,
                    'plan_type' => $planType,
                    'scope' => MaintenanceScope::Har,
                    'schedule_data' => ['3' => 'P1', '12' => 'P2', '22' => 'P3'],
                    'durasi_data' => ['3' => 2, '12' => 4, '22' => 6],
                    'jam_operasi' => 18500,
                    'keterangan' => 'Sesuai jadwal P0-P5',
                    'input_by' => $userId,
                ]);
                MaintenanceSchedule::query()->create([
                    'unit_id' => $unit->id,
                    'engine_id' => $m->id,
                    'year' => $year,
                    'month' => $month,
                    'plan_type' => $planType,
                    'scope' => MaintenanceScope::Pelumas,
                    'schedule_data' => ['5' => 'SPL', '12' => 'GT'],
                    'durasi_data' => ['5' => 1, '12' => 2],
                    'jam_operasi' => 18500,
                    'keterangan' => 'Pengambilan sampel & ganti oli rutin',
                    'input_by' => $userId,
                ]);
                MaintenanceSchedule::query()->create([
                    'unit_id' => $unit->id,
                    'engine_id' => $m->id,
                    'year' => $year,
                    'month' => $month,
                    'plan_type' => $planType,
                    'scope' => MaintenanceScope::Air,
                    'schedule_data' => ['6' => 'SPL', '18' => 'GT'],
                    'durasi_data' => ['6' => 1, '18' => 2],
                    'jam_operasi' => 18500,
                    'keterangan' => 'Pengujian & treatment air pendingin',
                    'input_by' => $userId,
                ]);
            }
        }

        // 2. har_laporan_kegiatans (mesin & listrik)
        HarLaporanKegiatan::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach (['mesin', 'listrik'] as $idx => $cat) {
            HarLaporanKegiatan::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'category' => $cat,
                'sort_order' => $idx + 1,
                'activity_date' => $testDate,
                'groups' => [[
                    'mesin' => $firstMachine->name,
                    'judul' => $cat === 'mesin' ? 'Pemeliharaan Rutin P2 Mesin Utama' : 'Pemeliharaan Panel Kontrol & Generator',
                    'jenis_har' => 'PM',
                    'uraian' => $cat === 'mesin'
                        ? ['Penggantian elemen filter oli utama', 'Penyetelan celah katup hisap dan buang']
                        : ['Pembersihan terminasi kabel generator', 'Pengukuran tahanan isolasi (Megger)'],
                ]],
                'hasil_pekerjaan' => 'Selesai / Normal',
                'material_nama' => $cat === 'mesin' ? 'Filter Pelumas & BBM' : 'Contact Cleaner',
                'material_no_part' => $cat === 'mesin' ? 'LF-9009' : 'CC-220',
                'jumlah' => '2 Set',
                'data' => 'Normal',
                'no_lh05' => '-',
                'no_sr_ba' => sprintf('SR%02d%02d%02d01', $unit->id, $year % 100, $month),
                'no_tug9' => sprintf('TUG9/%s/%02d/%d', $unit->code, $month, $year),
                'no_wo_spki' => sprintf('WO%02d%02d%02d01', $unit->id, $year % 100, $month),
                'input_by' => $userId,
            ]);
        }

        // 3. All 13 Technical Forms (har_pengusahaan)
        $commonSigners = [
            'manager_ul_id' => $signers['mgr_id'],
            'manager_ul_name' => $signers['mgr_name'],
            'manager_ul_title' => 'Manager UL',
            'tl_har_id' => $signers['tl_id'],
            'tl_har_name' => $signers['tl_name'],
            'tl_har_title' => 'Team Leader Pemeliharaan',
            'staff_har_id' => $signers['staf_id'],
            'staff_har_name' => $signers['staf_name'],
            'staff_har_title' => 'Staf Pemeliharaan',
            'format' => 'structured',
            'created_by' => $userId,
        ];
        $commonMachineHeader = [
            'unit_id' => $unit->id,
            'machine_id' => $firstMachine->id,
            'test_date' => $testDate,
            'revision' => '00',
            'effective_date' => '2026-01-02',
            'brand' => $firstMachine->merk ?: 'Wärtsilä',
            'model_type' => $firstMachine->type ?: 'V32',
            'serial_number' => $firstMachine->serial_number ?: "{$unit->code}-M01",
            'machine_number' => $firstMachine->name,
            'installed_power' => (string) ($firstMachine->capacity_kw ?: '1000'),
            'capable_power' => (string) round((float) ($firstMachine->capacity_kw ?: 1000) * 0.9, 0),
            'rpm' => (string) ($firstMachine->engine_rpm ?: '750'),
            ...$commonSigners,
        ];

        // 3.1 har_prelube_tests
        HarPrelubeTest::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarPrelubeTest::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-PLT-01',
            'cylinders_count' => 6,
            'checklist_items' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'camshaft' => 'v',
                'conrod' => 'v',
                'piston' => 'v',
                'rocker_arm' => 'v',
                'notes' => 'Normal',
            ], range(1, 6)),
            'notes' => 'Sirkulasi pelumasan awal (prelube) merata pada seluruh silinder.',
        ]);

        // 3.2 har_hydrotests
        HarHydrotest::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarHydrotest::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-HYD-01',
            'cylinders_count' => 6,
            'standard_allowed' => '6.0 Bar (30 Menit Tanpa Penurunan)',
            'checklist_items' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'oring_liner' => 'v',
                'liner' => 'v',
                'notes' => 'Tidak Bocor',
            ], range(1, 6)),
            'notes' => 'Hasil hydrotest cylinder head dan liner tidak ditemukan kebocoran.',
        ]);

        // 3.3 har_timing_injection_pumps
        HarTimingInjectionPump::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarTimingInjectionPump::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-TIP-01',
            'cylinders_count' => 6,
            'standard_allowed' => '18° ± 0.5° BTDC',
            'checklist_items' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'timing_before' => '18.0°',
                'timing_after' => '18.0°',
                'notes' => 'Normal',
            ], range(1, 6)),
            'notes' => 'Sudut pengabutan fuel injection pump sesuai manual book.',
        ]);

        // 3.4 har_crankshaft_deflections
        HarCrankshaftDeflection::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarCrankshaftDeflection::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-CSD-01',
            'cylinders_count' => 6,
            'measurements' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'pos_a' => '0.00',
                'pos_b' => '-0.01',
                'pos_c' => '-0.02',
                'pos_d' => '-0.01',
                'pos_e' => '0.00',
            ], range(1, 6)),
            'cylinder_notes' => 'Normal pada seluruh silinder',
            'standard_min' => '-0.03 mm',
            'standard_max' => '+0.03 mm',
            'standard_allowed' => 'Maks. ±0.05 mm',
            'visual_inspection' => 'Defleksi poros engkol masih dalam batas toleransi aman.',
        ]);

        // 3.5 har_counter_weights
        HarCounterWeight::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarCounterWeight::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-CWT-01',
            'cylinders_count' => 6,
            'measurements' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'condition' => 'baik',
                'notes' => 'Terkunci 450 Nm',
            ], range(1, 6)),
            'standard_allowed' => '450 Nm',
            'notes' => 'Seluruh baut counter weight terikat sesuai torsi standar.',
        ]);

        // 3.6 har_axial_conrods
        HarAxialConrod::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarAxialConrod::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-AXC-01',
            'cylinders_count' => 6,
            'torque_standard' => '380 Nm',
            'measurements' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'axial_check' => 'baik',
                'bolt_tightening' => 'baik',
                'notes' => '0.28 mm (Normal)',
            ], range(1, 6)),
            'standard_allowed' => '0.20 - 0.45 mm',
            'notes' => 'Clearance axial connecting rod masih dalam batas standar.',
        ]);

        // 3.7 har_clearance_valves
        HarClearanceValve::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarClearanceValve::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-CLV-01',
            'cylinders_count' => 6,
            'measurements' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'ex_before_r' => '0.60',
                'ex_before_l' => '0.60',
                'ex_after_r' => '0.60',
                'ex_after_l' => '0.60',
                'in_before_r' => '0.40',
                'in_before_l' => '0.40',
                'in_after_r' => '0.40',
                'in_after_l' => '0.40',
            ], range(1, 6)),
            'cylinder_notes' => 'Normal pada seluruh silinder',
            'standard_in' => '0.40 mm',
            'standard_ex' => '0.60 mm',
            'standard_allowed' => 'In: 0.40 mm / Ex: 0.60 mm',
            'visual_inspection' => 'Penyetelan celah katup hisap dan buang sesuai standar.',
        ]);

        // 3.8 har_combustion_pressures
        HarCombustionPressure::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarCombustionPressure::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-CMP-01',
            'cylinders_count' => 6,
            'measurements' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'combustion_pressure' => '118',
                'exhaust_temp' => '385',
                'rack_position' => '34',
            ], range(1, 6)),
            'cylinder_notes' => 'Normal pada seluruh silinder',
            'standard_allowed' => 'Pmax: 115-125 Bar',
            'visual_inspection' => 'Tekanan kompresi dan pembakaran tiap silinder merata.',
        ]);

        // 3.9 har_injector_pressures
        HarInjectorPressure::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarInjectorPressure::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-INJ-01',
            'cylinders_count' => 6,
            'measurements' => array_map(fn (int $i): array => [
                'cylinder' => $i,
                'pressure_before' => '315',
                'pressure_after' => '320',
                'nozzle_holes' => '8',
                'notes' => 'Kabutan Baik',
            ], range(1, 6)),
            'standard_allowed' => '320 ± 5 Bar',
            'visual_inspection' => 'Seluruh nozzle injector telah dikalibrasi pada tekanan 320 Bar.',
        ]);

        // 3.10 har_motor_currents
        HarMotorCurrent::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarMotorCurrent::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-MTC-01',
            'items' => HarMotorCurrentPdfBuilder::SAMPLE_SCAN_ITEMS,
            'notes' => 'Arus beban motor-motor alat bantu seimbang dan di bawah arus nominal.',
        ]);

        // 3.11 & 3.12 har_vibrations & har_lube_qualities
        $this->seedVibrationAndLubeQuality($unit, $machines, $year, $month, min($daysDue, 10), $signers, $userId);

        // 3.13 har_battery_voltages
        HarBatteryVoltage::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        $cells24 = HarBatteryVoltagePdfBuilder::sampleScanCells24v();
        $cells110 = HarBatteryVoltagePdfBuilder::sampleScanCells110v();
        HarBatteryVoltage::query()->create([
            ...$commonMachineHeader,
            'document_number' => 'FM-HAR-BAT-01',
            'cells_24v' => $cells24,
            'summary_24v' => HarBatteryVoltagePdfBuilder::calculateSummary($cells24),
            'cells_110v' => $cells110,
            'summary_110v' => HarBatteryVoltagePdfBuilder::calculateSummary($cells110),
            'charging_conditions' => HarBatteryVoltagePdfBuilder::DEFAULT_CHARGING_CONDITIONS,
            'notes' => 'Kondisi bank baterai starter dan kontrol proteksi sehat.',
        ]);
    }

    private function seedVibrationAndLubeQuality(
        Unit $unit,
        $machines,
        int $year,
        int $month,
        int $day,
        array $signers,
        ?int $userId,
    ): void {
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $testDate = sprintf('%04d-%02d-%02d', $year, $month, $day);

        HarVibration::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();
        HarLubeQuality::query()->where('unit_id', $unit->id)->whereBetween('test_date', [$startOfMonth, $endOfMonth])->delete();

        $commonSigners = [
            'manager_ul_id' => $signers['mgr_id'],
            'manager_ul_name' => $signers['mgr_name'],
            'manager_ul_title' => 'Manager UL',
            'tl_har_id' => $signers['tl_id'],
            'tl_har_name' => $signers['tl_name'],
            'tl_har_title' => 'Team Leader Pemeliharaan',
            'staff_har_id' => $signers['staf_id'],
            'staff_har_name' => $signers['staf_name'],
            'staff_har_title' => 'Staf Pemeliharaan',
            'format' => 'structured',
            'created_by' => $userId,
        ];

        foreach ($machines as $idx => $m) {
            $isWaspada = ($idx === 1 && $machines->count() > 2);
            $vMax = $isWaspada ? '5.20' : '3.15';
            $hMax = $isWaspada ? '4.90' : '2.85';
            $vibItems = HarVibration::sampleScanMeasurements();
            if (isset($vibItems[0])) {
                $vibItems[0]['v_max'] = $vMax;
                $vibItems[0]['h_max'] = $hMax;
                $vibItems[0]['notes'] = $isWaspada ? 'Waspada (Zone B)' : 'Normal (Zone A)';
            }

            HarVibration::query()->create([
                'unit_id' => $unit->id,
                'machine_id' => $m->id,
                'test_date' => $testDate,
                'document_number' => 'FM-HAR-VIB-01',
                'revision' => '00',
                'effective_date' => '2026-01-02',
                'brand' => $m->merk ?: 'Wärtsilä',
                'model_type' => $m->type ?: 'V32',
                'installed_power' => (string) ($m->capacity_kw ?: '1000'),
                'capable_power' => (string) round((float) ($m->capacity_kw ?: 1000) * 0.9, 0),
                'serial_number' => $m->serial_number ?: "{$unit->code}-M0".($idx + 1),
                'machine_number' => $m->name,
                'rpm' => (string) ($m->engine_rpm ?: '750'),
                'measurements' => $vibItems,
                'standard_text' => 'ISO 10816-3 (Normal <= 4.5 mm/s, Waspada <= 7.1 mm/s)',
                'max_text' => $vMax.' mm/s',
                'conclusion_text' => $isWaspada
                    ? 'Getaran Engine DE di Zona B (Waspada), dilakukan pengecekan kekencangan baut mounting.'
                    : 'Level vibrasi mesin dan generator berada di Zona A (Baik) standar ISO 10816-3.',
                ...$commonSigners,
            ]);

            HarLubeQuality::query()->create([
                'unit_id' => $unit->id,
                'machine_id' => $m->id,
                'test_date' => $testDate,
                'document_number' => 'FM-HAR-LUB-01',
                'revision' => '00',
                'effective_date' => '2026-01-02',
                'page_number' => '1 dari 1',
                'unit_sentral' => $unit->name,
                'machine_name' => $m->name,
                'machine_number' => $m->name,
                'serial_number' => $m->serial_number ?: "{$unit->code}-M0".($idx + 1),
                'sample_point' => 'System Lube Oil Before Filter',
                'parameters' => HarLubeQuality::defaultParameters(Carbon::parse($testDate)),
                'status_text' => $isWaspada ? 'Waspada / Perhatian' : 'Normal / Layak Pakai',
                'standard_text' => 'ASTM / OEM Standard Lube Oil',
                'analisa_text' => 'Viskositas, TBN, dan kandungan logam keausan masih dalam batas normal.',
                'cba_text' => 'Pelumas masih layak operasi hingga jadwal penggantian berikutnya.',
                'rekomendasi_text' => 'Lanjutkan operasi normal dan lakukan sampling berkala bulan depan.',
                'signature_location' => 'Kendari',
                'signature_date' => $testDate,
                ...$commonSigners,
            ]);
        }
    }
}
