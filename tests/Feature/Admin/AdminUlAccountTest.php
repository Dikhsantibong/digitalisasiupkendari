<?php

namespace Tests\Feature\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUlAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * One Admin UL account per service unit (user, 2026-11-18): every unit of
 * its UL, the Laporan UL of its UL only; re-running keeps the password.
 */
class AdminUlAccountTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_each_ul_gets_an_admin_ul_account_that_opens_its_laporan_ul(): void
    {
        $this->seedAccessControl();
        $poasia = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $kolaka = ServiceUnit::factory()->create(['name' => 'UL PLTD Kolaka']);
        $mine = Unit::factory()->forServiceUnit($poasia)->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        Unit::factory()->forServiceUnit($poasia)->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $other = Unit::factory()->forServiceUnit($kolaka)->create(['name' => 'PLTD Kolaka', 'is_active' => true]);

        $this->seed(AdminUlAccountSeeder::class);

        $admin = User::query()->where('email', 'admin.ul-pltd-poasia@upkendari.co.id')->sole();
        $this->assertSame('Admin UL PLTD Poasia', $admin->name);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertTrue($admin->hasPermissionTo(PermissionName::LaporanUlView));
        $this->assertTrue($admin->canAccessUnit($mine));
        $this->assertFalse($admin->canAccessUnit($other));
        $this->assertSame(1, User::query()->where('email', 'admin.ul-pltd-kolaka@upkendari.co.id')->count());

        $this->actingAs($admin)->get(route('laporan-ul.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('ul.name', 'UL PLTD Poasia')->has('units', 2)->has('options.uls', 1));
        $this->actingAs($admin)->get(route('laporan-ul.index', ['ul_id' => $kolaka->id]))->assertForbidden();

        // Re-running keeps a changed password and does not duplicate the role.
        $admin->forceFill(['password' => Hash::make('rahasia-baru')])->save();
        $this->seed(AdminUlAccountSeeder::class);
        $admin->refresh();
        $this->assertTrue(Hash::check('rahasia-baru', $admin->password));
        $this->assertSame(1, $admin->roleAssignments()->whereHas('role', fn ($q) => $q->where('name', RoleName::AdminUl->value))->count());
    }
}
