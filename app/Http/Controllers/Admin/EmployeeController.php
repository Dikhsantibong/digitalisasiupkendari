<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use EmbedsReportLogo;
    use RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $employees = $this->filteredQuery($request)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/employees/index', [
            'employees' => $employees->through(fn (Employee $employee): array => $this->presentEmployee($employee)),
            'filters' => $request->only(['search', 'unit_id', 'service_unit_id', 'position', 'regu']),
            'options' => $this->options($request),
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $this->authorize('viewAny', Employee::class);

        [$table, $unitHeaderName, $filenameBase] = $this->buildExportPayload($request);

        $html = view('admin.export-table-pdf', [
            'table' => $table,
            'unitHeaderName' => $unitHeaderName,
        ])->render();

        return $this->streamReportPdf(
            $request,
            'k3.input.export-raw',
            ['html' => $this->embedAssets($html)],
            $filenameBase.'.pdf',
            'landscape',
        );
    }

    public function exportData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        [$table, $unitHeaderName, $filenameBase] = $this->buildExportPayload($request);

        return response()->json([
            'filename' => $filenameBase.'.xlsx',
            'unit_header' => $unitHeaderName,
            'tables' => [$table],
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

        return back(fallback: route('admin.employees.index'));
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
            'regu' => $employee->regu,
            'is_shift_leader' => $employee->is_shift_leader,
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

    /**
     * @return Builder<Employee>
     */
    private function filteredQuery(Request $request): Builder
    {
        return Employee::query()
            ->visibleTo($request->user())
            ->with(['unit:id,name', 'serviceUnit:id,name', 'user:id,name,email'])
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($request->input('unit_id'), fn (Builder $query, $id) => $query->where('unit_id', $id))
            ->when($request->input('service_unit_id'), fn (Builder $query, $id) => $query->where('service_unit_id', $id))
            ->when($request->input('position'), fn (Builder $query, $pos) => $query->where('position', $pos))
            ->when($request->input('regu'), function (Builder $query, string $regu): void {
                if ($regu === 'leader') {
                    $query->where('is_shift_leader', true)->whereNotNull('regu');
                } else {
                    $query->where('regu', $regu);
                }
            })
            ->orderBy('name');
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string}
     */
    private function buildExportPayload(Request $request): array
    {
        $employees = $this->filteredQuery($request)->get();
        $unit = $request->filled('unit_id')
            ? Unit::query()->find($request->integer('unit_id'))
            : null;
        $serviceUnit = $request->filled('service_unit_id')
            ? ServiceUnit::query()->find($request->integer('service_unit_id'))
            : null;

        $scopeLabel = $unit?->name ?? $serviceUnit?->name ?? 'Semua Unit UP Kendari';
        $unitHeaderName = strtoupper($unit?->name ?? $serviceUnit?->name ?? 'PT PLN NUSANTARA POWER UP KENDARI');

        $meta = [
            ['Total Pegawai', $employees->count().' orang'],
        ];
        if ($request->filled('position')) {
            $meta[] = ['Filter Jabatan', (string) $request->input('position')];
        }
        if ($request->filled('regu')) {
            $reguVal = (string) $request->input('regu');
            $meta[] = ['Filter Regu', $reguVal === 'leader' ? 'Leader Shift' : 'Regu '.$reguVal];
        }
        if ($request->filled('search')) {
            $meta[] = ['Pencarian', (string) $request->string('search')->trim()];
        }

        $rows = [];
        foreach ($employees as $index => $employee) {
            $placement = $employee->serviceUnit
                ? 'UL '.$employee->serviceUnit->name
                : ($employee->unit?->name ?? '-');
            $reguLabel = $employee->regu
                ? 'Regu '.$employee->regu.($employee->is_shift_leader ? ' (Leader Shift)' : '')
                : '-';

            $rows[] = [
                'kind' => 'data',
                'cells' => [
                    (string) ($index + 1),
                    $employee->name,
                    $employee->nip ?: '-',
                    $placement,
                    $employee->position ?: '-',
                    $reguLabel,
                    ! empty($employee->signature_path) ? 'Ada' : 'Belum ada',
                    $employee->is_active ? 'Aktif' : 'Nonaktif',
                ],
            ];
        }

        $table = [
            'key' => 'employees',
            'title' => 'DATA MASTER PEGAWAI',
            'document_number' => '',
            'unit' => $scopeLabel,
            'period' => now()->format('d/m/Y'),
            'meta' => $meta,
            'columns' => [
                ['label' => 'No', 'width' => 6, 'align' => 'c'],
                ['label' => 'Nama Pegawai', 'width' => 28, 'align' => 'l'],
                ['label' => 'NID / NIP', 'width' => 24, 'align' => 'l'],
                ['label' => 'Penempatan / Unit', 'width' => 26, 'align' => 'l'],
                ['label' => 'Jabatan', 'width' => 24, 'align' => 'l'],
                ['label' => 'Regu Shift', 'width' => 18, 'align' => 'c'],
                ['label' => 'Tanda Tangan', 'width' => 14, 'align' => 'c'],
                ['label' => 'Status', 'width' => 12, 'align' => 'c'],
            ],
            'rows' => $rows,
            'has_data' => $rows !== [],
            'saved' => true,
            'dense' => false,
        ];

        $slug = Str::slug($scopeLabel, '_');

        return [$table, $unitHeaderName, "Master_Pegawai_{$slug}"];
    }
}
