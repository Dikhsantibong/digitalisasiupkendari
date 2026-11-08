<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\UnitAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Akun Unit: one account per unit that opens every divisi but only sees and
 * fills its own unit's data.
 */
class UnitAccountTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_every_unit_gets_one_account_for_all_divisions_of_that_unit_only(): void
    {
        $this->seedAccessControl();
        $poasia = Unit::factory()->create(['code' => 'PLTD-POASIA', 'name' => 'PLTD Poasia', 'is_active' => true]);
        $kolaka = Unit::factory()->create(['code' => 'PLTD-KOLAKA', 'name' => 'PLTD Kolaka', 'is_active' => true]);

        $this->seed(UnitAccountSeeder::class);
        $this->seed(UnitAccountSeeder::class);

        $account = User::query()->where('email', 'unit.pltd-poasia@upkendari.co.id')->sole();
        $this->assertSame(2, User::query()->where('email', 'like', 'unit.%')->count(), 'Re-running keeps one account per unit.');
        $this->assertTrue($account->hasRole(RoleName::AkunUnit));
        $this->assertSame([$poasia->id], Unit::query()->visibleTo($account)->pluck('id')->all());

        // Every divisi opens, on its own unit.
        foreach ([
            'operasi.input.kondisi-abnormal.index',
            'operasi.pengusahaan.jam-pembangkit.index',
            'operator.logsheet.index',
            'har.input.material-peralatan.index',
            'har.pengusahaan.rencana-realisasi.index',
            'k3.input.accident.index',
            'logistik.input.rekomendasi.index',
            'pdm.input.permit-to-work.index',
        ] as $route) {
            $this->actingAs($account)->get(route($route))->assertOk();
        }

        $this->actingAs($account)->get(route('operasi.input.kondisi-abnormal.index'))
            ->assertInertia(fn ($page) => $page->where('unit.id', $poasia->id)->where('options.units', fn ($units): bool => collect($units)->pluck('id')->all() === [$poasia->id])->where('can_write', true));

        // Another unit's data stays closed.
        $this->actingAs($account)->get(route('har.input.material-peralatan.index', ['unit_id' => $kolaka->id]))->assertForbidden();
        $this->actingAs($account)->post(route('operasi.input.kondisi-abnormal.store'), [
            'unit_id' => $kolaka->id, 'tanggal' => '2026-11-08', 'jenis' => 'gangguan', 'uraian_kondisi' => 'Trip',
        ])->assertForbidden();

        // No user or role management.
        $this->actingAs($account)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_the_super_admin_raises_a_unit_account_to_admin_ul_of_its_own_ul(): void
    {
        $this->seedAccessControl();
        $ulPoasia = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $ulKolaka = ServiceUnit::factory()->create(['name' => 'UL PLTD Kolaka']);
        $poasia = Unit::factory()->forServiceUnit($ulPoasia)->create(['code' => 'PLTD-POASIA', 'name' => 'PLTD Poasia', 'is_active' => true]);
        $container = Unit::factory()->forServiceUnit($ulPoasia)->create(['code' => 'PLTD-POASIA-CONT', 'name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $kolaka = Unit::factory()->forServiceUnit($ulKolaka)->create(['code' => 'PLTD-KOLAKA', 'name' => 'PLTD Kolaka', 'is_active' => true]);
        $noUl = Unit::factory()->create(['code' => 'PLTD-WUAWUA', 'name' => 'PLTD Wua-Wua', 'service_unit_id' => null, 'is_active' => true]);
        $this->seed(UnitAccountSeeder::class);

        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $account = User::query()->where('email', 'unit.pltd-poasia@upkendari.co.id')->sole();
        $adminUl = Role::query()->where('name', RoleName::AdminUl->value)->sole();
        $assign = fn (User $user, int $serviceUnitId) => $this->actingAs($admin)->post(route('admin.users.assignments.store', $user), ['role_id' => $adminUl->id, 'service_unit_id' => $serviceUnitId]);

        // Only the UL of the account's own unit.
        $assign($account, $ulKolaka->id)->assertSessionHasErrors('service_unit_id');
        $assign(User::query()->where('email', 'unit.pltd-wuawua@upkendari.co.id')->sole(), $ulPoasia->id)->assertSessionHasErrors('role_id');
        $assign($account, $ulPoasia->id)->assertSessionHasNoErrors();

        $account->refresh()->forgetAccessCache();
        $this->assertEqualsCanonicalizing([$poasia->id, $container->id], Unit::query()->visibleTo($account)->pluck('id')->all());
        $this->actingAs($account)->get(route('har.input.material-peralatan.index', ['unit_id' => $container->id]))->assertOk();
        $this->actingAs($account)->get(route('har.input.material-peralatan.index', ['unit_id' => $kolaka->id]))->assertForbidden();
        $this->assertNotNull($noUl);
    }
}
