<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\Reports\SlaCatalog;
use App\Services\Reports\SlaCover;
use App\Services\Reports\SlaReport;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\HttpFoundation\Response;

/**
 * SLA (Service Level Agreement) bulanan per divisi — Operasi, Pemeliharaan,
 * Logistik, PdM, K3L and Project Leader — scored from the unit's inputs
 * ({@see SlaReport}); a page with the PDF (landscape) and Excel downloads.
 * Open to whoever may view the divisi's laporan, for the units they can access.
 * The Laporan SLA gabungan (cover + every divisi + rekapitulasi, one file —
 * one unit or every accessible unit) is for the Super Admin and the holders
 * of sla.gabungan (Project Leader).
 */
class SlaController extends Controller
{
    public function __construct(private readonly SlaReport $sla) {}

    public function show(Request $request, string $divisi): InertiaResponse
    {
        [$units, $unit, $month, $year, $tagihan] = $this->target($request, $divisi);
        $now = Carbon::now();

        return Inertia::render('laporan/sla', [
            'sla' => $this->sla->build($divisi, $unit, $month, $year),
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, 'tagihan' => $tagihan],
            'options' => ['units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'divisions' => collect(SlaCatalog::DIVISI)
                ->filter(fn (array $definition, string $key): bool => SlaReport::allows($request->user(), $key))
                ->map(fn (array $definition, string $key): array => ['key' => $key, 'number' => $definition[0], 'label' => $definition[1]])
                ->values()->all(),
            'can_combined' => SlaReport::allowsCombined($request->user()),
        ]);
    }

    public function pdf(Request $request, string $divisi): Response
    {
        [, $unit, $month, $year, $tagihan] = $this->target($request, $divisi);
        $sla = $this->sla->build($divisi, $unit, $month, $year);
        $file = Str::slug("sla {$divisi} {$unit->name} {$month} {$year}").'.pdf';

        $pdf = Pdf::loadView('reports.sla-pdf', ['slas' => [$sla], 'tagihan' => $tagihan, 'recap' => false, 'printedAt' => Carbon::now()])->setPaper('a4', 'landscape');

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    /**
     * Laporan SLA gabungan page (Project Leader & Super Admin): one unit or
     * every accessible unit ("Semua unit"), with the rekap per divisi and the
     * PDF / Excel downloads in one file.
     */
    public function combinedIndex(Request $request): InertiaResponse
    {
        [$units, $selected, $month, $year, $tagihan, $all] = $this->combinedTarget($request);
        $now = Carbon::now();

        return Inertia::render('laporan/sla-gabungan', [
            'filters' => ['unit_id' => $all ? 'all' : $selected->first()->id, 'month' => $month, 'year' => $year, 'tagihan' => $tagihan],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'period' => Indonesian::monthName($month).' '.$year,
            'recap' => $selected->map(fn (Unit $unit): array => [
                'unit' => $unit->name,
                'rows' => array_map(fn (array $sla): array => [
                    'number' => $sla['number'],
                    'title' => $sla['title'],
                    'bobot' => $sla['total']['bobot'],
                    'percent' => $sla['total']['percent'],
                    'denda_count' => $sla['total']['denda_count'],
                    'measured' => $sla['total']['measured'],
                    'denda_bobot' => $sla['total']['denda_bobot'],
                ], $this->combined($unit, $month, $year)),
            ])->values()->all(),
        ]);
    }

    /**
     * Laporan SLA gabungan: per unit the cover (portrait) then every divisi's
     * SLA one under another and the Rekapitulasi (landscape) — one unit, or
     * every accessible unit one after another — in one PDF.
     */
    public function combinedPdf(Request $request, SlaCover $cover): Response
    {
        [, $selected, $month, $year, $tagihan, $all] = $this->combinedTarget($request);

        // "Semua unit" of the Super Admin spans every unit (cover + six divisi each, ~100 MB peak per
        // dompdf render): allow the time and memory it takes, for this request only.
        if ($selected->count() > 3) {
            set_time_limit(max(120, 20 * $selected->count()));
            ini_set('memory_limit', '1024M');
        }

        $pdfs = [];
        foreach ($selected as $unit) {
            $pdfs[] = Pdf::loadView('reports.sla-cover', ['cover' => $cover->png($unit->name, Indonesian::monthName($month), $year)])->setPaper('a4', 'portrait')->output();
            $pdfs[] = Pdf::loadView('reports.sla-pdf', ['slas' => $this->combined($unit, $month, $year), 'tagihan' => $tagihan, 'recap' => true, 'printedAt' => Carbon::now()])->setPaper('a4', 'landscape')->output();
        }

        $file = Str::slug('laporan sla '.($all ? 'semua unit' : $selected->first()->name)." {$month} {$year}").'.pdf';

        return response($this->merge($pdfs), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$file.'"',
        ]);
    }

    /**
     * Payload of the Laporan SLA gabungan's Excel (built in the browser): the
     * cover image and every divisi's SLA — for "Semua unit", one entry per unit.
     */
    public function combinedData(Request $request, SlaCover $cover): JsonResponse
    {
        [, $selected, $month, $year, , $all] = $this->combinedTarget($request);
        $payload = fn (Unit $unit): array => [
            'unit' => $unit->name,
            'cover' => $cover->png($unit->name, Indonesian::monthName($month), $year),
            'slas' => $this->combined($unit, $month, $year),
        ];

        return response()->json($all
            ? ['units' => $selected->map($payload)->values()->all()]
            : $payload($selected->first()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function combined(Unit $unit, int $month, int $year): array
    {
        return array_map(fn (string $divisi): array => $this->sla->build($divisi, $unit, $month, $year), array_keys(SlaCatalog::DIVISI));
    }

    /**
     * Pages of the PDFs one after another, each in its own orientation.
     *
     * @param  list<string>  $pdfs
     */
    private function merge(array $pdfs): string
    {
        $merged = new Fpdi;
        foreach ($pdfs as $content) {
            $pages = $merged->setSourceFile(StreamReader::createByString($content));
            for ($page = 1; $page <= $pages; $page++) {
                $template = $merged->importPage($page);
                $size = $merged->getTemplateSize($template);
                $merged->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $merged->useTemplate($template);
            }
        }

        return $merged->Output('', 'S');
    }

    /**
     * The Laporan SLA gabungan target: one accessible unit, or every one ("unit_id=all").
     *
     * @return array{0: Collection<int, Unit>, 1: Collection<int, Unit>, 2: int, 3: int, 4: float|null, 5: bool}
     */
    private function combinedTarget(Request $request): array
    {
        $user = $request->user();
        abort_unless(SlaReport::allowsCombined($user), 403);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'regex:/^(all|\d+)$/'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'tagihan' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
        ]);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id', 'status']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = $validated['unit_id'] ?? null;
        $all = $unitId === 'all';
        if ($all) {
            $selected = Unit::query()->whereIn('id', $units->pluck('id'))->orderBy('name')->get();
        } else {
            $unit = $unitId === null ? $units->first() : $units->firstWhere('id', (int) $unitId);
            abort_if($unit === null, 403);
            $selected = new Collection([Unit::query()->findOrFail($unit->id)]);
        }

        $now = Carbon::now();

        return [$units, $selected, (int) ($validated['month'] ?? $now->month), (int) ($validated['year'] ?? $now->year), isset($validated['tagihan']) ? (float) $validated['tagihan'] : null, $all];
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int, 4: float|null}
     */
    private function target(Request $request, ?string $divisi): array
    {
        $user = $request->user();
        abort_unless($divisi === null ? SlaReport::allowsCombined($user) : SlaReport::allows($user, $divisi), 403);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'tagihan' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
        ]);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'service_unit_id', 'status']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = $validated['unit_id'] ?? null;
        $unit = $unitId === null ? $units->first() : $units->firstWhere('id', (int) $unitId);
        abort_if($unit === null, 403);

        $now = Carbon::now();
        $tagihan = isset($validated['tagihan']) ? (float) $validated['tagihan'] : null;

        return [$units, Unit::query()->findOrFail($unit->id), (int) ($validated['month'] ?? $now->month), (int) ($validated['year'] ?? $now->year), $tagihan];
    }
}
