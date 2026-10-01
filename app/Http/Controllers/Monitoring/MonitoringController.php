<?php

namespace App\Http\Controllers\Monitoring;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
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
 * Menu Monitoring (Super Admin, or any role given monitoring.view): the
 * Ringkasan, Kelengkapan Input and Verifikasi Laporan of the units the user
 * can access. Read-only; every row links to the page where it is filled.
 */
class MonitoringController extends Controller
{
    public function __construct(
        private readonly InputCompleteness $completeness,
        private readonly ReportMonitoring $reports,
        private readonly ModuleAccess $access,
    ) {}

    public function index(Request $request): Response
    {
        [$units, $filters, $options] = $this->scope($request);
        $input = $this->completeness->build($units, $filters['month'], $filters['year'], groups: $this->access->groups($request->user()));
        $laporan = $this->reports->build($units, $filters['month'], $filters['year'], modules: $this->access->reportModules($request->user()));

        return Inertia::render('monitoring/index', [
            'filters' => $filters,
            'options' => $options,
            'period_label' => Indonesian::monthName($filters['month']).' '.$filters['year'],
            'input' => [
                'state' => $input['state'],
                'percent' => $input['percent'],
                'groups' => $this->groupList($input['groups']),
                'lowest_units' => collect($input['units'])->filter(fn (array $u): bool => $u['percent'] !== null)->sortBy('percent')->take(5)
                    ->map(fn (array $u): array => ['id' => $u['id'], 'name' => $u['name'], 'percent' => $u['percent']])->values()->all(),
                'empty_inputs' => $this->emptyInputs($input),
            ],
            'laporan' => [
                'counts' => $laporan['counts'],
                'stuck' => array_slice($laporan['stuck'], 0, 5),
                'stuck_total' => count($laporan['stuck']),
                'open_rejections' => count(array_filter($laporan['rejections'], fn (array $r): bool => ! $r['resolved'])),
                'missing_signers' => $laporan['missing_signers'],
                'total' => count($laporan['matrix']) * count($laporan['modules']),
            ],
        ]);
    }

    public function input(Request $request): Response
    {
        [$units, $filters, $options] = $this->scope($request);
        $input = $this->completeness->build($units, $filters['month'], $filters['year'], groups: $this->access->groups($request->user()));

        return Inertia::render('monitoring/input', [
            'filters' => $filters,
            'options' => $options,
            'period_label' => Indonesian::monthName($filters['month']).' '.$filters['year'],
            'groups' => $this->groupList($input['groups']),
            'state' => $input['state'],
            'days_due' => $input['days_due'],
            'days_in_month' => $input['days_in_month'],
            'percent' => $input['percent'],
            'entries' => array_map(fn (array $e): array => [
                ...$e,
                'url' => route($e['route'], [...$e['params'], 'unit_id' => '__UNIT__', 'month' => $filters['month'], 'year' => $filters['year']], false),
            ], $input['entries']),
            'units' => $input['units'],
        ]);
    }

    public function laporan(Request $request): Response
    {
        [$units, $filters, $options] = $this->scope($request);

        return Inertia::render('monitoring/laporan', [
            'filters' => $filters,
            'options' => $options,
            'period_label' => Indonesian::monthName($filters['month']).' '.$filters['year'],
            'stuck_after_days' => ReportMonitoring::STUCK_AFTER_DAYS,
            ...$this->reports->build($units, $filters['month'], $filters['year'], modules: $this->access->reportModules($request->user())),
        ]);
    }

    /**
     * The units (accessible, active, optionally one Unit Layanan) and period.
     *
     * @return array{0: Collection<int, Unit>, 1: array{month: int, year: int, service_unit_id: int|null}, 2: array{service_units: list<array{id: int, name: string}>, years: list<int>}}
     */
    private function scope(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::MonitoringView), 403);

        $now = Carbon::now(PresenceRecorder::TIMEZONE);
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);
        abort_unless($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100, 404);

        $serviceUnitId = $request->integer('service_unit_id') ?: null;
        $units = Unit::query()->visibleTo($user)->where('is_active', true)
            ->when($serviceUnitId !== null, fn ($q) => $q->where('service_unit_id', $serviceUnitId))
            ->orderBy('name')->get(['id', 'name', 'service_unit_id']);

        $serviceUnits = ServiceUnit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name'])
            ->map(fn (ServiceUnit $s): array => ['id' => $s->id, 'name' => $s->name])->all();

        return [
            $units,
            ['month' => $month, 'year' => $year, 'service_unit_id' => $serviceUnitId],
            ['service_units' => $serviceUnits, 'years' => range($now->year + 1, $now->year - 4)],
        ];
    }

    /**
     * @param  array<string, int|null>  $groups
     * @return list<array{key: string, label: string, percent: int|null}>
     */
    private function groupList(array $groups): array
    {
        return array_map(fn (string $key): array => ['key' => $key, 'label' => InputCatalog::GROUPS[$key], 'percent' => $groups[$key] ?? null], array_keys($groups));
    }

    /**
     * Inputs that no unit has filled this period (the clearest "perlu tindakan").
     *
     * @param  array<string, mixed>  $input
     * @return list<array{label: string, group: string, units: int}>
     */
    private function emptyInputs(array $input): array
    {
        return collect($input['entries'])
            ->map(function (array $entry) use ($input): array {
                $empty = collect($input['units'])->filter(fn (array $u): bool => ($u['cells'][$entry['key']]['percent'] ?? null) === 0)->count();

                return ['label' => $entry['label'], 'group' => InputCatalog::GROUPS[$entry['group']], 'units' => $empty];
            })
            ->filter(fn (array $row): bool => $row['units'] > 0)
            ->sortByDesc('units')->take(8)->values()->all();
    }
}
