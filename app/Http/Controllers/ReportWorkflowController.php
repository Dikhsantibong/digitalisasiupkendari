<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Enums\ReportModule;
use App\Models\ReportWorkflow;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\Reports\ReportWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The actions of the Laporan Pembangkit workflow for every report module:
 * Ajukan (pembuat), Verifikasi (Koordinator divisi), Setujui (Team Leader
 * sesuai modul), Sahkan (Manager UL, last) and Tolak (whoever's turn it is).
 * Each action is authorised by {@see ReportWorkflowService} (write
 * permission + unit scope to ajukan; the signer's own linked employee, in
 * order, for every other step), whatever buttons the page shows.
 */
class ReportWorkflowController extends Controller
{
    public function __construct(
        private readonly ReportWorkflowService $workflows,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function submit(Request $request, ReportModule $module): RedirectResponse
    {
        [$unit, $month, $year] = $this->target($request);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $workflow = $this->workflows->submit($request->user(), $module, $unit, $month, $year, $validated['note'] ?? null);

        return $this->done($workflow, "Mengajukan {$module->label()} {$unit->name} periode {$month}/{$year}", 'Laporan diajukan untuk diverifikasi Koordinator.');
    }

    public function verify(Request $request, ReportModule $module): RedirectResponse
    {
        $workflow = $this->workflow($request, $module);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $workflow = $this->workflows->verify($request->user(), $workflow, $validated['note'] ?? null);

        return $this->done($workflow, "Memverifikasi {$module->label()} {$workflow->unit->name} periode {$workflow->month}/{$workflow->year}", "Laporan diverifikasi dan diteruskan ke {$module->teamLeaderPosition()->value} untuk disetujui.");
    }

    public function reject(Request $request, ReportModule $module): RedirectResponse
    {
        $workflow = $this->workflow($request, $module);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], [], ['reason' => 'alasan penolakan']);

        $workflow = $this->workflows->reject($request->user(), $workflow, $validated['reason']);

        return $this->done($workflow, "Menolak {$module->label()} {$workflow->unit->name} periode {$workflow->month}/{$workflow->year}", 'Laporan ditolak dan dikembalikan untuk perbaikan.');
    }

    public function approve(Request $request, ReportModule $module): RedirectResponse
    {
        $workflow = $this->workflow($request, $module);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $workflow = $this->workflows->approve($request->user(), $workflow, $validated['note'] ?? null);

        return $this->done($workflow, "Menyetujui {$module->label()} {$workflow->unit->name} periode {$workflow->month}/{$workflow->year}", 'Laporan disetujui dan diteruskan ke Manager UL untuk pengesahan.');
    }

    public function ratify(Request $request, ReportModule $module): RedirectResponse
    {
        $workflow = $this->workflow($request, $module);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $workflow = $this->workflows->ratify($request->user(), $workflow, $validated['note'] ?? null);

        return $this->done($workflow, "Mengesahkan {$module->label()} {$workflow->unit->name} periode {$workflow->month}/{$workflow->year}", 'Laporan disahkan dan telah FINAL.');
    }

    /**
     * @return array{0: Unit, 1: int, 2: int}
     */
    private function target(Request $request): array
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        return [Unit::query()->findOrFail($validated['unit_id']), (int) $validated['month'], (int) $validated['year']];
    }

    private function workflow(Request $request, ReportModule $module): ReportWorkflow
    {
        [$unit, $month, $year] = $this->target($request);
        $workflow = $this->workflows->find($module, $unit->id, $month, $year);
        abort_if($workflow === null, 404, 'Laporan belum diajukan.');

        return $workflow;
    }

    private function done(ReportWorkflow $workflow, string $description, string $message): RedirectResponse
    {
        $this->activityLogger->log(ActivityEvent::Updated, $description, $workflow, unit: $workflow->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
