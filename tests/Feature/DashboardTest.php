<?php

namespace Tests\Feature;

use App\Enums\PersediaanJenis;
use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\LogistikFormRow;
use App\Models\LubricantType;
use App\Models\OperasiPersediaan;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_super_admin_sees_every_module_scope(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('scope.operasi', true)
                ->where('scope.har', true)
                ->where('scope.k3', true)
                ->where('scope.logistik', true)
                ->where('scope.pdm', true)
                ->where('scope.units', true)
                ->missing('isDummy')
                // The module sections load right after the page, one deferred group each.
                ->missing('operasi')
                ->has('moduleHealth', 5)
                ->loadDeferredProps(['operasi', 'har', 'k3', 'logistik', 'pdm'], fn ($reload) => $reload
                    ->has('operasi.kpis', 5)
                    ->has('har.backlog')
                    ->has('k3.s_curve.points')
                    ->has('logistik.tanks')
                    ->has('pdm.vibration.series')),
            );
    }

    public function test_tl_operasi_dashboard_shows_only_operasi_module(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->create(['name' => 'PLTD Uji']);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.operasi', true)
                ->where('scope.har', false)
                ->where('scope.k3', false)
                ->where('scope.logistik', false)
                ->where('scope.pdm', false)
                ->where('har', null)
                ->where('k3', null)
                ->where('logistik', null)
                ->where('pdm', null)
                ->has('moduleHealth', 1)
                ->loadDeferredProps('operasi', fn ($reload) => $reload
                    ->has('operasi.daily.series', 3)
                    // Per-unit rows are labelled with the units the viewer can see.
                    ->where('operasi.units.0.unit', 'PLTD Uji')),
            );
    }

    public function test_tl_and_staf_operasi_see_the_operasi_dashboard_with_the_master_jenis(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->create(['name' => 'PLTD Uji']);
        $b30 = BbmType::factory()->create(['code' => 'B30', 'name' => 'Biosolar B30']);
        FuelTank::factory()->create(['unit_id' => $unit->id, 'bbm_type_id' => $b30->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        LubricantType::factory()->forUnit($unit)->create(['name' => 'Sallyx 420', 'is_active' => true]);

        foreach ([RoleName::TeamLeaderOperasi, RoleName::StafOperasi] as $role) {
            $this->actingAs($this->userWithRole($role, $unit))
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('scope.operasi', true)
                    ->has('moduleHealth', 1)
                    ->where('moduleHealth.0.caption', 'Kelengkapan input')
                    ->loadDeferredProps('operasi', fn ($reload) => $reload
                        // The jenis BBM and pelumas come from the master, not fixed HSD / MFO.
                        ->has('operasi.fuels', 1)
                        ->where('operasi.fuels.0.code', 'B30')
                        ->where('operasi.fuel_monthly.series.0.name', 'B30 (kL)')
                        ->where('operasi.pelumas.0.label', 'Sallyx 420')
                        // Real completeness of the Pengusahaan menus, with links.
                        ->where('operasi.completeness', fn ($rows): bool => collect($rows)->contains('label', 'Pemakaian Bahan Bakar')
                            && ! collect($rows)->contains('label', 'Star-Stop Mesin')
                            && str_contains((string) collect($rows)->firstWhere('label', 'Pemakaian Bahan Bakar')['url'], '/operasi/pengusahaan/pemakaian-bbm'))));
        }
    }

    public function test_tl_logistik_and_tl_pdm_dashboards_are_scoped_to_their_module(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('scope.logistik', true)
                ->where('operasi', null)
                ->where('pdm', null)
                ->loadDeferredProps('logistik', fn ($reload) => $reload->has('logistik.critical')),
            );

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('scope.pdm', true)
                ->where('logistik', null)
                ->where('har', null)
                ->loadDeferredProps('pdm', fn ($reload) => $reload->has('pdm.assets')),
            );
    }

    public function test_operator_dashboard_is_scoped_to_operasi_only(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('scope.operasi', true)
                ->where('scope.har', false)
                ->where('scope.k3', false)
                ->loadDeferredProps('operasi', fn ($reload) => $reload->has('operasi'))
                // HAR/K3 sections are withheld from the operator.
                ->where('har', null)
                ->where('k3', null),
            );
    }

    public function test_tl_k3_dashboard_shows_k3_not_har(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.k3', true)
                ->where('scope.har', false)
                ->where('har', null)
                ->loadDeferredProps('k3', fn ($reload) => $reload->has('k3.s_curve')),
            );
    }

    public function test_module_figures_are_read_from_the_inputs_of_the_visible_units_only(): void
    {
        $this->seedAccessControl();
        Carbon::setTestNow('2026-11-20 08:00:00');
        $unit = Unit::factory()->create(['name' => 'PLTD Uji']);
        $other = Unit::factory()->create(['name' => 'PLTD Lain']);
        $b30 = BbmType::factory()->create(['code' => 'B30', 'name' => 'Biosolar B30']);
        FuelTank::factory()->create(['unit_id' => $unit->id, 'bbm_type_id' => $b30->id, 'fuel_type' => TankFuelType::Hsd, 'capacity_liter' => 100000, 'is_active' => true]);

        // Persediaan BBM: 50.000 L received, saldo akhir 40.000 L of a 100.000 L tank.
        OperasiPersediaan::factory()->create([
            'unit_id' => $unit->id, 'jenis' => PersediaanJenis::Bbm, 'month' => 11, 'year' => 2026,
            'opening' => [], 'entries' => ['B30' => [3 => ['penerimaan' => 50000]]], 'closing' => ['B30' => 40000],
        ]);

        // Work orders of November: one open, one closed (+ one of another unit, not visible).
        $period = ReportPeriod::factory()->forUnit($unit)->create(['month' => 11, 'year' => 2026]);
        $open = WoStatus::factory()->create(['name' => 'In Progress']);
        $closed = WoStatus::factory()->closed()->create(['name' => 'Closed']);
        WorkOrder::factory()->forUnit($unit)->create(['report_period_id' => $period->id, 'wo_status_id' => $open->id, 'wonum' => 'WO-OPEN', 'service_cost' => 1_000_000, 'material_cost' => 500_000, 'sched_start' => '2026-11-10']);
        WorkOrder::factory()->forUnit($unit)->create(['report_period_id' => $period->id, 'wo_status_id' => $closed->id, 'wonum' => 'WO-DONE']);
        WorkOrder::factory()->forUnit($other)->create(['report_period_id' => ReportPeriod::factory()->forUnit($other)->create(['month' => 11, 'year' => 2026])->id, 'wo_status_id' => $open->id, 'wonum' => 'WO-LAIN']);

        // Kondisi Stok (ROP = 1 × 3 + 2 = 5): Filter Oli 4 + 0 − 3 = 1 ≤ 5 (kritis), Majun 20 > 5,
        // Lem Pipa a default row without movement (not kritis).
        foreach ([['Filter Oli', 4, 0, 3, 1], ['Majun', 20, 0, 0, 1], ['Lem Pipa', 0, 0, 0, 0]] as $i => [$name, $awal, $masuk, $keluar, $rata]) {
            LogistikFormRow::factory()->create(['unit_id' => $unit->id, 'form' => 'kondisi-stok', 'year' => 2026, 'month' => 11, 'section' => 'material', 'sort_order' => $i, 'data' => [
                'nama' => $name, 'satuan' => 'Pcs', 'stok_awal' => $awal, 'material_masuk' => $masuk, 'material_keluar' => $keluar,
                'harga_satuan' => 1000, 'pemakaian_rata' => $rata, 'safety_stock' => 2, 'ilt' => 3,
            ]]);
        }

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->loadDeferredProps(['operasi', 'har', 'logistik'], fn ($reload) => $reload
                ->where('operasi.fuels.0.penerimaan', fn ($value): bool => (float) $value === 50000.0)
                ->where('har.kpis.0.value', 3)
                ->where('har.kpis.1.value', fn ($value): bool => (float) $value === 33.3)
                ->where('har.kpis.3.value', fn ($value): bool => (float) $value === 1_500_000.0)
                ->where('har.backlog', fn ($rows): bool => collect($rows)->pluck('code')->sort()->values()->all() === ['WO-LAIN', 'WO-OPEN'])
                ->where('logistik.kpis.3.value', fn ($value): bool => (float) $value === 50.0)
                ->where('logistik.tanks.0.value', 40)
                ->where('logistik.critical', fn ($rows): bool => collect($rows)->pluck('name')->all() === ['Filter Oli'])
                ->where('logistik.kpis.1.value', 3)));

        // A unit account sees only its own unit's figures.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $other))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->loadDeferredProps('har', fn ($reload) => $reload
                ->where('har.kpis.0.value', 1)
                ->where('har.backlog.0.code', 'WO-LAIN')));

        Carbon::setTestNow();
    }
}
