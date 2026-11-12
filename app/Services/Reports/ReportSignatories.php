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
     * The name printed for a signer of a saved form: the employee's current
     * name when the form stores the employee id (so a renamed employee shows
     * up everywhere), else the name typed on the form, else the current holder
     * of the jabatan — never the jabatan itself or someone else's name.
     *
     * @param  array<string, mixed>  $input  the saved form (e.g. manager_ul_id, manager_ul_name)
     * @param  string  $prefix  e.g. manager_ul, tl_har, staff_har
     */
    public function nameFor(array $input, string $prefix, ?Employee $employee): string
    {
        if (! empty($input["{$prefix}_id"])) {
            return (string) ($employee?->name ?? '');
        }

        $typed = trim((string) ($input["{$prefix}_name"] ?? ''));

        return $typed !== '' && ! self::isJabatanText($typed) ? $typed : (string) ($employee?->name ?? '');
    }

    /**
     * Whether a "name" is in fact a jabatan text (e.g. "Manager UL", "Staff
     * Pemeliharaan") that earlier versions printed in place of a name.
     */
    public static function isJabatanText(string $name): bool
    {
        $jabatan = [
            ...array_map(fn (EmployeePosition $position): string => strtolower($position->value), EmployeePosition::cases()),
            'staf pemeliharaan', 'staff pemeliharaan', 'staf operasi', 'staf k3',
        ];

        return in_array(strtolower(trim($name)), $jabatan, true);
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
