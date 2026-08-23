---
paths:
  - 'app/**'
---

# App

## Authorise by permission, never by role name
Never branch on a role name (`$user->hasRole(...)`) to authorise a business action — check a permission or a policy instead (`$user->can('report_unit.approve', $unit)`). `hasRole()` exists only for presentation and the Super Admin gate bypass.

The permission catalogue lives in `App\Enums\PermissionName`; role→permission defaults live in `App\Enums\RoleName::defaultPermissions()`. A new module adds enum cases + mapping and re-runs `RolePermissionSeeder` — no migration, no new authorisation code.

Unit/UL scope is resolved only in `App\Services\AccessControl` (memoised per request) and applied to queries via the `visibleTo($user)` scope on Unit, ServiceUnit, and ActivityLog. Do not re-derive scope in a controller. Call `$user->forgetAccessCache()` after changing assignments or role permissions.
