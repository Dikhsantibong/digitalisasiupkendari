<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitRequest;
use App\Models\ActivityLog;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::query()
            ->visibleTo($request->user())
            ->with('serviceUnit:id,name')
            ->withCount('machines')
            ->withSum('machines', 'capacity_kw')
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->when($request->input('type'), fn ($query, $type) => $query->where('type', $type))
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('service_unit_id'), fn ($query, $id) => $query->where('service_unit_id', $id))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/units/index', [
            'units' => $units->through(fn (Unit $unit): array => $this->presentUnit($unit)),
            'filters' => $request->only(['search', 'type', 'status', 'service_unit_id']),
            'options' => $this->options($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Unit::class);

        return Inertia::render('admin/units/create', [
            'options' => $this->options($request),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $this->authorize('create', Unit::class);

        $unit = Unit::query()->create($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah unit pembangkit {$unit->name}",
            $unit,
            unit: $unit,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit pembangkit berhasil ditambahkan.']);

        return to_route('admin.units.index');
    }

    public function show(Request $request, Unit $unit): Response
    {
        $this->authorize('view', $unit);

        $unit->load('serviceUnit:id,name,code');

        return Inertia::render('admin/units/show', [
            'unit' => $this->presentUnit($unit),
            'assignments' => $unit->roleAssignments()
                ->with(['user:id,name,email,position,is_active', 'role:id,name,display_name'])
                ->get()
                ->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'user' => [
                        'id' => $assignment->user->id,
                        'name' => $assignment->user->name,
                        'email' => $assignment->user->email,
                        'position' => $assignment->user->position,
                        'is_active' => $assignment->user->is_active,
                    ],
                    'role' => $assignment->role->display_name,
                ])
                ->all(),
            'activity' => $request->user()->can('viewAny', ActivityLog::class)
                ? $unit->activityLogs()
                    ->with('user:id,name')
                    ->latest('created_at')
                    ->limit(10)
                    ->get()
                    ->map(fn (ActivityLog $log): array => [
                        'id' => $log->id,
                        'event_label' => $log->event->label(),
                        'tone' => $log->event->tone(),
                        'description' => $log->description,
                        'user' => $log->user?->name,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ])
                    ->all()
                : [],
        ]);
    }

    public function edit(Request $request, Unit $unit): Response
    {
        $this->authorize('update', $unit);

        return Inertia::render('admin/units/edit', [
            'unit' => $this->presentUnit($unit),
            'options' => $this->options($request),
        ]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $this->authorize('update', $unit);

        $unit->update($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengubah data unit pembangkit {$unit->name}",
            $unit,
            properties: ['changed' => array_keys($unit->getChanges())],
            unit: $unit,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit pembangkit berhasil diperbarui.']);

        return to_route('admin.units.index');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->authorize('delete', $unit);

        $name = $unit->name;
        $unit->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus unit pembangkit {$name}",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit pembangkit berhasil dihapus.']);

        return to_route('admin.units.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUnit(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type->value,
            'type_label' => $unit->type->label(),
            'status' => $unit->status->value,
            'status_label' => $unit->status->label(),
            'status_tone' => $unit->status->tone(),
            'installed_capacity_mw' => $unit->installed_capacity_mw,
            'location' => $unit->location,
            'is_active' => $unit->is_active,
            'service_unit_id' => $unit->service_unit_id,
            'service_unit' => $unit->relationLoaded('serviceUnit') ? $unit->serviceUnit?->name : null,
            'machines_count' => $unit->machines_count ?? null,
            'machines_capacity' => $unit->machines_sum_capacity_kw,
        ];
    }

    /**
     * Reference data for the unit forms and filters.
     *
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'serviceUnits' => ServiceUnit::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'types' => collect(UnitType::cases())
                ->map(fn (UnitType $type): array => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'description' => $type->description(),
                ])
                ->all(),
            'statuses' => collect(UnitStatus::cases())
                ->map(fn (UnitStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'tone' => $status->tone(),
                ])
                ->all(),
        ];
    }
}
