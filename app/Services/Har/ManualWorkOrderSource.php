<?php

namespace App\Services\Har;

use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Reads Work Orders / Service Requests from the local tables filled in by hand.
 * The default source until the WPC integration is built.
 */
class ManualWorkOrderSource implements WorkOrderSource
{
    /**
     * @return Collection<int, WorkOrder>
     */
    public function workOrders(Unit $unit, int $month, int $year): Collection
    {
        $periodId = $this->periodId($unit, $month, $year);

        if ($periodId === null) {
            return collect();
        }

        return WorkOrder::query()
            ->where('unit_id', $unit->getKey())
            ->where('report_period_id', $periodId)
            ->with(['engine:id,name', 'maintenanceType:id,code,name', 'workGroup:id,code,name', 'status:id,code,name,is_closed'])
            ->orderBy('report_date')
            ->get();
    }

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function serviceRequests(Unit $unit, int $month, int $year): Collection
    {
        $periodId = $this->periodId($unit, $month, $year);

        if ($periodId === null) {
            return collect();
        }

        return ServiceRequest::query()
            ->where('unit_id', $unit->getKey())
            ->where('report_period_id', $periodId)
            ->with(['engine:id,name', 'category:id,code,name'])
            ->orderBy('sr_number')
            ->get();
    }

    private function periodId(Unit $unit, int $month, int $year): ?int
    {
        return ReportPeriod::query()
            ->where('unit_id', $unit->getKey())
            ->where('month', $month)
            ->where('year', $year)
            ->value('id');
    }
}
