<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logsheet sheets (lembar) per template (user, 2026-11-22): PLTD Poasia reads
 * several forms per machine with their own interval — Logsheet Lokal 02 every
 * hour, Logsheet Lokal 03 every 2 hours (with MIN / MAX / RATA2 rows) and
 * Temperature Cylinder Head ("Setiap 4 Jam", rows 01:00, 03:00 … 23:00 as on
 * the form). A template without sheets keeps one sheet (its time_slots), so
 * PLTD Poasia Containerized (Cummins) does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logsheet_templates', function (Blueprint $table): void {
            $table->json('sheets')->nullable()->after('time_slots');
        });
        Schema::table('logsheet_parameters', function (Blueprint $table): void {
            $table->string('sheet_key', 40)->nullable()->after('template_id');
        });

        $mirrlees = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->value('id');
        if ($mirrlees === null) {
            return;
        }

        $hourly = array_map(fn (int $h): string => sprintf('%02d:00', $h), range(1, 24));
        $odd = array_map(fn (int $h): string => sprintf('%02d:00', $h), range(1, 23, 2));
        DB::table('logsheet_templates')->where('id', $mirrlees)->update([
            'sheets' => json_encode([
                ['key' => 'lokal-02', 'name' => 'Logsheet Lokal 02', 'interval' => 'Setiap Jam', 'slots' => $hourly, 'summary' => false],
                ['key' => 'lokal-03', 'name' => 'Logsheet Lokal 03', 'interval' => 'Setiap 2 Jam', 'slots' => $odd, 'summary' => true],
                ['key' => 'cylinder-head', 'name' => 'Temperature Cylinder Head', 'interval' => 'Setiap 4 Jam', 'slots' => $odd, 'summary' => false],
            ]),
            'updated_at' => now(),
        ]);
        DB::table('logsheet_parameters')->where('template_id', $mirrlees)->whereNull('sheet_key')->update(['sheet_key' => 'lokal-02']);

        $now = now();
        $order = (int) DB::table('logsheet_parameters')->where('template_id', $mirrlees)->max('sort_order') + 1;
        $rows = [];
        $add = function (string $sheet, string $code, ?string $group, string $name, ?string $sub, ?string $uom, ?string $normal = null, ?string $alarm = null, ?string $trip = null, string $type = 'number', ?array $options = null) use (&$rows, &$order, $mirrlees, $now): void {
            $rows[] = [
                'template_id' => $mirrlees, 'sheet_key' => $sheet, 'plant_type' => 'all', 'code' => $code, 'group_label' => $group, 'name' => $name,
                'sub_channel' => $sub, 'unit_of_measure' => $uom, 'input_type' => $type, 'options' => $options === null ? null : json_encode($options),
                'normal_value' => $normal, 'alarm_value' => $alarm, 'trip_value' => $trip,
                'sort_order' => $order++, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        };

        // Logsheet Lokal 03 — setiap 2 jam.
        $add('lokal-03', 'L03_BEBAN', null, 'Beban', null, 'kW', '2300', '1700', '2860');
        foreach (['CAM' => 'Temperature Cam Shaft Door', 'CRANK' => 'Temperature Crank Shaft Door'] as $prefix => $group) {
            foreach (['A', 'B'] as $bank) {
                foreach (range(1, 8) as $i) {
                    $add('lokal-03', "L03_{$prefix}_{$bank}{$i}", $group, "Bank {$bank}", "{$bank}{$i}", '°C', '62', '57', '75');
                }
            }
        }
        $add('lokal-03', 'L03_AUTONIC_B1', null, 'Autonic', 'B1', '°C', '50', '47', '54');
        $add('lokal-03', 'L03_PRESS_COC', null, 'Press C O C', '1', 'bar', '5', '3', '7');
        $add('lokal-03', 'L03_PRESS_COOLING_TIP', null, 'Press Cooling Tip', '1', 'bar', '0,6', '0,4', '1,2');
        $add('lokal-03', 'L03_PRESS_SERVICE_UDARA', null, 'Press T Service Udara', '1', 'bar', '26', '20', '30');
        $add('lokal-03', 'L03_ASAP_SILENCER', null, 'Kondisi Asap Silencer', '1', null, 'N', 'N/T - S', 'T', 'choice', ['N', 'T', 'S']);
        $add('lokal-03', 'L03_ASAP_EXTRACTION', null, 'Kondisi Asap Extraction', '1', null, 'N', 'N/T - S', 'T', 'choice', ['N', 'T', 'S']);

        // Temperature Cylinder Head.
        $add('cylinder-head', 'CH_BEBAN', null, 'Beban', null, 'kW');
        foreach (['A', 'B'] as $bank) {
            foreach (range(1, 8) as $i) {
                $add('cylinder-head', "CH_{$bank}{$i}", 'Temperature Cylinder Head', "Bank {$bank}", "{$bank}{$i}", '°C');
            }
        }
        DB::table('logsheet_parameters')->insert($rows);
    }

    public function down(): void
    {
        $mirrlees = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->value('id');
        if ($mirrlees !== null) {
            DB::table('logsheet_parameters')->where('template_id', $mirrlees)->whereIn('sheet_key', ['lokal-03', 'cylinder-head'])->delete();
        }
        Schema::table('logsheet_parameters', function (Blueprint $table): void {
            $table->dropColumn('sheet_key');
        });
        Schema::table('logsheet_templates', function (Blueprint $table): void {
            $table->dropColumn('sheets');
        });
    }
};
