<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Operasi\DocumentGridBuilder;
use App\Services\Operasi\Reports\OperasiReport;
use App\Services\Operasi\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Laporan (modul OPERASI): one click from a filter to a print-ready
 * preview. Reports come from the {@see ReportRegistry}; this controller renders
 * any of them without per-report code.
 */
class LaporanController extends Controller
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly DocumentGridBuilder $gridBuilder,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $now = Carbon::now();

        return Inertia::render('operasi/laporan/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'engine_id' => (int) $request->integer('engine_id') ?: $machines->first()?->id,
                'month' => (int) ($request->integer('month') ?: $now->month),
                'year' => (int) ($request->integer('year') ?: $now->year),
            ],
            'reports' => $this->registry->all()
                ->map(fn (OperasiReport $report): array => [
                    'code' => $report->code(),
                    'title' => $report->title(),
                    'description' => $report->description(),
                    'requires_engine' => $report->requiresEngine(),
                ])
                ->values()
                ->all(),
            'options' => [
                'units' => $units->all(),
                'machines' => $machines->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
        ]);
    }

    public function show(Request $request, string $report): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $definition = $this->registry->find($report);
        abort_if($definition === null, 404);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $engine = null;
        if ($definition->requiresEngine()) {
            $engine = Machine::query()
                ->where('unit_id', $unit->id)
                ->findOrFail($request->integer('engine_id'));
        }

        $month = (int) $request->integer('month');
        $year = (int) $request->integer('year');

        return Inertia::render($definition->page(), [
            'report' => [
                'code' => $definition->code(),
                'title' => $definition->title(),
            ],
            'data' => $definition->build($unit, $month, $year, $engine),
        ]);
    }

    /**
     * The report as an editable spreadsheet (Excel mode). Ephemeral: it is not
     * persisted; the user downloads .xlsx or prints the PDF from the print view.
     */
    public function spreadsheet(Request $request, string $report): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $definition = $this->registry->find($report);
        abort_if($definition === null || ! $definition->requiresEngine(), 404);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $engine = Machine::query()
            ->where('unit_id', $unit->id)
            ->findOrFail($request->integer('engine_id'));

        $month = (int) $request->integer('month');
        $year = (int) $request->integer('year');

        $data = $definition->build($unit, $month, $year, $engine);

        return Inertia::render('operasi/laporan/spreadsheet', [
            'report' => ['code' => $definition->code(), 'title' => $definition->title()],
            'filters' => ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'month' => $month, 'year' => $year],
            'grid' => $this->gridBuilder->forMonthlyReport($data),
            'print_url' => route('operasi.laporan.show', [
                'report' => $definition->code(),
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => $month,
                'year' => $year,
            ]),
        ]);
    }
}
