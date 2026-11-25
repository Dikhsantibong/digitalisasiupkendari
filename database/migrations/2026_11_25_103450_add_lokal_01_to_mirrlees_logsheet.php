<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PLTD Poasia logsheet revised to the five paper forms (user, 2026-11-25):
 * Logsheet Lokal 01 (setiap jam — beban, jam operasi, BBM, speed, governor,
 * exhaust A1–B8 + T/C, jacket water, L/O, charge air, inter cooler) is added
 * as the first sheet; Lokal 02 (setiap jam) gets the MIN / MAX / RATA2 footer
 * of its form; Temperature Cylinder Head becomes "Logsheet Lokal 04 (Cylinder Head)" (its
 * key, parameters and readings unchanged). Lokal 03 is unchanged; Lokal 05
 * (Alat Bantu) follows when its form is supplied.
 */
return new class extends Migration
{
    /** The Exhaust reference notes, one merged cell over A1–B8 on the form. */
    private const EXHAUST_NORMAL = 'Pada beban 1900 - 2300 kw nilai 380° C - 470° C (selisih temp. max : 30° C - TEMP EXHAUST MAX 520° C)';

    private const EXHAUST_ALARM = 'Pada beban 1700 - 1900 kw nilai maximal 340 - 380° C (selisih temp. max : 30° C - TEMP EXHAUST MAX 520° C)';

    private const EXHAUST_TRIP = 'Pada beban diatas 2300 kw nilai maximal 500° C (selisih temp. max : 30° C - TEMP EXHAUST MAX 520° C)';

    public function up(): void
    {
        $template = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->first();
        if ($template === null || DB::table('logsheet_parameters')->where('template_id', $template->id)->where('sheet_key', 'lokal-01')->exists()) {
            return;
        }

        $hourly = array_map(fn (int $h): string => sprintf('%02d:00', $h), range(1, 24));
        $sheets = collect(json_decode((string) $template->sheets, true) ?: [])
            ->map(fn (array $sheet): array => match ($sheet['key']) {
                'lokal-02' => [...$sheet, 'summary' => true],
                'cylinder-head' => [...$sheet, 'name' => 'Logsheet Lokal 04 (Cylinder Head)'],
                default => $sheet,
            })
            ->prepend(['key' => 'lokal-01', 'name' => 'Logsheet Lokal 01', 'interval' => 'Setiap Jam', 'slots' => $hourly, 'summary' => true])
            ->values()->all();

        DB::table('logsheet_templates')->where('id', $template->id)->update([
            'sheets' => json_encode($sheets),
            'description' => 'Laporan Isian Data Operator Lokal — Mesin Mirrlees PLTD Poasia: Lokal 01 & 02 (setiap jam), Lokal 03 (setiap 2 jam), Lokal 04 Cylinder Head (setiap 4 jam).',
            'updated_at' => now(),
        ]);

        $now = now();
        $order = (int) DB::table('logsheet_parameters')->where('template_id', $template->id)->max('sort_order') + 1;
        $rows = [];
        $add = function (string $code, ?string $group, string $name, ?string $sub, ?string $uom, ?string $normal, ?string $alarm, ?string $trip, string $type = 'number', ?array $options = null) use (&$rows, &$order, $template, $now): void {
            $rows[] = [
                'template_id' => $template->id, 'sheet_key' => 'lokal-01', 'plant_type' => 'all', 'code' => $code, 'group_label' => $group, 'name' => $name,
                'sub_channel' => $sub, 'unit_of_measure' => $uom, 'input_type' => $type, 'options' => $options === null ? null : json_encode($options),
                'normal_value' => $normal, 'alarm_value' => $alarm, 'trip_value' => $trip,
                'sort_order' => $order++, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        };

        $add('L01_BEBAN', null, 'Beban', null, 'kW', '1500', '1300', '2860');
        $add('L01_JAM_OPERASI', null, 'Jam Operasi', null, 'Jam', 'JAM', '2', '24');
        $add('L01_BBM', null, 'BBM (HSD/MFO) yg Digunakan', null, null, 'MFO', 'HSD', 'MFO', 'choice', ['HSD', 'MFO']);
        $add('L01_SPEED', null, 'Speed', null, 'RPM', '1000', '1150/1200', '1200');
        $add('L01_ACTUATOR_GOVERNOR', null, 'Actuator Governor', null, '%', '75-90', '50', '95');
        foreach (['A', 'B'] as $bank) {
            foreach (range(1, 8) as $i) {
                $add("L01_EXH_{$bank}{$i}", 'Exhaust Temperature', "Bank {$bank}", "{$bank}{$i}", '°C', self::EXHAUST_NORMAL, self::EXHAUST_ALARM, self::EXHAUST_TRIP);
            }
        }
        $add('L01_TC_TURBINE_SPEED', 'Exhaust Temperature', 'T/C', 'Turbine Speed', null, null, null, null);
        $add('L01_TC_TEMP', 'Exhaust Temperature', 'T/C', 'Temperature', '°C', '± 20-40 Esh', '± 20-40 Esh', '± 20-40 Esh');
        $add('L01_JW_IN', null, 'Jacket Water', 'In', '°C', '70', '76', '82');
        $add('L01_JW_OUT', null, 'Jacket Water', 'Out', '°C', '76-83', '88', '90');
        $add('L01_LO_BEFORE_FILTER', null, 'L/O Before Filter', null, 'bar', '4-5', '2,6', '2,4');
        $add('L01_LO_AFTER_FILTER', null, 'L/O After Filter', null, 'bar', '4-5', '2,6', '2,4');
        $add('L01_LUB_OIL_TC_PRESS', null, 'Lub Oil T/C Press', null, 'bar', '1,2', '0,8', '0,5');
        $add('L01_JW_PRESS', null, 'Jacket Water Press', null, 'bar', '1.9-2', '1,6', '1,4');
        $add('L01_CHARGE_AIR_PRESS', null, 'Charge Air Press', null, 'bar', '2.2-2.4', '1,8', '2,8');
        $add('L01_IC_UDARA_PRESS_IN', 'Inter Cooler', 'Udara', 'Press In', 'bar', '1.8-2.2', '1,3', '2,8');
        $add('L01_IC_UDARA_TEMP_IN', 'Inter Cooler', 'Udara', 'Temp In', '°C', '130-180', '100', '250');
        $add('L01_IC_UDARA_TEMP_OUT', 'Inter Cooler', 'Udara', 'Temp Out', '°C', '48-52', '55', '65');
        $add('L01_IC_RW_PRESS_OUT', 'Inter Cooler', 'Raw Water', 'Press Out', 'bar', '1.8-2.4', '1,5', '2,8');
        $add('L01_IC_RW_TEMP_IN', 'Inter Cooler', 'Raw Water', 'Temp In', '°C', '33-38', '30', '40');
        $add('L01_IC_RW_TEMP_OUT', 'Inter Cooler', 'Raw Water', 'Temp Out', '°C', '40-46', '38', '50');
        DB::table('logsheet_parameters')->insert($rows);
    }

    public function down(): void
    {
        $template = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->first();
        if ($template === null) {
            return;
        }

        $ids = DB::table('logsheet_parameters')->where('template_id', $template->id)->where('sheet_key', 'lokal-01')->pluck('id');
        DB::table('operator_logsheet_readings')->whereIn('parameter_id', $ids)->delete();
        DB::table('logsheet_parameters')->whereIn('id', $ids)->delete();

        $sheets = collect(json_decode((string) $template->sheets, true) ?: [])
            ->reject(fn (array $sheet): bool => $sheet['key'] === 'lokal-01')
            ->map(fn (array $sheet): array => match ($sheet['key']) {
                'lokal-02' => [...$sheet, 'summary' => false],
                'cylinder-head' => [...$sheet, 'name' => 'Temperature Cylinder Head'],
                default => $sheet,
            })
            ->values()->all();
        DB::table('logsheet_templates')->where('id', $template->id)->update(['sheets' => json_encode($sheets), 'updated_at' => now()]);
    }
};
