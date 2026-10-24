<?php

namespace Tests\Feature\Operasi;

use App\Enums\MesinHarianJenis;
use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperasiMesinHarian;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The operators of every unit — the non-containerized PLTD Poasia as much as
 * the containerized one — fill the Pengusahaan Operasi inputs
 * (operasi.pengusahaan.input), while the Berita Acara and the Laporan
 * Pengusahaan (and its approval) stay with TL & Staf Operasi.
 */
class OperatorPengusahaanAccessTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Poasia', 'code' => 'PLTD-POASIA', 'is_active' => true]);
        Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'merk' => 'MAK', 'capacity_kw' => 2544, 'is_active' => true]);
    }

    /**
     * @return array{unit_id: int, month: int, year: int}
     */
    private function period(): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026];
    }

    public function test_the_operator_and_the_project_leader_hold_the_pengusahaan_input_permission_only(): void
    {
        foreach ([RoleName::Operator, RoleName::ProjectLeaderOperasi] as $role) {
            $user = $this->userWithRole($role, $this->unit);

            $this->assertTrue($user->hasPermissionTo('operasi.pengusahaan.input'), $role->value);
            $this->assertFalse($user->hasPermissionTo('operasi.pengusahaan.view'), $role->value);
            $this->assertFalse($user->hasPermissionTo('operasi.pengusahaan.write'), $role->value);
        }
    }

    public function test_the_operator_opens_every_pengusahaan_operasi_input_page(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $pages = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'operasi.pengusahaan.') && str_ends_with($name, '.index')
                && $name !== 'operasi.pengusahaan.index' && ! str_starts_with($name, 'operasi.pengusahaan.berita-acara.'))
            ->values();

        $this->assertGreaterThan(25, $pages->count());

        foreach ($pages as $name) {
            $this->actingAs($operator)->get(route($name, $this->period()))->assertOk();
        }

        $this->actingAs($operator)->get(route('operasi.pengusahaan.index', ['section' => 'input']))->assertOk();
        $this->actingAs($operator)->get(route('operasi.pengusahaan.tara-kalor.index', $this->period()))
            ->assertInertia(fn ($page) => $page->where('can_write', true));
    }

    public function test_the_operator_saves_a_pengusahaan_input(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $machine = Machine::query()->firstOrFail();

        $this->actingAs($operator)->post(route('operasi.pengusahaan.tara-kalor.store'), $this->period() + [
            'readings' => [$machine->id => ['1' => 2618, '2' => 2623]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, OperasiMesinHarian::query()->where('unit_id', $this->unit->id)->where('jenis', MesinHarianJenis::TaraKalor)->count());
    }

    public function test_the_berita_acara_and_the_laporan_pengusahaan_stay_with_tl_and_staf(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        $this->actingAs($operator)->get(route('operasi.pengusahaan.index', ['section' => 'formulir']))->assertForbidden();
        $this->actingAs($operator)->get(route('operasi.pengusahaan.berita-acara.index', $this->period()))->assertForbidden();
        $this->actingAs($operator)->get(route('operasi.laporan.pengusahaan.edit', $this->period()))->assertForbidden();
        $this->actingAs($operator)->post(route('operasi.laporan.pengusahaan.store'), $this->period() + ['format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();

        // An operator of another unit cannot fill this unit's inputs.
        $elsewhere = $this->userWithRole(RoleName::Operator, Unit::factory()->create(['is_active' => true]));
        $this->actingAs($elsewhere)->post(route('operasi.pengusahaan.tara-kalor.store'), $this->period() + ['readings' => []])->assertForbidden();

        // TL Operasi keeps everything.
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.berita-acara.index', $this->period()))->assertOk();
        $this->actingAs($tl)->get(route('operasi.pengusahaan.index', ['section' => 'formulir']))->assertOk();
    }

    public function test_a_containerized_unit_closes_the_pengusahaan_inputs_to_its_operators_and_super_admin_can_change_it(): void
    {
        $container = Unit::factory()->forServiceUnit($this->unit->serviceUnit)->create(['name' => 'PLTD Poasia Containerized', 'code' => 'PLTD-POASIA-CONT', 'is_active' => true, 'operator_pengusahaan' => false]);
        $period = ['unit_id' => $container->id, 'month' => 9, 'year' => 2026];
        $operator = $this->userWithRole(RoleName::Operator, $container);

        // No unit of theirs allows it: the permission is not even offered (menus hidden) and the pages refuse.
        $this->assertFalse($operator->hasPermissionTo('operasi.pengusahaan.input'));
        $this->actingAs($operator)->get(route('operasi.pengusahaan.tara-kalor.index', $period))->assertForbidden();
        $this->actingAs($operator)->get(route('operasi.pengusahaan.index', ['section' => 'input']))->assertForbidden();
        $this->actingAs($operator)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($names): bool => ! collect($names)->contains('operasi.pengusahaan.input')));

        // A user of both units: open for PLTD Poasia, closed for the containerized one.
        $both = $this->userWithRole(RoleName::Operator, $this->unit);
        $both->assignRole(RoleName::Operator, $container);
        $both = $both->fresh();
        $this->actingAs($both)->get(route('operasi.pengusahaan.tara-kalor.index', $this->period()))->assertOk();
        $this->actingAs($both)->get(route('operasi.pengusahaan.tara-kalor.index', $period))->assertForbidden();
        $this->actingAs($both)->post(route('operasi.pengusahaan.tara-kalor.store'), $period + ['readings' => []])->assertForbidden();

        // TL Operasi of the containerized unit is not affected.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $container))->get(route('operasi.pengusahaan.tara-kalor.index', $period))->assertOk();

        // Super Admin switches it on in Master Unit: the operator gets the inputs.
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $this->actingAs($admin)->put(route('admin.units.update', $container), [
            'code' => $container->code, 'name' => $container->name, 'type' => $container->type->value, 'status' => $container->status->value,
            'is_active' => '1', 'operator_pengusahaan' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($container->fresh()->operator_pengusahaan);

        $operator = $operator->fresh();
        $operator->forgetAccessCache();
        $this->assertTrue($operator->hasPermissionTo('operasi.pengusahaan.input'));
        $this->actingAs($operator)->get(route('operasi.pengusahaan.tara-kalor.index', $period))->assertOk();
    }
}
