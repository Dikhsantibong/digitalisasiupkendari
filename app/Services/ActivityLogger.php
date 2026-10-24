<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Models\ActivityLog;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records auditable actions so Super Admins can review who changed what.
 *
 * Modules added later call this service instead of writing to the table, which
 * keeps the log shape consistent across the application.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        ActivityEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        Unit|int|null $unit = null,
        ServiceUnit|int|null $serviceUnit = null,
    ): ActivityLog {
        $unitId = $unit instanceof Unit ? $unit->getKey() : $unit;
        $serviceUnitId = $serviceUnit instanceof ServiceUnit ? $serviceUnit->getKey() : $serviceUnit;

        if ($unitId !== null && $serviceUnitId === null) {
            $serviceUnitId = $unit instanceof Unit
                ? $unit->service_unit_id
                : Unit::query()->whereKey($unitId)->value('service_unit_id');
        }

        // Saved while a Super Admin works as this user (Pindah Akun): keep who really did it.
        $impersonator = Request::hasSession() ? (Request::session()->get(Impersonation::SESSION_KEY)['admin_id'] ?? null) : null;
        if ($impersonator !== null) {
            $properties['impersonated_by'] = $impersonator;
        }

        return ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'unit_id' => $unitId,
            'service_unit_id' => $serviceUnitId,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }
}
