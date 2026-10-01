<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Http\Controllers\Controller;
use App\Models\ReportSignerDelegation;
use App\Models\Role;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Reports\ReportSignatories;
use App\Services\Reports\SignerDelegations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administrasi → Penanda Tangan Laporan: which unit's jabatan holder signs
 * (verifies / approves / ratifies) the Laporan Pembangkit of a unit — e.g. the
 * TL Pemeliharaan of PLTD Poasia also verifies PLTD Poasia Containerized.
 * Managed by whoever may assign roles (the access it can grant is a role).
 */
class ReportSignerController extends Controller
{
    public function __construct(
        private readonly SignerDelegations $delegations,
        private readonly ReportSignatories $signatories,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('assign', Role::class);
        $this->signatories->flush();

        $units = Unit::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name', 'service_unit_id', 'is_active']);
        $positions = SignerDelegations::positions();
        $delegations = ReportSignerDelegation::query()->with(['unit:id,name', 'sourceUnit:id,name,service_unit_id'])->whereIn('unit_id', $units->pluck('id'))->get();

        return Inertia::render('admin/report-signers/index', [
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name, 'is_active' => (bool) $u->is_active])->values()->all(),
            'positions' => array_map(fn (EmployeePosition $p): array => ['value' => $p->value, 'label' => $p->value], $positions),
            // Each unit's own holder per jabatan (for the form preview) and the resolved signer (own or delegated).
            'matrix' => $units->map(fn (Unit $unit): array => [
                'unit_id' => $unit->id,
                'cells' => collect($positions)->mapWithKeys(function (EmployeePosition $position) use ($unit): array {
                    $delegation = $this->signatories->delegation($unit, $position);

                    return [$position->value => [
                        'own' => $this->signatories->ownHolder($unit, $position)?->name,
                        'signer' => $this->signatories->holder($unit, $position)?->name,
                        'source_unit' => $delegation?->sourceUnit?->name,
                    ]];
                })->all(),
            ])->values()->all(),
            'delegations' => $delegations->sortBy(fn (ReportSignerDelegation $d): string => $d->unit->name.$d->position->value)->values()->map(fn (ReportSignerDelegation $d): array => [
                'id' => $d->id,
                'unit' => $d->unit->name,
                'unit_id' => $d->unit_id,
                'position' => $d->position->value,
                'source_unit' => $d->sourceUnit->name,
                'source_unit_id' => $d->source_unit_id,
                'signer' => $this->signatories->ownHolder($d->sourceUnit, $d->position)?->name,
                'granted' => count($d->granted_assignment_ids ?? []),
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('assign', Role::class);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'position' => ['required', Rule::in(array_map(fn (EmployeePosition $p): string => $p->value, SignerDelegations::positions()))],
            'source_unit_id' => ['required', 'integer', 'exists:units,id', 'different:unit_id'],
            'grant_access' => ['boolean'],
        ], [
            'source_unit_id.different' => 'Unit penanda tangan harus berbeda dengan unit laporan.',
        ], [
            'unit_id' => 'unit laporan',
            'position' => 'jabatan',
            'source_unit_id' => 'unit penanda tangan',
        ]);

        $user = $request->user();
        $unit = Unit::query()->findOrFail($validated['unit_id']);
        $source = Unit::query()->findOrFail($validated['source_unit_id']);
        abort_unless($user->canAccessUnit($unit) && $user->canAccessUnit($source), 403);

        $position = EmployeePosition::from($validated['position']);
        $delegation = $this->delegations->save($unit, $position, $source, (bool) ($validated['grant_access'] ?? false), $user);
        $signer = $this->signatories->ownHolder($source, $position)?->name;

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengatur penanda tangan {$position->value} untuk laporan {$unit->name}: ditangani {$position->value} {$source->name}".($signer ? " ({$signer})" : ''),
            $delegation,
            unit: $unit->id,
        );

        Inertia::flash('toast', [
            'type' => $signer ? 'success' : 'warning',
            'message' => $signer
                ? "{$position->value} {$unit->name} kini ditandatangani {$signer} ({$source->name})."
                : "Tersimpan, tetapi {$source->name} belum punya pegawai aktif dengan jabatan {$position->value}.",
        ]);

        return back();
    }

    public function destroy(Request $request, ReportSignerDelegation $delegation): RedirectResponse
    {
        $this->authorize('assign', Role::class);
        abort_unless($request->user()->canAccessUnit($delegation->unit_id), 403);

        $delegation->loadMissing(['unit', 'sourceUnit']);
        $this->delegations->remove($delegation);

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus penanda tangan lintas unit {$delegation->position->value} laporan {$delegation->unit->name} (dari {$delegation->sourceUnit->name})",
            unit: $delegation->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$delegation->position->value} {$delegation->unit->name} kembali ditandatangani pegawai unitnya sendiri."]);

        return back();
    }
}
