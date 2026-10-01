<?php

namespace App\Services\Reports;

use App\Enums\EmployeePosition;
use App\Models\Employee;
use App\Models\ReportSignerDelegation;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves who signs a Laporan Pembangkit: the one active employee holding a
 * jabatan in the report's unit (the Manager UL in the unit's service unit),
 * looked up by its exact one-per-unit key — never by name or fuzzy match.
 *
 * Penanda tangan lintas unit: when the Super Admin delegated a jabatan of a
 * unit to another unit ({@see ReportSignerDelegation}, e.g. PLTD Poasia
 * Containerized → TL Pemeliharaan of PLTD Poasia), that unit's holder signs
 * instead. One level only: the source unit's own holder, never a chain.
 */
class ReportSignatories
{
    /** @var array<string, Employee|null> */
    private array $holders = [];

    /** @var array<string, ReportSignerDelegation>|null unit_id|position => delegation */
    private ?array $delegations = null;

    public function holder(Unit $unit, EmployeePosition $position): ?Employee
    {
        $delegation = $this->delegation($unit, $position);

        return $delegation !== null
            ? $this->ownHolder($delegation->sourceUnit, $position)
            : $this->ownHolder($unit, $position);
    }

    /**
     * The delegation of a unit's jabatan to another unit, if any.
     */
    public function delegation(Unit $unit, EmployeePosition $position): ?ReportSignerDelegation
    {
        $this->delegations ??= ReportSignerDelegation::query()->with('sourceUnit')->get()
            ->keyBy(fn (ReportSignerDelegation $d): string => $d->unit_id.'|'.$d->position->value)
            ->all();

        return $this->delegations[$unit->id.'|'.$position->value] ?? null;
    }

    /**
     * The jabatan's holder registered in the unit itself (no delegation).
     */
    public function ownHolder(Unit $unit, EmployeePosition $position): ?Employee
    {
        $key = $position->singletonKey($unit->id, $unit->service_unit_id);
        if ($key === null) {
            return null;
        }

        return $this->holders[$key] ??= Employee::query()
            ->where('singleton_key', $key)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Forget what was looked up (after the delegations or employees changed).
     */
    public function flush(): void
    {
        $this->holders = [];
        $this->delegations = null;
    }

    /**
     * The employee's signature image as a data URI (dompdf cannot fetch
     * storage URLs), or null when none is uploaded.
     */
    public function signatureImage(?Employee $employee): ?string
    {
        $path = $employee?->signature_path;
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path)
            ? 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) $disk->get($path))
            : null;
    }
}
