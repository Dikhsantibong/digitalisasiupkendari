<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Operasi "Monitoring FLM": the first line maintenance findings of a
 * unit & month — mesin/peralatan, tanggal, masalah awal, kondisi awal actions
 * (bersihkan, lumasi, kencangkan, perbaikan koneksi, lainnya), kondisi akhir,
 * catatan FLM and status Open/Close. At least {@see self::MIN_ROWS} numbered
 * rows, like the paper form. PDF A4 landscape; Excel from the page.
 */
class FlmMonitoringController extends Controller
{
    use RendersReportPdf;

    public const MIN_ROWS = 10;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$units, $unit, $month, $year] = $this->target($request);

        return Inertia::render('operasi/input/flm-monitoring', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'years' => range(Carbon::now()->year - 3, Carbon::now()->year + 1),
                'kondisi_awal' => OperasiFlmMonitoring::KONDISI_AWAL,
            ],
            'rows' => $this->rows($unit, $month, $year),
            'has_saved' => $this->saved($unit, $month, $year)->isNotEmpty(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.mesin' => ['nullable', 'string', 'max:255'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.masalah' => ['nullable', 'string', 'max:255'],
            'rows.*.kondisi_awal' => ['nullable', 'array'],
            'rows.*.kondisi_awal.*' => [Rule::in(array_keys(OperasiFlmMonitoring::KONDISI_AWAL))],
            'rows.*.kondisi_akhir' => ['nullable', 'string', 'max:255'],
            'rows.*.catatan' => ['nullable', 'string', 'max:255'],
            'rows.*.status' => ['required', Rule::in(['open', 'close'])],
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

            $number = 0;
            foreach ($validated['rows'] as $row) {
                $text = fn (string $key): ?string => trim((string) ($row[$key] ?? '')) ?: null;
                if ($text('mesin') === null && $text('masalah') === null && empty($row['tanggal'])) {
                    continue;
                }

                OperasiFlmMonitoring::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => ++$number,
                    'mesin' => $text('mesin'),
                    'tanggal' => $row['tanggal'] ?? null,
                    'masalah' => $text('masalah'),
                    'kondisi_awal' => array_values(array_unique((array) ($row['kondisi_awal'] ?? []))),
                    'kondisi_akhir' => $text('kondisi_akhir'),
                    'catatan' => $text('catatan'),
                    'status' => $row['status'],
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Monitoring FLM {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Monitoring FLM berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $unit, $month, $year] = $this->target($request);
        [$view, $data] = $this->pdfView($unit, $month, $year);

        return $this->streamReportPdf($request, $view, $data, sprintf('Monitoring_FLM_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year), 'landscape');
    }

    /**
     * The PDF view and its data — shared by the PDF and the Laporan Operasi Pembangkit.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        return ['operasi.input.flm-monitoring-pdf', [
            'unit' => $unit,
            'periodLabel' => strtoupper(Indonesian::monthName($month).' '.$year),
            'rows' => $this->rows($unit, $month, $year),
            'kondisiAwal' => OperasiFlmMonitoring::KONDISI_AWAL,
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Saved findings followed by blank numbered rows up to {@see self::MIN_ROWS}.
     *
     * @return list<array{no_urut: int, mesin: string, tanggal: string|null, masalah: string, kondisi_awal: list<string>, kondisi_akhir: string, catatan: string, status: string}>
     */
    private function rows(Unit $unit, int $month, int $year): array
    {
        $rows = $this->saved($unit, $month, $year)->map(fn (OperasiFlmMonitoring $r): array => [
            'no_urut' => $r->no_urut,
            'mesin' => (string) ($r->mesin ?? ''),
            'tanggal' => $r->tanggal?->format('Y-m-d'),
            'masalah' => (string) ($r->masalah ?? ''),
            'kondisi_awal' => array_values((array) ($r->kondisi_awal ?? [])),
            'kondisi_akhir' => (string) ($r->kondisi_akhir ?? ''),
            'catatan' => (string) ($r->catatan ?? ''),
            'status' => $r->status,
        ])->all();

        for ($n = count($rows) + 1; $n <= self::MIN_ROWS; $n++) {
            $rows[] = ['no_urut' => $n, 'mesin' => '', 'tanggal' => null, 'masalah' => '', 'kondisi_awal' => [], 'kondisi_akhir' => '', 'catatan' => '', 'status' => 'open'];
        }

        return $rows;
    }

    /**
     * @return Collection<int, OperasiFlmMonitoring>
     */
    private function saved(Unit $unit, int $month, int $year): Collection
    {
        return OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('no_urut')->get();
    }

    /**
     * Gate a read (page or PDF) and resolve the unit & period.
     *
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();

        return [$units, $unit, max(1, min(12, (int) ($request->integer('month') ?: $now->month))), (int) ($request->integer('year') ?: $now->year)];
    }
}
