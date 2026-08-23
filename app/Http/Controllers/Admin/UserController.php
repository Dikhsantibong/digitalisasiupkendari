<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccessControl;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewer = $request->user();

        $users = User::query()
            ->with(['roleAssignments.role:id,display_name', 'roleAssignments.unit:id,name', 'roleAssignments.serviceUnit:id,name'])
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
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('is_active', $request->input('status') === 'active');
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users->through(fn (User $user): array => $this->presentUser($user)),
            'filters' => $request->only(['search', 'role_id', 'unit_id', 'status']),
            'options' => $this->options($request),
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

        $data = $request->validated();
        $data['email_verified_at'] = now();

        $user = User::query()->create($data);

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

        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

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

        return to_route('admin.users.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'employee_id' => $user->employee_id,
            'email' => $user->email,
            'position' => $user->position,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
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
}
