<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class JadwalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::LogistikInputView) ||
            $user->hasPermissionTo(PermissionName::LogistikLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();

        return Inertia::render('logistik/jadwal/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => (int) ($request->integer('month') ?: $now->month),
                'year' => (int) ($request->integer('year') ?: $now->year),
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
        ]);
    }
}
