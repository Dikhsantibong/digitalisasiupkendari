<?php

namespace App\Http\Controllers\Keuangan;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\DefersReportDocument;
use App\Http\Controllers\Concerns\HandlesKeuangan;
use App\Http\Controllers\Controller;
use App\Models\CashCardReport;
use App\Models\KeuanganDocument;
use App\Models\ServiceUnit;
use App\Services\ActivityLogger;
use App\Services\Keuangan\KeuanganDocumentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Laporan Administrasi Keuangan of a UL as an editable document (user,
 * 2026-11-20) — opened from the Laporan UL like the other reports: Teks /
 * Excel / Pratinjau PDF, Simpan & Muat Ulang dari Data. Page one is the
 * Laporan Realisasi Cash Card table, then per row the Bukti Penerimaan Kas
 * Kecil and its Lampiran ({@see KeuanganDocumentBuilder}).
 */
class DocumentController extends Controller
{
    use DefersReportDocument;
    use HandlesKeuangan;

    public function __construct(
        private readonly KeuanganDocumentBuilder $builder,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function show(Request $request): Response
    {
        [$uls, $ul, $month, $year] = $this->keuanganTarget($request);
        $document = $this->current($ul, $month, $year);
        $user = $request->user();

        return Inertia::render('keuangan/dokumen', [
            'ul' => ['id' => $ul->id, 'name' => $ul->name],
            'filters' => ['ul_id' => $ul->id, 'month' => $month, 'year' => $year],
            'options' => $this->keuanganOptions($uls),
            ...$this->deferredDocument(function () use ($ul, $month, $year, $document): array {
                $template = $this->builder->template($this->report($ul, $month, $year));

                return [
                    'document_number' => 'KEU/'.Str::upper(Str::slug($ul->code ?? $ul->name)).'/'.sprintf('%02d', $month).'/'.$year,
                    'content' => $document?->content_html ?? $template['body'],
                    'content_styles' => $this->builder->contentStyles($template['css']),
                    'letterhead' => $this->builder->letterhead($ul->name, $month, $year),
                    'grid' => $document?->content_grid ?? $this->builder->grid($ul->name, $month, $year, $template['laporan']),
                ];
            }),
            'format' => $document?->format ?? 'html',
            'has_saved' => $document !== null,
            'pdf_url' => route('keuangan.document.pdf', ['ul_id' => $ul->id, 'month' => $month, 'year' => $year]),
            'can_write' => $user->hasPermissionTo(PermissionName::KeuanganWrite) && ! $user->isReadOnly(),
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $ul, $month, $year] = $this->keuanganTarget($request);
        $pdf = $this->builder->pdf($this->current($ul, $month, $year), $this->builder->template($this->report($ul, $month, $year)), $ul->name, $month, $year);
        $file = Str::slug("laporan administrasi keuangan {$ul->name} {$month} {$year}").'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$file.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$ul, $month, $year] = $this->writable($request);
        $validated = $request->validate([
            'format' => ['required', Rule::in(['html', 'grid'])],
            'content_html' => ['required_if:format,html', 'nullable', 'string'],
            'content_grid' => ['required_if:format,grid', 'nullable', 'array'],
        ]);

        $document = $this->record($ul, $month, $year, $request->user()->id);
        $document->fill(['format' => $validated['format'], 'content_version' => KeuanganDocumentBuilder::BODY_VERSION, 'updated_by' => $request->user()->id]);
        if (($validated['content_html'] ?? null) !== null) {
            $document->content_html = $validated['content_html'];
        }
        if (($validated['content_grid'] ?? null) !== null) {
            $document->content_grid = $validated['content_grid'];
        }
        $document->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Laporan Administrasi Keuangan {$ul->name} {$month}/{$year}", $document);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Administrasi Keuangan disimpan.']);

        return back();
    }

    /**
     * Rebuild the saved document from the latest cash card data, discarding manual edits.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        [$ul, $month, $year] = $this->writable($request);
        $template = $this->builder->template($this->report($ul, $month, $year));

        $document = $this->record($ul, $month, $year, $request->user()->id);
        $document->fill([
            'format' => 'html',
            'content_html' => $template['body'],
            'content_grid' => $this->builder->grid($ul->name, $month, $year, $template['laporan']),
            'content_version' => KeuanganDocumentBuilder::BODY_VERSION,
            'updated_by' => $request->user()->id,
        ])->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Memuat ulang Laporan Administrasi Keuangan {$ul->name} {$month}/{$year}", $document);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen dimuat ulang dari data terbaru.']);

        return back();
    }

    /**
     * @return array{0: ServiceUnit, 1: int, 2: int}
     */
    private function writable(Request $request): array
    {
        abort_if($request->user()->isReadOnly(), 403);
        $request->validate([
            'ul_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);
        [, $ul, $month, $year] = $this->keuanganTarget($request, PermissionName::KeuanganWrite);

        return [$ul, $month, $year];
    }

    /**
     * The month's cash card (an empty one when nothing is saved yet).
     */
    private function report(ServiceUnit $ul, int $month, int $year): CashCardReport
    {
        $report = CashCardReport::query()->where('service_unit_id', $ul->id)->where('month', $month)->where('year', $year)->first()
            ?? new CashCardReport(['service_unit_id' => $ul->id, 'month' => $month, 'year' => $year, 'saldo_awal' => 0]);

        return $report->setRelation('serviceUnit', $ul);
    }

    private function record(ServiceUnit $ul, int $month, int $year, int $userId): KeuanganDocument
    {
        $document = KeuanganDocument::query()->firstOrNew(['service_unit_id' => $ul->id, 'month' => $month, 'year' => $year]);
        if (! $document->exists) {
            $document->created_by = $userId;
        }

        return $document;
    }

    /**
     * The saved document, only when built on the current template version.
     */
    private function current(ServiceUnit $ul, int $month, int $year): ?KeuanganDocument
    {
        $document = KeuanganDocument::query()->where('service_unit_id', $ul->id)->where('month', $month)->where('year', $year)->first();

        return $document !== null && $document->content_version === KeuanganDocumentBuilder::BODY_VERSION
            && ($document->content_html !== null || $document->content_grid !== null) ? $document : null;
    }
}
