<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Project Leader (user, 2026-11-18): one "Laporan" menu with every report by
 * module (laporan.hub) instead of a Laporan menu per module, and the Laporan
 * SLA gabungan (sla.gabungan) of one or every accessible unit in one PDF /
 * Excel — while the SLA pages per divisi stay Super Admin only.
 */
class LaporanHubTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Carbon::setTestNow('2026-11-18 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_project_leader_sees_every_report_grouped_by_module(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        $projectLeader = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        $this->actingAs($projectLeader)->get(route('laporan.hub', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('laporan/index')
                ->where('filters.unit_id', $unit->id)
                ->where('can_sla', true)
                ->where('groups', fn ($groups): bool => collect($groups)->pluck('key')->all() === ['operasi', 'pemeliharaan', 'k3', 'logistik', 'pdm']
                    && collect($groups)->every(fn (array $group): bool => $group['resume_url'] !== null && $group['reports'] !== [])
                    && collect($groups)->firstWhere('key', 'operasi')['reports'][0]['title'] === 'Laporan Project Operasi'
                    && str_contains(collect($groups)->firstWhere('key', 'pdm')['reports'][0]['document_url'], '/pdm/laporan/dokumen')
                    && collect($groups)->firstWhere('key', 'pdm')['reports'][0]['status'] === 'Draft'));
    }

    public function test_accounts_without_the_single_laporan_menu_are_forbidden(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        foreach ([RoleName::Operator, RoleName::KoordinatorOperasi] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->actingAs($user)->get(route('laporan.hub'))->assertForbidden();
            $this->actingAs($user)->get(route('sla.combined.index'))->assertForbidden();
        }
    }

    public function test_the_project_leader_prints_the_sla_of_one_or_every_unit_in_one_file(): void
    {
        $poasia = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        $container = Unit::factory()->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $other = Unit::factory()->create(['name' => 'PLTD Kolaka', 'is_active' => true]);
        $projectLeader = $this->userWithRole(RoleName::ProjectLeaderOperasi, $poasia);
        $projectLeader->assignRole(RoleName::ProjectLeaderOperasi, $container);
        $projectLeader = $projectLeader->fresh();
        $query = ['month' => 10, 'year' => 2026];

        // The page: one unit, or "Semua unit" with the rekap of each (six divisi).
        $this->actingAs($projectLeader)->get(route('sla.combined.index', ['unit_id' => $poasia->id, ...$query]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('laporan/sla-gabungan')->has('recap', 1)->has('recap.0.rows', 6)->has('options.units', 2));
        $this->actingAs($projectLeader)->get(route('sla.combined.index', ['unit_id' => 'all', ...$query]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.unit_id', 'all')
                ->where('recap', fn ($recap): bool => collect($recap)->pluck('unit')->all() === ['PLTD Poasia', 'PLTD Poasia Containerized']));

        // PDF of every unit: per unit the cover (portrait) then the SLA (landscape).
        $response = $this->actingAs($projectLeader)->get(route('sla.combined.pdf', ['unit_id' => 'all', 'download' => 1, ...$query]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('laporan-sla-semua-unit-10-2026.pdf', (string) $response->headers->get('content-disposition'));
        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $portrait = collect(range(1, $pages))->filter(fn (int $p): bool => $reader->getTemplateSize($reader->importPage($p))['orientation'] === 'P')->count();
        $this->assertSame(2, $portrait);

        // Excel data: one entry per unit; a single unit keeps the former shape.
        $this->actingAs($projectLeader)->getJson(route('sla.combined.data', ['unit_id' => 'all', ...$query]))
            ->assertOk()->assertJsonCount(2, 'units')->assertJsonCount(6, 'units.0.slas')->assertJsonPath('units.1.unit', 'PLTD Poasia Containerized');
        $this->actingAs($projectLeader)->getJson(route('sla.combined.data', ['unit_id' => $poasia->id, ...$query]))
            ->assertOk()->assertJsonCount(6, 'slas')->assertJsonPath('unit', 'PLTD Poasia');

        // Another unit stays closed, and so do the SLA pages per divisi (Super Admin only).
        $this->actingAs($projectLeader)->get(route('sla.combined.index', ['unit_id' => $other->id]))->assertForbidden();
        $this->actingAs($projectLeader)->get(route('sla.show', ['divisi' => 'operasi', 'unit_id' => $poasia->id]))->assertForbidden();
    }

    public function test_the_super_admin_prints_the_sla_of_every_module_of_every_unit_in_one_file(): void
    {
        Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        Unit::factory()->create(['name' => 'PLTD Kolaka', 'is_active' => true]);
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $query = ['unit_id' => 'all', 'month' => 10, 'year' => 2026];

        $this->actingAs($superAdmin)->get(route('sla.combined.index', $query))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('laporan/sla-gabungan')->where('filters.unit_id', 'all')->has('options.units', 2)->has('recap', 2)->has('recap.0.rows', 6));

        $response = $this->actingAs($superAdmin)->get(route('sla.combined.pdf', [...$query, 'download' => 1]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('laporan-sla-semua-unit-10-2026.pdf', (string) $response->headers->get('content-disposition'));

        $this->actingAs($superAdmin)->getJson(route('sla.combined.data', $query))->assertOk()->assertJsonCount(2, 'units')->assertJsonCount(6, 'units.0.slas');
    }

    public function test_the_project_leader_has_full_akses_1_of_every_module_of_its_unit(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);
        $projectLeader = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        // Named "Project Leader" (not "Project Leader Operasi").
        $this->assertSame('Project Leader', RoleName::ProjectLeaderOperasi->label());

        foreach (['operasi', 'har', 'k3', 'logistik', 'pdm'] as $module) {
            foreach (['input.view', 'input.write', 'laporan.view', 'master.view_any', 'master.manage'] as $ability) {
                $this->assertTrue($projectLeader->hasPermissionTo("{$module}.{$ability}"), "{$module}.{$ability}");
            }
            $this->actingAs($projectLeader)->get(route("{$module}.jadwal.index", ['unit_id' => $unit->id]))->assertOk();
            $this->actingAs($projectLeader)->get(route("{$module}.input.index", ['unit_id' => $unit->id]))->assertOk();
        }

        // Only its own unit, and not the Akses 2 (TL) menus — e.g. the Input Logistik of Pemeliharaan.
        $this->actingAs($projectLeader)->get(route('logistik.input.form.index', ['form' => 'kondisi-stok', 'unit_id' => $other->id]))->assertForbidden();
        $this->assertFalse($projectLeader->hasPermissionTo('har.pengusahaan.view'));
        $this->actingAs($projectLeader)->get(route('har.pengusahaan.input-logistik.index', ['unit_id' => $unit->id]))->assertForbidden();
    }
}
