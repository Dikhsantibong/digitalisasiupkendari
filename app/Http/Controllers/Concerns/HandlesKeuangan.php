<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PermissionName;
use App\Models\ServiceUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared plumbing of the Administrasi & Keuangan pages (per UL): permission
 * gate, the UL & period being viewed and the filter options.
 */
trait HandlesKeuangan
{
    /**
     * Gate the request and resolve the UL (ul_id, else the first accessible one) and the period.
     *
     * @return array{0: Collection<int, ServiceUnit>, 1: ServiceUnit, 2: int, 3: int}
     */
    protected function keuanganTarget(Request $request, PermissionName $permission = PermissionName::KeuanganView): array
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo($permission), 403);

        $uls = ServiceUnit::query()->visibleTo($user)->orderBy('name')->get(['id', 'code', 'name']);
        abort_if($uls->isEmpty(), 403, 'Anda belum ditugaskan pada Unit Layanan manapun.');

        $ul = $request->filled('ul_id') ? $uls->firstWhere('id', $request->integer('ul_id')) : $uls->first();
        abort_if($ul === null, 403);

        $now = Carbon::now();

        return [
            $uls,
            ServiceUnit::query()->findOrFail($ul->id),
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            (int) ($request->integer('year') ?: $now->year),
        ];
    }

    protected function canSeeUl(User $user, int $serviceUnitId): bool
    {
        return ServiceUnit::query()->visibleTo($user)->whereKey($serviceUnitId)->exists();
    }

    /**
     * @param  Collection<int, ServiceUnit>  $uls
     * @return array{uls: list<array{id: int, name: string}>, years: list<int>}
     */
    protected function keuanganOptions(Collection $uls): array
    {
        $year = Carbon::now()->year;

        return [
            'uls' => $uls->map(fn (ServiceUnit $ul): array => ['id' => $ul->id, 'name' => $ul->name])->values()->all(),
            'years' => range($year - 3, $year + 1),
        ];
    }
}
