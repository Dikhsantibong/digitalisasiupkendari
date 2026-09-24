<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesPdmInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\PdmJadwalMeta;
use App\Models\PdmKesiapanApd;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\PdmInputKop;
use App\Support\PdmKesiapanApdForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input PdM "Kesiapan APD Bagian PdM Pembangkit": the inspected APD items per
 * unit & month with their assessment, plus the catatan kept in pdm_jadwal_meta.
 * Signatures are left out until the verification flow is built.
 */
class KesiapanApdController extends Controller
{
    use HandlesPdmInput;
    use RendersReportPdf;

    private const META_TYPE = 'kesiapan-apd';

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$units, $unit, $month, $year] = $this->pdmReadTarget($request);

        return Inertia::render('pdm/input/kesiapan-apd/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'kop_lines' => PdmInputKop::lines('kesiapan-apd', $unit->name),
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->pdmFilterOptions($units) + [
                'answers' => PdmKesiapanApdForm::OPTIONS,
            ],
            'rows' => $this->rows($unit, $month, $year),
            'meta' => $this->meta($unit, $month, $year),
            'has_saved' => PdmKesiapanApd::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->exists(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::PdmInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$unit, $month, $year] = $this->pdmWriteTarget($request);

        $answerRules = [];
        foreach (PdmKesiapanApdForm::OPTIONS as $field => $options) {
            $answerRules["rows.*.{$field}"] = ['nullable', Rule::in($options)];
        }

        $validated = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.kelompok' => ['nullable', 'string', 'max:255'],
            'rows.*.inspeksi' => ['required', 'string', 'max:255'],
            'rows.*.jumlah' => ['nullable', 'integer', 'min:0'],
            'rows.*.satuan' => ['nullable', 'string', 'max:30'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            ...$answerRules,
            'meta' => ['nullable', 'array'],
            'meta.catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($validated, $unit, $month, $year, $request): void {
            PdmKesiapanApd::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

            foreach (array_values($validated['rows']) as $index => $row) {
                PdmKesiapanApd::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'kelompok' => trim((string) ($row['kelompok'] ?? '')) ?: PdmKesiapanApdForm::DEFAULT_KELOMPOK,
                    'no_urut' => $index + 1,
                    'inspeksi' => $row['inspeksi'],
                    'jumlah' => $row['jumlah'] ?? null,
                    'satuan' => $row['satuan'] ?? null,
                    ...collect(array_keys(PdmKesiapanApdForm::OPTIONS))->mapWithKeys(fn (string $field): array => [$field => $row[$field] ?? null])->all(),
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $request->user()->id,
                ]);
            }

            $meta = $validated['meta'] ?? [];
            PdmJadwalMeta::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'type' => self::META_TYPE, 'year' => $year, 'month' => $month],
                ['catatan' => $meta['catatan'] ?? null],
            );
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Kesiapan APD PdM {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kesiapan APD Bagian PdM berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $unit, $month, $year] = $this->pdmReadTarget($request);

        return response($this->renderPdf($unit, $month, $year), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.sprintf('Kesiapan_APD_PdM_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year).'"',
        ]);
    }

    /**
     * The PDF bytes (landscape).
     */
    private function renderPdf(Unit $unit, int $month, int $year): string
    {
        [$view, $data] = $this->pdfView($unit, $month, $year);

        return $this->renderReportPdf($view, $data, 'landscape');
    }

    /**
     * The PDF view and its data — shared by the PDF and the editable Laporan PdM document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        return ['pdm.input.kesiapan-apd-pdf', [
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' '.$year,
            'groups' => collect($this->rows($unit, $month, $year))->groupBy('kelompok')->all(),
            'meta' => $this->meta($unit, $month, $year),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Saved rows, or the default APD list the form starts with.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Unit $unit, int $month, int $year): array
    {
        $saved = PdmKesiapanApd::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        if ($saved->isEmpty()) {
            return PdmKesiapanApdForm::defaultRows();
        }

        return $saved->map(fn (PdmKesiapanApd $r): array => [
            'id' => $r->id,
            'kelompok' => $r->kelompok,
            'no_urut' => $r->no_urut,
            'inspeksi' => $r->inspeksi,
            'jumlah' => $r->jumlah,
            'satuan' => $r->satuan,
            ...collect(array_keys(PdmKesiapanApdForm::OPTIONS))->mapWithKeys(fn (string $field): array => [$field => $r->{$field}])->all(),
            'keterangan' => (string) ($r->keterangan ?? ''),
        ])->all();
    }

    /**
     * The saved catatan (empty when nothing is saved yet).
     *
     * @return array{catatan: string}
     */
    private function meta(Unit $unit, int $month, int $year): array
    {
        $meta = PdmJadwalMeta::query()->where('unit_id', $unit->id)->where('type', self::META_TYPE)
            ->where('year', $year)->where('month', $month)->first();

        return ['catatan' => (string) ($meta?->catatan ?? '')];
    }
}
