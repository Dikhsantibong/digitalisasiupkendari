<?php

namespace App\Services\Har;

use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Placeholder for pulling Work Orders / Service Requests straight from the PLN
 * WPC (Work Planning & Control) database. Not wired up yet: the connection
 * details (DB type, schema, credentials for 192.168.3.85/wpc-ditgas) are
 * confirmed and configured only during the integration phase. Bind this in
 * place of {@see ManualWorkOrderSource} once implemented.
 */
class WpcWorkOrderSource implements WorkOrderSource
{
    /**
     * @return Collection<int, WorkOrder>
     */
    public function workOrders(Unit $unit, int $month, int $year): Collection
    {
        throw new RuntimeException('Integrasi WPC belum aktif. Gunakan sumber manual.');
    }

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function serviceRequests(Unit $unit, int $month, int $year): Collection
    {
        throw new RuntimeException('Integrasi WPC belum aktif. Gunakan sumber manual.');
    }
}
