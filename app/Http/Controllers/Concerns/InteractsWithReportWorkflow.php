<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Models\Unit;
use App\Services\Reports\ReportWorkflowService;
use Illuminate\Http\Request;

/**
 * Shared by the Laporan Pembangkit document controllers: who may open the
 * document, the lock while the report is in verification / pengesahan, the
 * current signature blocks inside a saved document and the workflow props of
 * the document page.
 */
trait InteractsWithReportWorkflow
{
    protected function reportWorkflows(): ReportWorkflowService
    {
        return app(ReportWorkflowService::class);
    }

    /**
     * The report may be read with one of the module's view permissions, or by
     * one of its signers — who must be able to read what they sign.
     */
    protected function authorizeReportView(Request $request, ReportModule $module, PermissionName ...$permissions): void
    {
        $user = $request->user();
        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return;
            }
        }

        abort_unless(
            $request->filled('unit_id') && $this->reportWorkflows()->isSigner($user, $module, $request->integer('unit_id'), $request->integer('month'), $request->integer('year')),
            403,
        );
    }

    /**
     * A report in verification, pengesahan or FINAL is locked; it opens again
     * only when it is rejected (DITOLAK).
     */
    protected function ensureReportEditable(ReportModule $module, Unit $unit, int $month, int $year): void
    {
        abort_unless(
            $this->reportWorkflows()->isEditable($module, $unit->id, $month, $year),
            403,
            'Laporan sedang dalam proses verifikasi/pengesahan atau sudah final sehingga tidak dapat diubah.',
        );
    }

    /**
     * The saved document body with its signature blocks replaced by the
     * current ones (signers, and signatures once FINAL).
     */
    protected function withCurrentSignatures(string $html, ReportModule $module, Unit $unit, int $month, int $year): string
    {
        $html = (string) preg_replace('#src=(["\'])(\.\./)+logo/#i', 'src=$1/logo/', $html);

        return $this->reportWorkflows()->refreshSignatureBlocks($html, $module, $unit, $month, $year);
    }
}
