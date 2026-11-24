<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $machines = DB::table('machines')
            ->get(['id', 'name', 'merk', 'type', 'serial_number', 'capacity_kw', 'engine_rpm']);

        $tables = [
            'har_prelube_tests',
            'har_hydrotests',
            'har_timing_injection_pumps',
            'har_crankshaft_deflections',
            'har_counter_weights',
            'har_axial_conrods',
            'har_clearance_valves',
            'har_combustion_pressures',
            'har_injector_pressures',
            'har_motor_currents',
            'har_vibrations',
            'har_battery_voltages',
        ];

        foreach ($machines as $machine) {
            $merk = ($machine->merk !== null && trim((string) $machine->merk) !== '')
                ? (string) $machine->merk
                : (string) $machine->name;

            $machineNumber = $this->resolveMachineNumber((string) $machine->name);
            $capNum = $machine->capacity_kw !== null && (float) $machine->capacity_kw > 0
                ? (float) $machine->capacity_kw
                : null;
            $installedPower = $capNum !== null ? number_format($capNum, 2, '.', '') : null;
            $capablePower = $capNum !== null ? number_format(round($capNum * 0.9, 2), 2, '.', '') : null;

            $payload = ['brand' => $merk, 'machine_number' => $machineNumber];
            if (! empty($machine->type)) {
                $payload['model_type'] = $machine->type;
            }
            if (! empty($machine->serial_number)) {
                $payload['serial_number'] = $machine->serial_number;
            }
            if ($installedPower !== null) {
                $payload['installed_power'] = $installedPower;
            }
            if ($capablePower !== null) {
                $payload['capable_power'] = $capablePower;
            }
            if (! empty($machine->engine_rpm)) {
                $payload['rpm'] = (string) $machine->engine_rpm;
            }

            foreach ($tables as $table) {
                DB::table($table)
                    ->where('machine_id', $machine->id)
                    ->update($payload);
            }

            $lubePayload = [
                'machine_name' => $machine->name,
                'machine_number' => $machineNumber,
            ];
            if (! empty($machine->serial_number)) {
                $lubePayload['serial_number'] = $machine->serial_number;
            }

            DB::table('har_lube_qualities')
                ->where('machine_id', $machine->id)
                ->update($lubePayload);
        }
    }

    public function down(): void
    {
        // One-way data alignment from Master Mesin.
    }

    private function resolveMachineNumber(string $name): string
    {
        if (preg_match('/#\s*([A-Za-z0-9]+)\b/', $name, $matches) === 1) {
            return $matches[1];
        }

        $stripped = trim((string) preg_replace(
            '/^(MIRRLEES|CUMMINS|MAK|DEUTZ|CAT|CATERPILLAR|WARTSILA|WÄRTSILÄ|DAIHATSU|YANMAR|MESIN|UNIT)\s*#?\s*/iu',
            '',
            $name,
        ));

        return $stripped !== '' ? $stripped : $name;
    }
};
