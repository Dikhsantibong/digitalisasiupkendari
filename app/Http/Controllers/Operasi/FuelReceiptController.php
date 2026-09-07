<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\TankFuelType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operasi\FuelReceiptStoreRequest;
use App\Models\FuelReceipt;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fuel-delivery register (modul OPERASI, menu Input): manual entries of BBM
 * received from suppliers, feeding the administrative stock in the Berita Acara.
 */
class FuelReceiptController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $receipts = FuelReceipt::query()
            ->where('unit_id', $unit->id)
            ->whereYear('report_date', $year)
            ->whereMonth('report_date', $month)
            ->orderBy('report_date')
            ->get();

        return Inertia::render('operasi/input/fuel-receipts', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'receipts' => $receipts->map(fn (FuelReceipt $receipt): array => [
                'id' => $receipt->id,
                'report_date' => $receipt->report_date->toDateString(),
                'fuel_type' => $receipt->fuel_type->value,
                'fuel_type_label' => $receipt->fuel_type->label(),
                'supplier' => $receipt->supplier,
                'do_number' => $receipt->do_number,
                'volume_liter' => $receipt->volume_liter,
                'price_per_liter' => $receipt->price_per_liter,
                'keterangan' => $receipt->keterangan,
            ])->all(),
            'totals' => [
                'hsd' => (float) $receipts->where('fuel_type', TankFuelType::Hsd)->sum('volume_liter'),
                'mfo' => (float) $receipts->where('fuel_type', TankFuelType::Mfo)->sum('volume_liter'),
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 2, $year + 1),
                'fuel_types' => collect(TankFuelType::cases())
                    ->map(fn (TankFuelType $type): array => ['value' => $type->value, 'label' => $type->label()])
                    ->all(),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(FuelReceiptStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        FuelReceipt::query()->create([
            ...$request->safe()->except('unit_id'),
            'unit_id' => $unit->id,
            'input_by' => $user->id,
        ]);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah penerimaan BBM {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penerimaan BBM ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, FuelReceipt $fuelReceipt): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);
        abort_unless($user->canAccessUnit($fuelReceipt->unit_id), 403);

        $fuelReceipt->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            'Menghapus penerimaan BBM',
            unit: $fuelReceipt->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penerimaan BBM dihapus.']);

        return back();
    }
}
