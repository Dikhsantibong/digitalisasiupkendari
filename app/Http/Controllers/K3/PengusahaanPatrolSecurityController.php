<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3PengusahaanPatrolSecurity;
use App\Models\K3PengusahaanPatrolSecurityItem;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanPatrolSecurityController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->findOrFail($request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);
        } else {
            $unit = $units->first();
        }

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        $dateObj = Carbon::createFromDate($year, $month, 1);
        $daysInMonth = $dateObj->daysInMonth;

        // Collect all day numbers that fall on Sunday
        $sundays = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            if (Carbon::createFromDate($year, $month, $d)->isSunday()) {
                $sundays[] = $d;
            }
        }

        $record = K3PengusahaanPatrolSecurity::query()
            ->with('items')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $hasSaved = $record !== null;

        if ($record && $record->items->isNotEmpty()) {
            $itemsData = $record->items->map(fn (K3PengusahaanPatrolSecurityItem $item, int $idx): array => [
                'id' => $item->id,
                'lokasi_kode' => $item->lokasi_kode,
                'lokasi_nama' => $item->lokasi_nama ?? $item->lokasi_kode,
                'scans' => is_array($item->scans) ? $item->scans : [],
                'total' => (int) $item->total,
                'persentase' => (float) $item->persentase,
                'sort_order' => $item->sort_order ?? $idx,
            ])->all();

            $judul = $record->judul ?? 'PATROL CHECK SECURITY';
            $catatan = $record->catatan ?? '';
        } else {
            // Check previous month for prefill
            $prevRecord = K3PengusahaanPatrolSecurity::query()
                ->with('items')
                ->where('unit_id', $unit->id)
                ->where(function ($q) use ($year, $month) {
                    $q->where('year', '<', $year)
                        ->orWhere(function ($q2) use ($year, $month) {
                            $q2->where('year', $year)->where('month', '<', $month);
                        });
                })
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->first();

            if ($prevRecord && $prevRecord->items->isNotEmpty()) {
                $itemsData = $prevRecord->items->map(function (K3PengusahaanPatrolSecurityItem $item, int $idx) use ($daysInMonth): array {
                    $scans = [];
                    for ($d = 1; $d <= $daysInMonth; $d++) {
                        $scans[(string) $d] = 0;
                    }

                    return [
                        'id' => null,
                        'lokasi_kode' => $item->lokasi_kode,
                        'lokasi_nama' => $item->lokasi_nama ?? $item->lokasi_kode,
                        'scans' => $scans,
                        'total' => 0,
                        'persentase' => 0.0,
                        'sort_order' => $idx,
                    ];
                })->all();
                $judul = $prevRecord->judul ?? 'PATROL CHECK SECURITY';
            } else {
                $itemsData = K3PengusahaanPatrolSecurity::defaultCheckpoints($unit, $year, $month);
                $judul = 'PATROL CHECK SECURITY';
            }

            $catatan = '';
        }

        return Inertia::render('pengusahaan/k3/patrol-security/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'days_in_month' => $daysInMonth,
            'sundays' => $sundays,
            'options' => [
                'units' => $units->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'record' => [
                'judul' => $judul,
                'catatan' => $catatan,
                'items' => $itemsData,
            ],
            'has_saved' => $hasSaved,
            'can_write' => $user->hasPermissionTo(PermissionName::K3PengusahaanWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['nullable', 'string', 'max:150'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.lokasi_kode' => ['required', 'string', 'max:50'],
            'items.*.lokasi_nama' => ['nullable', 'string', 'max:150'],
            'items.*.scans' => ['nullable', 'array'],
            'items.*.total' => ['nullable', 'integer'],
            'items.*.persentase' => ['nullable', 'numeric'],
            'items.*.sort_order' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($validated, $unit, $user): void {
            $record = K3PengusahaanPatrolSecurity::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ],
                [
                    'judul' => $validated['judul'] ?? 'PATROL CHECK SECURITY',
                    'catatan' => $validated['catatan'] ?? null,
                    'input_by' => $user->id,
                ]
            );

            $record->items()->delete();

            $itemsToCreate = array_map(function (array $item, int $idx): array {
                $scans = is_array($item['scans'] ?? null) ? $item['scans'] : [];
                $total = array_sum(array_map('intval', $scans));

                return [
                    'lokasi_kode' => $item['lokasi_kode'],
                    'lokasi_nama' => $item['lokasi_nama'] ?? $item['lokasi_kode'],
                    'scans' => $scans,
                    'total' => $total,
                    'persentase' => isset($item['persentase']) ? (float) $item['persentase'] : 0,
                    'sort_order' => $item['sort_order'] ?? $idx,
                ];
            }, $validated['items'], array_keys($validated['items']));

            $record->items()->createMany($itemsToCreate);

            $this->activityLogger->log(
                ActivityEvent::Created,
                "Menyimpan patrol check security unit {$unit->name} periode {$validated['month']}/{$validated['year']} dengan ".count($itemsToCreate).' titik lokasi.',
                $record,
            );
        });

        return redirect()->back()->with('success', 'Data patrol check security berhasil disimpan.');
    }
}
