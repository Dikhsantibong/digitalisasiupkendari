<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            ->with(['unit:id,name', 'serviceUnit:id,name', 'user:id,name,email'])
            ->when($request->string('search')->trim()->value(), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($request->input('unit_id'), fn ($query, $id) => $query->where('unit_id', $id))
            ->when($request->input('service_unit_id'), fn ($query, $id) => $query->where('service_unit_id', $id))
            ->when($request->input('position'), fn ($query, $pos) => $query->where('position', $pos))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/employees/index', [
            'employees' => $employees->through(fn (Employee $employee): array => $this->presentEmployee($employee)),
            'filters' => $request->only(['search', 'unit_id', 'service_unit_id', 'position']),
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

        $attributes = $request->validatedAttributes();

        if ($request->hasFile('signature')) {
            $attributes['signature_path'] = $request->file('signature')->store('signatures', 'public');
        } elseif ($request->filled('signature_base64')) {
            $base64 = (string) $request->input('signature_base64');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64)) {
                $imageData = base64_decode(substr($base64, strpos($base64, ',') + 1));
                $filename = 'signatures/'.Str::random(40).'.png';
                Storage::disk('public')->put($filename, $imageData);
                $attributes['signature_path'] = $filename;
            }
        }

        $employee = Employee::query()->create($attributes);

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
            'employee' => $this->presentEmployee($employee->load('user:id,name,email')),
            'options' => $this->options($request),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $attributes = $request->validatedAttributes();

        if ($request->boolean('remove_signature')) {
            if ($employee->signature_path) {
                Storage::disk('public')->delete($employee->signature_path);
            }
            $attributes['signature_path'] = null;
        } elseif ($request->hasFile('signature')) {
            if ($employee->signature_path) {
                Storage::disk('public')->delete($employee->signature_path);
            }
            $attributes['signature_path'] = $request->file('signature')->store('signatures', 'public');
        } elseif ($request->filled('signature_base64')) {
            $base64 = (string) $request->input('signature_base64');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64)) {
                if ($employee->signature_path) {
                    Storage::disk('public')->delete($employee->signature_path);
                }
                $imageData = base64_decode(substr($base64, strpos($base64, ',') + 1));
                $filename = 'signatures/'.Str::random(40).'.png';
                Storage::disk('public')->put($filename, $imageData);
                $attributes['signature_path'] = $filename;
            }
        }

        $employee->update($attributes);

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

        if ($employee->signature_path) {
            Storage::disk('public')->delete($employee->signature_path);
        }

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
            'division' => $employee->division,
            'user_id' => $employee->user_id,
            'user' => $employee->relationLoaded('user') && $employee->user ? "{$employee->user->name} ({$employee->user->email})" : null,
            'is_active' => $employee->is_active,
            'unit_id' => $employee->unit_id,
            'unit' => $employee->relationLoaded('unit') ? $employee->unit?->name : null,
            'service_unit_id' => $employee->service_unit_id,
            'service_unit' => $employee->relationLoaded('serviceUnit') ? $employee->serviceUnit?->name : null,
            'signature_url' => $employee->signatureUrl(),
            'has_signature' => ! empty($employee->signature_path),
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
            'service_units' => ServiceUnit::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'positions' => Employee::query()
                ->visibleTo($request->user())
                ->whereNotNull('position')
                ->where('position', '!=', '')
                ->distinct()
                ->pluck('position')
                ->merge(array_column(EmployeePosition::cases(), 'value'))
                ->unique()
                ->sort()
                ->values()
                ->all(),
            // Login accounts an employee can be linked to (User → Employee), for
            // verifying & signing the Laporan Pembangkit.
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => ['id' => $user->id, 'name' => "{$user->name} ({$user->email})"])
                ->all(),
        ];
    }
}
