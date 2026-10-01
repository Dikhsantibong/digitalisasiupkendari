<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\HarDocumentRecord;
use App\Models\K3DocumentRecord;
use App\Models\OperasiReportDocument;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Monitoring\InputCatalog;
use App\Services\Monitoring\InputCompleteness;
use App\Services\Monitoring\ModuleAccess;
use App\Services\Monitoring\ReportMonitoring;
use App\Services\Operator\PresenceRecorder;
use App\Support\Indonesian;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal Pemantauan — the view-focused home of the kantor induk UP Kendari
 * (Manager UP, TL & Asman bidang) and the Manager UL: what each unit has
 * input, the status of the Laporan Pembangkit, and the final / pengusahaan
 * reports to view or download. Only the bidang the account's view
 * permissions open are shown ({@see ModuleAccess}), only for its units.
 */
class PortalController extends Controller
{
    /** Pengusahaan reports per area: area => [module key, label, pdf route, saved-document query]. */
    private const PENGUSAHAAN = [
        'operasi' => ['operasi', 'Laporan Pengusahaan Operasi', 'operasi.laporan.pengusahaan.pdf', PermissionName::OperasiPengusahaanView],
        'pemeliharaan' => ['har', 'Laporan Pengusahaan Pemeliharaan', 'har.laporan.pengusahaan.pdf', PermissionName::HarPengusahaanView],
        'k3' => ['k3', 'Laporan Pengusahaan K3 & KAM', 'k3.laporan.pengusahaan.pdf', PermissionName::K3PengusahaanView],
    ];

    public function __construct(
        private readonly ModuleAccess $access,
        private readonly InputCompleteness $completeness,
        private readonly ReportMonitoring $reports,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->authorizePortal($request);
        [$units, $period] = $this->scope($request);
        $areas = $this->access->areas($user);
        $modules = $this->access->reportModules($user);

        $input = $this->completeness->build($units, $period['month'], $period['year'], groups: $this->access->groups($user));
        $laporan = $this->reports->build($units, $period['month'], $period['year'], modules: $modules);

        $areaCards = array_map(function (string $area) use ($input, $laporan): array {
            [$label, , $groups, $modules] = ModuleAccess::AREAS[$area];
            $percents = array_values(array_filter(array_map(fn (string $g): ?int => $input['groups'][$g] ?? null, $groups), fn ($p): bool => $p !== null));
            $cells = collect($laporan['matrix'])->flatMap(fn (array $row): array => array_values(array_intersect_key($row['cells'], array_flip(array_map(fn (ReportModule $m): string => $m->value, $modules)))));

            return [
                'key' => $area,
                'label' => $label,
                'input_percent' => $percents === [] ? null : (int) round(array_sum($percents) / count($percents)),
                'final' => $cells->where('status', 'final')->count(),
                'waiting' => $cells->whereIn('status', ['diajukan', 'verifikasi', 'disetujui'])->count(),
                'total' => $cells->count(),
            ];
        }, $areas);

        return Inertia::render('portal/index', [
            'period' => $period + ['label' => Indonesian::monthName($period['month']).' '.$period['year']],
            'scope_label' => $this->scopeLabel($user),
            'read_only' => $user->isReadOnly(),
            'unit_count' => $units->count(),
            'areas' => $areaCards,
            'input_percent' => $input['percent'],
            'stuck' => array_slice($laporan['stuck'], 0, 5),
            'recent_final' => $this->finalReports($units, $modules)->take(8)->values()->all(),
            'my_turn' => $this->awaitingMe($user, $units, $modules),
        ]);
    }

    public function laporan(Request $request): Response
    {
        $user = $this->authorizePortal($request);
        [$units, $period, $serviceUnitId] = $this->scope($request);
        $modules = $this->access->reportModules($user);
        $kind = $request->query('jenis') === 'pengusahaan' ? 'pengusahaan' : 'pembangkit';
        $onlyFinal = $request->query('status', 'final') !== 'semua';
        $unitId = $request->integer('unit_id') ?: null;
        $shown = $unitId === null ? $units : $units->where('id', $unitId)->values();

        $rows = $kind === 'pembangkit'
            ? $this->pembangkitRows($shown, $modules, $period['month'], $period['year'], $onlyFinal)
            : $this->pengusahaanRows($user, $shown, $period['month'], $period['year']);

        return Inertia::render('portal/laporan', [
            'filters' => ['month' => $period['month'], 'year' => $period['year'], 'service_unit_id' => $serviceUnitId, 'unit_id' => $unitId, 'jenis' => $kind, 'status' => $onlyFinal ? 'final' : 'semua'],
            'options' => $this->options($user, $units),
            'period_label' => Indonesian::monthName($period['month']).' '.$period['year'],
            'rows' => $rows,
            'has_pengusahaan' => $this->pengusahaanAreas($user) !== [],
        ]);
    }

    public function input(Request $request): Response
    {
        $user = $this->authorizePortal($request);
        [$units, $period, $serviceUnitId] = $this->scope($request);
        $groups = $this->access->groups($user);
        $areas = $this->access->areas($user);
        $area = in_array($request->query('bidang'), $areas, true) ? $request->query('bidang') : ($areas[0] ?? null);
        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();

        $data = $unit === null || $area === null
            ? null
            : $this->completeness->build(collect([$unit]), $period['month'], $period['year'], groups: array_values(array_intersect(ModuleAccess::AREAS[$area][2], $groups)));

        return Inertia::render('portal/input', [
            'filters' => ['month' => $period['month'], 'year' => $period['year'], 'service_unit_id' => $serviceUnitId, 'unit_id' => $unit?->id, 'bidang' => $area],
            'options' => $this->options($user, $units),
            'areas' => array_map(fn (string $a): array => ['key' => $a, 'label' => ModuleAccess::AREAS[$a][0]], $areas),
            'period_label' => Indonesian::monthName($period['month']).' '.$period['year'],
            'state' => $data['state'] ?? 'past',
            'groups' => $data === null ? [] : array_map(fn (string $g): array => [
                'key' => $g,
                'label' => InputCatalog::GROUPS[$g],
                'percent' => $data['groups'][$g] ?? null,
                'items' => array_values(array_map(function (array $entry) use ($data, $unit, $period): array {
                    $cell = $data['units'][0]['cells'][$entry['key']] ?? null;

                    return [
                        'key' => $entry['key'],
                        'label' => $entry['label'],
                        'period' => $entry['period'],
                        'kind' => $entry['kind'],
                        'filled' => $cell['filled'] ?? 0,
                        'expected' => $cell['expected'] ?? 0,
                        'percent' => $cell['percent'] ?? null,
                        'last' => $cell['last'] ?? null,
                        'url' => route($entry['route'], [...$entry['params'], 'unit_id' => $unit->id, 'month' => $period['month'], 'year' => $period['year']], false),
                    ];
                }, array_filter($data['entries'], fn (array $e): bool => $e['group'] === $g))),
            ], array_keys($data['groups'])),
        ]);
    }

    private function authorizePortal(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::PortalView), 403);

        return $user;
    }

    /**
     * The accessible active units (optionally of one Unit Layanan) and the period.
     *
     * @return array{0: Collection<int, Unit>, 1: array{month: int, year: int}, 2: int|null}
     */
    private function scope(Request $request): array
    {
        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 404);
        $serviceUnitId = $request->integer('service_unit_id') ?: null;

        $units = Unit::query()->visibleTo($request->user())->where('is_active', true)
            ->when($serviceUnitId !== null, fn ($q) => $q->where('service_unit_id', $serviceUnitId))
            ->orderBy('name')->get(['id', 'name', 'service_unit_id']);

        return [$units, ['month' => $month, 'year' => $year], $serviceUnitId];
    }

    /**
     * @param  Collection<int, Unit>  $units
     * @return array{service_units: list<array{id: int, name: string}>, units: list<array{id: int, name: string}>, years: list<int>, modules: list<array{key: string, label: string}>}
     */
    private function options(User $user, Collection $units): array
    {
        $now = Carbon::now(PresenceRecorder::TIMEZONE);

        return [
            'service_units' => ServiceUnit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])->map(fn (ServiceUnit $s): array => ['id' => $s->id, 'name' => $s->name])->all(),
            'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
            'years' => range($now->year + 1, $now->year - 4),
            'modules' => array_map(fn (ReportModule $m): array => ['key' => $m->value, 'label' => $m->label()], $this->access->reportModules($user)),
        ];
    }

    private function scopeLabel(User $user): string
    {
        if ($user->hasGlobalAccess()) {
            return 'Seluruh unit UP Kendari';
        }

        $names = ServiceUnit::query()->visibleTo($user)->orderBy('name')->pluck('name');

        return $names->isEmpty() ? 'Unit yang ditugaskan' : $names->implode(', ');
    }

    /**
     * Laporan Pembangkit per unit × module, with their PDF links.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<ReportModule>  $modules
     * @return list<array<string, mixed>>
     */
    private function pembangkitRows(Collection $units, array $modules, int $month, int $year, bool $onlyFinal): array
    {
        $workflows = ReportWorkflow::query()->whereIn('unit_id', $units->pluck('id'))->where('month', $month)->where('year', $year)
            ->whereIn('module', array_map(fn (ReportModule $m): string => $m->value, $modules))
            ->get()->keyBy(fn (ReportWorkflow $w): string => $w->unit_id.'|'.$w->module->value);

        $rows = [];
        foreach ($units as $unit) {
            foreach ($modules as $module) {
                $workflow = $workflows->get($unit->id.'|'.$module->value);
                $status = $workflow?->status;

                if ($onlyFinal && $status !== ReportStatus::Final) {
                    continue;
                }

                $rows[] = [
                    'key' => $unit->id.'-'.$module->value,
                    'unit' => $unit->name,
                    'title' => $module->label(),
                    'module' => $module->value,
                    'status' => $status?->value ?? ($module->hasSavedDocument($unit->id, $month, $year) ? 'draft' : 'belum'),
                    'status_label' => $status?->label() ?? ($module->hasSavedDocument($unit->id, $month, $year) ? 'Draft' : 'Belum dibuat'),
                    'status_tone' => $status?->tone() ?? 'neutral',
                    'finalized_at' => $workflow?->finalized_at?->toIso8601String(),
                    ...$this->reportLinks($module, $unit->id, $month, $year),
                ];
            }
        }

        return $rows;
    }

    /**
     * Laporan Pengusahaan per unit for the areas the user may see.
     *
     * @param  Collection<int, Unit>  $units
     * @return list<array<string, mixed>>
     */
    private function pengusahaanRows(User $user, Collection $units, int $month, int $year): array
    {
        $rows = [];
        foreach ($this->pengusahaanAreas($user) as $area) {
            [$module, $title, $pdfRoute] = self::PENGUSAHAAN[$area];
            $saved = match ($module) {
                'operasi' => OperasiReportDocument::query()->where('report_code', 'pengusahaan'),
                'har' => HarDocumentRecord::query()->where('type', 'pengusahaan'),
                default => K3DocumentRecord::query()->where('type', 'pengusahaan'),
            };
            $savedUnits = $saved->whereIn('unit_id', $units->pluck('id'))->where('month', $month)->where('year', $year)->pluck('updated_at', 'unit_id');

            foreach ($units as $unit) {
                $query = ['unit_id' => $unit->id, 'month' => $month, 'year' => $year];
                $rows[] = [
                    'key' => $unit->id.'-'.$module,
                    'unit' => $unit->name,
                    'title' => $title,
                    'module' => $module,
                    'status' => $savedUnits->has($unit->id) ? 'tersimpan' : 'otomatis',
                    'status_label' => $savedUnits->has($unit->id) ? 'Dokumen tersimpan' : 'Otomatis dari data input',
                    'status_tone' => $savedUnits->has($unit->id) ? 'success' : 'info',
                    'finalized_at' => $savedUnits->has($unit->id) ? Carbon::parse((string) $savedUnits[$unit->id])->toIso8601String() : null,
                    'view_url' => route($pdfRoute, $query, false),
                    'download_url' => route($pdfRoute, $query + ['download' => 1], false),
                    'document_url' => route(str_replace('.pdf', '.edit', $pdfRoute), $query, false),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function pengusahaanAreas(User $user): array
    {
        return array_values(array_filter(array_keys(self::PENGUSAHAAN), fn (string $area): bool => $user->hasPermissionTo(self::PENGUSAHAAN[$area][3])));
    }

    /**
     * @return array{view_url: string, download_url: string, document_url: string}
     */
    private function reportLinks(ReportModule $module, int $unitId, int $month, int $year): array
    {
        $query = ['unit_id' => $unitId, 'month' => $month, 'year' => $year];
        [$pdf, $edit] = $module === ReportModule::Operasi
            ? [fn (array $q): string => route('operasi.laporan.document.pdf', ['report' => 'laporan-operasi-bulanan', ...$q], false), fn (array $q): string => route('operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan', ...$q], false)]
            : [fn (array $q): string => route("{$module->value}.laporan.document.pdf", $q, false), fn (array $q): string => route("{$module->value}.laporan.document.edit", $q, false)];

        return ['view_url' => $pdf($query), 'download_url' => $pdf($query + ['download' => 1]), 'document_url' => $edit($query)];
    }

    /**
     * The latest final Laporan Pembangkit of the units.
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<ReportModule>  $modules
     * @return Collection<int, array<string, mixed>>
     */
    private function finalReports(Collection $units, array $modules): Collection
    {
        $names = $units->pluck('name', 'id');

        return ReportWorkflow::query()->whereIn('unit_id', $units->pluck('id'))->where('status', ReportStatus::Final->value)
            ->whereIn('module', array_map(fn (ReportModule $m): string => $m->value, $modules))
            ->latest('finalized_at')->limit(20)->get()
            ->map(fn (ReportWorkflow $w): array => [
                'key' => $w->id,
                'unit' => $names[$w->unit_id] ?? '-',
                'title' => $w->module->label(),
                'period' => Indonesian::monthName($w->month).' '.$w->year,
                'finalized_at' => $w->finalized_at?->toIso8601String(),
                ...$this->reportLinks($w->module, $w->unit_id, $w->month, $w->year),
            ]);
    }

    /**
     * Reports waiting for the user's own signature (e.g. the Manager UL mengesahkan).
     *
     * @param  Collection<int, Unit>  $units
     * @param  list<ReportModule>  $modules
     * @return list<array<string, mixed>>
     */
    private function awaitingMe(User $user, Collection $units, array $modules): array
    {
        $employee = $user->employee;

        if ($employee === null) {
            return [];
        }

        $names = $units->pluck('name', 'id');

        return ReportWorkflow::query()->whereIn('status', [ReportStatus::Diajukan->value, ReportStatus::Verifikasi->value, ReportStatus::Disetujui->value])
            ->whereIn('module', array_map(fn (ReportModule $m): string => $m->value, ReportModule::cases()))
            ->whereHas('steps', fn ($q) => $q->where('employee_id', $employee->id)->whereNull('signed_at'))
            ->with('steps')->get()
            ->filter(function (ReportWorkflow $w) use ($employee): bool {
                $sequence = match ($w->status) {
                    ReportStatus::Diajukan => 1,
                    ReportStatus::Verifikasi => 2,
                    default => 3,
                };

                return $w->steps->contains(fn (ReportWorkflowStep $s): bool => $s->isPengesahan() && $s->sequence === $sequence && $s->employee_id === $employee->id);
            })
            ->map(fn (ReportWorkflow $w): array => [
                'key' => $w->id,
                'unit' => $names[$w->unit_id] ?? Unit::query()->whereKey($w->unit_id)->value('name') ?? '-',
                'title' => $w->module->label(),
                'period' => Indonesian::monthName($w->month).' '.$w->year,
                'status' => $w->status->label(),
                ...$this->reportLinks($w->module, $w->unit_id, $w->month, $w->year),
            ])->values()->all();
    }
}
