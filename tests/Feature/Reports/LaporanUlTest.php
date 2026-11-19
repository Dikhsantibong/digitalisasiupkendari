<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Reports\UlReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Laporan UL (Admin UL & Super Admin): Laporan Pengusahaan Pembangkit,
 * Laporan Logistik and Laporan K3L built from every unit under the UL, the
 * PDF (landscape) and the data of the PPT bulanan (PRD.md §8.13).
 */
class LaporanUlTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private ServiceUnit $poasia;

    private ServiceUnit $kolaka;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Carbon::setTestNow('2026-11-18 08:00:00');

        $this->poasia = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $this->kolaka = ServiceUnit::factory()->create(['name' => 'UL PLTD Kolaka']);
        Unit::factory()->forServiceUnit($this->poasia)->create(['name' => 'PLTD Poasia 11 KIT', 'is_active' => true]);
        Unit::factory()->forServiceUnit($this->poasia)->create(['name' => 'PLTD Poasia Containerized 6 KIT', 'is_active' => true]);
        Unit::factory()->forServiceUnit($this->kolaka)->create(['name' => 'PLTD Kolaka', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_admin_ul_opens_the_hub_of_its_ul_with_the_three_reports(): void
    {
        $this->actingAs($this->userWithRole(RoleName::AdminUl, $this->poasia))
            ->get(route('laporan-ul.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('laporan/ul/index')
                ->where('ul.name', 'UL PLTD Poasia')
                ->where('units', fn ($units): bool => collect($units)->pluck('name')->all() === ['PLTD Poasia 11 KIT', 'PLTD Poasia Containerized 6 KIT'])
                // The last full month by default.
                ->where('filters.month', 10)
                ->where('filters.year', 2026)
                ->has('options.uls', 1)
                ->where('reports', fn ($reports): bool => collect($reports)->pluck('kind')->all() === ['pengusahaan', 'logistik', 'k3l']));
    }

    public function test_each_report_accumulates_the_units_of_the_ul(): void
    {
        $parts = ['pengusahaan' => ['operasi', 'pemeliharaan', 'k3'], 'logistik' => ['logistik'], 'k3l' => ['k3']];

        foreach ($parts as $kind => $keys) {
            $report = app(UlReport::class)->build($kind, $this->poasia, 10, 2026);
            $this->assertSame('UL PLTD Poasia', $report['ul']['name']);
            $this->assertCount(2, $report['units']);
            $this->assertSame($keys, array_column($report['parts'], 'key'));
            foreach ($report['parts'] as $part) {
                $this->assertNotEmpty($part['kpis']);
                // Each indicator with its average per unit (user, 2026-11-18).
                $this->assertTrue(collect($part['kpis'])->every(fn (array $kpi): bool => isset($kpi['average'])));
                $this->assertCount(2, $part['unit_table']['rows']);
                $this->assertSame('TOTAL / AKUMULASI UL', $part['unit_table']['footer'][0]);
                $this->assertTrue(collect($part['charts'])->every(fn (array $chart): bool => str_starts_with($chart['image'], 'data:image/png;base64,')));
            }
            $this->assertCount(2, $report['reports']['rows']);
            $this->assertCount(2, $report['completeness']['rows']);
        }
    }

    public function test_each_report_opens_as_an_editable_document(): void
    {
        $admin = $this->userWithRole(RoleName::AdminUl, $this->poasia);

        foreach (array_keys(UlReport::KINDS) as $kind) {
            $this->actingAs($admin)
                ->get(route('laporan-ul.show', ['kind' => $kind, 'month' => 10, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('laporan/ul/document')
                    ->where('title', UlReport::KINDS[$kind][0])
                    ->where('can_write', true)
                    ->where('workflow.status', 'draft')
                    ->where('workflow.can.submit', true)
                    ->where('workflow.action_urls.submit', fn (string $url): bool => str_contains($url, "/laporan-ul/{$kind}/ajukan"))
                    ->missing('content')
                    ->loadDeferredProps('document', fn ($page) => $page
                        ->where('content', fn (string $html): bool => str_contains($html, 'LEMBAR PENGESAHAN') && str_contains($html, mb_strtoupper(UlReport::KINDS[$kind][0])) && str_contains($html, 'id="ul-ttd"')
                            // No MKP logo in the Laporan UL (user, 2026-11-18).
                            && ! str_contains($html, 'mkp.jpg'))
                        ->where('letterhead', fn (string $html): bool => ! str_contains($html, 'mkp.jpg'))
                        ->where('document_number', fn (string $number): bool => str_starts_with($number, 'LAP-UL-'))
                        ->has('grid.rows')));
        }
    }

    public function test_the_pdf_mixes_portrait_front_pages_with_landscape_parts_and_the_ppt_data_holds_the_three_reports(): void
    {
        $admin = $this->userWithRole(RoleName::AdminUl, $this->poasia);

        $response = $this->actingAs($admin)->get(route('laporan-ul.pdf', ['kind' => 'logistik', 'month' => 10, 'year' => 2026]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        // The document: Sampul, Daftar Isi & Lembar Pengesahan portrait, then the bagian landscape.
        $orientations = array_map(fn (int $page): string => $reader->getTemplateSize($reader->importPage($page))['orientation'], range(1, $pages));
        $this->assertSame(['P', 'P', 'P'], array_slice($orientations, 0, 3));
        $this->assertContains('L', array_slice($orientations, 3));

        $this->actingAs($admin)->getJson(route('laporan-ul.ppt-data', ['month' => 10, 'year' => 2026]))
            ->assertOk()
            ->assertJsonCount(3, 'reports')
            ->assertJsonPath('reports.0.title', 'Laporan Pengusahaan Pembangkit')
            ->assertJsonPath('reports.1.title', 'Laporan Logistik')
            ->assertJsonPath('reports.2.title', 'Laporan K3 Lingkungan');
    }

    public function test_only_the_admin_ul_of_that_ul_and_the_super_admin_get_in(): void
    {
        // Another UL stays closed to the Admin UL of Poasia.
        $this->actingAs($this->userWithRole(RoleName::AdminUl, $this->poasia))
            ->get(route('laporan-ul.index', ['ul_id' => $this->kolaka->id]))
            ->assertForbidden();

        // Other roles — the Manager UL included (user, 2026-11-18) — have no Laporan UL.
        foreach ([RoleName::ManagerUl, RoleName::KoordinatorOperasi, RoleName::TeamLeaderPemeliharaan] as $role) {
            $user = $this->userWithRole($role, $role === RoleName::ManagerUl ? $this->poasia : Unit::query()->first());
            $this->actingAs($user)->get(route('laporan-ul.index'))->assertForbidden();
            $this->actingAs($user)->get(route('laporan-ul.ppt-data'))->assertForbidden();
        }

        // The Super Admin picks any UL.
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('laporan-ul.index', ['ul_id' => $this->kolaka->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('ul.name', 'UL PLTD Kolaka')->has('options.uls', 2));

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))->get('/laporan-ul/tidak-ada')->assertNotFound();
    }
}
