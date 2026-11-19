<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Concerns\DefersReportDocument;
use App\Models\SlaDocument;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Reports\SlaCatalog;
use App\Services\Reports\SlaCover;
use App\Services\Reports\SlaDocumentBuilder;
use App\Services\Reports\SlaReport;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
 *
 * Both open as an editable document (user, 2026-11-19 — {@see SlaDocumentBuilder}):
 * Teks / Excel / Pratinjau PDF, Simpan & Muat Ulang dari Data; a saved document
 * is what the PDF prints, otherwise the PDF is built from the inputs as before.
 */
class SlaController extends Controller
{
    use DefersReportDocument;

    public function __construct(
        private readonly SlaReport $sla,
        private readonly SlaDocumentBuilder $documents,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function show(Request $request, string $divisi): InertiaResponse
    {
        [$units, $unit, $month, $year, $tagihan] = $this->target($request, $divisi);
        $now = Carbon::now();
        $sla = $this->sla->build($divisi, $unit, $month, $year);

        return Inertia::render('laporan/sla', [
            'sla' => $sla,
            ...$this->documentProps($request->user(), $divisi, new Collection([$unit]), false, $month, $year, $tagihan,
                pdfUrl: route('sla.pdf', ['divisi' => $divisi, 'unit_id' => $unit->id, 'month' => $month, 'year' => $year, ...($tagihan === null ? [] : ['tagihan' => $tagihan])]),
                canWrite: SlaReport::allows($request->user(), $divisi)),
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
        $file = Str::slug("sla {$divisi} {$unit->name} {$month} {$year}").'.pdf';

        // The edited & saved document, when there is one.
        $document = $this->currentDocument($divisi, $unit->id, $month, $year);
        if ($document !== null) {
            $css = $this->documents->template($this->documents->entries($divisi, [$unit], $month, $year), $tagihan, false)['css'];

            return $this->pdfResponse($request, $this->documents->pdf($document, $css, $this->documentTitle($divisi), $unit->name, $month, $year, covers: false), $file);
        }

        $sla = $this->sla->build($divisi, $unit, $month, $year);

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
            ...$this->documentProps($request->user(), 'gabungan', $selected, $all, $month, $year, $tagihan,
                pdfUrl: route('sla.combined.pdf', ['unit_id' => $all ? 'all' : $selected->first()->id, 'month' => $month, 'year' => $year, ...($tagihan === null ? [] : ['tagihan' => $tagihan])]),
                canWrite: SlaReport::allowsCombined($request->user())),
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

        $file = Str::slug('laporan sla '.($all ? 'semua unit' : $selected->first()->name)." {$month} {$year}").'.pdf';

        // The edited & saved document, when there is one.
        $document = $this->currentDocument('gabungan', $all ? null : $selected->first()->id, $month, $year);
        if ($document !== null) {
            $css = $this->documents->template($this->documents->entries('gabungan', [$selected->first()], $month, $year), $tagihan, true)['css'];

            return $this->pdfResponse($request, $this->documents->pdf($document, $css, 'Laporan SLA Gabungan', $all ? 'Semua Unit' : $selected->first()->name, $month, $year, covers: true), $file);
        }

        $pdfs = [];
        foreach ($selected as $unit) {
            $pdfs[] = Pdf::loadView('reports.sla-cover', ['cover' => $cover->png($unit->name, Indonesian::monthName($month), $year)])->setPaper('a4', 'portrait')->output();
            $pdfs[] = Pdf::loadView('reports.sla-pdf', ['slas' => $this->combined($unit, $month, $year), 'tagihan' => $tagihan, 'recap' => true, 'printedAt' => Carbon::now()])->setPaper('a4', 'landscape')->output();
        }

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
     * Simpan the SLA document edited in the editor (Teks or Excel).
     */
    public function storeDocument(Request $request): RedirectResponse
    {
        [$scope, $unitId, $month, $year, $label] = $this->documentTarget($request);
        $validated = $request->validate([
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $document = $this->documentRecord($scope, $unitId, $month, $year, $request->user());
        $document->fill(['format' => $validated['format'], 'content_version' => SlaDocumentBuilder::BODY_VERSION, 'updated_by' => $request->user()->id]);
        if (($validated['content_html'] ?? null) !== null) {
            $document->content_html = $validated['content_html'];
        }
        if (($validated['content_grid'] ?? null) !== null) {
            $document->content_grid = $validated['content_grid'];
        }
        $document->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan dokumen {$this->documentTitle($scope)} {$label} periode {$month}/{$year}", $document);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen SLA disimpan.']);

        return back();
    }

    /**
     * Muat ulang dari data: the saved document rebuilt from the inputs, discarding manual edits.
     */
    public function regenerateDocument(Request $request): RedirectResponse
    {
        [$scope, $unitId, $month, $year, $label, $units, $tagihan] = $this->documentTarget($request);
        $template = $this->documents->template($this->documents->entries($scope, $units, $month, $year), $tagihan, $scope === 'gabungan');

        $document = $this->documentRecord($scope, $unitId, $month, $year, $request->user());
        $document->fill([
            'format' => 'html',
            'content_html' => $template['body'],
            'content_grid' => $this->documents->grid($this->documentTitle($scope), $label, Indonesian::monthName($month).' '.$year, $template['body']),
            'content_version' => SlaDocumentBuilder::BODY_VERSION,
            'updated_by' => $request->user()->id,
        ])->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Memuat ulang dokumen {$this->documentTitle($scope)} {$label} periode {$month}/{$year}", $document);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    /**
     * The document editor's props: the body (deferred — built from the inputs,
     * or the saved document), its format, save state, PDF and save URLs.
     *
     * @param  Collection<int, Unit>  $units
     * @return array<string, mixed>
     */
    private function documentProps(User $user, string $scope, Collection $units, bool $all, int $month, int $year, ?float $tagihan, string $pdfUrl, bool $canWrite): array
    {
        $unitId = $all ? null : $units->first()?->id;
        $document = $this->currentDocument($scope, $unitId, $month, $year);
        $title = $this->documentTitle($scope);
        $label = $all ? 'Semua Unit' : (string) $units->first()?->name;
        $period = Indonesian::monthName($month).' '.$year;

        return [
            ...$this->deferredDocument(function () use ($scope, $units, $month, $year, $tagihan, $document, $title, $label, $period, $unitId): array {
                $template = $this->documents->template($this->documents->entries($scope, $units, $month, $year), $tagihan, $scope === 'gabungan');

                return [
                    'document_number' => 'SLA/'.mb_strtoupper($scope).'/'.($unitId ?? 'ALL').'/'.sprintf('%02d', $month).'/'.$year,
                    'content' => $document?->content_html ?? $template['body'],
                    'content_styles' => $this->documents->contentStyles($template['css']),
                    'letterhead' => $this->documents->letterhead($title, $label, $period),
                    'grid' => $document?->content_grid ?? $this->documents->grid($title, $label, $period, $template['body']),
                ];
            }),
            'document' => [
                'format' => $document?->format ?? 'html',
                'has_saved' => $document !== null,
                'pdf_url' => $pdfUrl,
                'can_write' => $canWrite && ! $user->isReadOnly(),
                'save_url' => route('sla.document.store'),
                'regenerate_url' => route('sla.document.regenerate'),
                'save_extra' => ['scope' => $scope, 'unit_id' => $all ? 'all' : (int) $unitId, 'month' => $month, 'year' => $year, ...($tagihan === null ? [] : ['tagihan' => $tagihan])],
                'title' => $title,
                'unit_label' => $label,
            ],
        ];
    }

    /**
     * The scope (divisi or gabungan), unit (null = semua unit) and period being
     * saved — gated like the page itself, never for a read-only account.
     *
     * @return array{0: string, 1: int|null, 2: int, 3: int, 4: string, 5: Collection<int, Unit>, 6: float|null}
     */
    private function documentTarget(Request $request): array
    {
        abort_if($request->user()->isReadOnly(), 403);
        $scope = (string) $request->validate([
            'scope' => ['required', Rule::in(['gabungan', ...array_keys(SlaCatalog::DIVISI)])],
            'unit_id' => ['required'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ])['scope'];

        if ($scope === 'gabungan') {
            [, $selected, $month, $year, $tagihan, $all] = $this->combinedTarget($request);

            return [$scope, $all ? null : $selected->first()->id, $month, $year, $all ? 'Semua Unit' : $selected->first()->name, $selected, $tagihan];
        }

        [, $unit, $month, $year, $tagihan] = $this->target($request, $scope);

        return [$scope, $unit->id, $month, $year, $unit->name, new Collection([$unit]), $tagihan];
    }

    private function documentRecord(string $scope, ?int $unitId, int $month, int $year, User $user): SlaDocument
    {
        $document = SlaDocument::query()->where('scope', $scope)->where('unit_id', $unitId)->where('month', $month)->where('year', $year)->first()
            ?? new SlaDocument(['scope' => $scope, 'unit_id' => $unitId, 'month' => $month, 'year' => $year]);
        if (! $document->exists) {
            $document->created_by = $user->id;
        }

        return $document;
    }

    /**
     * The saved document, only when built on the current template version.
     */
    private function currentDocument(string $scope, ?int $unitId, int $month, int $year): ?SlaDocument
    {
        $document = SlaDocument::query()->where('scope', $scope)->where('unit_id', $unitId)->where('month', $month)->where('year', $year)->first();

        return $document !== null && $document->content_version === SlaDocumentBuilder::BODY_VERSION
            && ($document->content_html !== null || $document->content_grid !== null) ? $document : null;
    }

    private function documentTitle(string $scope): string
    {
        if ($scope === 'gabungan') {
            return 'Laporan SLA Gabungan';
        }

        [$number, $label] = SlaCatalog::DIVISI[$scope];

        return "SLA {$number} {$label}";
    }

    private function pdfResponse(Request $request, string $pdf, string $file): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$file.'"',
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
