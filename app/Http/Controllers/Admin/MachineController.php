<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MachineRequest;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MachineController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Machine::class);

        $machines = Machine::query()
            ->visibleTo($request->user())
            ->with('unit:id,name')
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%");
                });
            })
            ->when($request->input('unit_id'), fn ($query, $id) => $query->where('unit_id', $id))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/machines/index', [
            'machines' => $machines->through(fn (Machine $machine): array => $this->presentMachine($machine)),
            'filters' => $request->only(['search', 'unit_id']),
            'options' => $this->options($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Machine::class);

        return Inertia::render('admin/machines/create', [
            'options' => $this->options($request),
        ]);
    }

    public function store(MachineRequest $request): RedirectResponse
    {
        $this->authorize('create', Machine::class);

        $machine = Machine::query()->create($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah mesin {$machine->name}",
            $machine,
            unit: $machine->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mesin berhasil ditambahkan.']);

        return to_route('admin.machines.index');
    }

    public function edit(Request $request, Machine $machine): Response
    {
        $this->authorize('update', $machine);

        return Inertia::render('admin/machines/edit', [
            'machine' => $this->presentMachine($machine),
            'options' => $this->options($request),
        ]);
    }

    public function update(MachineRequest $request, Machine $machine): RedirectResponse
    {
        $this->authorize('update', $machine);

        $machine->update($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengubah data mesin {$machine->name}",
            $machine,
            properties: ['changed' => array_keys($machine->getChanges())],
            unit: $machine->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mesin berhasil diperbarui.']);

        return to_route('admin.machines.index');
    }

    public function destroy(Machine $machine): RedirectResponse
    {
        $this->authorize('delete', $machine);

        $name = $machine->name;
        $machine->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus mesin {$name}",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mesin berhasil dihapus.']);

        return to_route('admin.machines.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMachine(Machine $machine): array
    {
        return [
            'id' => $machine->id,
            'name' => $machine->name,
            'type' => $machine->type,
            'serial_number' => $machine->serial_number,
            'capacity_kw' => $machine->capacity_kw,
            'is_active' => $machine->is_active,
            'unit_id' => $machine->unit_id,
            'unit' => $machine->relationLoaded('unit') ? $machine->unit?->name : null,
        ];
    }

    /**
     * Reference data for the machine forms and filters.
     *
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'units' => Unit::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ];
    }
}
