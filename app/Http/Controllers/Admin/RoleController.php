<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Enums\PermissionGroup;
use App\Enums\RoleScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        return Inertia::render('admin/roles/index', [
            'roles' => Role::query()
                ->withCount(['permissions', 'assignments'])
                ->orderByDesc('is_system')
                ->orderBy('display_name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'scope' => $role->scope->value,
                    'scope_label' => $role->scope->label(),
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'permissions_count' => $role->permissions_count,
                    'assignments_count' => $role->assignments_count,
                ])
                ->all(),
            'permissionTotal' => Permission::query()->count(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('admin/roles/create', [
            'permissionGroups' => $this->permissionGroups(),
            'scopes' => $this->scopes(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();
        $permissions = $data['permissions'];
        unset($data['permissions']);

        $role = Role::query()->create([...$data, 'is_system' => false]);
        $role->permissions()->sync($this->permissionIds($permissions));

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Membuat role {$role->display_name}",
            $role,
            properties: ['permissions' => $permissions],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role berhasil dibuat.']);

        return to_route('admin.roles.index');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('admin/roles/edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'scope' => $role->scope->value,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions()->pluck('name')->all(),
            ],
            'permissionGroups' => $this->permissionGroups(),
            'scopes' => $this->scopes(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();
        $permissions = $data['permissions'];
        unset($data['permissions']);

        $role->update($data);

        $before = $role->permissions()->pluck('name')->all();
        $role->permissions()->sync($this->permissionIds($permissions));

        $this->activityLogger->log(
            ActivityEvent::PermissionsUpdated,
            "Mengubah permission role {$role->display_name}",
            $role,
            properties: [
                'added' => array_values(array_diff($permissions, $before)),
                'removed' => array_values(array_diff($before, $permissions)),
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role berhasil diperbarui.']);

        return to_route('admin.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        /**
         * A structural invariant, not a permission: system roles belong to the
         * organisation's shape, so even the Super Admin gate bypass must not
         * remove them.
         */
        abort_if($role->is_system, 403, 'Role sistem tidak dapat dihapus.');

        $name = $role->display_name;
        $role->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus role {$name}");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Role berhasil dihapus.']);

        return to_route('admin.roles.index');
    }

    /**
     * The permission catalogue arranged for the matrix editor.
     *
     * @return list<array<string, mixed>>
     */
    private function permissionGroups(): array
    {
        return Permission::query()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Permission $permission): string => $permission->group->value)
            ->map(fn ($permissions, string $group): array => [
                'value' => $group,
                'label' => PermissionGroup::from($group)->label(),
                'permissions' => $permissions->map(fn (Permission $permission): array => [
                    'name' => $permission->name,
                    'label' => $permission->display_name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function scopes(): array
    {
        return collect(RoleScope::cases())
            ->map(fn (RoleScope $scope): array => [
                'value' => $scope->value,
                'label' => $scope->label(),
            ])
            ->all();
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function permissionIds(array $names): array
    {
        return Permission::query()->whereIn('name', $names)->pluck('id')->all();
    }
}
