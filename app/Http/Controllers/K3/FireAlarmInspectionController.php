<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3FireAlarmInspection;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FireAlarmInspectionController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        $rows = K3FireAlarmInspection::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('k3/input/fire-alarm', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'rows' => $rows,
            'options' => ['units' => $units->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.lokasi' => ['nullable', 'string', 'max:255'],
            'rows.*.tanggal_periksa' => ['nullable', 'date'],
            'rows.*.kondisi' => ['nullable', 'string', 'max:255'],
            'rows.*.panel_indikator' => ['nullable', 'string', 'max:255'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            $existingIds = [];
            foreach ($validated['rows'] ?? [] as $index => $row) {
                $attributes = [
                    'no_urut' => $index + 1,
                    'lokasi' => $row['lokasi'] ?? null,
                    'tanggal_periksa' => ! empty($row['tanggal_periksa']) ? Carbon::parse($row['tanggal_periksa'])->toDateString() : null,
                    'kondisi' => $row['kondisi'] ?? null,
                    'panel_indikator' => $row['panel_indikator'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3FireAlarmInspection::query()->where('id', $row['id'])->where('unit_id', $unit->id)->first();
                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $existingIds[] = K3FireAlarmInspection::create([...$attributes, 'unit_id' => $unit->id, 'year' => $year, 'month' => $month])->id;
            }

            K3FireAlarmInspection::query()
                ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Inspeksi Fire Alarm {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Inspeksi Fire Alarm berhasil disimpan.']);
    }
}
