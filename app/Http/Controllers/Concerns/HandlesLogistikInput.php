<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PermissionName;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared plumbing of the Logistik & Gudang input pages: permission gates, unit
 * & period resolution and the filter options. Mirrors {@see HandlesPdmInput}.
 */
trait HandlesLogistikInput
{
    /**
     * Gate a read (page or PDF) and resolve the unit & period.
     *
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    protected function logistikReadTarget(Request $request): array
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::LogistikInputView) || $user->hasPermissionTo(PermissionName::LogistikLaporanView),
            403,
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            (int) ($request->integer('year') ?: $now->year),
        ];
    }

    /**
     * Gate a write and resolve the unit & period being saved.
     *
     * @return array{0: Unit, 1: int, 2: int}
     */
    protected function logistikWriteTarget(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::LogistikInputWrite), 403);

        $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        return [$unit, (int) $request->integer('month'), (int) $request->integer('year')];
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return array{units: list<array{id: int, name: string}>, years: list<int>}
     */
    protected function logistikFilterOptions(Collection $units): array
    {
        $year = Carbon::now()->year;

        return [
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
            'years' => range($year - 3, $year + 1),
        ];
    }
}
