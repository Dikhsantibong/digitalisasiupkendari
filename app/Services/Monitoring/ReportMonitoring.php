<?php

namespace App\Services\Monitoring;

use App\Enums\EmployeePosition;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowLog;
use App\Models\ReportWorkflowStep;
use App\Models\Unit;
use App\Services\Reports\ReportSignatories;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The verification & approval picture of the Laporan Pembangkit (the report
 * workflow): every unit × module status of a month, reports waiting too long
 * at one step (whoever's turn it is), recent rejections, average time per
 * step, and jabatan without an active holder (a report cannot be diajukan
 * until each signer exists in Master Pegawai).
 */
class ReportMonitoring
{
    /** A report waiting this many days at one step is "macet". */
    public const STUCK_AFTER_DAYS = 3;

    private const WAITING = [ReportStatus::Diajukan, ReportStatus::Verifikasi, ReportStatus::Disetujui];

    public function __construct(private readonly ReportSignatories $signatories) {}

    /**
     * @param  Collection<int, Unit>  $units
     * @param  list<ReportModule>|null  $modules  only these reports (null = all)
     * @return array<string, mixed>
     */
    public function build(Collection $units, int $month, int $year, ?Carbon $now = null, ?array $modules = null): array
    {
        $now ??= Carbon::now();
        $modules ??= ReportModule::cases();
        $moduleValues = array_map(fn (ReportModule $m): string => $m->value, $modules);
        $unitIds = $units->pluck('id')->all();

        $workflows = ReportWorkflow::query()->whereIn('unit_id', $unitIds)->whereIn('module', $moduleValues)->where('month', $month)->where('year', $year)
            ->with(['steps.employee', 'logs' => fn ($q) => $q->latest('created_at')])
            ->get()->keyBy(fn (ReportWorkflow $w): string => $w->unit_id.'|'.$w->module->value);

        $matrix = $units->map(function (Unit $unit) use ($workflows, $month, $year, $now, $modules): array {
            $cells = [];
            foreach ($modules as $module) {
                $workflow = $workflows->get($unit->id.'|'.$module->value);
                $cells[$module->value] = $workflow !== null
                    ? $this->cell($workflow, $now)
                    : ['status' => $module->hasSavedDocument($unit->id, $month, $year) ? 'draft' : 'belum', 'label' => $module->hasSavedDocument($unit->id, $month, $year) ? 'Draft tersimpan' : 'Belum dibuat', 'tone' => 'neutral', 'waiting_for' => null, 'days' => null, 'stuck' => false, 'url' => $this->url($module, $unit->id, $month, $year)];
            }

            return ['id' => $unit->id, 'name' => $unit->name, 'cells' => $cells];
        })->values()->all();

        $counts = ['belum' => 0, 'draft' => 0];
        foreach (ReportStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }
        foreach ($matrix as $row) {
            foreach ($row['cells'] as $cell) {
                $counts[$cell['status']] = ($counts[$cell['status']] ?? 0) + 1;
            }
        }

        return [
            'modules' => array_map(fn (ReportModule $m): array => ['key' => $m->value, 'label' => $m->label()], $modules),
            'matrix' => $matrix,
            'counts' => $counts,
            'stuck' => $this->stuck($unitIds, $now, $moduleValues),
            'rejections' => $this->rejections($unitIds, $now, $moduleValues),
            'durations' => $this->durations($unitIds, $year, $modules),
            'missing_signers' => $this->missingSigners($units, $modules),
        ];
    }

    /**
     * @return array{status: string, label: string, tone: string, waiting_for: string|null, days: int|null, stuck: bool, url: string}
     */
    private function cell(ReportWorkflow $workflow, Carbon $now): array
    {
        $since = $workflow->logs->first()?->created_at ?? $workflow->submitted_at;
        $days = $since === null ? null : (int) $since->diffInDays($now);
        $waiting = in_array($workflow->status, self::WAITING, true);
        $step = $waiting ? $this->openStep($workflow) : null;

        return [
            'status' => $workflow->status->value,
            'label' => $workflow->status->label(),
            'tone' => $workflow->status->tone(),
            'waiting_for' => $step === null ? null : trim(($step->employee?->name ?? '—').' · '.$step->position),
            'days' => $days,
            'stuck' => $waiting && $days !== null && $days >= self::STUCK_AFTER_DAYS,
            'url' => $this->url($workflow->module, $workflow->unit_id, $workflow->month, $workflow->year),
        ];
    }

    private function openStep(ReportWorkflow $workflow): ?ReportWorkflowStep
    {
        $sequence = match ($workflow->status) {
            ReportStatus::Diajukan => 1,
            ReportStatus::Verifikasi => 2,
            ReportStatus::Disetujui => 3,
            default => null,
        };

        return $sequence === null ? null : $workflow->steps->first(fn (ReportWorkflowStep $s): bool => $s->isPengesahan() && $s->sequence === $sequence);
    }

    /**
     * Reports of any month waiting at one step for at least STUCK_AFTER_DAYS days.
     *
     * @param  list<int>  $unitIds
     * @return list<array<string, mixed>>
     */
    private function stuck(array $unitIds, Carbon $now, array $moduleValues): array
    {
        return ReportWorkflow::query()->whereIn('unit_id', $unitIds)->whereIn('module', $moduleValues)->whereIn('status', array_map(fn (ReportStatus $s): string => $s->value, self::WAITING))
            ->with(['unit:id,name', 'steps.employee', 'logs' => fn ($q) => $q->latest('created_at')])
            ->get()
            ->map(function (ReportWorkflow $workflow) use ($now): array {
                $cell = $this->cell($workflow, $now);

                return [
                    'id' => $workflow->id,
                    'module' => $workflow->module->label(),
                    'unit' => $workflow->unit?->name ?? '-',
                    'period' => Indonesian::monthName($workflow->month).' '.$workflow->year,
                    'status' => $workflow->status->label(),
                    'waiting_for' => $cell['waiting_for'],
                    'days' => $cell['days'] ?? 0,
                    'url' => $cell['url'],
                ];
            })
            ->filter(fn (array $row): bool => $row['days'] >= self::STUCK_AFTER_DAYS)
            ->sortByDesc('days')->values()->all();
    }

    /**
     * Rejections of the last 90 days, newest first, and whether the report was diajukan again.
     *
     * @param  list<int>  $unitIds
     * @return list<array<string, mixed>>
     */
    private function rejections(array $unitIds, Carbon $now, array $moduleValues): array
    {
        return ReportWorkflowLog::query()->where('action', 'tolak')->where('created_at', '>=', $now->copy()->subDays(90))
            ->whereHas('workflow', fn ($q) => $q->whereIn('unit_id', $unitIds)->whereIn('module', $moduleValues))
            ->with('workflow.unit:id,name')->latest('created_at')->limit(30)->get()
            ->map(fn (ReportWorkflowLog $log): array => [
                'id' => $log->id,
                'module' => $log->workflow->module->label(),
                'unit' => $log->workflow->unit?->name ?? '-',
                'period' => Indonesian::monthName($log->workflow->month).' '.$log->workflow->year,
                'by' => trim(($log->user_name ?? '-').($log->jabatan ? " · {$log->jabatan}" : '')),
                'reason' => (string) ($log->note ?? ''),
                'at' => $log->created_at?->toIso8601String(),
                'resolved' => $log->workflow->status !== ReportStatus::Ditolak,
                'url' => $this->url($log->workflow->module, $log->workflow->unit_id, $log->workflow->month, $log->workflow->year),
            ])->all();
    }

    /**
     * Average days per step this year, per module: ajukan → verifikasi,
     * verifikasi → setujui, setujui → sahkan.
     *
     * @param  list<int>  $unitIds
     * @return list<array{module: string, verifikasi: float|null, setujui: float|null, sahkan: float|null, final: int}>
     */
    private function durations(array $unitIds, int $year, array $modules): array
    {
        $logs = ReportWorkflowLog::query()->whereHas('workflow', fn ($q) => $q->whereIn('unit_id', $unitIds)->where('year', $year))
            ->with('workflow:id,module')->orderBy('created_at')->get()
            ->groupBy('report_workflow_id');

        $steps = ['verifikasi' => ['ajukan', 'ajukan_kembali'], 'setujui' => ['verifikasi'], 'sahkan' => ['setujui']];
        $samples = [];
        $finals = [];

        foreach ($logs as $trail) {
            $module = $trail->first()->workflow->module->value;
            $finals[$module] = ($finals[$module] ?? 0) + ($trail->contains('action', 'final') ? 1 : 0);
            $previous = null;
            foreach ($trail as $log) {
                foreach ($steps as $step => $from) {
                    if ($log->action === $step && $previous !== null && in_array($previous->action, $from, true)) {
                        $samples[$module][$step][] = $previous->created_at->diffInHours($log->created_at) / 24;
                    }
                }
                $previous = $log;
            }
        }

        return array_map(function (ReportModule $module) use ($samples, $finals): array {
            $avg = fn (string $step): ?float => empty($samples[$module->value][$step]) ? null : round(array_sum($samples[$module->value][$step]) / count($samples[$module->value][$step]), 1);

            return ['module' => $module->label(), 'verifikasi' => $avg('verifikasi'), 'setujui' => $avg('setujui'), 'sahkan' => $avg('sahkan'), 'final' => $finals[$module->value] ?? 0];
        }, $modules);
    }

    /**
     * Jabatan of the report chains without an active holder, per unit.
     *
     * @param  Collection<int, Unit>  $units
     * @return list<array{unit: string, positions: list<string>}>
     */
    private function missingSigners(Collection $units, array $modules): array
    {
        $positions = collect($modules)
            ->flatMap(fn (ReportModule $m): array => [...$m->pengesahanSigners(), ...$m->reportSigners()])
            ->map(fn (array $s): EmployeePosition => $s['position'])
            ->unique(fn (EmployeePosition $p): string => $p->value)->values();

        return $units->map(fn (Unit $unit): array => [
            'unit' => $unit->name,
            'positions' => $positions->filter(fn (EmployeePosition $p): bool => $this->signatories->holder($unit, $p) === null)
                ->map(fn (EmployeePosition $p): string => $p->value)->values()->all(),
        ])->filter(fn (array $row): bool => $row['positions'] !== [])->values()->all();
    }

    private function url(ReportModule $module, int $unitId, int $month, int $year): string
    {
        return $module === ReportModule::Operasi
            ? route('operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan', 'unit_id' => $unitId, 'month' => $month, 'year' => $year], false)
            : route("{$module->value}.laporan.document.edit", ['unit_id' => $unitId, 'month' => $month, 'year' => $year], false);
    }
}
