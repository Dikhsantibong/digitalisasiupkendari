<?php

namespace Tests\Concerns;

use App\Enums\LogsheetStatus;
use App\Models\LogsheetParameter;
use App\Models\Machine;
use App\Models\OperatorLogsheet;

/**
 * Operator logsheet LOAD readings (kW) of a machine on a date — the source of
 * Beban Tertinggi.
 */
trait RecordsLogsheetLoad
{
    /**
     * @param  list<float|int>  $loads  one reading per hour slot
     */
    protected function logsheetLoad(Machine $machine, string $date, array $loads): void
    {
        $load = LogsheetParameter::query()->where('code', 'LOAD')->first()
            ?? LogsheetParameter::factory()->create(['code' => 'LOAD', 'name' => 'Load', 'unit_of_measure' => 'kW', 'is_active' => true]);

        $sheet = OperatorLogsheet::query()->create([
            'unit_id' => $machine->unit_id,
            'engine_id' => $machine->id,
            'log_date' => $date,
            'status' => LogsheetStatus::Draft,
        ]);

        foreach (array_values($loads) as $i => $value) {
            $sheet->readings()->create(['time_slot' => sprintf('%02d:00', $i + 1), 'parameter_id' => $load->id, 'value' => $value]);
        }
    }
}
