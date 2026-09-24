<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesPdmInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\PdmJadwalMeta;
use App\Models\PdmRealisasiPrediktif;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\PdmInputKop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input PdM "Realisasi Pemeliharaan Prediktif Bulanan": per predictive
 * activity & machine the planned (RENCANA) and realised (REAL) days of the
 * month, durasi, target, realisasi and kinerja. Weekends & holidays are red.
 * Header (sentral, no. dokumen) in pdm_jadwal_meta; signatures are left out
 * until the verification flow is built.
 */
class RealisasiPrediktifController extends Controller
{
    use HandlesPdmInput;
    use RendersReportPdf;

    private const META_TYPE = 'realisasi-prediktif';

    /** @var list<string> */
    public const DEFAULT_URAIAN = ['VIBRASI', 'SISTEM DC'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$units, $unit, $month, $year] = $this->pdmReadTarget($request);

        return Inertia::render('pdm/input/realisasi-prediktif/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'kop_lines' => PdmInputKop::lines('realisasi-prediktif', $unit->name),
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->pdmFilterOptions($units),
            'days' => $this->days($month, $year),
            'rows' => $this->rows($unit, $month, $year),
            'meta' => $this->meta($unit, $month, $year),
            'has_saved' => PdmRealisasiPrediktif::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->exists(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::PdmInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$unit, $month, $year] = $this->pdmWriteTarget($request);

        $validated = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.uraian' => ['required', 'string', 'max:255'],
            'rows.*.mesin' => ['nullable', 'string', 'max:255'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.rencana.*' => ['integer', 'between:1,31'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.realisasi.*' => ['integer', 'between:1,31'],
            'rows.*.durasi' => ['nullable', 'numeric', 'min:0'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'meta.doc_number' => ['nullable', 'string', 'max:100'],
            'meta.revision' => ['nullable', 'string', 'max:20'],
            'meta.effective_date' => ['nullable', 'string', 'max:50'],
        ]);

        $days = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $clean = fn (?array $list): array => collect($list ?? [])->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 1 && $d <= $days)->unique()->sort()->values()->all();

        DB::transaction(function () use ($validated, $unit, $month, $year, $clean, $request): void {
            PdmRealisasiPrediktif::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

            foreach (array_values($validated['rows']) as $index => $row) {
                PdmRealisasiPrediktif::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $index + 1,
                    'uraian' => $row['uraian'],
                    'mesin' => $row['mesin'] ?? null,
                    'rencana' => $clean($row['rencana'] ?? []),
                    'realisasi' => $clean($row['realisasi'] ?? []),
                    'durasi' => $row['durasi'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $request->user()->id,
                ]);
            }

            $meta = $validated['meta'] ?? [];
            PdmJadwalMeta::query()->updateOrCreate(
                ['unit_id' => $unit->id, 'type' => self::META_TYPE, 'year' => $year, 'month' => $month],
                [
                    'doc_number' => $meta['doc_number'] ?? null,
                    'revision' => $meta['revision'] ?? '00',
                    'effective_date' => $meta['effective_date'] ?? null,
                ],
            );
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Realisasi Pemeliharaan Prediktif {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Realisasi Pemeliharaan Prediktif Bulanan berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $unit, $month, $year] = $this->pdmReadTarget($request);
        $filename = sprintf('Realisasi_Pemeliharaan_Prediktif_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year);

        return response($this->renderPdf($unit, $month, $year), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
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
        return ['pdm.input.realisasi-prediktif-pdf', [
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' '.$year,
            'days' => $this->days($month, $year),
            'rows' => $this->rows($unit, $month, $year),
            'meta' => $this->meta($unit, $month, $year),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * @return list<array{day: int, dow: string, is_red: bool}>
     */
    private function days(int $month, int $year): array
    {
        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date'])
            ->map(fn ($h): int => Carbon::parse($h->date)->day)->all();

        return collect(range(1, (int) Carbon::create($year, $month, 1)->daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);

            return [
                'day' => $day,
                'dow' => ['MG', 'SN', 'SL', 'RB', 'KM', 'JM', 'SB'][$date->dayOfWeek],
                'is_red' => $date->isWeekend() || in_array($day, $holidays, true),
            ];
        })->all();
    }

    /**
     * Saved rows, or the default activities, with target/realisasi/kinerja.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Unit $unit, int $month, int $year): array
    {
        $saved = PdmRealisasiPrediktif::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')->get();

        $rows = $saved->isEmpty()
            ? array_map(fn (string $uraian): array => ['id' => null, 'uraian' => $uraian, 'mesin' => '', 'rencana' => [], 'realisasi' => [], 'durasi' => null, 'keterangan' => ''], self::DEFAULT_URAIAN)
            : $saved->map(fn (PdmRealisasiPrediktif $r): array => [
                'id' => $r->id,
                'uraian' => $r->uraian,
                'mesin' => (string) ($r->mesin ?? ''),
                'rencana' => array_map('intval', $r->rencana ?? []),
                'realisasi' => array_map('intval', $r->realisasi ?? []),
                'durasi' => $r->durasi !== null ? (float) $r->durasi : null,
                'keterangan' => (string) ($r->keterangan ?? ''),
            ])->all();

        return array_map(fn (array $row): array => $row + [
            'target' => count($row['rencana']),
            'realisasi_count' => count($row['realisasi']),
            'kinerja' => count($row['rencana']) > 0 ? round(count($row['realisasi']) / count($row['rencana']) * 100).'%' : '-',
        ], $rows);
    }

    /**
     * @return array{sentral: string, doc_number: string, revision: string, effective_date: string}
     */
    private function meta(Unit $unit, int $month, int $year): array
    {
        $meta = PdmJadwalMeta::query()->where('unit_id', $unit->id)->where('type', self::META_TYPE)
            ->where('year', $year)->where('month', $month)->first();

        return [
            'sentral' => $unit->name,
            'doc_number' => (string) ($meta?->doc_number ?? 'SMT-FM-KIT-02.01'),
            'revision' => (string) ($meta?->revision ?? '01'),
            'effective_date' => (string) ($meta?->effective_date ?? '13 Oktober 2021'),
        ];
    }
}
