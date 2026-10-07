<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\PersediaanJenis;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiPersediaan;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\PersediaanSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Persediaan Bahan Bakar and Persediaan Pelumas: the
 * daily stock per jenis, with the sheet passed as the route's `jenis` default.
 * Penerimaan and pengiriman (BBM: TUG 8, Pinjam THAS; pelumas: TUG 8, TUG 10,
 * Over Flow) are typed in, pemakaian comes from the Pemakaian
 * sheet and the opening stock is carried from last month (correctable).
 */
class PengusahaanPersediaanController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly PersediaanSheet $sheet,
    ) {}

    public function index(Request $request, PersediaanJenis $jenis): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $items = $this->sheet->items($unit, $jenis);
        $record = $this->sheet->record($unit, $jenis, $month, $year);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        return Inertia::render("pengusahaan/operasi/persediaan-{$jenis->value}/index", [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'items' => $items,
            'kirim_columns' => $this->sheet->kirimColumns($jenis),
            'periods' => $this->sheet->periods($jenis, $daysInMonth),
            'days_in_month' => $daysInMonth,
            'entries' => (object) ($record?->entries ?? []),
            'opening' => (object) ($record?->opening ?? []),
            'carried_opening' => (object) $this->sheet->carriedOpening($unit, $jenis, $month, $year, $items),
            'pemakaian' => (object) $this->sheet->pemakaian($unit, $jenis, $month, $year, $items),
            'catatan' => $record?->catatan ?? '',
            'saved_at' => $record?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
            'can_manage_master' => $user->hasPermissionTo(PermissionName::OperasiMasterManage),
        ]);
    }

    public function store(Request $request, PersediaanJenis $jenis): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'opening' => ['nullable', 'array'],
            'opening.*' => ['nullable', 'numeric', 'min:0'],
            'entries' => ['nullable', 'array'],
            'entries.*' => ['array'],
            'entries.*.*' => ['array'],
            'entries.*.*.*' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'entries.*.*.*.min' => 'Jumlah tidak boleh negatif.',
            'opening.*.min' => 'Persediaan awal tidak boleh negatif.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $items = $this->sheet->items($unit, $jenis);
        $entries = $this->sheet->sanitize($jenis, $validated['entries'] ?? [], $items, $daysInMonth);
        $carried = $this->sheet->carriedOpening($unit, $jenis, $month, $year, $items);

        // Only an opening that differs from last month's saldo akhir is a correction worth keeping.
        $opening = [];
        foreach ($items as $item) {
            $value = $validated['opening'][$item['key']] ?? null;

            if (is_numeric($value) && round((float) $value, 2) !== round((float) ($carried[$item['key']] ?? 0), 2)) {
                $opening[$item['key']] = round((float) $value, 2);
            }
        }

        $record = OperasiPersediaan::query()->firstOrNew(['unit_id' => $unit->id, 'jenis' => $jenis, 'month' => $month, 'year' => $year]);
        $record->fill(['opening' => $opening, 'entries' => $entries, 'catatan' => $validated['catatan'] ?? null, 'input_by' => $user->id]);
        $computed = $this->sheet->compute($jenis, $items, $this->sheet->openingOf($record, $carried, $items), $entries, $this->sheet->pemakaian($unit, $jenis, $month, $year, $items), $daysInMonth);
        $record->closing = array_map(fn (array $block): float => $block['total']['akhir'], $computed);
        $record->save();

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "{$jenis->label()} {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', "{$jenis->label()} berhasil disimpan.");
    }

    public function pdf(Request $request, PersediaanJenis $jenis): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $items = $this->sheet->items($unit, $jenis);
        $record = $this->sheet->record($unit, $jenis, $month, $year);
        $opening = $this->sheet->openingOf($record, $this->sheet->carriedOpening($unit, $jenis, $month, $year, $items), $items);

        $pdf = Pdf::loadView('operasi.persediaan.pdf', [
            'unit' => $unit,
            'title' => $jenis->title(),
            'period_label' => Indonesian::monthName($month).' '.$year,
            'items' => $items,
            'kirim_columns' => $this->sheet->kirimColumns($jenis),
            'periods' => $this->sheet->periods($jenis, $daysInMonth),
            'days_in_month' => $daysInMonth,
            'sheet' => $this->sheet->compute($jenis, $items, $opening, $record?->entries ?? [], $this->sheet->pemakaian($unit, $jenis, $month, $year, $items), $daysInMonth),
            'catatan' => $record?->catatan,
        ])->setPaper('a4', count($items) > 1 ? 'landscape' : 'portrait');

        $file = str_replace(' ', '_', $jenis->label());
        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$month}_{$year}.pdf");
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }
}
