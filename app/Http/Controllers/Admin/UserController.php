<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Employee;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\ActivityLogger;
use App\Services\Operator\AttendanceRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use EmbedsReportLogo;
    use RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = $this->filteredQuery($request)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users->through(fn (User $user): array => $this->presentUser($user)),
            'filters' => $request->only(['search', 'role_id', 'unit_id', 'regu', 'status']),
            'options' => $this->options($request),
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $this->authorize('viewAny', User::class);

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
        $this->authorize('viewAny', User::class);

        [$table, $unitHeaderName, $filenameBase, $accounts] = $this->buildExportPayload($request);

        return response()->json([
            'filename' => $filenameBase.'.xlsx',
            'unit_header' => $unitHeaderName,
            'tables' => [$table],
            'accounts' => $accounts,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('admin/users/create', [
            'options' => $this->options($request),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = Arr::except($request->validated(), ['regu', 'is_shift_leader']);
        $data['email_verified_at'] = now();

        $user = User::query()->create($data);
        $this->syncEmployeeShift($user, $request);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah pengguna {$user->name}",
            $user,
            properties: ['email' => $user->email],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengguna berhasil dibuat. Lanjutkan dengan menugaskan role.',
        ]);

        return to_route('admin.users.edit', $user);
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('admin/users/show', [
            'user' => $this->presentUser($user->load([
                'employee:id,user_id,unit_id,nip,regu,is_shift_leader',
                'roleAssignments.role:id,display_name,scope',
                'roleAssignments.unit:id,name',
                'roleAssignments.serviceUnit:id,name',
            ])),
            'permissions' => app(AccessControl::class)->permissionNames($user)->sort()->values()->all(),
            'activity' => $user->activityLogs()
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($log): array => [
                    'id' => $log->id,
                    'event_label' => $log->event->label(),
                    'tone' => $log->event->tone(),
                    'description' => $log->description,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('admin/users/edit', [
            'user' => $this->presentUser($user->load([
                'employee:id,user_id,unit_id,nip,regu,is_shift_leader',
                'roleAssignments.role:id,display_name,scope',
                'roleAssignments.unit:id,name',
                'roleAssignments.serviceUnit:id,name',
            ])),
            'options' => $this->options($request),
            'canAssignRoles' => $request->user()->can('assign', Role::class),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = Arr::except($request->validated(), ['regu', 'is_shift_leader']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);
        $this->syncEmployeeShift($user, $request);

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengubah data pengguna {$user->name}",
            $user,
            properties: ['changed' => array_keys($user->getChanges())],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pengguna berhasil diperbarui.']);

        return to_route('admin.users.edit', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        /**
         * Guarded outside the policy so the Super Admin gate bypass cannot lock
         * the organisation out of its own administration.
         */
        abort_if($request->user()->is($user), 403, 'Anda tidak dapat menghapus akun sendiri.');

        $name = $user->name;
        $user->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus pengguna {$name}");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengguna berhasil dihapus.']);

        return back(fallback: route('admin.users.index'));
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        $employee = $user->relationLoaded('employee') ? $user->employee : null;
        if ($employee === null && filled($user->employee_id)) {
            $employee = Employee::query()
                ->where('nip', $user->employee_id)
                ->first(['id', 'user_id', 'unit_id', 'nip', 'regu', 'is_shift_leader']);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'employee_id' => $user->employee_id,
            'email' => $user->email,
            'position' => $user->position,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'regu' => $employee?->regu,
            'is_shift_leader' => (bool) ($employee?->is_shift_leader ?? false),
            'employee_record_id' => $employee?->id,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'assignments' => $user->relationLoaded('roleAssignments')
                ? $user->roleAssignments->map(fn (RoleAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'role_id' => $assignment->role_id,
                    'role' => $assignment->role->display_name,
                    'scope_label' => $assignment->locationLabel(),
                ])->all()
                : [],
        ];
    }

    /**
     * Synchronise the user's shift regu and Leader Shift flag with their linked
     * Master Pegawai ({@see Employee}) record when submitted from UserForm.
     */
    private function syncEmployeeShift(User $user, Request $request): void
    {
        if (! $request->hasAny(['regu', 'is_shift_leader'])) {
            return;
        }

        $rawRegu = $request->input('regu');
        $regu = is_string($rawRegu) && in_array($rawRegu, AttendanceRoster::REGU, true) ? $rawRegu : null;
        $isShiftLeader = $regu !== null && $request->boolean('is_shift_leader');

        $employee = $user->employee
            ?? (filled($user->employee_id)
                ? Employee::query()->where('nip', $user->employee_id)->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))->first()
                : null);

        $unitId = $employee?->unit_id ?? $user->roleAssignments()->whereNotNull('unit_id')->value('unit_id');

        if ($employee !== null) {
            $employee->fill([
                'user_id' => $user->id,
                'unit_id' => $unitId,
                'name' => $user->name,
                'nip' => $user->employee_id ?: $employee->nip,
                'regu' => $regu,
                'is_shift_leader' => $isShiftLeader,
                'is_active' => $user->is_active,
            ]);
            $employee->save();

            return;
        }

        if ($regu !== null) {
            Employee::query()->create([
                'user_id' => $user->id,
                'unit_id' => $unitId,
                'name' => $user->name,
                'nip' => $user->employee_id,
                'position' => $user->position ?: 'Operator',
                'regu' => $regu,
                'is_shift_leader' => $isShiftLeader,
                'is_active' => $user->is_active,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'roles' => Role::query()
                ->orderBy('display_name')
                ->get(['id', 'name', 'display_name', 'scope'])
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'scope' => $role->scope->value,
                    'scope_label' => $role->scope->label(),
                ])
                ->all(),
            'serviceUnits' => ServiceUnit::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
            'units' => Unit::query()
                ->visibleTo($request->user())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->all(),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function filteredQuery(Request $request): Builder
    {
        $viewer = $request->user();

        return User::query()
            ->with([
                'employee:id,user_id,unit_id,nip,regu,is_shift_leader',
                'roleAssignments.role:id,display_name',
                'roleAssignments.unit:id,name',
                'roleAssignments.serviceUnit:id,name',
            ])
            ->unless($viewer->hasGlobalAccess(), function (Builder $query) use ($viewer): void {
                $query->whereHas('roleAssignments', function (Builder $query) use ($viewer): void {
                    $query->whereIn('unit_id', $viewer->accessibleUnitIds())
                        ->orWhereIn('service_unit_id', $viewer->accessibleServiceUnitIds());
                });
            })
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_id', 'like', "%{$search}%");
                });
            })
            ->when($request->input('role_id'), function (Builder $query, $roleId): void {
                $query->whereHas('roleAssignments', fn (Builder $query) => $query->where('role_id', $roleId));
            })
            ->when($request->input('unit_id'), function (Builder $query, $unitId): void {
                $query->whereHas('roleAssignments', fn (Builder $query) => $query->where('unit_id', $unitId));
            })
            ->when($request->input('regu'), function (Builder $query, string $regu): void {
                if ($regu === 'leader') {
                    $query->whereHas('employee', fn (Builder $q) => $q->where('is_shift_leader', true)->whereNotNull('regu'));
                } elseif (in_array($regu, AttendanceRoster::REGU, true)) {
                    $query->whereHas('employee', fn (Builder $q) => $q->where('regu', $regu));
                }
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('is_active', $request->input('status') === 'active');
            })
            ->orderBy('name');
    }

    /**
     * @return array{0: array<string, mixed>, 1: string, 2: string, 3: list<array<string, mixed>>}
     */
    private function buildExportPayload(Request $request): array
    {
        $users = $this->filteredQuery($request)->get();
        $unit = $request->filled('unit_id')
            ? Unit::query()->find($request->integer('unit_id'))
            : null;
        $role = $request->filled('role_id')
            ? Role::query()->find($request->integer('role_id'))
            : null;

        $scopeLabel = $unit?->name ?? 'Semua Unit UP Kendari';
        $unitHeaderName = strtoupper($unit?->name ?? 'PT PLN NUSANTARA POWER UP KENDARI');

        $meta = [
            ['Total Pengguna', $users->count().' akun'],
        ];
        if ($role !== null) {
            $meta[] = ['Filter Role', $role->display_name];
        }
        if ($request->filled('regu')) {
            $reguVal = (string) $request->input('regu');
            $meta[] = ['Filter Regu', $reguVal === 'leader' ? 'Leader Shift' : 'Regu '.$reguVal];
        }
        if ($request->filled('status')) {
            $meta[] = ['Filter Status', $request->input('status') === 'active' ? 'Aktif' : 'Nonaktif'];
        }
        if ($request->filled('search')) {
            $meta[] = ['Pencarian', (string) $request->string('search')->trim()];
        }

        $rows = [];
        $accounts = [];
        foreach ($users as $index => $user) {
            $presented = $this->presentUser($user);
            $accounts[] = $presented;

            $assignmentText = collect($presented['assignments'])
                ->map(fn (array $a): string => "{$a['role']} · {$a['scope_label']}")
                ->implode('; ') ?: 'Belum ditugaskan';
            $reguLabel = $presented['regu']
                ? 'Regu '.$presented['regu'].($presented['is_shift_leader'] ? ' (Leader Shift)' : '')
                : '-';

            $rows[] = [
                'kind' => 'data',
                'cells' => [
                    (string) ($index + 1),
                    $user->name,
                    $user->email,
                    $user->employee_id ?: '-',
                    $assignmentText,
                    $reguLabel,
                    $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : '-',
                    $user->is_active ? 'Aktif' : 'Nonaktif',
                ],
            ];
        }

        $table = [
            'key' => 'users',
            'title' => 'DATA AKUN PENGGUNA KIT-SIPP',
            'document_number' => '',
            'unit' => $scopeLabel,
            'period' => now()->format('d/m/Y'),
            'meta' => $meta,
            'columns' => [
                ['label' => 'No', 'width' => 6, 'align' => 'c'],
                ['label' => 'Username / Nama', 'width' => 26, 'align' => 'l'],
                ['label' => 'Email', 'width' => 30, 'align' => 'l'],
                ['label' => 'NIP', 'width' => 22, 'align' => 'l'],
                ['label' => 'Penugasan & Role', 'width' => 32, 'align' => 'l'],
                ['label' => 'Regu Shift', 'width' => 16, 'align' => 'c'],
                ['label' => 'Login Terakhir', 'width' => 16, 'align' => 'c'],
                ['label' => 'Status', 'width' => 12, 'align' => 'c'],
            ],
            'rows' => $rows,
            'has_data' => $rows !== [],
            'saved' => true,
            'dense' => false,
        ];

        $slug = Str::slug($scopeLabel, '_');

        return [$table, $unitHeaderName, "Data_Pengguna_{$slug}", $accounts];
    }
}
