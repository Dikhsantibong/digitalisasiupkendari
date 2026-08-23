---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Super Admin bypasses every policy — guard invariants in the controller
`AuthServiceProvider` registers `Gate::before` granting Super Admin everything, so any rule written inside a policy is invisible to them.

Structural invariants (system roles cannot be deleted, a user cannot delete their own account) must therefore be enforced with an explicit `abort_if(...)` in the controller, alongside — not inside — the `authorize()` call. See `RoleController::destroy` and `UserController::destroy`.

The bypass is deliberate: it means permissions added by future modules reach Super Admin without re-seeding.
