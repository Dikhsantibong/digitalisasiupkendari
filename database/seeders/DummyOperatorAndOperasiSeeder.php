<?php

namespace Database\Seeders;

use App\Enums\AttendanceRequestStatus;
use App\Enums\AttendanceRequestType;
use App\Enums\BeritaAcaraType;
use App\Enums\JamMesinJenis;
use App\Enums\KwhMeterKategori;
use App\Enums\LogsheetStatus;
use App\Enums\MaterialKelengkapan;
use App\Enums\MesinHarianJenis;
use App\Enums\OpnameJenis;
use App\Enums\PersediaanJenis;
use App\Enums\ScheduleGroupType;
use App\Enums\TankJenis;
use App\Enums\TugJenis;
use App\Models\AttendanceCode;
use App\Models\AttendanceRequest;
use App\Models\AuxiliarySource;
use App\Models\DailyAuxiliaryReading;
use App\Models\DailyEngineReport;
use App\Models\DailyFeederReading;
use App\Models\DocumentRecord;
use App\Models\Employee;
use App\Models\Feeder;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\KondisiAbnormal;
use App\Models\KwhMeter;
use App\Models\LogsheetParameter;
use App\Models\LogsheetTemplate;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiBlackstartJadwal;
use App\Models\OperasiChecklistCommissioningMesin;
use App\Models\OperasiCommissioningTest;
use App\Models\OperasiCommPeralatan;
use App\Models\OperasiDataTeknis;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiFlmMonitoring;
use App\Models\OperasiInstruksiKerja;
use App\Models\OperasiInventarisJadwal;
use App\Models\OperasiJamMesin;
use App\Models\OperasiMaterialPeralatan;
use App\Models\OperasiMeetingShiftJadwal;
use App\Models\OperasiMesinHarian;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\OperasiPemakaianBbm;
use App\Models\OperasiPemakaianPelumas;
use App\Models\OperasiPemakaianPelumasItem;
use App\Models\OperasiPembuatanIk;
use App\Models\OperasiPerformanceTestMesin;
use App\Models\OperasiPermitToWork;
use App\Models\OperasiPersediaan;
use App\Models\OperasiProgram5s5rItem;
use App\Models\OperasiRekap;
use App\Models\OperasiStandMeter;
use App\Models\OperasiTug;
use App\Models\OperatorLogsheet;
use App\Models\OperatorMutasi;
use App\Models\OperatorOpname;
use App\Models\OperatorStartStop;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use App\Models\WorkSchedule;
use App\Services\Operasi\BeritaAcaraBuilder;
use App\Support\OperasiIkTemplates;
use App\Support\OperasiProgram5s5r;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeds comprehensive dummy data for Modul Operator, Modul Operasi Akses 1,
 * and Modul Operasi Akses 2 (Pengusahaan) across all active units.
 */
class DummyOperatorAndOperasiSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $year = (int) $now->year;
        $currentMonth = (int) $now->month;
        $prevMonth = $currentMonth > 1 ? $currentMonth - 1 : 12;
        $monthsToSeed = array_values(array_unique([$prevMonth, $currentMonth]));

        $units = Unit::query()->where('is_active', true)->orderBy('id')->get();
        $attendanceCodes = AttendanceCode::query()->pluck('id', 'code');
        $statusCodes = UnitStatusCode::query()->pluck('id', 'code');
        $loadParam = LogsheetParameter::query()->ofTemplate(LogsheetTemplate::default())->where('code', 'LOAD')->first();
        $kwhParam = LogsheetParameter::query()->ofTemplate(LogsheetTemplate::default())->where('code', 'KWH_METER')->first();
        $rpmParam = LogsheetParameter::query()->ofTemplate(LogsheetTemplate::default())->where('code', 'RPM')->first();

        foreach ($units as $unit) {
            $machines = Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('id')->get();
            if ($machines->isEmpty()) {
                continue;
            }

            $employees = Employee::query()->where('unit_id', $unit->id)->where('is_active', true)->get();
            $tlOperasi = $employees->firstWhere('position', 'Team Leader Operasi');
            $stafOperasi = $employees->firstWhere('position', 'Staf Operasi');
            $koordOperasi = $employees->firstWhere('position', 'Koordinator Operasi');
            $officeOperasi = $employees->firstWhere('position', 'Office Operasi');
            $projectLeader = $employees->firstWhere('position', 'Project Leader');
            $operators = $employees->where('position', 'Operator')->values();
            $firstOperator = $operators->first() ?? $employees->first();
            $inputUserId = $officeOperasi?->user_id ?? $koordOperasi?->user_id ?? $tlOperasi?->user_id ?? $firstOperator?->user_id;
            $pengusahaanUserId = $stafOperasi?->user_id ?? $tlOperasi?->user_id ?? $inputUserId;

            $lubricants = LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();
            $tanks = FuelTank::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();
            $kwhMeters = KwhMeter::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();
            $feeders = Feeder::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();
            $auxSources = AuxiliarySource::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();

            // 1. Seed 6-month trend history for BBM & Persediaan (months 6..9 of $year)
            for ($m = max(1, $currentMonth - 5); $m < $prevMonth; $m++) {
                $this->seedMonthlyBbmAndPersediaan($unit, $machines, $lubricants, $year, $m, Carbon::create($year, $m, 1)->daysInMonth, $pengusahaanUserId);
            }

            // 2. Seed Yearly Operasi Akses 1 Jadwal
            $this->seedYearlyOperasiAkses1($unit, $machines, $year, $koordOperasi?->name ?? 'Koordinator Operasi', $inputUserId);

            // 3. Seed baseline day (last day of month before $prevMonth) for cumulative stands
            $baselineDate = Carbon::create($year, $prevMonth, 1)->subDay()->toDateString();
            $this->seedBaselineStands($unit, $machines, $feeders, $auxSources, $baselineDate, $pengusahaanUserId);

            // 4. Seed full months ($prevMonth & $currentMonth)
            foreach ($monthsToSeed as $month) {
                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                $daysDue = ($month === $currentMonth) ? min((int) $now->day, $daysInMonth) : $daysInMonth;

                $this->seedOperatorModule(
                    $unit,
                    $machines,
                    $employees,
                    $operators,
                    $lubricants,
                    $tanks,
                    $kwhMeters,
                    $attendanceCodes->all(),
                    $loadParam?->id,
                    $kwhParam?->id,
                    $rpmParam?->id,
                    $year,
                    $month,
                    $daysInMonth,
                    $daysDue,
                    $inputUserId,
                );

                $this->seedMonthlyOperasiAkses1(
                    $unit,
                    $machines,
                    $year,
                    $month,
                    $daysInMonth,
                    $daysDue,
                    $koordOperasi?->name ?? 'Koordinator Operasi',
                    $projectLeader?->name ?? 'Project Leader',
                    $inputUserId,
                );

                $this->seedMonthlyOperasiPengusahaan(
                    $unit,
                    $machines,
                    $lubricants,
                    $feeders,
                    $auxSources,
                    $statusCodes->all(),
                    $tlOperasi?->name ?? 'Team Leader Operasi',
                    $stafOperasi?->name ?? 'Staf Operasi',
                    $firstOperator?->name ?? 'Operator Shift',
                    $year,
                    $month,
                    $daysInMonth,
                    $daysDue,
                    $pengusahaanUserId,
                );
            }
        }
    }

    private function seedYearlyOperasiAkses1(Unit $unit, $machines, int $year, string $koordName, ?int $userId): void
    {
        OperasiPembuatanIk::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
        $ikTitles = [
            'IK Pengoperasian Start & Stop Mesin Pembangkit',
            'IK Sinkronisasi Generator ke Sistem 20 kV',
            'IK Pengoperasian Sistem Bahan Bakar (HSD)',
            'IK Pengoperasian Sistem Pelumasan & Pendingin',
            'IK Penanganan Gangguan Blackout / Blackstart',
        ];
        foreach ($ikTitles as $idx => $title) {
            OperasiPembuatanIk::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'section' => 'PEMBUATAN INSTRUKSI KERJA',
                'nama' => $title,
                'pic' => $koordName,
                'months' => ['1' => '1', '3' => '1', '6' => '1', '9' => '1', '10' => '1', '11' => '1'],
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        OperasiDataTeknis::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
        $dtTitles = [
            'Data Teknis Mesin & Generator Pembangkit',
            'Data Teknis Sistem Proteksi & Kubikel 20 kV',
            'Data Teknis Alat Bantu (Auxiliaries) & Pompa',
        ];
        foreach ($dtTitles as $idx => $title) {
            OperasiDataTeknis::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'section' => 'PEMBUATAN DATA TEKNIS',
                'nama' => $title,
                'pic' => $koordName,
                'months' => ['1' => '1', '4' => '1', '7' => '1', '10' => '1', '11' => '1'],
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        OperasiBlackstartJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
        $bsItems = [
            'ENGINE DIESEL GENSET (EDG)',
            'SISTEM BATTERY',
            'COMPRESSOR START',
            'KUBIKEL OUTGOING & INCOMING',
            'SIMULASI BLACKSTART',
        ];
        $bsWeeks = ['1-2', '2-2', '3-2', '4-2', '5-2', '6-2', '7-2', '8-2', '9-2', '10-2', '11-2'];
        foreach ($bsItems as $idx => $uraian) {
            OperasiBlackstartJadwal::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'uraian' => $uraian,
                'pic' => 'Operator & Koord Operasi',
                'rencana' => [...$bsWeeks, '12-2'],
                'realisasi' => $bsWeeks,
                'keterangan' => 'Siap Operasi / Normal',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        OperasiCommPeralatan::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
        $commItems = ['COMPRESSOR START #1', 'POMPA TRANSFER BBM #1', 'POMPA SIRKULASI PENDINGIN #1', 'BATTERY CHARGER 110 VDC'];
        foreach ($commItems as $idx => $nama) {
            OperasiCommPeralatan::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'nama_peralatan' => $nama,
                'beban_50' => ['3-1', '9-1', '10-1'],
                'beban_75' => ['3-2', '9-2', '10-2'],
                'beban_100' => ['3-3', '9-3', '11-1'],
                'keterangan' => 'Beroperasi normal sesuai standar',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        OperasiPerformanceTestMesin::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
        foreach ($machines->values() as $idx => $machine) {
            OperasiPerformanceTestMesin::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'no_urut' => $idx + 1,
                'nama_mesin' => $machine->name,
                'section' => 'A. PEMBUATAN DATA TEKNIKS',
                'beban_50' => ['2-2', '6-2', '10-1'],
                'beban_75' => ['2-3', '6-3', '10-2'],
                'beban_100' => ['2-4', '6-4', '11-1'],
                'keterangan' => 'Performance test tercapai sesuai DMP',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }
    }

    private function seedBaselineStands(Unit $unit, $machines, $feeders, $auxSources, string $baselineDate, ?int $userId): void
    {
        foreach ($machines as $idx => $machine) {
            $baseKwh = 150000 + ($idx * 25000);
            $basePs = 6000 + ($idx * 1000);
            $baseHsd = 40000 + ($idx * 5000);

            DailyEngineReport::query()->updateOrCreate(
                ['engine_id' => $machine->id, 'report_date' => $baselineDate],
                [
                    'unit_id' => $unit->id,
                    'source' => 'manual',
                    'kwh_produksi_stand_akhir' => $baseKwh,
                    'kwh_pakai_sendiri_stand_akhir' => $basePs,
                    'beban_puncak_pagi_kw' => round((float) $machine->capacity_kw * 0.70, 2),
                    'beban_puncak_malam_kw' => round((float) $machine->capacity_kw * 0.80, 2),
                    'pemakaian_pelumas_liter' => 5.0,
                    'flowmeter_hsd_stand_akhir' => $baseHsd,
                    'flowmeter_hsd_tambah_liter' => 0,
                    'air_pps_stand_akhir' => 1000,
                    'air_softener_stand_akhir' => 500,
                    'catatan' => 'Stand akhir bulan sebelumnya',
                    'input_by' => $userId,
                ],
            );
        }

        foreach ($feeders as $idx => $feeder) {
            DailyFeederReading::query()->updateOrCreate(
                ['feeder_id' => $feeder->id, 'report_date' => $baselineDate],
                [
                    'unit_id' => $unit->id,
                    'stand_akhir' => 200000 + ($idx * 30000),
                    'is_active_today' => true,
                    'input_by' => $userId,
                ],
            );
        }

        foreach ($auxSources as $idx => $aux) {
            DailyAuxiliaryReading::query()->updateOrCreate(
                ['auxiliary_source_id' => $aux->id, 'report_date' => $baselineDate],
                [
                    'unit_id' => $unit->id,
                    'stand_kwh_akhir' => 12000 + ($idx * 2000),
                    'stand_bbm_akhir' => 3000 + ($idx * 500),
                    'input_by' => $userId,
                ],
            );
        }
    }

    private function seedOperatorModule(
        Unit $unit,
        $machines,
        $employees,
        $operators,
        $lubricants,
        $tanks,
        $kwhMeters,
        array $attendanceCodes,
        ?int $loadParamId,
        ?int $kwhParamId,
        ?int $rpmParamId,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();

        // 1. Work Schedules (Shift & Non-Shift)
        $shiftCycle = ['P', 'P', 'S', 'S', 'M', 'M', 'OFF', 'OFF'];
        $reguOffsets = ['A' => 0, 'B' => 2, 'C' => 4, 'D' => 6];

        $shiftSchedule = WorkSchedule::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'group_type' => ScheduleGroupType::Shift],
            ['generated_at' => $nowTs, 'input_by' => $userId],
        );
        $nonShiftSchedule = WorkSchedule::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'group_type' => ScheduleGroupType::NonShift],
            ['generated_at' => $nowTs, 'input_by' => $userId],
        );

        DB::table('work_schedule_entries')->whereIn('work_schedule_id', [$shiftSchedule->id, $nonShiftSchedule->id])->delete();

        $entryRows = [];
        $shiftEmployees = $employees->filter(fn (Employee $e): bool => $e->regu !== null && $e->regu !== '');
        $nonShiftEmployees = $employees->filter(fn (Employee $e): bool => $e->regu === null || $e->regu === '');

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $dow = Carbon::parse($dateStr)->dayOfWeek;
            $isWeekend = in_array($dow, [Carbon::SATURDAY, Carbon::SUNDAY], true);

            foreach ($shiftEmployees as $emp) {
                $offset = $reguOffsets[$emp->regu] ?? 0;
                $code = $shiftCycle[($d - 1 + $offset) % 8];
                $entryRows[] = [
                    'work_schedule_id' => $shiftSchedule->id,
                    'employee_id' => $emp->id,
                    'work_date' => $dateStr,
                    'attendance_code_id' => $attendanceCodes[$code] ?? null,
                    'regu' => $emp->regu,
                    'note' => null,
                    'attendance_request_id' => null,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }

            foreach ($nonShiftEmployees as $emp) {
                $code = $isWeekend ? 'OFF' : 'P';
                $entryRows[] = [
                    'work_schedule_id' => $nonShiftSchedule->id,
                    'employee_id' => $emp->id,
                    'work_date' => $dateStr,
                    'attendance_code_id' => $attendanceCodes[$code] ?? null,
                    'regu' => null,
                    'note' => null,
                    'attendance_request_id' => null,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($entryRows, 500) as $chunk) {
            DB::table('work_schedule_entries')->insert($chunk);
        }

        // 2. Employee Presences (for every day 1..daysDue, seed 3 employees per day so Presensi completeness is 100%)
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        DB::table('employee_presences')
            ->where('unit_id', $unit->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->delete();

        $presenceRows = [];
        $sampleEmps = $operators->take(4)->isNotEmpty() ? $operators->take(4) : $employees->take(4);
        $lat = (float) ($unit->latitude ?: -3.9985);
        $lng = (float) ($unit->longitude ?: 122.5672);

        for ($d = 1; $d <= $daysDue; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            foreach ($sampleEmps as $idx => $emp) {
                $shiftCode = ['P', 'S', 'M', 'P'][$idx % 4];
                [$inHour, $outHour] = match ($shiftCode) {
                    'S' => ['14:52:00', '23:05:00'],
                    'M' => ['22:50:00', '07:05:00'],
                    default => ['07:48:00', '16:05:00'],
                };
                $presenceRows[] = [
                    'unit_id' => $unit->id,
                    'employee_id' => $emp->id,
                    'user_id' => $emp->user_id ?? $userId,
                    'work_date' => $dateStr,
                    'shift_code' => $shiftCode,
                    'check_in_at' => "{$dateStr} {$inHour}",
                    'check_in_latitude' => number_format($lat + 0.0001, 7, '.', ''),
                    'check_in_longitude' => number_format($lng + 0.0001, 7, '.', ''),
                    'check_in_distance_m' => 18 + ($idx * 5),
                    'check_in_accuracy_m' => 10,
                    'late_minutes' => 0,
                    'check_in_note' => 'Hadir tepat waktu',
                    'check_out_at' => "{$dateStr} {$outHour}",
                    'check_out_latitude' => number_format($lat + 0.0001, 7, '.', ''),
                    'check_out_longitude' => number_format($lng + 0.0001, 7, '.', ''),
                    'check_out_distance_m' => 20 + ($idx * 5),
                    'check_out_accuracy_m' => 10,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($presenceRows, 500) as $chunk) {
            DB::table('employee_presences')->insert($chunk);
        }

        // 3. Attendance Requests (Pengajuan Absensi)
        if ($operators->count() >= 2) {
            $op1 = $operators[0];
            $op2 = $operators[1];
            AttendanceRequest::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'employee_id' => $op1->id, 'start_date' => sprintf('%04d-%02d-05', $year, $month)],
                [
                    'requested_by' => $op1->user_id ?? $userId,
                    'type' => AttendanceRequestType::Izin,
                    'end_date' => sprintf('%04d-%02d-05', $year, $month),
                    'reason' => 'Pengurusan dokumen administrasi keluarga',
                    'status' => AttendanceRequestStatus::Disetujui,
                    'decided_by' => $userId,
                    'decided_at' => now(),
                    'decision_note' => 'Disetujui Koordinator Operasi',
                    'applied' => [['employee_id' => $op1->id, 'date' => sprintf('%04d-%02d-05', $year, $month), 'from' => 'P', 'to' => 'I']],
                ],
            );
            AttendanceRequest::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'employee_id' => $op2->id, 'start_date' => sprintf('%04d-%02d-10', $year, $month)],
                [
                    'requested_by' => $op2->user_id ?? $userId,
                    'type' => AttendanceRequestType::TukarShift,
                    'end_date' => sprintf('%04d-%02d-10', $year, $month),
                    'reason' => 'Tukar jadwal jaga shift pagi dengan rekan Regu B',
                    'substitute_employee_id' => $op1->id,
                    'swap_date' => sprintf('%04d-%02d-12', $year, $month),
                    'status' => AttendanceRequestStatus::Disetujui,
                    'substitute_responded_at' => now(),
                    'substitute_note' => 'Bersedia menggantikan shift',
                    'decided_by' => $userId,
                    'decided_at' => now(),
                    'decision_note' => 'Tukar shift disetujui',
                ],
            );
        }

        // 4. Operator Logsheets & Readings (every machine × every day 1..daysDue)
        $existingLogsheetIds = OperatorLogsheet::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('log_date', [$startOfMonth, $endOfMonth])
            ->pluck('id');
        if ($existingLogsheetIds->isNotEmpty()) {
            DB::table('operator_logsheet_readings')->whereIn('logsheet_id', $existingLogsheetIds)->delete();
            DB::table('operator_logsheets')->whereIn('id', $existingLogsheetIds)->delete();
        }

        $logsheetRows = [];
        for ($d = 1; $d <= $daysDue; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $op = $operators->isNotEmpty() ? $operators[$d % $operators->count()] : null;
            foreach ($machines as $mIdx => $machine) {
                $logsheetRows[] = [
                    'unit_id' => $unit->id,
                    'engine_id' => $machine->id,
                    'log_date' => $dateStr,
                    'operator_employee_id' => $op?->id,
                    'operator_name' => $op?->name ?? 'Operator Shift',
                    'shift' => $op?->regu ?? 'A',
                    'status' => LogsheetStatus::Submitted->value,
                    'submitted_at' => "{$dateStr} 23:30:00",
                    'input_by' => $op?->user_id ?? $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($logsheetRows, 500) as $chunk) {
            DB::table('operator_logsheets')->insert($chunk);
        }

        // Insert key readings (08:00, 14:00, 19:00, 22:00) for LOAD, KWH_METER, RPM
        if ($loadParamId !== null) {
            $insertedLogsheets = OperatorLogsheet::query()
                ->where('unit_id', $unit->id)
                ->whereBetween('log_date', [$startOfMonth, $endOfMonth])
                ->get(['id', 'engine_id', 'log_date']);

            $capByMachine = $machines->mapWithKeys(fn (Machine $m): array => [$m->id => max(200.0, (float) $m->capacity_kw)])->all();
            $readingRows = [];

            foreach ($insertedLogsheets as $ls) {
                $cap = $capByMachine[$ls->engine_id] ?? 1000.0;
                $dayNum = (int) Carbon::parse($ls->log_date)->day;
                $peakLoad = round($cap * (0.76 + (($dayNum % 5) * 0.02)), 2);
                $dayLoad = round($cap * 0.68, 2);

                $slots = [
                    '08:00:00' => $dayLoad,
                    '14:00:00' => round($dayLoad * 1.04, 2),
                    '19:00:00' => $peakLoad,
                    '22:00:00' => round($peakLoad * 0.92, 2),
                ];
                foreach ($slots as $slot => $val) {
                    $readingRows[] = [
                        'logsheet_id' => $ls->id,
                        'time_slot' => $slot,
                        'parameter_id' => $loadParamId,
                        'value' => $val,
                        'note' => null,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
                if ($kwhParamId !== null) {
                    $readingRows[] = [
                        'logsheet_id' => $ls->id,
                        'time_slot' => '24:00:00',
                        'parameter_id' => $kwhParamId,
                        'value' => round(150000 + ($dayNum * $dayLoad * 18), 2),
                        'note' => null,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
                if ($rpmParamId !== null) {
                    $readingRows[] = [
                        'logsheet_id' => $ls->id,
                        'time_slot' => '19:00:00',
                        'parameter_id' => $rpmParamId,
                        'value' => 750,
                        'note' => null,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
            }
            foreach (array_chunk($readingRows, 1000) as $chunk) {
                DB::table('operator_logsheet_readings')->insert($chunk);
            }
        }

        // 5. Operator Start Stops (normal + 1 gangguan on day 7 for machine #1)
        OperatorStartStop::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->delete();

        $firstMachine = $machines->first();
        for ($d = 1; $d <= min($daysDue, 10); $d += 3) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $isGangguan = ($d === 7);
            OperatorStartStop::query()->create([
                'unit_id' => $unit->id,
                'engine_id' => $firstMachine->id,
                'tanggal' => $dateStr,
                'start_time' => '06:00',
                'cbg_on' => '06:08',
                'cbg_off' => $isGangguan ? '14:15' : '22:30',
                'stop_time' => $isGangguan ? '14:20' : '22:40',
                'penyebab_stop' => $isGangguan ? 'gangguan' : 'normal',
                'keterangan' => $isGangguan ? 'Alarm high temp air pendingin, dilakukan flushing radiator' : 'Stop normal pengaturan beban sistem',
                'input_by' => $userId,
            ]);
        }

        // 6. Operator Opnames (bbm, pelumas, kwh)
        OperatorOpname::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->delete();

        $opnameRows = [];
        for ($d = 1; $d <= $daysDue; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);

            // BBM Opname
            $fisikBbm = [];
            $admBbm = [];
            foreach ($tanks as $tank) {
                if ($tank->jenis === TankJenis::Service) {
                    $admBbm["t{$tank->id}"] = ['cm' => 95.0, 'liter' => 4500.0 - ($d * 20)];
                } else {
                    $fisikBbm["t{$tank->id}"] = ['cm' => 165.0, 'liter' => 85000.0 - ($d * 400)];
                }
            }
            foreach ($machines as $mIdx => $machine) {
                $awal = 40000.0 + ($mIdx * 5000) + (($d - 1) * 850.0);
                $admBbm["m{$machine->id}"] = ['awal' => $awal, 'akhir' => $awal + 850.0];
            }
            $opnameRows[] = [
                'unit_id' => $unit->id,
                'tanggal' => $dateStr,
                'jenis' => OpnameJenis::Bbm->value,
                'data' => json_encode(['fisik' => $fisikBbm, 'administrasi' => $admBbm]),
                'catatan' => 'Opname harian BBM normal',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];

            // Pelumas Opname
            $lubeData = [];
            foreach ($lubricants as $lube) {
                $awal = max(100.0, 600.0 - (($d - 1) * 6.0));
                $lubeData["l{$lube->id}"] = ['awal' => $awal, 'akhir' => $awal - 6.0];
            }
            $opnameRows[] = [
                'unit_id' => $unit->id,
                'tanggal' => $dateStr,
                'jenis' => OpnameJenis::Pelumas->value,
                'data' => json_encode(['pelumas' => $lubeData]),
                'catatan' => 'Opname harian pelumas normal',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];

            // kWh Opname
            $prodData = [];
            $psData = [];
            $trafoData = [];
            foreach ($kwhMeters as $meter) {
                if ($meter->kategori === KwhMeterKategori::Produksi) {
                    $awal = 150000.0 + (($d - 1) * 4200.0);
                    $prodData["k{$meter->id}"] = ['awal' => $awal, 'akhir' => $awal + 4200.0];
                } elseif ($meter->kategori === KwhMeterKategori::Ps) {
                    $awal = 6000.0 + (($d - 1) * 140.0);
                    $psData["k{$meter->id}"] = ['awal' => $awal, 'akhir' => $awal + 140.0];
                } else {
                    $awal = 12000.0 + (($d - 1) * 90.0);
                    $trafoData["k{$meter->id}"] = ['awal' => $awal, 'akhir' => $awal + 90.0];
                }
            }
            $opnameRows[] = [
                'unit_id' => $unit->id,
                'tanggal' => $dateStr,
                'jenis' => OpnameJenis::Kwh->value,
                'data' => json_encode(['produksi' => $prodData, 'ps' => $psData, 'susut_trafo' => $trafoData]),
                'catatan' => 'Opname harian kWh meter sesuai logsheet',
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        foreach (array_chunk($opnameRows, 500) as $chunk) {
            DB::table('operator_opnames')->insert($chunk);
        }

        // 7. Operator Mutasis (Lembar Mutasi Operator per day 1..daysDue)
        OperatorMutasi::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->delete();

        $leadersByRegu = $operators->where('is_shift_leader', true)->keyBy('regu');
        $mesinJson = $machines->map(fn (Machine $m): array => [
            'machine_id' => $m->id,
            'nama' => $m->name,
            'level_bbm' => '82%',
            'tambah_bbm' => '450 L',
            'pelumas' => 'normal',
            'status' => 'operasi',
        ])->values()->all();
        $tangkiJson = $tanks->take(2)->values()->map(fn (FuelTank $t, int $i): array => [
            'nama' => (string) ($i + 1),
            'level_cm' => (145 - ($i * 25)).' cm',
        ])->all();
        $peralatanJson = [
            ['nama' => 'Radio HT', 'ada' => true, 'jumlah' => 2],
            ['nama' => 'Senter Sorot', 'ada' => true, 'jumlah' => 2],
            ['nama' => 'Earplug / Earmuff', 'ada' => true, 'jumlah' => 4],
            ['nama' => 'Kunci Ruang Mesin', 'ada' => true, 'jumlah' => 1],
        ];
        $kejadianJson = [
            ['jam' => '08:00', 'uraian' => 'Serah terima tugas jaga shift dan pemeriksaan keliling mesin normal'],
            ['jam' => '11:30', 'uraian' => 'Pengisian tangki harian BBM dan pencatatan parameter beban puncak'],
        ];

        $mutasiRows = [];
        $reguList = ['A', 'B', 'C', 'D'];
        for ($d = 1; $d <= $daysDue; $d++) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $reguPenyerah = $reguList[($d - 1) % 4];
            $reguPenerima = $reguList[$d % 4];
            $penyerahName = $leadersByRegu->get($reguPenyerah)?->name ?? $operators->first()?->name ?? 'Leader Shift A';
            $penerimaName = $leadersByRegu->get($reguPenerima)?->name ?? $operators->last()?->name ?? 'Leader Shift B';

            $mutasiRows[] = [
                'unit_id' => $unit->id,
                'tanggal' => $dateStr,
                'shift' => 'pagi',
                'mesin' => json_encode($mesinJson),
                'tangki' => json_encode($tangkiJson),
                'peralatan' => json_encode($peralatanJson),
                'kejadian' => json_encode($kejadianJson),
                'gangguan_mesin' => 'Nihil, seluruh mesin beroperasi normal sesuai jadwal pembebanan.',
                'catatan' => 'Area power house bersih, peralatan inventaris lengkap.',
                'regu_penyerah' => $reguPenyerah,
                'penyerah_nama' => $penyerahName,
                'paraf_penyerah' => 'Signed',
                'diserahkan_at' => "{$dateStr} 15:00:00",
                'regu_penerima' => $reguPenerima,
                'penerima_nama' => $penerimaName,
                'paraf_penerima' => 'Signed',
                'diterima_at' => "{$dateStr} 15:05:00",
                'input_by' => $userId,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];
        }
        foreach (array_chunk($mutasiRows, 500) as $chunk) {
            DB::table('operator_mutasis')->insert($chunk);
        }
    }

    private function seedMonthlyOperasiAkses1(
        Unit $unit,
        $machines,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        string $koordName,
        string $plName,
        ?int $userId,
    ): void {
        $markDays = [];
        $minDays = [];
        $shiftDays = [];
        $reguCycle = ['A', 'B', 'C', 'D'];
        for ($d = 1; $d <= $daysDue; $d++) {
            $markDays[(string) $d] = '1';
            $minDays[(string) $d] = '60';
            $shiftDays[(string) $d] = $reguCycle[($d - 1) % 4];
        }
        $weeklyDays = array_values(array_filter([3, 10, 17, 24], fn (int $d): bool => $d <= $daysInMonth));
        $weeklyReal = array_values(array_filter($weeklyDays, fn (int $d): bool => $d <= $daysDue));
        if ($weeklyReal === []) {
            $weeklyReal = [1];
        }

        // 1. operasi_flm_jadwals
        OperasiFlmJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $flmRows = [
            ['section' => 'rutin', 'shift' => 'JADWAL', 'label' => 'REALISASI', 'row_type' => 'mark', 'days' => $markDays, 'target' => $daysInMonth, 'sort_order' => 1],
            ['section' => 'rutin', 'shift' => '', 'label' => '08:00 sd 15:00', 'row_type' => 'minutes', 'days' => $minDays, 'target' => 60, 'sort_order' => 2],
            ['section' => 'rutin', 'shift' => 'A', 'label' => '08:00 sd 15:00', 'row_type' => 'shift', 'days' => $shiftDays, 'target' => 0, 'sort_order' => 3],
            ['section' => 'non_rutin', 'shift' => 'JADWAL', 'label' => 'REALISASI', 'row_type' => 'mark', 'days' => ['3' => '1', '10' => '1', '17' => '1', '24' => '1'], 'target' => 4, 'sort_order' => 4],
            ['section' => 'non_rutin', 'shift' => '', 'label' => 'Waktu tentatif', 'row_type' => 'minutes', 'days' => ['3' => '60', '10' => '60', '17' => '60', '24' => '60'], 'target' => 60, 'sort_order' => 5],
            ['section' => 'non_rutin', 'shift' => 'A', 'label' => 'Waktu tentatif', 'row_type' => 'shift', 'days' => ['3' => 'A', '10' => 'B', '17' => 'C', '24' => 'D'], 'target' => 0, 'sort_order' => 6],
        ];
        foreach ($flmRows as $r) {
            OperasiFlmJadwal::query()->create(['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'input_by' => $userId, ...$r]);
        }

        // 2. operasi_5s5r_jadwals
        Operasi5s5rJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $pelaksana5s = ['OFFICER FUELFACILITY', 'OPERATOR SHIFT A', 'OPERATOR SHIFT B', 'OPERATOR SHIFT C', 'OPERATOR SHIFT D'];
        foreach ($pelaksana5s as $idx => $p) {
            Operasi5s5rJadwal::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'pelaksana' => $p,
                'rencana' => $weeklyDays,
                'realisasi' => $weeklyReal,
                'target' => count($weeklyDays),
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // 3. operasi_meeting_shift_jadwals
        OperasiMeetingShiftJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $meetingRows = [
            ['no_urut' => 1, 'label' => 'Serah Terima Tugas (PAGI)', 'row_type' => 'mark', 'days' => $markDays, 'target' => $daysInMonth, 'sort_order' => 1],
            ['no_urut' => 2, 'label' => 'Meeting Shift (PAGI)', 'row_type' => 'mark', 'days' => $markDays, 'target' => $daysInMonth, 'sort_order' => 2],
            ['no_urut' => 3, 'label' => 'Serah Terima Tugas (SORE)', 'row_type' => 'mark', 'days' => $markDays, 'target' => $daysInMonth, 'sort_order' => 3],
            ['no_urut' => 4, 'label' => 'Meeting Shift (SORE)', 'row_type' => 'mark', 'days' => $markDays, 'target' => $daysInMonth, 'sort_order' => 4],
            ['no_urut' => 5, 'label' => 'PAGI (07.00 - 15.00)', 'row_type' => 'shift', 'days' => $shiftDays, 'target' => 0, 'sort_order' => 5],
            ['no_urut' => 6, 'label' => 'SORE (15.00 - 23.00)', 'row_type' => 'shift', 'days' => $shiftDays, 'target' => 0, 'sort_order' => 6],
        ];
        foreach ($meetingRows as $r) {
            OperasiMeetingShiftJadwal::query()->create(['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'input_by' => $userId, ...$r]);
        }

        // 4. operasi_inventaris_jadwals
        OperasiInventarisJadwal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        OperasiInventarisJadwal::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'uraian' => 'Inventarisasi Tools & Material Operasi Pembangkit',
            'shift' => 'JADWAL',
            'rencana' => $weeklyDays,
            'realisasi' => $weeklyReal,
            'target' => count($weeklyDays),
            'sort_order' => 1,
            'input_by' => $userId,
        ]);

        // 5. operasi_commissioning_tests
        OperasiCommissioningTest::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $commTests = [
            ['section' => 'PERSIAPAN', 'no_urut' => 1, 'kegiatan' => 'Pemeriksaan level oli carter, governor, dan air radiator', 'status' => 'SIAP OPERASI'],
            ['section' => 'PERSIAPAN', 'no_urut' => 2, 'kegiatan' => 'Pemeriksaan tekanan udara start (min. 25 Bar) & tegangan baterai 24/110 VDC', 'status' => 'SIAP OPERASI'],
            ['section' => 'PARAREL GENERATOR', 'no_urut' => 1, 'kegiatan' => 'Sinkronisasi tegangan, frekuensi (50 Hz), dan sudut fasa (Synchroscope)', 'status' => 'ON'],
            ['section' => 'PARAREL GENERATOR', 'no_urut' => 2, 'kegiatan' => 'Pembebanan bertahap 25%, 50%, 75%, hingga beban nominal DMP', 'status' => 'ON'],
        ];
        foreach ($commTests as $idx => $ct) {
            OperasiCommissioningTest::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                ...$ct,
                'pic' => 'Operator Shift & Koord Operasi',
                'paraf' => 'OK',
                'catatan' => 'Sesuai SOP Pengoperasian',
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // 6. kondisi_abnormals
        KondisiAbnormal::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $firstMachine = $machines->first();
        KondisiAbnormal::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'no_urut' => 1,
            'uraian_kondisi' => "Temperatur jacket water {$firstMachine->name} meningkat hingga 87°C saat beban puncak malam",
            'tanggal' => sprintf('%04d-%02d-07', $year, $month),
            'is_abnormal' => 1,
            'durasi_abnormal' => 1.50,
            'is_gangguan' => 1,
            'durasi_gangguan' => 1.00,
            'jam_mulai' => '14:15',
            'jam_selesai' => '15:15',
            'machine_id' => $firstMachine->id,
            'peralatan' => 'Cooling System / Radiator',
            'parameter_terkait' => 'Jacket Water Temp (°C)',
            'kronologi' => 'Saat beban puncak sistem naik, indikator suhu air pendingin mencapai 87°C. Dilakukan penurunan beban dan pembersihan kisi radiator.',
            'tl_pendek_sr' => true,
            'tl_pendek_flm' => true,
            'tl_pendek_lainnya' => false,
            'tl_pendek_keterangan' => 'Flushing kisi radiator & pengecekan fan belt oleh tim FLM',
            'tl_panjang' => 'Penjadwalan chemical cleaning cooler pada pemeliharaan rutin P2',
            'dampak_sistem' => 'Beban dialihkan ke mesin cadangan tanpa pemadaman penyulang',
            'dampak_pembangkit' => 'Derating sementara selama 60 menit',
            'input_by' => $userId,
        ]);

        // 7. operasi_material_peralatans
        OperasiMaterialPeralatan::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $matItems = [
            ['kategori' => 'PERALATAN', 'kode_material' => 'OP-TL-001', 'stok_code' => 'SC-OP-01', 'nama_item' => 'Infrared Thermometer Fluke 62 Max', 'satuan' => 'UNIT', 'stok_awal' => 2, 'masuk' => 0, 'keluar' => 0, 'stok_akhir' => 2, 'harga_satuan' => 2500000],
            ['kategori' => 'PERALATAN', 'kode_material' => 'OP-TL-002', 'stok_code' => 'SC-OP-02', 'nama_item' => 'Sound Level Meter Digital', 'satuan' => 'UNIT', 'stok_awal' => 1, 'masuk' => 0, 'keluar' => 0, 'stok_akhir' => 1, 'harga_satuan' => 1850000],
            ['kategori' => 'MATERIAL', 'kode_material' => 'OP-MT-001', 'stok_code' => 'SC-OP-03', 'nama_item' => 'Majun / Kain Lap Katun Bersih', 'satuan' => 'KG', 'stok_awal' => 25, 'masuk' => 10, 'keluar' => 12, 'stok_akhir' => 23, 'harga_satuan' => 35000],
            ['kategori' => 'MATERIAL', 'kode_material' => 'OP-MT-002', 'stok_code' => 'SC-OP-04', 'nama_item' => 'Cairan Pembersih Contact Cleaner', 'satuan' => 'KLG', 'stok_awal' => 8, 'masuk' => 4, 'keluar' => 3, 'stok_akhir' => 9, 'harga_satuan' => 85000],
        ];
        foreach ($matItems as $idx => $item) {
            OperasiMaterialPeralatan::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                ...$item,
                'pemakaian_rata_rata' => 2,
                'safety_stock' => 2,
                'ilt' => 1,
                'rop' => 4,
                'roq' => 6,
                'kelengkapan' => MaterialKelengkapan::Lengkap,
                'uraian_kondisi' => 'Lengkap dan berfungsi baik',
                'checked_by' => $userId,
                'checked_at' => now(),
                'sort_order' => $idx + 1,
                'input_by' => $userId,
            ]);
        }

        // 8. operasi_permit_to_works
        OperasiPermitToWork::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $ptwItems = [
            ['no_urut' => 1, 'uraian' => "Isolasi & LOTO untuk inspeksi rutin {$firstMachine->name}", 'tanggal' => sprintf('%04d-%02d-04', $year, $month), 'status' => 'close'],
            ['no_urut' => 2, 'uraian' => 'Pekerjaan pembersihan strainer BBM dan pengecekan kebocoran pipa', 'tanggal' => sprintf('%04d-%02d-11', $year, $month), 'status' => 'close'],
        ];
        foreach ($ptwItems as $item) {
            OperasiPermitToWork::query()->create(['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'input_by' => $userId, ...$item]);
        }

        // 9. operasi_flm_monitorings
        OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach ($machines->take(2)->values() as $idx => $m) {
            OperasiFlmMonitoring::query()->create([
                'unit_id' => $unit->id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $idx + 1,
                'mesin' => $m->name,
                'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 3 + ($idx * 4))),
                'masalah' => 'Rembesan halus pada sambungan pipa pelumas & debu pada body alternator',
                'kondisi_awal' => ['bersihkan', 'kencangkan'],
                'kondisi_akhir' => 'Bersih, baut dikencangkan, tidak ada rembesan',
                'catatan' => 'Telah ditindaklanjuti oleh Operator Shift bersama Koordinator Operasi',
                'status' => 'close',
                'input_by' => $userId,
            ]);
        }

        // 10. operasi_patrol_check_mesins
        OperasiPatrolCheckMesin::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        $checksMap = [];
        for ($d = 1; $d <= $daysDue; $d++) {
            $checksMap[(string) $d] = 'normal';
        }
        $patrolItems = [
            ['no' => 1, 'system' => 'LUBRICATING SYSTEM', 'peralatan' => 'Lube Oil Line Pipe & Pressure Gauge', 'checks' => $checksMap],
            ['no' => 2, 'system' => 'FUEL OIL SYSTEM', 'peralatan' => 'High Pressure Fuel Pipe & Injection Pump', 'checks' => $checksMap],
            ['no' => 3, 'system' => 'COOLING WATER SYSTEM', 'peralatan' => 'Jacket Water Pump & Radiator Fan', 'checks' => $checksMap],
            ['no' => 4, 'system' => 'INTAKE & EXHAUST SYSTEM', 'peralatan' => 'Turbocharger & Expansion Joint', 'checks' => $checksMap],
            ['no' => 5, 'system' => 'ELECTRICAL & GENERATOR', 'peralatan' => 'Main Alternator Bearing & Panel Kontrol', 'checks' => $checksMap],
        ];
        foreach ($machines as $m) {
            OperasiPatrolCheckMesin::query()->create([
                'unit_id' => $unit->id,
                'machine_id' => $m->id,
                'nama_mesin' => $m->name,
                'year' => $year,
                'month' => $month,
                'items' => $patrolItems,
                'shift_pagi' => $shiftDays,
                'shift_sore' => $shiftDays,
                'shift_malam' => $shiftDays,
                'catatan' => 'Seluruh parameter sistem mesin dalam batas aman operasional.',
                'input_by' => $userId,
            ]);
        }

        // 11. operasi_program_5s5r_items
        OperasiProgram5s5rItem::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();
        foreach (range(1, 4) as $minggu) {
            foreach (OperasiProgram5s5r::PROGRAMS as $progKey => $progDef) {
                OperasiProgram5s5rItem::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'minggu' => $minggu,
                    'program' => $progKey,
                    'detail' => $progDef['default_detail'] ?? "Pelaksanaan {$progDef['label']} di area ruang mesin & ruang kontrol",
                    'pic' => 'Operator Shift '.$reguCycle[($minggu - 1) % 4],
                    'kondisi_awal' => 'Baik',
                    'membersihkan' => true,
                    'merapikan' => true,
                    'membuang_sampah' => $progKey === 'resik',
                    'mengecat' => false,
                    'lainnya' => false,
                    'progres' => '76-100',
                    'kondisi_akhir' => 'Baik',
                    'jumlah' => 4,
                    'keterangan' => 'Terlaksana sesuai jadwal mingguan',
                    'input_by' => $userId,
                ]);
            }
        }

        // 12. operasi_checklist_commissioning_mesins (date_col = tanggal)
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        OperasiChecklistCommissioningMesin::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->delete();

        OperasiChecklistCommissioningMesin::query()->create([
            'unit_id' => $unit->id,
            'machine_id' => $firstMachine->id,
            'nama_mesin' => $firstMachine->name,
            'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, min($daysDue, 5)),
            'rows' => [
                ['no' => 1, 'section' => 'PERSIAPAN', 'kegiatan' => 'Memastikan kesiapan peralatan pemadam kebakaran (APAR) di sekitar mesin', 'status' => 'SIAP OPERASI', 'pic' => 'Operator Shift A', 'paraf' => 'OK'],
                ['no' => 2, 'section' => 'PERSIAPAN', 'kegiatan' => 'Memeriksa level minyak pelumas carter dan turbocharger', 'status' => 'SIAP OPERASI', 'pic' => 'Operator Shift A', 'paraf' => 'OK'],
                ['no' => 3, 'section' => 'PARAREL GENERATOR', 'kegiatan' => 'Pengujian sinkronisasi dan pembebanan bertahap ke jaringan 20 kV', 'status' => 'ON', 'pic' => $koordName, 'paraf' => 'OK'],
            ],
            'catatan' => 'Commissioning pasca pemeliharaan rutin dinyatakan berhasil dan siap operasi.',
            'input_by' => $userId,
        ]);

        // 13. operasi_instruksi_kerjas (date_col = tanggal)
        OperasiInstruksiKerja::query()
            ->where('unit_id', $unit->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->delete();

        $ikDefaults = array_slice(OperasiIkTemplates::all(), 1, 2);
        foreach ($ikDefaults as $idx => $tpl) {
            OperasiInstruksiKerja::query()->create([
                'unit_id' => $unit->id,
                'sort_order' => $idx + 1,
                'kop' => $tpl['kop'] ?? "INSTRUKSI KERJA OPERASI {$unit->name}",
                'judul' => $tpl['judul'] ?? "PENGOPERASIAN MESIN {$firstMachine->name}",
                'mesin' => $firstMachine->name,
                'no_dokumen' => sprintf('IK-OPR/%s/%02d/%d', $unit->code, $idx + 1, $year),
                'tanggal' => sprintf('%04d-%02d-02', $year, $month),
                'revisi' => '00',
                'sections' => $tpl['sections'] ?? [
                    ['judul' => 'TUJUAN', 'bernomor' => true, 'gaya' => 'paragraf', 'lanjut' => false, 'pengantar' => 'Memastikan pengoperasian mesin pembangkit aman dan andal.', 'butir' => []],
                ],
                'dibuat_jabatan' => 'Koordinator Operasi',
                'dibuat_nama' => $koordName,
                'disetujui_jabatan' => 'Project Leader',
                'disetujui_nama' => $plName,
                'input_by' => $userId,
            ]);
        }
    }

    private function seedMonthlyBbmAndPersediaan(Unit $unit, $machines, $lubricants, int $year, int $month, int $daysDue, ?int $userId): void
    {
        $rawBbm = [];
        $totalsByMachineBbm = [];
        $totalHsd = 0.0;

        foreach ($machines as $mIdx => $machine) {
            $key = "HSD_{$machine->id}";
            $cap = max(200.0, (float) $machine->capacity_kw);
            $dailyBbm = round($cap * 0.72 * 18.0 * 0.255, 2); // ~0.255 L/kWh SFC
            $daysMap = [];
            $mSum = 0.0;
            for ($d = 1; $d <= $daysDue; $d++) {
                $val = round($dailyBbm * (0.96 + (($d + $mIdx) % 5) * 0.02), 2);
                $daysMap[(string) $d] = $val;
                $mSum += $val;
            }
            $rawBbm[$key] = $daysMap;
            $totalsByMachineBbm[$key] = round($mSum, 2);
            $totalHsd += $mSum;
        }

        OperasiPemakaianBbm::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            [
                'raw_readings' => $rawBbm,
                'totals_by_fuel' => ['HSD' => round($totalHsd, 2)],
                'totals_by_machine' => $totalsByMachineBbm,
                'grand_total_liter' => round($totalHsd, 2),
                'catatan' => 'Rekap pemakaian BBM bulanan terverifikasi',
                'input_by' => $userId,
            ],
        );

        $openingHsd = round(max(45000.0, $totalHsd * 0.45), 2);
        $receipt1 = round($totalHsd * 0.55, 2);
        $receipt2 = round($totalHsd * 0.50, 2);
        $closingHsd = round(max(25000.0, $openingHsd + $receipt1 + $receipt2 - $totalHsd), 2);

        OperasiPersediaan::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => PersediaanJenis::Bbm, 'month' => $month, 'year' => $year],
            [
                'opening' => ['HSD' => $openingHsd],
                'entries' => [
                    'HSD' => [
                        '5' => ['penerimaan' => $receipt1],
                        '14' => ['penerimaan' => $receipt2],
                    ],
                ],
                'closing' => ['HSD' => $closingHsd],
                'catatan' => 'Persediaan BBM fisik dan administrasi sesuai',
                'input_by' => $userId,
            ],
        );

        $lubeOpening = [];
        $lubeEntries = [];
        $lubeClosing = [];
        foreach ($lubricants as $lube) {
            $k = (string) $lube->id;
            $lubeOpening[$k] = 1200.0;
            $lubeEntries[$k] = ['6' => ['penerimaan' => 418.0]]; // 2 drums
            $lubeClosing[$k] = 1350.0;
        }
        OperasiPersediaan::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => PersediaanJenis::Pelumas, 'month' => $month, 'year' => $year],
            [
                'opening' => $lubeOpening,
                'entries' => $lubeEntries,
                'closing' => $lubeClosing,
                'catatan' => 'Persediaan minyak pelumas gudang utama',
                'input_by' => $userId,
            ],
        );
    }

    private function seedMonthlyOperasiPengusahaan(
        Unit $unit,
        $machines,
        $lubricants,
        $feeders,
        $auxSources,
        array $statusCodes,
        string $tlName,
        string $stafName,
        string $opName,
        int $year,
        int $month,
        int $daysInMonth,
        int $daysDue,
        ?int $userId,
    ): void {
        $nowTs = now()->toDateTimeString();
        $startOfMonth = sprintf('%04d-%02d-01', $year, $month);
        $endOfMonth = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        // 1. Seed BBM & Persediaan first
        $this->seedMonthlyBbmAndPersediaan($unit, $machines, $lubricants, $year, $month, $daysDue, $userId);

        // 2. Daily Engine Reports, Stand Meters, Jam Mesin (24h invariant!), Mesin Harian, Engine Status Logs
        DB::table('daily_engine_reports')->where('unit_id', $unit->id)->whereBetween('report_date', [$startOfMonth, $endOfMonth])->delete();
        DB::table('engine_status_logs')->where('unit_id', $unit->id)->whereBetween('report_date', [$startOfMonth, $endOfMonth])->delete();

        $prevMonthEnd = Carbon::create($year, $month, 1)->subDay()->toDateString();
        $prevReports = DailyEngineReport::query()
            ->where('unit_id', $unit->id)
            ->where('report_date', $prevMonthEnd)
            ->get()
            ->keyBy('engine_id');

        $dailyReportRows = [];
        $statusLogRows = [];
        $standMeterParams = [];
        $standMeterReadingsByDay = [];
        $standMeterTotalsMachines = [];
        $standMeterGrandTotal = 0.0;

        $jamOpsReadings = [];
        $jamHarReadings = [];
        $jamMoReadings = [];
        $jamGgnReadings = [];
        $jamSbyReadings = [];

        $jamOpsTotals = [];
        $jamHarTotals = [];
        $jamMoTotals = [];
        $jamGgnTotals = [];
        $jamSbyTotals = [];

        $bebanReadings = [];
        $dayaMampuParams = [];
        $kaliGgnReadings = [];
        $taraKalorReadings = [];

        $opCodeId = $statusCodes['OP'] ?? reset($statusCodes);
        $sbCodeId = $statusCodes['RS'] ?? $opCodeId;
        $poCodeId = $statusCodes['PO'] ?? $opCodeId;
        $foCodeId = $statusCodes['FO'] ?? $opCodeId;

        for ($d = 1; $d <= $daysDue; $d++) {
            $standMeterReadingsByDay[$d] = [
                'tgl' => $d,
                'machines' => [],
                'adm' => 0.0,
                'real' => 0.0,
                'selisih' => 0.0,
            ];
        }

        foreach ($machines as $mIdx => $machine) {
            $mId = (string) $machine->id;
            $cap = max(200.0, (float) $machine->capacity_kw);
            $dmp = round($cap * 0.90, 2);
            $dayaMampuParams[$mId] = $dmp;

            $prev = $prevReports->get($machine->id);
            $cumKwh = (float) ($prev?->kwh_produksi_stand_akhir ?? (150000 + ($mIdx * 25000)));
            $cumPs = (float) ($prev?->kwh_pakai_sendiri_stand_akhir ?? (6000 + ($mIdx * 1000)));
            $cumHsd = (float) ($prev?->flowmeter_hsd_stand_akhir ?? (40000 + ($mIdx * 5000)));
            $initialHsd = $cumHsd;

            $standMeterParams[] = [
                'id' => $machine->id,
                'name' => $machine->name,
                'stand_awal_bln_lalu' => $initialHsd,
                'faktor_koreksi' => 1.0,
                'faktor_kali' => 1.0,
            ];

            $jamOpsTotals[$mId] = 0.0;
            $jamHarTotals[$mId] = 0.0;
            $jamMoTotals[$mId] = 0.0;
            $jamGgnTotals[$mId] = 0.0;
            $jamSbyTotals[$mId] = 0.0;

            for ($d = 1; $d <= $daysDue; $d++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $dKey = (string) $d;

                // Strict 24.0 hour distribution per machine per day
                if ($mIdx === 0 && $d === 7) {
                    // 1 hour forced outage (gangguan) on day 7 for machine #1
                    $hOps = 18.0;
                    $hHar = 0.0;
                    $hMo = 0.0;
                    $hGgn = 1.0;
                    $hSby = 5.0;
                } elseif ($d === 12) {
                    // Planned maintenance (HAR) on day 12
                    $hOps = 16.0;
                    $hHar = 4.0;
                    $hMo = 0.0;
                    $hGgn = 0.0;
                    $hSby = 4.0;
                } else {
                    $hOps = 19.0;
                    $hHar = 0.0;
                    $hMo = 0.0;
                    $hGgn = 0.0;
                    $hSby = 5.0;
                }

                $jamOpsReadings[$mId][$dKey] = $hOps;
                $jamHarReadings[$mId][$dKey] = $hHar;
                $jamMoReadings[$mId][$dKey] = $hMo;
                $jamGgnReadings[$mId][$dKey] = $hGgn;
                $jamSbyReadings[$mId][$dKey] = $hSby;

                $jamOpsTotals[$mId] += $hOps;
                $jamHarTotals[$mId] += $hHar;
                $jamMoTotals[$mId] += $hMo;
                $jamGgnTotals[$mId] += $hGgn;
                $jamSbyTotals[$mId] += $hSby;

                $pagiKw = round($cap * 0.70, 2);
                $malamKw = round($cap * (0.76 + (($d % 5) * 0.02)), 2);
                $dayKwhProd = round($cap * 0.72 * $hOps, 2);
                $dayKwhPs = round($dayKwhProd * 0.032, 2);
                $dayHsd = round($dayKwhProd * 0.255, 2);

                $awalHsd = $cumHsd;
                $cumKwh = round($cumKwh + $dayKwhProd, 4);
                $cumPs = round($cumPs + $dayKwhPs, 4);
                $cumHsd = round($cumHsd + $dayHsd, 4);

                $dailyReportRows[] = [
                    'unit_id' => $unit->id,
                    'engine_id' => $machine->id,
                    'report_date' => $dateStr,
                    'source' => 'manual',
                    'kwh_produksi_stand_akhir' => $cumKwh,
                    'kwh_pakai_sendiri_stand_akhir' => $cumPs,
                    'beban_puncak_pagi_kw' => $pagiKw,
                    'beban_puncak_malam_kw' => $malamKw,
                    'pemakaian_pelumas_liter' => 6.0,
                    'flowmeter_hsd_stand_akhir' => $cumHsd,
                    'flowmeter_hsd_tambah_liter' => 0,
                    'flowmeter_mfo_stand_akhir' => null,
                    'flowmeter_mfo_tambah_liter' => null,
                    'air_pps_stand_akhir' => 1000 + ($d * 10),
                    'air_softener_stand_akhir' => 500 + ($d * 5),
                    'catatan' => 'Operasi normal',
                    'input_by' => $userId,
                    'verified_by' => $userId,
                    'verified_at' => $nowTs,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];

                // Stand meter day entry
                $standMeterReadingsByDay[$d]['machines'][$mId] = [
                    'machine_id' => $machine->id,
                    'awal' => $awalHsd,
                    'akhir' => $cumHsd,
                    'pemakaian' => $dayHsd,
                ];
                $standMeterReadingsByDay[$d]['adm'] = round($standMeterReadingsByDay[$d]['adm'] + $dayHsd, 2);
                $standMeterReadingsByDay[$d]['real'] = $standMeterReadingsByDay[$d]['adm'];

                // Mesin harian
                $bebanReadings[$mId][$dKey] = $malamKw;
                $kaliGgnReadings[$mId][$dKey] = ($mIdx === 0 && $d === 7) ? 1.0 : 0.0;
                $taraKalorReadings[$mId][$dKey] = 2285.0;

                // Engine Status Logs (summing to 1440 minutes = 24h)
                $statusLogRows[] = [
                    'unit_id' => $unit->id,
                    'engine_id' => $machine->id,
                    'report_date' => $dateStr,
                    'status_code_id' => $opCodeId,
                    'operator_name' => $opName,
                    'dispatcher_name' => 'Dispatcher UPBT',
                    'start_datetime' => "{$dateStr} 00:00:00",
                    'stop_datetime' => sprintf('%s %02d:00:00', $dateStr, (int) $hOps),
                    'duration_minutes' => (int) ($hOps * 60),
                    'keterangan' => 'Operasi beban sistem',
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
                if ($hHar > 0) {
                    $statusLogRows[] = [
                        'unit_id' => $unit->id,
                        'engine_id' => $machine->id,
                        'report_date' => $dateStr,
                        'status_code_id' => $poCodeId,
                        'operator_name' => $opName,
                        'dispatcher_name' => 'Dispatcher UPBT',
                        'start_datetime' => "{$dateStr} 16:00:00",
                        'stop_datetime' => "{$dateStr} 20:00:00",
                        'duration_minutes' => (int) ($hHar * 60),
                        'keterangan' => 'Pemeliharaan rutin P1/P2',
                        'input_by' => $userId,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
                if ($hGgn > 0) {
                    $statusLogRows[] = [
                        'unit_id' => $unit->id,
                        'engine_id' => $machine->id,
                        'report_date' => $dateStr,
                        'status_code_id' => $foCodeId,
                        'operator_name' => $opName,
                        'dispatcher_name' => 'Dispatcher UPBT',
                        'start_datetime' => "{$dateStr} 14:00:00",
                        'stop_datetime' => "{$dateStr} 15:00:00",
                        'duration_minutes' => (int) ($hGgn * 60),
                        'keterangan' => 'Flushing radiator',
                        'input_by' => $userId,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
                if ($hSby > 0) {
                    $statusLogRows[] = [
                        'unit_id' => $unit->id,
                        'engine_id' => $machine->id,
                        'report_date' => $dateStr,
                        'status_code_id' => $sbCodeId,
                        'operator_name' => $opName,
                        'dispatcher_name' => 'Dispatcher UPBT',
                        'start_datetime' => "{$dateStr} 19:00:00",
                        'stop_datetime' => "{$dateStr} 23:59:59",
                        'duration_minutes' => (int) ($hSby * 60),
                        'keterangan' => 'Standby siap operasi',
                        'input_by' => $userId,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ];
                }
            }

            $mPemakaianHsd = round($cumHsd - $initialHsd, 2);
            $standMeterTotalsMachines[$mId] = [
                'awal' => $initialHsd,
                'akhir' => $cumHsd,
                'pemakaian' => $mPemakaianHsd,
            ];
            $standMeterGrandTotal = round($standMeterGrandTotal + $mPemakaianHsd, 2);
        }

        foreach (array_chunk($dailyReportRows, 500) as $chunk) {
            DB::table('daily_engine_reports')->insert($chunk);
        }
        foreach (array_chunk($statusLogRows, 500) as $chunk) {
            DB::table('engine_status_logs')->insert($chunk);
        }

        // Save Stand Meter
        OperasiStandMeter::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'fuel_name' => 'HSD', 'month' => $month, 'year' => $year],
            [
                'machine_parameters' => $standMeterParams,
                'readings' => array_values($standMeterReadingsByDay),
                'totals' => [
                    'machines' => $standMeterTotalsMachines,
                    'adm' => $standMeterGrandTotal,
                    'real' => $standMeterGrandTotal,
                    'selisih' => 0.0,
                ],
                'catatan' => 'Stand flowmeter BBM sesuai opname harian',
                'input_by' => $userId,
            ],
        );

        // Save all 5 Jam Mesin rows (strictly totaling 24h per machine per day)
        $jamSheets = [
            [JamMesinJenis::Operasi, $jamOpsReadings, $jamOpsTotals],
            [JamMesinJenis::Pemeliharaan, $jamHarReadings, $jamHarTotals],
            [JamMesinJenis::MaintenanceOutage, $jamMoReadings, $jamMoTotals],
            [JamMesinJenis::Gangguan, $jamGgnReadings, $jamGgnTotals],
            [JamMesinJenis::Standby, $jamSbyReadings, $jamSbyTotals],
        ];
        foreach ($jamSheets as [$jenis, $readings, $totals]) {
            OperasiJamMesin::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'jenis' => $jenis, 'month' => $month, 'year' => $year],
                [
                    'readings' => $readings,
                    'totals_by_machine' => $totals,
                    'grand_total' => round(array_sum($totals), 2),
                    'catatan' => 'Total jam pembangkit = 24 jam/hari/mesin',
                    'input_by' => $userId,
                ],
            );
        }

        // Save all 3 Mesin Harian rows
        OperasiMesinHarian::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => MesinHarianJenis::BebanTinggi, 'month' => $month, 'year' => $year],
            [
                'readings' => $bebanReadings,
                'params' => ['daya_mampu' => $dayaMampuParams],
                'catatan' => 'Beban tertinggi harian dari parameter LOAD logsheet operator',
                'input_by' => $userId,
            ],
        );
        OperasiMesinHarian::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => MesinHarianJenis::KaliGangguan, 'month' => $month, 'year' => $year],
            [
                'readings' => $kaliGgnReadings,
                'params' => [],
                'catatan' => 'Jumlah kali gangguan otomatis dari Start Stop Engine (penyebab_stop = gangguan)',
                'input_by' => $userId,
            ],
        );
        OperasiMesinHarian::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => MesinHarianJenis::TaraKalor, 'month' => $month, 'year' => $year],
            [
                'readings' => $taraKalorReadings,
                'params' => [],
                'catatan' => 'Tara kalor rata-rata pembangkit',
                'input_by' => $userId,
            ],
        );

        // 3. Pemakaian Pelumas & Items
        $firstLube = $lubricants->first();
        if ($firstLube) {
            $rawLube = [];
            $gantiLube = [];
            $totalsByLube = [(string) $firstLube->id => 0.0];
            $totalsByMachineLube = [];

            foreach ($machines as $machine) {
                $pairKey = "{$firstLube->id}_{$machine->id}";
                $mDays = [];
                $mSum = 0.0;
                for ($d = 1; $d <= $daysDue; $d++) {
                    $val = ($d === 12) ? 45.0 : 5.0;
                    $mDays[(string) $d] = $val;
                    $mSum += $val;
                }
                $rawLube[$pairKey] = $mDays;
                if ($daysDue >= 12) {
                    $gantiLube[$pairKey] = ['12' => 40.0];
                }
                $totalsByLube[(string) $firstLube->id] = round($totalsByLube[(string) $firstLube->id] + $mSum, 2);
                $totalsByMachineLube[(string) $machine->id] = round($mSum, 2);
            }

            $pelumasDoc = OperasiPemakaianPelumas::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
                [
                    'grand_total_liter' => $totalsByLube[(string) $firstLube->id],
                    'raw_readings' => $rawLube,
                    'readings_ganti' => $gantiLube,
                    'totals_by_lubricant' => $totalsByLube,
                    'totals_by_machine' => $totalsByMachineLube,
                    'catatan' => 'Pemakaian pelumas penambah dan penggantian berkala',
                    'input_by' => $userId,
                ],
            );

            OperasiPemakaianPelumasItem::query()->where('pemakaian_pelumas_id', $pelumasDoc->id)->delete();
            foreach ($machines as $machine) {
                $pairKey = "{$firstLube->id}_{$machine->id}";
                $mDays = $rawLube[$pairKey] ?? [];
                $p1 = 0.0;
                $p2 = 0.0;
                $p3 = 0.0;
                foreach ($mDays as $dStr => $v) {
                    $dn = (int) $dStr;
                    if ($dn <= 10) {
                        $p1 += $v;
                    } elseif ($dn <= 20) {
                        $p2 += $v;
                    } else {
                        $p3 += $v;
                    }
                }
                OperasiPemakaianPelumasItem::query()->create([
                    'pemakaian_pelumas_id' => $pelumasDoc->id,
                    'lubricant_type_id' => $firstLube->id,
                    'lubricant_name' => $firstLube->name,
                    'machine_id' => $machine->id,
                    'machine_name' => $machine->name,
                    'daily_readings' => $mDays,
                    'subtotal_p1' => round($p1, 2),
                    'subtotal_p2' => round($p2, 2),
                    'subtotal_p3' => round($p3, 2),
                    'total_liter' => round($p1 + $p2 + $p3, 2),
                ]);
            }
        }

        // 4. Daily Feeder & Auxiliary Readings + Fuel Receipts
        DB::table('daily_feeder_readings')->where('unit_id', $unit->id)->whereBetween('report_date', [$startOfMonth, $endOfMonth])->delete();
        DB::table('daily_auxiliary_readings')->where('unit_id', $unit->id)->whereBetween('report_date', [$startOfMonth, $endOfMonth])->delete();
        DB::table('fuel_receipts')->where('unit_id', $unit->id)->whereBetween('report_date', [$startOfMonth, $endOfMonth])->delete();

        $prevFeeders = DailyFeederReading::query()->where('unit_id', $unit->id)->where('report_date', $prevMonthEnd)->get()->keyBy('feeder_id');
        $feederRows = [];
        foreach ($feeders as $fIdx => $feeder) {
            $cumFeeder = (float) ($prevFeeders->get($feeder->id)?->stand_akhir ?? (200000 + ($fIdx * 30000)));
            for ($d = 1; $d <= $daysDue; $d++) {
                $cumFeeder = round($cumFeeder + 12500.0, 4);
                $feederRows[] = [
                    'unit_id' => $unit->id,
                    'feeder_id' => $feeder->id,
                    'report_date' => sprintf('%04d-%02d-%02d', $year, $month, $d),
                    'stand_akhir' => $cumFeeder,
                    'is_active_today' => true,
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($feederRows, 500) as $chunk) {
            DB::table('daily_feeder_readings')->insert($chunk);
        }

        $prevAux = DailyAuxiliaryReading::query()->where('unit_id', $unit->id)->where('report_date', $prevMonthEnd)->get()->keyBy('auxiliary_source_id');
        $auxRows = [];
        foreach ($auxSources as $aIdx => $aux) {
            $cumAuxKwh = (float) ($prevAux->get($aux->id)?->stand_kwh_akhir ?? (12000 + ($aIdx * 2000)));
            $cumAuxBbm = (float) ($prevAux->get($aux->id)?->stand_bbm_akhir ?? (3000 + ($aIdx * 500)));
            for ($d = 1; $d <= $daysDue; $d++) {
                $cumAuxKwh = round($cumAuxKwh + 180.0, 4);
                $cumAuxBbm = round($cumAuxBbm + 25.0, 4);
                $auxRows[] = [
                    'unit_id' => $unit->id,
                    'auxiliary_source_id' => $aux->id,
                    'report_date' => sprintf('%04d-%02d-%02d', $year, $month, $d),
                    'stand_kwh_akhir' => $cumAuxKwh,
                    'stand_bbm_akhir' => $cumAuxBbm,
                    'input_by' => $userId,
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ];
            }
        }
        foreach (array_chunk($auxRows, 500) as $chunk) {
            DB::table('daily_auxiliary_readings')->insert($chunk);
        }

        FuelReceipt::query()->create([
            'unit_id' => $unit->id,
            'report_date' => sprintf('%04d-%02d-05', $year, $month),
            'fuel_type' => 'hsd',
            'supplier' => 'PT Pertamina Patra Niaga',
            'do_number' => sprintf('DO-%s-%04d%02d-01', $unit->code, $year, $month),
            'unloading_date' => sprintf('%04d-%02d-05', $year, $month),
            'volume_liter' => 25000.00,
            'calorie_value' => 10289.00,
            'price_per_liter' => 14500.00,
            'transport_cost' => 2500000.00,
            'surveyor_cost' => 500000.00,
            'keterangan' => 'Penerimaan BBM HSD via mobil tangki',
            'input_by' => $userId,
        ]);

        // 5. Operasi Rekaps (incl. BA Pemeriksaan Fisik Pelumas) & TUGs
        $lubeItemsOverride = [];
        $lubeFisikOverride = [];
        foreach ($lubricants as $lube) {
            $lubeItemsOverride[(string) $lube->id] = [
                'utuh' => 6.0,
                'cm' => 35.0,
                'liter' => 1350.0,
                'kg' => 0.0,
                'ket' => 'Baik / Sesuai',
            ];
            $lubeFisikOverride[(string) $lube->id] = 1350.0;
        }

        OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => 'ba-fisik-pelumas', 'month' => $month, 'year' => $year],
            [
                'overrides' => [
                    'nomor' => sprintf('0%02d.BA/KIT.00.01/%s/%d', $month, $unit->code, $year),
                    'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, $daysDue),
                    'pemeriksa' => [
                        ['nama' => $tlName, 'jabatan' => 'Team Leader Operasi'],
                        ['nama' => $stafName, 'jabatan' => 'Staf Operasi'],
                        ['nama' => $opName, 'jabatan' => 'Operator'],
                    ],
                    'manager_nama' => 'Manager UL',
                    'items' => $lubeItemsOverride,
                ],
                'catatan' => 'BA Pemeriksaan Fisik Pelumas bulanan',
                'input_by' => $userId,
            ],
        );

        foreach (['perincian-pelumas', 'rekap-pelumas', 'rincian-bbm', 'kinerja', 'kinerja-termal', 'inventaris'] as $rekapJenis) {
            OperasiRekap::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'jenis' => $rekapJenis, 'month' => $month, 'year' => $year],
                [
                    'overrides' => $rekapJenis === 'perincian-pelumas' || $rekapJenis === 'rekap-pelumas'
                        ? ['fisik' => $lubeFisikOverride]
                        : [],
                    'catatan' => "Rekap {$rekapJenis} terverifikasi",
                    'input_by' => $userId,
                ],
            );
        }

        foreach ($machines as $idx => $machine) {
            foreach ([TugJenis::Bbm, TugJenis::Pelumas] as $tugJenis) {
                OperasiTug::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'machine_id' => $machine->id, 'jenis' => $tugJenis, 'month' => $month, 'year' => $year],
                    [
                        'nomor' => sprintf('%03d/TUG9-%s/%s/%02d/%d', $idx + 1, strtoupper($tugJenis->value), $unit->code, $month, $year),
                        'pekerjaan' => 'RUTIN OPERASI PEMBANGKIT',
                        'no_spk' => sprintf('SPK-OPR/%s/%d', $unit->code, $year),
                        'cost_center' => 'CC-'.(2100 + $unit->id),
                        'kode_perkiraan' => $tugJenis === TugJenis::Bbm ? '510101' : '510102',
                        'tanggal_dokumen' => sprintf('%04d-%02d-%02d', $year, $month, $daysDue),
                        'input_by' => $userId,
                    ],
                );
            }
        }

        // 6. Document Records (all 4 Berita Acara types: hsd, mfo, pelumas, feeder)
        $baBuilder = app(BeritaAcaraBuilder::class);
        foreach (BeritaAcaraType::cases() as $baType) {
            $snapshot = $baBuilder->build($unit, $baType, $month, $year);
            DocumentRecord::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'type' => $baType, 'month' => $month, 'year' => $year],
                [
                    'document_number' => $snapshot['nomor'] ?? sprintf('BA-%s/%s/%02d/%d', strtoupper($baType->value), $unit->code, $month, $year),
                    'format' => 'form',
                    'snapshot' => $snapshot,
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'approved_at' => now(),
                ],
            );
        }
    }
}
