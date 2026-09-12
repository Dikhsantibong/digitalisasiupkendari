<?php

namespace App\Services\Har;

use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * The single seam between the HAR module and where Work Orders / Service
 * Requests actually come from. Controllers and reports depend only on this
 * interface, so the WPC database integration can be added later by swapping the
 * bound implementation — no controller or report changes.
 */
interface WorkOrderSource
{
    /**
     * @return Collection<int, WorkOrder>
     */
    public function workOrders(Unit $unit, int $month, int $year): Collection;

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function serviceRequests(Unit $unit, int $month, int $year): Collection;
}
