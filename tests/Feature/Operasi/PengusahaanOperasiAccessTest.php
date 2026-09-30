<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The daily Operasi inputs and the Berita Acara are Akses 2 — Pengusahaan
 * Operasi: pages under pengusahaan/operasi, routes operasi.pengusahaan.*,
 * filled by the TL & Staf Operasi (Manager UL reads), not by the Koordinator.
 */
class PengusahaanOperasiAccessTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    /** Route name → Inertia page. */
    private const PAGES = [
        'operasi.pengusahaan.daily-report.index' => 'pengusahaan/operasi/daily-report/index',
        'operasi.pengusahaan.star-stop.index' => 'pengusahaan/operasi/star-stop/index',
        'operasi.pengusahaan.feeder.index' => 'pengusahaan/operasi/feeder-readings/index',
        'operasi.pengusahaan.auxiliary.index' => 'pengusahaan/operasi/auxiliary-readings/index',
        'operasi.pengusahaan.fuel-receipt.index' => 'pengusahaan/operasi/fuel-receipts/index',
        'operasi.pengusahaan.resource-pembangkit.index' => 'pengusahaan/operasi/resource-pembangkit/index',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_team_leader_and_staf_operasi_fill_every_page(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        foreach ([RoleName::TeamLeaderOperasi, RoleName::StafOperasi] as $role) {
            $user = $this->userWithRole($role, $unit);

            foreach (self::PAGES as $route => $component) {
                $this->actingAs($user)
                    ->get(route($route, ['unit_id' => $unit->id]))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page->component($component)->where('can_write', true));
            }

            $this->actingAs($user)
                ->get(route('operasi.pengusahaan.berita-acara.index', ['unit_id' => $unit->id]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('pengusahaan/operasi/berita-acara/index')->where('can_create', true));
        }
    }

    public function test_the_koordinator_operasi_no_longer_opens_them(): void
    {
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, Unit::factory()->create(['is_active' => true]));

        foreach (array_keys(self::PAGES) as $route) {
            if ($route === 'operasi.pengusahaan.resource-pembangkit.index') {
                continue;
            }

            $this->actingAs($user)->get(route($route))->assertForbidden();
        }

        // Resource Pembangkit feeds the Laporan Operasi the Koordinator prepares: readable, not writable.
        $this->actingAs($user)
            ->get(route('operasi.pengusahaan.resource-pembangkit.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($user)->post(route('operasi.pengusahaan.resource-pembangkit.store'), [])->assertForbidden();

        $this->actingAs($user)->get(route('operasi.pengusahaan.berita-acara.index'))->assertForbidden();
    }

    public function test_the_manager_ul_reads_without_writing(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('operasi.pengusahaan.daily-report.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)
            ->get(route('operasi.pengusahaan.berita-acara.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_create', false));
        $this->actingAs($manager)
            ->post(route('operasi.pengusahaan.berita-acara.store'), [])
            ->assertForbidden();
    }
}
