<?php

namespace App\Http\Controllers\K3;

use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\K3\K3InputTables;
use App\Services\K3\K3ReportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * PDF (always A4 landscape) and Excel exports of every K3 input page. Both are
 * built from {@see K3InputTables}, the same tables the Laporan K3 uses: the PDF
 * is rendered from resources/views/k3/input/{input}-pdf.blade.php, the Excel
 * workbook is assembled in the browser (ExcelJS, resources/js/lib/k3-input-excel.ts)
 * from the JSON returned by {@see self::data()} and downloaded as
 * K3_{input}_{unit}_{month}_{year}.xlsx.
 */
class InputExportController extends Controller
{
    use EmbedsReportLogo;
    use RendersReportPdf;

    public function __construct(
        private readonly K3InputTables $tables,
        private readonly K3ReportBuilder $reports,
    ) {}

    /**
     * Streams resources/views/k3/input/{input}-pdf.blade.php (A4 landscape).
     * Air Limbah keeps its own official PDF (AirLimbahController::pdf).
     */
    public function pdf(Request $request, string $input): Response|RedirectResponse
    {
        [$unit, $month, $year] = $this->resolveTarget($request, $input);

        if ($input === 'air-limbah') {
            return redirect()->route('k3.input.air-limbah.pdf', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]);
        }

        $tables = $input === 'attachments'
            ? [$this->tables->attachmentsWithImages($unit, $month, $year)]
            : $this->tables->tables($input, $unit, $month, $year, $this->options($request));

        $html = view("k3.input.{$input}-pdf", [
            'tables' => $tables,
            'unitHeaderName' => $this->reports->resolveUnitHeaderName($unit),
        ])->render();

        return $this->streamReportPdf(
            $request,
            'k3.input.export-raw',
            ['html' => $this->embedAssets($html)],
            $this->filename($input, $unit, $month, $year).'.pdf',
            'landscape',
        );
    }

    public function data(Request $request, string $input): JsonResponse
    {
        [$unit, $month, $year] = $this->resolveTarget($request, $input);

        return response()->json([
            'filename' => $this->filename($input, $unit, $month, $year).'.xlsx',
            'unit_header' => $this->reports->resolveUnitHeaderName($unit),
            'tables' => $this->tables->tables($input, $unit, $month, $year, $this->options($request)),
        ]);
    }

    /**
     * @return array{0: Unit, 1: int, 2: int}
     */
    private function resolveTarget(Request $request, string $input): array
    {
        abort_unless(K3InputTables::exists($input), 404);

        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) || $user->hasPermissionTo(PermissionName::K3LaporanView),
            403,
        );

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();

        return [
            $unit,
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            (int) ($request->integer('year') ?: $now->year),
        ];
    }

    /**
     * @return array{form_code: string|null, week: int|null}
     */
    private function options(Request $request): array
    {
        $week = $request->query('week');

        return [
            'form_code' => $request->filled('form_code') ? (string) $request->query('form_code') : null,
            'week' => $week === null || $week === '' || $week === 'bulanan' ? null : (int) $week,
        ];
    }

    private function filename(string $input, Unit $unit, int $month, int $year): string
    {
        $unitName = str_replace(' ', '_', $unit->name);

        return $input === 'certificates'
            ? "K3_{$input}_{$unitName}"
            : "K3_{$input}_{$unitName}_{$month}_{$year}";
    }
}
