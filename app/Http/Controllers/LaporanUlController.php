<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Concerns\DefersReportDocument;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Models\ServiceUnit;
use App\Models\UlReportDocument;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Reports\OrientationPdfMerger;
use App\Services\Reports\UlReport;
use App\Services\Reports\UlReportDocumentBuilder;
use App\Services\Reports\UlReportWorkflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan UL (PRD.md §8.13): the hub of a service unit's reports
 * (Pengusahaan Pembangkit, Logistik, K3L, Administrasi Keuangan placeholder,
 * PPT bulanan) and each report as an editable document (Teks / Excel /
 * Pratinjau PDF, like the Laporan Project) built from every unit under the UL
 * ({@see UlReport}), with its approval: Admin UL mengajukan → TL per bidang
 * menyetujui → Manager UL mengesahkan ({@see UlReportWorkflow}). The Admin
 * UL (& Super Admin) open every report of their UL; a TL / the Manager UL
 * only the reports they approve.
 */
class LaporanUlController extends Controller
{
    use DefersReportDocument;
    use EmbedsReportLogo;

    private const FOOTER = 'PT PLN NUSANTARA POWER UP KENDARI - LAPORAN UNIT LAYANAN';

    public function __construct(
        private readonly UlReport $report,
        private readonly UlReportDocumentBuilder $builder,
        private readonly UlReportWorkflow $workflow,
        private readonly DocumentGridBuilder $grids,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        [$uls, $ul, $month, $year] = $this->target($request);

        return Inertia::render('laporan/ul/index', [
            ...$this->shared($uls, $ul, $month, $year),
            'reports' => collect(UlReport::KINDS)->map(fn (array $definition, string $kind): array => [
                'kind' => $kind,
                'title' => $definition[0],
                'parts' => array_values($definition[1]),
                'status' => UlReportWorkflow::statusLabel($this->workflow->status($this->workflow->find($ul, $kind, $month, $year))),
                'status_tone' => $this->workflow->status($this->workflow->find($ul, $kind, $month, $year))->tone(),
            ])->values()->all(),
        ]);
    }

    /**
     * The report as an editable document with its approval panel.
     */
    public function show(Request $request, string $kind): InertiaResponse
    {
        $user = $request->user();
        [$uls, $ul, $month, $year] = $this->target($request, $kind);
        $document = $this->workflow->find($ul, $kind, $month, $year);
        $record = $this->current($document);
        $workflow = $this->workflow->present($user, $ul, $kind, $month, $year, $document);

        return Inertia::render('laporan/ul/document', [
            ...$this->shared($uls, $ul, $month, $year),
            'kind' => $kind,
            'title' => UlReport::KINDS[$kind][0],
            ...$this->deferredDocument(function () use ($kind, $ul, $month, $year, $document, $record, $user): array {
                $data = $this->builder->build($kind, $ul, $month, $year, $document, $user);

                return [
                    'document_number' => $data['document_number'],
                    'content' => $record?->content_html !== null
                        ? $this->builder->withCurrentSignatures($record->content_html, $data['signatures'])
                        : $this->builder->bodyHtml($data),
                    'content_styles' => $this->builder->contentStyles(),
                    'letterhead' => $this->builder->letterhead($data),
                    'grid' => $record?->content_grid ?? $this->builder->grid($data),
                ];
            }),
            'format' => $record?->format ?? 'html',
            'has_saved' => $record !== null,
            'pdf_url' => route('laporan-ul.pdf', ['kind' => $kind, 'ul_id' => $ul->id, 'month' => $month, 'year' => $year]),
            'can_write' => UlReport::allows($user) && ! $user->isReadOnly() && $workflow['editable'],
            'workflow' => $workflow,
        ]);
    }

    public function pdf(Request $request, string $kind, OrientationPdfMerger $merger): Response
    {
        [, $ul, $month, $year] = $this->target($request, $kind);
        $document = $this->workflow->find($ul, $kind, $month, $year);
        $record = $this->current($document);
        $data = $this->builder->build($kind, $ul, $month, $year, $document, $request->user());
        $styles = $this->builder->contentStyles();

        if ($record !== null && $record->format === 'grid' && ! empty($record->content_grid)) {
            $body = $this->embedAssets($this->builder->letterhead($data).$this->grids->gridToHtml($record->content_grid));
            $pdf = $merger->render($styles, $body, [['show' => '', 'orientation' => 'landscape']], [], self::FOOTER);
        } else {
            $body = $this->embedAssets($record?->content_html !== null
                ? $this->builder->withCurrentSignatures($record->content_html, $data['signatures'])
                : $this->builder->bodyHtml($data));
            $pdf = $merger->renderSections($styles, $body, 'ul-section', 'ul-landscape', self::FOOTER, unnumberedPages: 1);
        }

        $filename = sprintf('%s_%s_%02d_%d.pdf', str_replace(' ', '_', UlReport::KINDS[$kind][0]), str_replace(' ', '_', $ul->name), $month, $year);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $user = $request->user();
        abort_unless(UlReport::allows($user) && ! $user->isReadOnly(), 403);
        [, $ul, $month, $year] = $this->target($request, $kind);

        $validated = $request->validate([
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $record = $this->editableRecord($ul, $kind, $month, $year, $user);
        $record->fill([
            'document_number' => UlReportDocumentBuilder::documentNumber($kind, $ul, $month, $year),
            'format' => $validated['format'],
            'content_version' => UlReportDocumentBuilder::BODY_VERSION,
            'updated_by' => $user->id,
        ]);
        if (($validated['content_html'] ?? null) !== null) {
            $record->content_html = $validated['content_html'];
        }
        if (($validated['content_grid'] ?? null) !== null) {
            $record->content_grid = $validated['content_grid'];
        }
        $record->save();

        $this->activityLogger->log(ActivityEvent::Updated, 'Menyimpan '.UlReport::KINDS[$kind][0]." {$ul->name} periode {$month}/{$year}", $record, serviceUnit: $ul);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan UL disimpan.']);

        return back();
    }

    /**
     * Rebuild the saved document from the latest data & template, discarding manual edits.
     */
    public function regenerate(Request $request, string $kind): RedirectResponse
    {
        $user = $request->user();
        abort_unless(UlReport::allows($user) && ! $user->isReadOnly(), 403);
        [, $ul, $month, $year] = $this->target($request, $kind);

        $record = $this->editableRecord($ul, $kind, $month, $year, $user);
        $this->fillFromTemplate($record, $kind, $ul, $month, $year, $user);
        $record->save();

        $this->activityLogger->log(ActivityEvent::Updated, 'Memuat ulang '.UlReport::KINDS[$kind][0]." {$ul->name} periode {$month}/{$year}", $record, serviceUnit: $ul);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    /** Ajukan (Admin UL): the saved document — or the template when none is saved — goes to the TL. */
    public function submit(Request $request, string $kind): RedirectResponse
    {
        $user = $request->user();
        [, $ul, $month, $year] = $this->target($request, $kind);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;

        $record = $this->editableRecord($ul, $kind, $month, $year, $user);
        if (! $record->exists || $record->content_version !== UlReportDocumentBuilder::BODY_VERSION) {
            $this->fillFromTemplate($record, $kind, $ul, $month, $year, $user);
            $record->save();
        }

        $this->workflow->submit($user, $ul, $kind, $month, $year, $record->load('steps'), $note);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan UL diajukan ke Team Leader.']);

        return back();
    }

    public function approve(Request $request, string $kind): RedirectResponse
    {
        return $this->act($request, $kind, 'approve', 'Laporan UL disetujui.');
    }

    public function ratify(Request $request, string $kind): RedirectResponse
    {
        return $this->act($request, $kind, 'ratify', 'Laporan UL disahkan — status FINAL.');
    }

    public function reject(Request $request, string $kind): RedirectResponse
    {
        return $this->act($request, $kind, 'reject', 'Laporan UL dikembalikan untuk diperbaiki.');
    }

    /**
     * The three reports of the month for the PPT bulanan (made in the browser).
     */
    public function pptData(Request $request): JsonResponse
    {
        [, $ul, $month, $year] = $this->target($request);

        return response()->json([
            'reports' => array_map(fn (string $kind): array => $this->report->build($kind, $ul, $month, $year), array_keys(UlReport::KINDS)),
        ]);
    }

    private function act(Request $request, string $kind, string $action, string $message): RedirectResponse
    {
        $user = $request->user();
        [, $ul, $month, $year] = $this->target($request, $kind);
        $document = $this->workflow->find($ul, $kind, $month, $year);
        abort_if($document === null, 404);

        if ($action === 'reject') {
            $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Alasan penolakan wajib diisi.'])['reason'];
            $this->workflow->reject($user, $document, $reason);
        } else {
            $note = $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null;
            $this->workflow->{$action}($user, $document, $note);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * The document of the month that may still be edited (draft or ditolak).
     */
    private function editableRecord(ServiceUnit $ul, string $kind, int $month, int $year, User $user): UlReportDocument
    {
        $record = UlReportDocument::query()->firstOrNew(['service_unit_id' => $ul->id, 'kind' => $kind, 'month' => $month, 'year' => $year]);
        abort_unless($this->workflow->status($record->exists ? $record : null)->isEditable(), 422, 'Laporan sedang dalam proses persetujuan atau sudah final, tidak dapat diubah.');
        if (! $record->exists) {
            $record->created_by = $user->id;
        }

        return $record;
    }

    private function fillFromTemplate(UlReportDocument $record, string $kind, ServiceUnit $ul, int $month, int $year, User $user): void
    {
        $data = $this->builder->build($kind, $ul, $month, $year, $record->exists ? $record->load('steps') : null, $user);
        $record->fill([
            'document_number' => $data['document_number'],
            'format' => 'html',
            'content_version' => UlReportDocumentBuilder::BODY_VERSION,
            'content_html' => $this->builder->bodyHtml($data),
            'content_grid' => $this->builder->grid($data),
            'updated_by' => $user->id,
        ]);
    }

    /**
     * The saved document only when built on the current template version.
     */
    private function current(?UlReportDocument $document): ?UlReportDocument
    {
        return $document !== null && ($document->content_html !== null || $document->content_grid !== null)
            && $document->content_version === UlReportDocumentBuilder::BODY_VERSION ? $document : null;
    }

    /**
     * @param  Collection<int, ServiceUnit>  $uls
     * @return array<string, mixed>
     */
    private function shared(Collection $uls, ServiceUnit $ul, int $month, int $year): array
    {
        $now = Carbon::now();

        return [
            'ul' => ['id' => $ul->id, 'name' => $ul->name],
            'units' => $this->report->units($ul)->map(fn ($unit): array => ['id' => $unit->id, 'name' => $unit->name])->values()->all(),
            'filters' => ['ul_id' => $ul->id, 'month' => $month, 'year' => $year],
            'options' => [
                'uls' => $uls->map(fn (ServiceUnit $item): array => ['id' => $item->id, 'name' => $item->name])->values()->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
        ];
    }

    /**
     * The UL & period. The Admin UL / Super Admin open the ULs they can see;
     * with a report kind, a TL or the Manager UL also opens the one report
     * they approve (ul_id given).
     *
     * @return array{0: Collection<int, ServiceUnit>, 1: ServiceUnit, 2: int, 3: int}
     */
    private function target(Request $request, ?string $kind = null): array
    {
        $user = $request->user();
        $validated = $request->validate([
            'ul_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        // Default: the last full month (the report of a month is made at the start of the next).
        $previous = Carbon::now()->startOfMonth()->subMonth();
        $month = (int) ($validated['month'] ?? $previous->month);
        $year = (int) ($validated['year'] ?? $previous->year);
        $ulId = $validated['ul_id'] ?? null;

        if (UlReport::allows($user)) {
            $uls = ServiceUnit::query()->visibleTo($user)->whereHas('units')->orderBy('name')->get(['id', 'name', 'code']);
            abort_if($uls->isEmpty(), 403, 'Akun Anda belum terhubung ke Unit Layanan (UL) manapun.');
            $ul = $ulId === null ? $uls->first() : $uls->firstWhere('id', (int) $ulId);
            abort_if($ul === null, 403);

            return [$uls, $ul, $month, $year];
        }

        // An approver (TL per bidang / Manager UL) of this very report.
        abort_if($kind === null || $ulId === null, 403);
        $ul = ServiceUnit::query()->find((int) $ulId, ['id', 'name', 'code']);
        abort_if($ul === null, 403);
        abort_unless($this->workflow->isApprover($user, $ul, $kind, $this->workflow->find($ul, $kind, $month, $year)), 403);

        return [new Collection([$ul]), $ul, $month, $year];
    }
}
