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
 * The Laporan SLA gabungan (cover + every divisi + rekapitulasi, one file) is
 * for accounts that may open every divisi (Super Admin).
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
     * Laporan SLA gabungan: the cover (portrait) then every divisi's SLA one
     * under another and the Rekapitulasi (landscape), in one PDF.
     */
    public function combinedPdf(Request $request, SlaCover $cover): Response
    {
        [, $unit, $month, $year, $tagihan] = $this->target($request, null);
        $slas = $this->combined($unit, $month, $year);

        $coverPdf = Pdf::loadView('reports.sla-cover', ['cover' => $cover->png($unit->name, Indonesian::monthName($month), $year)])->setPaper('a4', 'portrait')->output();
        $bodyPdf = Pdf::loadView('reports.sla-pdf', ['slas' => $slas, 'tagihan' => $tagihan, 'recap' => true, 'printedAt' => Carbon::now()])->setPaper('a4', 'landscape')->output();

        $file = Str::slug("laporan sla {$unit->name} {$month} {$year}").'.pdf';

        return response($this->merge([$coverPdf, $bodyPdf]), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$file.'"',
        ]);
    }

    /**
     * Payload of the Laporan SLA gabungan's Excel (built in the browser): the
     * cover image and every divisi's SLA.
     */
    public function combinedData(Request $request, SlaCover $cover): JsonResponse
    {
        [, $unit, $month, $year] = $this->target($request, null);

        return response()->json([
            'cover' => $cover->png($unit->name, Indonesian::monthName($month), $year),
            'slas' => $this->combined($unit, $month, $year),
        ]);
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
