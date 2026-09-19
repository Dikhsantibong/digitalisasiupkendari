<?php

namespace App\Http\Controllers\Pdm;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesPdmInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\PdmPermitToWork;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input PdM "Laporan Permit to Work (PTW) Pembangkit": numbered permits per
 * unit & month (uraian, tanggal, status Open/Close) with the Open/Close totals.
 * The form shows at least 30 numbered rows, like the paper form.
 */
class PermitToWorkController extends Controller
{
    use HandlesPdmInput;
    use RendersReportPdf;

    public const MIN_ROWS = 30;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$units, $unit, $month, $year] = $this->pdmReadTarget($request);
        $rows = $this->rows($unit, $month, $year);

        return Inertia::render('pdm/input/permit-to-work/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->pdmFilterOptions($units),
            'rows' => $rows,
            'has_saved' => collect($rows)->contains(fn (array $row): bool => $row['id'] !== null),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::PdmInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$unit, $month, $year] = $this->pdmWriteTarget($request);

        $validated = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.uraian' => ['nullable', 'string', 'max:255'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.status' => ['required', 'string', 'in:open,close'],
        ]);

        DB::transaction(function () use ($validated, $unit, $month, $year, $request): void {
            PdmPermitToWork::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

            $number = 0;
            foreach ($validated['rows'] as $row) {
                if (trim((string) ($row['uraian'] ?? '')) === '' && empty($row['tanggal'])) {
                    continue;
                }

                PdmPermitToWork::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => ++$number,
                    'uraian' => $row['uraian'] ?? null,
                    'tanggal' => $row['tanggal'] ?? null,
                    'status' => $row['status'],
                    'input_by' => $request->user()->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Laporan PTW PdM {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan Permit to Work PdM berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $unit, $month, $year] = $this->pdmReadTarget($request);

        return response($this->renderPdf($unit, $month, $year), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.sprintf('Laporan_PTW_PdM_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year).'"',
        ]);
    }

    /**
     * The PDF bytes (portrait).
     */
    private function renderPdf(Unit $unit, int $month, int $year): string
    {
        [$view, $data] = $this->pdfView($unit, $month, $year);

        return $this->renderReportPdf($view, $data, 'portrait');
    }

    /**
     * The PDF view and its data — shared by the PDF and the editable Laporan PdM document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $rows = $this->rows($unit, $month, $year);

        return ['pdm.input.permit-to-work-pdf', [
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' '.$year,
            'rows' => $rows,
            'totalOpen' => collect($rows)->where('id', '!==', null)->where('status', 'open')->count(),
            'totalClose' => collect($rows)->where('id', '!==', null)->where('status', 'close')->count(),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Saved permits followed by blank numbered rows up to {@see self::MIN_ROWS}.
     *
     * @return list<array{id: int|null, no_urut: int, uraian: string, tanggal: string|null, status: string}>
     */
    private function rows(Unit $unit, int $month, int $year): array
    {
        $rows = PdmPermitToWork::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('no_urut')->get()
            ->values()
            ->map(fn (PdmPermitToWork $p, int $i): array => [
                'id' => $p->id,
                'no_urut' => $i + 1,
                'uraian' => (string) ($p->uraian ?? ''),
                'tanggal' => $p->tanggal?->format('Y-m-d'),
                'status' => $p->status,
            ])->all();

        for ($n = count($rows) + 1; $n <= self::MIN_ROWS; $n++) {
            $rows[] = ['id' => null, 'no_urut' => $n, 'uraian' => '', 'tanggal' => null, 'status' => 'open'];
        }

        return $rows;
    }
}
