<?php

namespace Database\Seeders;

use App\Enums\PlantType;
use App\Models\LogsheetParameter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The shared logsheet parameter set (plant_type = all), from the Cummins
 * Containerized Poasia logsheet. PLTM/PLTG sets can be added later as extra rows
 * with their own plant_type — no schema change.
 */
class LogsheetParameterSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{code: string, name: string, unit_of_measure: string|null, sub_channel: string|null}>
     */
    private const PARAMETERS = [
        ['code' => 'LOAD', 'name' => 'Load', 'unit_of_measure' => 'kW', 'sub_channel' => null],
        ['code' => 'COOLANT_TEMP_1', 'name' => 'Coolant Temp', 'unit_of_measure' => '°C', 'sub_channel' => '1'],
        ['code' => 'COOLANT_TEMP_2', 'name' => 'Coolant Temp', 'unit_of_measure' => '°C', 'sub_channel' => '2'],
        ['code' => 'LUBE_OIL_TEMP', 'name' => 'Lube Oil Temp', 'unit_of_measure' => '°C', 'sub_channel' => null],
        ['code' => 'LUBE_OIL_PRESS', 'name' => 'Lube Oil Press', 'unit_of_measure' => 'bar', 'sub_channel' => null],
        ['code' => 'WINDING_TEMP_L1', 'name' => 'Winding Temp', 'unit_of_measure' => '°C', 'sub_channel' => 'L1'],
        ['code' => 'WINDING_TEMP_L2', 'name' => 'Winding Temp', 'unit_of_measure' => '°C', 'sub_channel' => 'L2'],
        ['code' => 'WINDING_TEMP_L3', 'name' => 'Winding Temp', 'unit_of_measure' => '°C', 'sub_channel' => 'L3'],
        ['code' => 'TEG_BATTERY', 'name' => 'TEG Battery', 'unit_of_measure' => 'V', 'sub_channel' => null],
        ['code' => 'AMPERE_R', 'name' => 'Ampere', 'unit_of_measure' => 'A', 'sub_channel' => 'R'],
        ['code' => 'AMPERE_S', 'name' => 'Ampere', 'unit_of_measure' => 'A', 'sub_channel' => 'S'],
        ['code' => 'AMPERE_T', 'name' => 'Ampere', 'unit_of_measure' => 'A', 'sub_channel' => 'T'],
        ['code' => 'COS_PHI', 'name' => 'Cos Phi', 'unit_of_measure' => null, 'sub_channel' => null],
        ['code' => 'HOUR_METER', 'name' => 'Hour Meter', 'unit_of_measure' => 'jam', 'sub_channel' => null],
        ['code' => 'FREQ', 'name' => 'Freq', 'unit_of_measure' => 'Hz', 'sub_channel' => null],
        ['code' => 'TEG', 'name' => 'TEG', 'unit_of_measure' => 'V', 'sub_channel' => null],
        ['code' => 'KVAR', 'name' => 'KVAR', 'unit_of_measure' => 'kVAR', 'sub_channel' => null],
        ['code' => 'KWH_METER', 'name' => 'kWh Meter', 'unit_of_measure' => 'kWh', 'sub_channel' => null],
        ['code' => 'FLOW_METER_IN', 'name' => 'Flow Meter', 'unit_of_measure' => 'L', 'sub_channel' => 'IN'],
        ['code' => 'FLOW_METER_OUT', 'name' => 'Flow Meter', 'unit_of_measure' => 'L', 'sub_channel' => 'OUT'],
        ['code' => 'DAILY_TANK_LEVEL', 'name' => 'Daily Tank Level', 'unit_of_measure' => null, 'sub_channel' => null],
        ['code' => 'BEARING_GEN_TEMP', 'name' => 'Bearing Generator Temperatur', 'unit_of_measure' => '°C', 'sub_channel' => null],
    ];

    public function run(): void
    {
        foreach (self::PARAMETERS as $i => $parameter) {
            LogsheetParameter::query()->updateOrCreate(
                ['plant_type' => PlantType::All->value, 'code' => $parameter['code']],
                [
                    'name' => $parameter['name'],
                    'unit_of_measure' => $parameter['unit_of_measure'],
                    'sub_channel' => $parameter['sub_channel'],
                    'sort_order' => $i,
                    'is_active' => true,
                ],
            );
        }
    }
}
