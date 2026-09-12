<?php

namespace App\Services\Operasi;

/**
 * Prepared, NOT YET ACTIVE. The future bridge from the standalone OPERATOR
 * module into OPERASI: it rolls a submitted operator logsheet up into the TL
 * Operasi daily engine report. This is the "OPERASI can later pull operator
 * data" hook — the wiring exists (the `source` column on daily_engine_reports
 * and this service) but nothing calls it yet, so the two modules stay
 * independent and TL Operasi still types the daily figures manually.
 *
 * Planned aggregation once activated:
 *  - beban_puncak_pagi_kw  = MAX(Load) over the morning slots
 *  - beban_puncak_malam_kw = MAX(Load) over the evening slots (incl. 17:30–21:30)
 *  - kwh_produksi_stand_akhir = kWh Meter at the 24:00 slot
 *  - jam operasi           = derived from Hour meter / run duration
 *  - HSD usage             = Flow meter IN/OUT delta
 *
 * When implemented, the produced daily_engine_report row is marked source =
 * 'logsheet' to distinguish it from manual entry.
 */
class LogsheetAggregator
{
    public function aggregateToDailyReport(int $engineId, string $date): void
    {
        // TODO: implement — read the submitted OperatorLogsheet for (engineId,
        // date), compute the daily figures per the plan above, and upsert the
        // DailyEngineReport with source = 'logsheet'. Deliberately inert for now.
    }
}
