<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The TL Logistik & Gudang and TL PdM roles were dropped (user, 2026-11-09):
 * the migration renames them to the Koordinator roles in place, keeping every
 * assigned account and the permissions customised in Role & Akses.
 */
class KoordinatorRoleMigrationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_the_tl_roles_become_the_koordinator_roles_with_their_accounts_and_permissions(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->create();

        // An existing database: the old roles, one customised, each with an account.
        foreach (['logistik' => RoleName::KoordinatorLogistik, 'pdm' => RoleName::KoordinatorPdm] as $old => $new) {
            Role::query()->where('name', $new->value)->update(['name' => "tl_{$old}", 'display_name' => 'TL lama']);
        }
        $legacy = Role::query()->where('name', 'tl_logistik')->sole();
        $extra = Permission::query()->where('name', 'operasi.laporan.view')->sole();
        $legacy->permissions()->attach($extra->id);
        $user = User::factory()->create();
        $user->roleAssignments()->create(['role_id' => $legacy->id, 'unit_id' => $unit->id]);

        (require database_path('migrations/2026_11_09_125110_rename_tl_logistik_and_tl_pdm_roles_to_koordinator.php'))->up();

        $this->assertFalse(Role::query()->whereIn('name', ['tl_logistik', 'tl_pdm'])->exists());
        $role = Role::query()->where('name', RoleName::KoordinatorLogistik->value)->sole();
        $this->assertSame($legacy->id, $role->id);
        $this->assertSame('Koordinator Logistik & Gudang', $role->display_name);
        $this->assertTrue($role->permissions()->whereKey($extra->id)->exists(), 'Role & Akses customisations are kept.');
        $user = $user->fresh();
        $user->forgetAccessCache();
        $this->assertTrue($user->hasRole(RoleName::KoordinatorLogistik));
        $this->assertTrue($user->hasPermissionTo('logistik.input.write'));
        $this->assertSame('Koordinator PdM & Maturity Level', Role::query()->where('name', RoleName::KoordinatorPdm->value)->value('display_name'));
    }
}
