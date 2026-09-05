<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->with('unit:id,name')
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($request->input('unit_id'), fn ($query, $id) => $query->where('unit_id', $id))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/employees/index', [
            'employees' => $employees->through(fn (Employee $employee): array => $this->presentEmployee($employee)),
            'filters' => $request->only(['search', 'unit_id']),
            'options' => $this->options($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('admin/employees/create', [
            'options' => $this->options($request),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $employee = Employee::query()->create($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah pegawai {$employee->name}",
            $employee,
            unit: $employee->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pegawai berhasil ditambahkan.']);

        return to_route('admin.employees.index');
    }

    public function edit(Request $request, Employee $employee): Response
    {
        $this->authorize('update', $employee);

        return Inertia::render('admin/employees/edit', [
            'employee' => $this->presentEmployee($employee),
            'options' => $this->options($request),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $employee->update($request->validatedAttributes());

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengubah data pegawai {$employee->name}",
            $employee,
            properties: ['changed' => array_keys($employee->getChanges())],
            unit: $employee->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pegawai berhasil diperbarui.']);

        return to_route('admin.employees.index');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('delete', $employee);

        $name = $employee->name;
        $employee->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus pegawai {$name}",
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pegawai berhasil dihapus.']);

        return to_route('admin.employees.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentEmployee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'nip' => $employee->nip,
            'position' => $employee->position,
            'is_active' => $employee->is_active,
            'unit_id' => $employee->unit_id,
            'unit' => $employee->relationLoaded('unit') ? $employee->unit?->name : null,
        ];
    }

    /**
     * Reference data for the employee forms and filters.
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
