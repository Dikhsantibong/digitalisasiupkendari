<?php

namespace Database\Seeders;

use App\Enums\WoWaitingReason;
use App\Models\Machine;
use App\Models\MaintenanceCycle;
use App\Models\MaintenanceType;
use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\SrCategory;
use App\Models\Unit;
use App\Models\WorkGroup;
use App\Models\WorkOrder;
use App\Models\WoStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HarSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(HarMasterSeeder::class);

        $unit = Unit::query()->where('code', 'PLTD-WUAWUA')->first() ?? Unit::query()->first();
        if (! $unit) {
            return;
        }

        // Report Period: Agustus 2026
        $period = ReportPeriod::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026],
            ['total_days' => 31, 'total_hours' => 744],
        );

        $engines = Machine::query()->where('unit_id', $unit->id)->get()->keyBy('name');

        $types = MaintenanceType::query()->get()->keyBy('code');
        $statuses = WoStatus::query()->get()->keyBy('code');
        $groups = WorkGroup::query()->get()->keyBy('code');
        $cycles = MaintenanceCycle::query()->get()->keyBy('code');
        $srCategories = SrCategory::query()->get()->keyBy('code');

        $engineId = function (string $name) use ($engines): ?int {
            // Flexible matching for MaK names
            if (isset($engines[$name])) {
                return $engines[$name]->id;
            }
            $clean = preg_replace('/[^0-9]/', '', $name);
            foreach ($engines as $engName => $eng) {
                if (preg_replace('/[^0-9]/', '', $engName) === $clean) {
                    return $eng->id;
                }
            }
            return null;
        };

        // 1. PM Work Orders (30 items)
        $pmWos = [
            ['wonum' => 'WO13258', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P2 MAK #03 14D', 'date' => '2026-08-04', 'eng' => 'MAK #3', 'group' => 'MECHD', 'cycle' => 'P2'],
            ['wonum' => 'WO13289', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #01 7D', 'date' => '2026-08-05', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13320', 'desc' => 'Pemeriksaan kebocoran air pada liner', 'date' => '2026-08-06', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13392', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #05 7D', 'date' => '2026-08-10', 'eng' => 'MAK #5', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13410', 'desc' => 'PM WUAW GENERATOR PANEL INSPECTION P4 MAK #01 84D', 'date' => '2026-08-11', 'eng' => 'MAK #1', 'group' => 'ELECD', 'cycle' => 'P4'],
            ['wonum' => 'WO13411', 'desc' => 'PM WUAW PEMERIKSAAN KEKENCANGAN BAUT CONNECTING ROD P4 MAK #01 84D', 'date' => '2026-08-11', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P4'],
            ['wonum' => 'WO13412', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P4 MAK #01 84D', 'date' => '2026-08-11', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P4'],
            ['wonum' => 'WO13427', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #03 7D', 'date' => '2026-08-12', 'eng' => 'MAK #3', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13442', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #04 7D', 'date' => '2026-08-13', 'eng' => 'MAK #4', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13574', 'desc' => 'PM WUAW GENERATOR PANEL INSPECTION P3 MAK #05 28D', 'date' => '2026-08-19', 'eng' => 'MAK #5', 'group' => 'ELECD', 'cycle' => 'P3'],
            ['wonum' => 'WO13575', 'desc' => 'PM WUAWPEMERIKSAAN KEKENCANGAN BAUT CONNECTING ROD P3 MAK #05 28D', 'date' => '2026-08-19', 'eng' => 'MAK #5', 'group' => 'MECHD', 'cycle' => 'P3'],
            ['wonum' => 'WO13576', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P3 MAK #05 28D', 'date' => '2026-08-19', 'eng' => 'MAK #5', 'group' => 'MECHD', 'cycle' => 'P3'],
            ['wonum' => 'WO13577', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #01 7D', 'date' => '2026-08-19', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13595', 'desc' => 'PM WUAW GENERATOR PANEL INSPECTION P4 MAK #03 84D', 'date' => '2026-08-20', 'eng' => 'MAK #3', 'group' => 'ELECD', 'cycle' => 'P4'],
            ['wonum' => 'WO13596', 'desc' => 'Pemeriksaan ikatan baut Connecting Rod', 'date' => '2026-08-20', 'eng' => 'MAK #3', 'group' => 'MECHD', 'cycle' => 'P4'],
            ['wonum' => 'WO13597', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P4 MAK #03 84D', 'date' => '2026-08-20', 'eng' => 'MAK #3', 'group' => 'MECHD', 'cycle' => 'P4'],
            ['wonum' => 'WO13651', 'desc' => 'PM WUAW GENERATOR PANEL INSPECTION P3 MAK #04 28D', 'date' => '2026-08-21', 'eng' => 'MAK #4', 'group' => 'ELECD', 'cycle' => 'P3'],
            ['wonum' => 'WO13652', 'desc' => 'PM WUAWPEMERIKSAAN KEKENCANGAN BAUT CONNECTING ROD P3 MAK #04 28D', 'date' => '2026-08-21', 'eng' => 'MAK #4', 'group' => 'MECHD', 'cycle' => 'P3'],
            ['wonum' => 'WO13653', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P3 MAK #04 28D', 'date' => '2026-08-21', 'eng' => 'MAK #4', 'group' => 'MECHD', 'cycle' => 'P3'],
            ['wonum' => 'WO13678', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #03 7D', 'date' => '2026-08-24', 'eng' => 'MAK #3', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13706', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #02 7D', 'date' => '2026-08-26', 'eng' => 'MAK #2', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13793', 'desc' => 'PM WUAWPEMERIKSAAN KEBOCORAN AIR PADA LINER P1 MAK #05 7D', 'date' => '2026-08-28', 'eng' => 'MAK #5', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13801', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #04 7D', 'date' => '2026-08-28', 'eng' => 'MAK #4', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13802', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P1 MAK #02 7D', 'date' => '2026-08-28', 'eng' => 'MAK #2', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13805', 'desc' => 'PM WUAW PEMERIKSAAN KEKENCANGAN BAUT CONNECTING ROD P1 MAK #02 7D', 'date' => '2026-08-29', 'eng' => 'MAK #2', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13808', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #05 7D', 'date' => '2026-08-29', 'eng' => 'MAK #5', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13812', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P1 MAK #01 7D', 'date' => '2026-08-30', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13815', 'desc' => 'PM WUAW PEMERIKSAAN KEKENCANGAN BAUT CONNECTING ROD P1 MAK #03 7D', 'date' => '2026-08-30', 'eng' => 'MAK #3', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13818', 'desc' => 'PM WUAW INSPECTION COMPRESI CYLINDER HEAD P1 MAK #01 7D', 'date' => '2026-08-31', 'eng' => 'MAK #1', 'group' => 'MECHD', 'cycle' => 'P1'],
            ['wonum' => 'WO13820', 'desc' => 'PM WUAW PEMERIKSAAN INJEKTOR P1 MAK #05 7D', 'date' => '2026-08-31', 'eng' => 'MAK #5', 'group' => 'MECHD', 'cycle' => 'P1'],
        ];

        foreach ($pmWos as $w) {
            WorkOrder::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'report_period_id' => $period->id, 'wonum' => $w['wonum']],
                [
                    'description' => $w['desc'],
                    'maintenance_type_id' => $types['PM']?->id,
                    'engine_id' => $engineId($w['eng']),
                    'work_group_id' => $groups[$w['group']]?->id,
                    'wo_status_id' => $statuses['CLOSE']?->id,
                    'cycle_id' => $cycles[$w['cycle']]?->id,
                    'report_date' => $w['date'],
                    'sched_start' => $w['date'],
                    'sched_finish' => $w['date'],
                    'actual_finish' => $w['date'],
                    'service_cost' => 0,
                    'material_cost' => 0,
                    'source' => 'manual',
                ],
            );
        }

        // 2. CM Work Orders (11 items)
        $cmWos = [
            ['wonum' => 'WO13349', 'desc' => 'PERBAIKAN KWH PELANGGAN - TRIP SECARA BERULANG', 'date' => '2026-08-07', 'start' => '2026-08-01', 'finish' => '2026-08-08', 'status' => 'APPR', 'group' => 'MECHD', 'eng' => null],
            ['wonum' => 'WO13604', 'desc' => 'PERBAIKAN MAK #5 - KEBOCORAN AIR PENDINGIN SISI FLEKSIBEL HOSE JW OUT ENGINE', 'date' => '2026-08-21', 'start' => '2026-08-20', 'finish' => '2026-08-27', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #5'],
            ['wonum' => 'WO13605', 'desc' => 'PERBAIKAN MAK #3 - MINYAK PELUMAS TURBOCHARGER BERUBAH WARNA', 'date' => '2026-08-21', 'start' => '2026-08-19', 'finish' => '2026-08-26', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #3'],
            ['wonum' => 'WO13784', 'desc' => 'PENGECEKAN MAK #3 - KEBOCORAN BBM SISI HEADER OVERFLOW INJECTION PUMP', 'date' => '2026-08-28', 'start' => '2026-08-27', 'finish' => '2026-08-27', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #3'],
            ['wonum' => 'WO13675', 'desc' => 'PERBAIKAN MAK #2 - SUARA ABNORMAL POMPA JW', 'date' => '2026-08-24', 'start' => '2026-08-22', 'finish' => '2026-08-27', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #2'],
            ['wonum' => 'WO13832', 'desc' => 'PERBAIKAN MAK #4 - SUARA ABNORMAL PADA INJECTION PUMP CYL 3', 'date' => '2026-08-29', 'start' => '2026-08-27', 'finish' => '2026-08-31', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #4'],
            ['wonum' => 'WO13833', 'desc' => 'PERBAIKAN MAK #5 - BOCOR AIR PADA SISI LUAR LINER CYL 2', 'date' => '2026-08-29', 'start' => '2026-08-28', 'finish' => '2026-08-31', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #5'],
            ['wonum' => 'WO13834', 'desc' => 'PERBAIKAN MAK #5 - LEMAH KOMPRESY CYL 4', 'date' => '2026-08-29', 'start' => '2026-08-18', 'finish' => '2026-08-31', 'status' => 'CLOSE', 'group' => 'MECHD', 'eng' => 'MAK #5'],
            ['wonum' => 'WO13377', 'desc' => 'PENGECEKAN MAK #3 - SUARA ABNORMAL BEARING GENERATOR', 'date' => '2026-08-10', 'start' => '2026-08-08', 'finish' => '2026-08-14', 'status' => 'INPRG', 'group' => 'ELECD', 'eng' => 'MAK #3'],
            ['wonum' => 'WO13346', 'desc' => 'PEMBENAHAN DIESEL HIDRANT - TIDAK BISA START', 'date' => '2026-08-07', 'start' => '2026-08-06', 'finish' => '2026-08-14', 'status' => 'WPTW', 'group' => 'MECHD', 'eng' => null],
            ['wonum' => 'WO13181', 'desc' => 'PERBAIKAN MAK #5 - KVAR HUNTING', 'date' => '2026-08-04', 'start' => '2026-08-03', 'finish' => '2026-08-05', 'status' => 'WPTW', 'group' => 'MECHD', 'eng' => 'MAK #5'],
        ];

        foreach ($cmWos as $w) {
            WorkOrder::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'report_period_id' => $period->id, 'wonum' => $w['wonum']],
                [
                    'description' => $w['desc'],
                    'maintenance_type_id' => $types['CM']?->id,
                    'engine_id' => $w['eng'] ? $engineId($w['eng']) : null,
                    'work_group_id' => $groups[$w['group']]?->id,
                    'wo_status_id' => $statuses[$w['status']]?->id,
                    'cycle_id' => null,
                    'report_date' => $w['date'],
                    'sched_start' => $w['start'],
                    'sched_finish' => $w['finish'],
                    'actual_finish' => $w['status'] === 'CLOSE' ? $w['finish'] : null,
                    'service_cost' => 0,
                    'material_cost' => 0,
                    'source' => 'manual',
                ],
            );
        }

        // 3. Waiting Work Orders (4 items)
        $waitingWos = [
            ['wonum' => 'WO11369', 'desc' => 'Waiting Shutdown', 'eng' => 'MAK #2', 'reason' => WoWaitingReason::Shutdown, 'date' => '2026-08-02'],
            ['wonum' => 'WO10735', 'desc' => 'Menunggu proses kontrak hingga disburse', 'eng' => 'MAK #4', 'reason' => WoWaitingReason::Material, 'date' => '2026-08-05'],
            ['wonum' => 'WO11144', 'desc' => 'Outage UP Kendari', 'eng' => 'MAK #5', 'reason' => WoWaitingReason::Shutdown, 'date' => '2026-08-08'],
            ['wonum' => 'WO10932', 'desc' => 'Menunggu kajian dari tim Engineering UPKD', 'eng' => 'MAK #1', 'reason' => WoWaitingReason::Jasa, 'date' => '2026-08-12'],
        ];

        foreach ($waitingWos as $w) {
            WorkOrder::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'report_period_id' => $period->id, 'wonum' => $w['wonum']],
                [
                    'description' => $w['desc'],
                    'maintenance_type_id' => $types['CM']?->id,
                    'engine_id' => $engineId($w['eng']),
                    'work_group_id' => $groups['MECHD']?->id,
                    'wo_status_id' => $statuses['INPRG']?->id,
                    'cycle_id' => null,
                    'waiting_reason' => $w['reason'],
                    'report_date' => $w['date'],
                    'sched_start' => $w['date'],
                    'sched_finish' => '2026-08-31',
                    'service_cost' => 0,
                    'material_cost' => 0,
                    'source' => 'manual',
                ],
            );
        }

        // 4. Service Requests (17 items)
        $srs = [
            ['num' => 'SR2608001', 'desc' => 'Pemeriksaan kebocoran pelumas rocker arm', 'eng' => 'MAK #1', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608002', 'desc' => 'Penggantian filter bahan bakar duplex', 'eng' => 'MAK #1', 'cat' => 'FLM', 'status' => 'close'],
            ['num' => 'SR2608003', 'desc' => 'Pemeriksaan suara dengung bearing pompa pendingin', 'eng' => 'MAK #2', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608004', 'desc' => 'Pengecekan indikator suhu air pendingin JW', 'eng' => 'MAK #2', 'cat' => 'FLM', 'status' => 'close'],
            ['num' => 'SR2608005', 'desc' => 'Pengecekan getaran abnormal turbocharger sisi turbin', 'eng' => 'MAK #3', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608006', 'desc' => 'Perbaikan rembesan minyak pelumas sisi header', 'eng' => 'MAK #3', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608007', 'desc' => 'Pembersihan cooler pelumas berkala', 'eng' => 'MAK #3', 'cat' => 'FLM', 'status' => 'open'],
            ['num' => 'SR2608008', 'desc' => 'Pengecekan tekanan udara start kompresor #1', 'eng' => 'MAK #4', 'cat' => 'FLM', 'status' => 'close'],
            ['num' => 'SR2608009', 'desc' => 'Pemeriksaan kelainan bunyi fuel injection pump cyl 3', 'eng' => 'MAK #4', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608010', 'desc' => 'Penggantian seal pipa air pendingin silinder 4', 'eng' => 'MAK #4', 'cat' => 'CM', 'status' => 'open'],
            ['num' => 'SR2608011', 'desc' => 'Perbaikan kebocoran fleksibel hose jaket water', 'eng' => 'MAK #5', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608012', 'desc' => 'Pengecekan kompresi rendah pada silinder 4', 'eng' => 'MAK #5', 'cat' => 'CM', 'status' => 'close'],
            ['num' => 'SR2608013', 'desc' => 'Pengecekan fluktuasi daya reaktif kVAR hunting', 'eng' => 'MAK #5', 'cat' => 'CM', 'status' => 'open'],
            ['num' => 'SR2608014', 'desc' => 'Penggantian thermocouple gas buang cyl 2', 'eng' => 'MAK #5', 'cat' => 'FLM', 'status' => 'close'],
            ['num' => 'SR2608015', 'desc' => 'Inspeksi berkala switch panel generator', 'eng' => 'MAK #1', 'cat' => 'FLM', 'status' => 'close'],
            ['num' => 'SR2608016', 'desc' => 'Pemeriksaan kebocoran udara pneumatik governor', 'eng' => 'MAK #2', 'cat' => 'FLM', 'status' => 'close'],
            ['num' => 'SR2608017', 'desc' => 'Kalibrasi pressure gauge pelumas utama', 'eng' => 'MAK #4', 'cat' => 'FLM', 'status' => 'open'],
        ];

        foreach ($srs as $s) {
            ServiceRequest::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'report_period_id' => $period->id, 'sr_number' => $s['num']],
                [
                    'description' => $s['desc'],
                    'sr_category_id' => $srCategories[$s['cat']]?->id,
                    'status' => $s['status'],
                    'engine_id' => $engineId($s['eng']),
                    'source' => 'manual',
                ],
            );
        }
    }
}
