<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Services\Reports\ModuleResume;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resume Bulanan per modul (Operasi, Pemeliharaan, K3, Logistik, PdM): a
 * preview page with the PDF and Excel downloads, beside the Laporan Project
 * and Pengusahaan on each module's Laporan page. Open to whoever may view
 * that module's laporan, for the units they can access.
 */
class ModuleResumeController extends Controller
{
    public function __construct(private readonly ModuleResume $resume) {}

    public function show(Request $request, string $module): InertiaResponse
    {
        [$units, $unit, $month, $year] = $this->target($request, $module);
        $now = Carbon::now();

        return Inertia::render('laporan/resume', [
            'resume' => $this->resume->build($module, $unit, $month, $year),
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => ['units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'modules' => collect(ModuleResume::MODULES)
                ->filter(fn (array $definition, string $key): bool => ModuleResume::allows($request->user(), $key))
                ->map(fn (array $definition, string $key): array => ['key' => $key, 'label' => $definition[0]])
                ->values()->all(),
        ]);
    }

    public function pdf(Request $request, string $module): Response
    {
        [, $unit, $month, $year] = $this->target($request, $module);
        $resume = $this->resume->build($module, $unit, $month, $year);
        $file = Str::slug("resume {$module} {$unit->name} {$month} {$year}").'.pdf';

        $pdf = Pdf::loadView('reports.resume-pdf', ['resume' => $resume, 'unitModel' => $unit, 'printedAt' => Carbon::now()])->setPaper('a4', 'landscape');

        return $request->boolean('download') ? $pdf->download($file) : $pdf->stream($file);
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request, string $module): array
    {
        $user = $request->user();
        abort_unless(ModuleResume::allows($user, $module), 403);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'service_unit_id', 'status']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = $validated['unit_id'] ?? null;
        $unit = $unitId === null ? $units->first() : $units->firstWhere('id', (int) $unitId);
        abort_if($unit === null, 403);

        $now = Carbon::now();

        return [$units, Unit::query()->findOrFail($unit->id), (int) ($validated['month'] ?? $now->month), (int) ($validated['year'] ?? $now->year)];
    }
}
