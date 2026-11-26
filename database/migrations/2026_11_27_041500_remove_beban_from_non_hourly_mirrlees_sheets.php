<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the Beban parameter from non-hourly logsheet sheets of PLTD Poasia
 * (Mirrlees): only the hourly sheets (Lokal 01 & Lokal 02 — "Setiap Jam")
 * keep their Beban parameter; the 2-hour sheet (Lokal 03 — L03_BEBAN) and
 * 4-hour sheet (Lokal 04 Cylinder Head — CH_BEBAN) no longer include Beban.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->first();
        if ($template === null) {
            return;
        }

        $nonHourlySheetKeys = collect(json_decode((string) $template->sheets, true) ?: [])
            ->filter(fn (array $sheet): bool => mb_strtolower(trim((string) ($sheet['interval'] ?? ''))) !== 'setiap jam')
            ->pluck('key')
            ->values()
            ->all();

        if ($nonHourlySheetKeys === []) {
            $nonHourlySheetKeys = ['lokal-03', 'cylinder-head'];
        }

        $paramIds = DB::table('logsheet_parameters')
            ->where('template_id', $template->id)
            ->whereIn('sheet_key', $nonHourlySheetKeys)
            ->where(function ($query): void {
                $query->whereIn('code', ['L03_BEBAN', 'CH_BEBAN'])
                    ->orWhereRaw('LOWER(name) = ?', ['beban']);
            })
            ->pluck('id')
            ->all();

        if ($paramIds !== []) {
            DB::table('operator_logsheet_readings')->whereIn('parameter_id', $paramIds)->delete();
            DB::table('logsheet_parameters')->whereIn('id', $paramIds)->delete();
        }
    }

    public function down(): void
    {
        $template = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->first();
        if ($template === null) {
            return;
        }

        $now = now();

        if (! DB::table('logsheet_parameters')->where('template_id', $template->id)->where('code', 'L03_BEBAN')->exists()) {
            $minOrder03 = (int) DB::table('logsheet_parameters')
                ->where('template_id', $template->id)
                ->where('sheet_key', 'lokal-03')
                ->min('sort_order');

            DB::table('logsheet_parameters')->insert([
                'template_id' => $template->id,
                'sheet_key' => 'lokal-03',
                'plant_type' => 'all',
                'code' => 'L03_BEBAN',
                'group_label' => null,
                'name' => 'Beban',
                'sub_channel' => null,
                'unit_of_measure' => 'kW',
                'input_type' => 'number',
                'options' => null,
                'normal_value' => '2300',
                'alarm_value' => '1700',
                'trip_value' => '2860',
                'sort_order' => max(0, $minOrder03 - 1),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('logsheet_parameters')->where('template_id', $template->id)->where('code', 'CH_BEBAN')->exists()) {
            $minOrderCh = (int) DB::table('logsheet_parameters')
                ->where('template_id', $template->id)
                ->where('sheet_key', 'cylinder-head')
                ->min('sort_order');

            DB::table('logsheet_parameters')->insert([
                'template_id' => $template->id,
                'sheet_key' => 'cylinder-head',
                'plant_type' => 'all',
                'code' => 'CH_BEBAN',
                'group_label' => null,
                'name' => 'Beban',
                'sub_channel' => null,
                'unit_of_measure' => 'kW',
                'input_type' => 'number',
                'options' => null,
                'normal_value' => null,
                'alarm_value' => null,
                'trip_value' => null,
                'sort_order' => max(0, $minOrderCh - 1),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
