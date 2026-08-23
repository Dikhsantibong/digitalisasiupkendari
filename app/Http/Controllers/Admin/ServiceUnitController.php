<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceUnitRequest;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceUnitController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServiceUnit::class);

        $serviceUnits = ServiceUnit::query()
            ->visibleTo($request->user())
            ->withCount('units')
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/service-units/index', [
            'serviceUnits' => $serviceUnits->through(fn (ServiceUnit $serviceUnit): array => [
                'id' => $serviceUnit->id,
                'code' => $serviceUnit->code,
                'name' => $serviceUnit->name,
                'description' => $serviceUnit->description,
                'is_active' => $serviceUnit->is_active,
                'units_count' => $serviceUnit->units_count,
            ]),
            'filters' => $request->only(['search']),
            'unassignedUnits' => Unit::query()
                ->visibleTo($request->user())
                ->whereNull('service_unit_id')
                ->count(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ServiceUnit::class);

        return Inertia::render('admin/service-units/create');
    }

    public function store(ServiceUnitRequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceUnit::class);

        $serviceUnit = ServiceUnit::query()->create($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah unit layanan {$serviceUnit->name}",
            $serviceUnit,
            serviceUnit: $serviceUnit,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit layanan berhasil ditambahkan.']);

        return to_route('admin.service-units.index');
    }

    public function show(ServiceUnit $serviceUnit): Response
    {
        $this->authorize('view', $serviceUnit);

        return Inertia::render('admin/service-units/show', [
            'serviceUnit' => [
                'id' => $serviceUnit->id,
                'code' => $serviceUnit->code,
                'name' => $serviceUnit->name,
                'description' => $serviceUnit->description,
                'is_active' => $serviceUnit->is_active,
            ],
            'units' => $serviceUnit->units()
                ->orderBy('name')
                ->get()
                ->map(fn (Unit $unit): array => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'type_label' => $unit->type->label(),
                    'status_label' => $unit->status->label(),
                    'status_tone' => $unit->status->tone(),
                    'installed_capacity_mw' => $unit->installed_capacity_mw,
                    'is_active' => $unit->is_active,
                ])
                ->all(),
            'managers' => $serviceUnit->roleAssignments()
                ->with(['user:id,name,email,position', 'role:id,display_name'])
                ->get()
                ->map(fn ($assignment): array => [
                    'id' => $assignment->id,
                    'name' => $assignment->user->name,
                    'email' => $assignment->user->email,
                    'position' => $assignment->user->position,
                    'role' => $assignment->role->display_name,
                ])
                ->all(),
        ]);
    }

    public function edit(ServiceUnit $serviceUnit): Response
    {
        $this->authorize('update', $serviceUnit);

        return Inertia::render('admin/service-units/edit', [
            'serviceUnit' => [
                'id' => $serviceUnit->id,
                'code' => $serviceUnit->code,
                'name' => $serviceUnit->name,
                'description' => $serviceUnit->description,
                'is_active' => $serviceUnit->is_active,
            ],
        ]);
    }

    public function update(ServiceUnitRequest $request, ServiceUnit $serviceUnit): RedirectResponse
    {
        $this->authorize('update', $serviceUnit);

        $serviceUnit->update($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengubah data unit layanan {$serviceUnit->name}",
            $serviceUnit,
            properties: ['changed' => array_keys($serviceUnit->getChanges())],
            serviceUnit: $serviceUnit,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Unit layanan berhasil diperbarui.']);

        return to_route('admin.service-units.index');
    }

    public function destroy(ServiceUnit $serviceUnit): RedirectResponse
    {
        $this->authorize('delete', $serviceUnit);

        $name = $serviceUnit->name;
        $serviceUnit->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus unit layanan {$name}",
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Unit layanan berhasil dihapus. Unit pembangkit di bawahnya kini tanpa induk.',
        ]);

        return to_route('admin.service-units.index');
    }
}
