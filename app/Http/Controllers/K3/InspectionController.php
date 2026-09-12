<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The generic inspection-checklist engine (modul K3). One session per
 * unit/month/form_code, its rows seeded from the form's checklist master
 * (inspection_checklists) and merged with any stored results. Backs every
 * uniform checklist form (tempat kerja, rambu K3, fire alarm, …).
 */
class InspectionController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $formCodes = InspectionChecklist::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->orderBy('form_code')->distinct()->pluck('form_code')->all();

        $formCode = (string) ($request->query('form_code') ?: ($formCodes[0] ?? ''));

        $items = InspectionChecklist::query()
            ->where('unit_id', $unit->id)->where('form_code', $formCode)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $inspection = Inspection::query()
            ->with('results')
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->where('form_code', $formCode)
            ->first();

        $stored = $inspection?->results->keyBy('item_ref') ?? collect();

        $rows = $items->map(function (InspectionChecklist $item) use ($stored): array {
            $result = $stored->get($item->item_text);

            return [
                'item_ref' => $item->item_text,
                'kondisi' => $result?->kondisi,
                'tindak_lanjut' => $result?->tindak_lanjut,
                'nilai' => $result?->nilai,
                'catatan' => $result?->catatan,
            ];
        })->all();

        return Inertia::render('k3/input/inspections', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'form_code' => $formCode],
            'header' => [
                'inspection_date' => $inspection?->inspection_date?->format('Y-m-d'),
                'inspector_team' => $inspection?->inspector_team,
                'ketua_tim' => $inspection?->ketua_tim,
                'keterangan' => $inspection?->keterangan,
            ],
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'form_codes' => $formCodes,
            ],
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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'form_code' => ['required', 'string', 'max:255'],
            'header' => ['array'],
            'header.inspection_date' => ['nullable', 'date'],
            'header.inspector_team' => ['nullable', 'string', 'max:255'],
            'header.ketua_tim' => ['nullable', 'string', 'max:255'],
            'header.keterangan' => ['nullable', 'string', 'max:255'],
            'rows' => ['array'],
            'rows.*.item_ref' => ['required', 'string', 'max:255'],
            'rows.*.kondisi' => ['nullable', 'string', 'max:255'],
            'rows.*.tindak_lanjut' => ['nullable', 'string', 'max:255'],
            'rows.*.nilai' => ['nullable', 'string', 'max:255'],
            'rows.*.catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $header = $validated['header'] ?? [];

        DB::transaction(function () use ($validated, $unit, $month, $year, $header, $user): void {
            $inspection = Inspection::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'form_code' => $validated['form_code']],
                [
                    'inspection_date' => $header['inspection_date'] ?? null,
                    'inspector_team' => $header['inspector_team'] ?? null,
                    'ketua_tim' => $header['ketua_tim'] ?? null,
                    'keterangan' => $header['keterangan'] ?? null,
                    'input_by' => $user->id,
                ],
            );

            $inspection->results()->delete();
            foreach ($validated['rows'] ?? [] as $i => $row) {
                $inspection->results()->create([
                    'item_ref' => $row['item_ref'],
                    'kondisi' => $row['kondisi'] ?? null,
                    'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                    'nilai' => $row['nilai'] ?? null,
                    'catatan' => $row['catatan'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan inspeksi {$validated['form_code']} {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Inspeksi disimpan.']);

        return back();
    }
}
