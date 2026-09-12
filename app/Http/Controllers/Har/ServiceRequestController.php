<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderSource;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\ReportPeriod;
use App\Models\ServiceRequest;
use App\Models\SrCategory;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Service Request input (modul HAR). Same Excel-like grid as Work Orders:
 * category is typed as a code and the engine as a name, resolved server-side.
 */
class ServiceRequestController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $requests = $period === null ? collect() : ServiceRequest::query()
            ->where('unit_id', $unit->id)
            ->where('report_period_id', $period->id)
            ->with(['engine:id,name', 'category:id,code'])
            ->orderBy('sr_number')
            ->get();

        return Inertia::render('har/input/service-requests', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'rows' => $requests->map(fn (ServiceRequest $sr): array => [
                'sr_number' => $sr->sr_number,
                'description' => $sr->description,
                'category_code' => $sr->category?->code,
                'status' => $sr->status->value,
                'engine_name' => $sr->engine?->name,
            ])->all(),
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'categories' => SrCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name']),
                'machines' => Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'statuses' => collect(ServiceRequestStatus::cases())->map(fn (ServiceRequestStatus $s): array => ['value' => $s->value, 'label' => $s->label()])->all(),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.sr_number' => ['nullable', 'string', 'max:100'],
            'rows.*.description' => ['nullable', 'string', 'max:1000'],
            'rows.*.category_code' => ['nullable', 'string'],
            'rows.*.status' => ['nullable', 'string'],
            'rows.*.engine_name' => ['nullable', 'string'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;

        $period = ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => $totalDays, 'total_hours' => $totalDays * 24],
        );

        $categories = SrCategory::query()->pluck('id', 'code');
        $machines = Machine::query()->where('unit_id', $unit->id)->pluck('id', 'name');

        $submitted = [];

        DB::transaction(function () use ($validated, $unit, $period, $user, $categories, $machines, &$submitted): void {
            foreach ($validated['rows'] ?? [] as $row) {
                $srNumber = trim((string) ($row['sr_number'] ?? ''));
                if ($srNumber === '') {
                    continue;
                }
                $submitted[] = $srNumber;

                $status = ServiceRequestStatus::tryFrom((string) ($row['status'] ?? '')) ?? ServiceRequestStatus::Open;

                ServiceRequest::query()->updateOrCreate(
                    ['unit_id' => $unit->id, 'report_period_id' => $period->id, 'sr_number' => $srNumber],
                    [
                        'description' => $row['description'] ?? null,
                        'sr_category_id' => $categories[$row['category_code'] ?? ''] ?? null,
                        'status' => $status,
                        'engine_id' => $machines[$row['engine_name'] ?? ''] ?? null,
                        'source' => WorkOrderSource::Manual,
                        'input_by' => $user->id,
                    ],
                );
            }

            ServiceRequest::query()
                ->where('unit_id', $unit->id)
                ->where('report_period_id', $period->id)
                ->whereNotIn('sr_number', $submitted === [] ? [''] : $submitted)
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Service Request {$unit->name} periode {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service Request disimpan.']);

        return back();
    }
}
