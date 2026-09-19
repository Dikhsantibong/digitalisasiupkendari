<?php

namespace App\Services\Reports;

use App\Enums\EmployeePosition;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;

/**
 * Resolves who signs a Laporan Pembangkit: the one active employee holding a
 * jabatan in the report's unit (the Manager UL in the unit's service unit),
 * looked up by its exact one-per-unit key — never by name or fuzzy match.
 */
class ReportSignatories
{
    /** @var array<string, Employee|null> */
    private array $holders = [];

    public function holder(Unit $unit, EmployeePosition $position): ?Employee
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
