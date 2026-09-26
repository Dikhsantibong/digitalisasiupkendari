<?php

namespace Tests\Feature;

use App\Enums\PermissionGroup;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Operasi, Pemeliharaan & K3 run through two accesses: Akses 1 — Laporan
 * Project (Project Leader & Koordinator) and Akses 2 — Pengusahaan (Team
 * Leader & Staf), each menu behind its own permission.
 */
class AksesPengusahaanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_team_leader_holds_akses_2_and_only_reads_the_laporan_project(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $tl = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);
        $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        foreach (['jadwal', 'input', 'formulir'] as $section) {
            $this->actingAs($tl)->get(route('har.pengusahaan.index', $section))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('pengusahaan/index')->where('module.key', 'har')->where('section.key', $section));
        }
        // The Laporan Pengusahaan has one door: the module's Laporan page.
        $this->actingAs($tl)->get(route('har.pengusahaan.index', 'laporan'))->assertNotFound();
        $this->actingAs($tl)->get(route('har.laporan.index', ['unit_id' => $unit->id]))->assertOk();
        $this->actingAs($tl)->get(route('har.laporan.pengusahaan.edit', $query))->assertOk()->assertInertia(fn ($page) => $page->where('can_write', true));

        // Akses 1 input is closed; the Laporan Pembangkit (and the jadwal it
        // prints, as for every laporan reader) stays readable for approval.
        $this->actingAs($tl)->get(route('har.input.index'))->assertForbidden();
        $this->actingAs($tl)->post(route('har.input.work-order.store'), $query + ['rows' => []])->assertForbidden();
        $this->actingAs($tl)->get(route('har.laporan.document.edit', $query))->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
    }

    public function test_the_koordinator_holds_akses_1_without_the_pengusahaan(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($koordinator)->get(route('har.input.index'))->assertOk();
        $this->actingAs($koordinator)->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('can_write', true));
        $this->actingAs($koordinator)->get(route('har.pengusahaan.index', 'input'))->assertForbidden();
        $this->actingAs($koordinator)->get(route('har.laporan.pengusahaan.edit', ['unit_id' => $unit->id]))->assertForbidden();
    }

    public function test_staf_reach_only_their_own_module_pengusahaan(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        foreach ([[RoleName::StafOperasi, 'operasi'], [RoleName::StafPemeliharaan, 'har'], [RoleName::StafK3, 'k3']] as [$role, $module]) {
            $staf = $this->userWithRole($role, $unit);

            foreach (['operasi', 'har', 'k3'] as $other) {
                $response = $this->actingAs($staf)->get(route("{$other}.pengusahaan.index", 'input'));
                $other === $module ? $response->assertOk() : $response->assertForbidden();
            }
            $this->actingAs($staf)->get(route("{$module}.input.index"))->assertForbidden();
            // Staf reach the Laporan Pengusahaan from the module's Laporan page.
            $this->actingAs($staf)->get(route("{$module}.laporan.index", ['unit_id' => $unit->id]))->assertOk();
        }
        $this->actingAs($this->userWithRole(RoleName::StafK3, $unit))->get(route('k3.laporan.pengusahaan.edit', ['unit_id' => $unit->id]))->assertOk();
        $this->actingAs($this->userWithRole(RoleName::StafK3, $unit))->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id]))->assertForbidden();
    }

    public function test_the_project_leader_reads_every_laporan_project(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $projectLeader = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        foreach (['operasi', 'har', 'k3', 'logistik', 'pdm'] as $module) {
            $this->actingAs($projectLeader)->get(route("{$module}.laporan.index", ['unit_id' => $unit->id]))->assertOk();
        }
        $this->actingAs($projectLeader)->get(route('har.pengusahaan.index', 'input'))->assertForbidden();
        $this->actingAs($projectLeader)->get(route('har.laporan.pengusahaan.edit', ['unit_id' => $unit->id]))->assertForbidden();
    }

    public function test_manager_ul_reads_the_pengusahaan_and_an_unknown_section_is_not_found(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)->get(route('k3.pengusahaan.index', 'input'))->assertOk();
        $this->actingAs($manager)->get(route('har.laporan.pengusahaan.edit', ['unit_id' => $unit->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)->get('/har/pengusahaan/lainnya')->assertNotFound();
    }

    public function test_each_access_is_its_own_group_in_role_and_akses(): void
    {
        $this->assertSame(PermissionGroup::PemeliharaanPengusahaan, PermissionName::HarPengusahaanView->group());
        $this->assertSame(PermissionGroup::OperasiPengusahaan, PermissionName::OperasiPengusahaanWrite->group());
        $this->assertSame(PermissionGroup::K3Pengusahaan, PermissionName::K3PengusahaanView->group());
        $this->assertSame(PermissionGroup::Pemeliharaan, PermissionName::HarInputView->group());
        $this->assertStringContainsString('Akses 2', PermissionGroup::PemeliharaanPengusahaan->label());
        $this->assertStringContainsString('Akses 1', PermissionGroup::Pemeliharaan->label());
    }
}
