<?php

namespace App\Http\Middleware;

use App\Enums\PermissionName;
use App\Models\Unit;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The operators fill the Pengusahaan Operasi inputs (operasi.pengusahaan.input)
 * only for a unit where Master Unit allows it (units.operator_pengusahaan —
 * off for the containerized units). Users holding the TL/Staf permission
 * (operasi.pengusahaan.view / .write) are not affected.
 */
class EnsureOperatorPengusahaanUnit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = (string) $request->route()?->getName();

        if (! $user instanceof User || ! str_starts_with($route, 'operasi.pengusahaan.') || $this->holdsTlAccess($user)) {
            return $next($request);
        }

        // The unit the page works on: the one asked for, else the default the pages pick.
        $unit = $request->filled('unit_id')
            ? Unit::query()->find($request->integer('unit_id'))
            : Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->first();

        abort_if($unit !== null && ! $unit->operator_pengusahaan, 403, 'Input Pengusahaan Operasi tidak diaktifkan untuk unit ini (Master Unit).');

        return $next($request);
    }

    private function holdsTlAccess(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperasiPengusahaanView) || $user->hasPermissionTo(PermissionName::OperasiPengusahaanWrite);
    }
}
