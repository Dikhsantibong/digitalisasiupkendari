<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\OperasiInstruksiKerja;
use App\Models\PdmPermitToWork;
use App\Models\Unit;
use App\Services\Reports\SlaCatalog;
use App\Services\Reports\SlaReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * SLA bulanan per divisi: the SLA sheet's criteria scored from the unit's
 * inputs, the Denda status per criterion, the page, the landscape PDF and the
 * access per divisi (PRD.md §8.12).
 */
class SlaReportTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Carbon::setTestNow('2026-11-20 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_every_divisi_shows_the_sla_sheet_criteria(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Uji', 'is_active' => true]);
        $user = $this->userWithRole(RoleName::SuperAdmin);
        $expected = ['operasi' => [18, 29], 'pemeliharaan' => [12, 18], 'logistik' => [12, 18], 'pdm' => [11, 20], 'k3' => [14, 20], 'project-leader' => [8, 12]];

        foreach ($expected as $divisi => [$rows, $bobot]) {
            $this->actingAs($user)->get(route('sla.show', ['divisi' => $divisi, 'unit_id' => $unit->id, 'month' => 10, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('laporan/sla')
                    ->where('sla.divisi', $divisi)
                    ->where('sla.number', SlaCatalog::DIVISI[$divisi][0])
                    ->where('sla.unit.name', 'PLTD Uji')
                    ->where('sla.period.label', 'OKTOBER 2026')
                    ->where('sla.contract_number', 'KDD056.PJ/612/UPKD/2026')
                    ->has('sla.rows', $rows)
                    ->where('sla.total.bobot', $bobot)
                    ->where('sla.rows.0.formula', fn (string $formula): bool => str_ends_with($formula, '/10 x 9% x Tagihan bulan berjalan'))
                    ->has('divisions', 6)
                    ->where('can_combined', true),
                );
        }
    }

    public function test_pencapaian_and_denda_come_from_the_inputs(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        OperasiInstruksiKerja::factory()->for($unit)->create(['tanggal' => '2026-10-05']);
        $sla = app(SlaReport::class);

        // IK Operasi: target 2 dokumen — 1 input = 50%, Denda (jenis 2 → bobot 1).
        $row = $sla->build('operasi', $unit, 10, 2026)['rows'][1];
        $this->assertSame([2, 1, 1, 50, true], [$row['target'], $row['bobot'], $row['achieved'], $row['percent'], $row['denda']]);
        $this->assertSame('1/10 x 9% x Tagihan bulan berjalan', $row['formula']);

        OperasiInstruksiKerja::factory()->for($unit)->create(['tanggal' => '2026-10-20']);
        $row = $sla->build('operasi', $unit, 10, 2026)['rows'][1];
        $this->assertSame([2, 100, false], [$row['achieved'], $row['percent'], $row['denda']]);

        // A criterion without application data is not scored (no Denda either).
        $maturity = $sla->build('operasi', $unit, 10, 2026)['rows'][5];
        $this->assertSame([], $maturity['sources']);
        $this->assertNull($maturity['percent']);
        $this->assertNull($maturity['denda']);

        // PdM PtW (jenis 1 → bobot 2): filled in October only.
        PdmPermitToWork::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 10]);
        $october = $sla->build('pdm', $unit, 10, 2026);
        $september = $sla->build('pdm', $unit, 9, 2026);
        $this->assertSame([100, false, 2], [$october['rows'][9]['percent'], $october['rows'][9]['denda'], $october['rows'][9]['bobot']]);
        $this->assertSame([0, true], [$september['rows'][9]['percent'], $september['rows'][9]['denda']]);
        $this->assertGreaterThan($october['total']['denda_count'], $september['total']['denda_count']);
        $this->assertSame(array_sum(array_column(array_filter($september['rows'], fn (array $r): bool => $r['denda'] === true), 'bobot')), $september['total']['denda_bobot']);

        // A month not yet started is not scored.
        $future = $sla->build('pdm', $unit, 12, 2026);
        $this->assertSame('future', $future['period']['state']);
        $this->assertSame(0, $future['total']['measured']);
        $this->assertNull($future['total']['percent']);
    }

    public function test_the_pdf_is_landscape_and_the_filters_are_validated(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $response = $this->actingAs($admin)->get(route('sla.pdf', ['divisi' => 'k3', 'unit_id' => $unit->id, 'month' => 10, 'year' => 2026, 'tagihan' => 150000000]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);

        $this->actingAs($admin)->get(route('sla.show', ['divisi' => 'k3', 'unit_id' => $unit->id, 'tagihan' => -5]))->assertSessionHasErrors('tagihan');
        $this->actingAs($admin)->get('/sla/tidak-ada')->assertNotFound();
    }

    /**
     * SLA is Super Admin only for now (user, 2026-11-17): even the divisi's
     * Team Leader and the Project Leader, who view every laporan, stay out.
     */
    public function test_only_the_super_admin_opens_the_sla_for_now(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $this->assertTrue(SlaReport::SUPER_ADMIN_ONLY);

        foreach ([RoleName::TeamLeaderK3, RoleName::ProjectLeaderOperasi] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->assertFalse(SlaReport::allowsCombined($user));

            foreach (array_keys(SlaCatalog::DIVISI) as $divisi) {
                $this->assertFalse(SlaReport::allows($user, $divisi), "{$role->value} / {$divisi}");
            }

            $this->actingAs($user)->get(route('sla.show', ['divisi' => 'k3', 'unit_id' => $unit->id]))->assertForbidden();
            $this->actingAs($user)->get(route('sla.pdf', ['divisi' => 'k3', 'unit_id' => $unit->id]))->assertForbidden();
            $this->actingAs($user)->get(route('sla.combined.pdf', ['unit_id' => $unit->id]))->assertForbidden();
            $this->actingAs($user)->get(route('sla.combined.data', ['unit_id' => $unit->id]))->assertForbidden();
        }
    }

    public function test_the_super_admin_downloads_the_combined_sla_in_one_file(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Raha', 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'tagihan' => 150000000];

        // PDF: the cover (portrait) first, then every divisi and the Rekapitulasi (landscape).
        $response = $this->actingAs($admin)->get(route('sla.combined.pdf', $query + ['download' => 1]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment; filename="laporan-sla-pltd-raha-8-2026.pdf"', (string) $response->headers->get('content-disposition'));
        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertGreaterThan(2, $pages);
        $this->assertSame('P', $reader->getTemplateSize($reader->importPage(1))['orientation']);
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(2))['orientation']);

        // Excel payload: the cover image and the six divisi in catalogue order.
        $data = $this->actingAs($admin)->getJson(route('sla.combined.data', $query))->assertOk()->json();
        $this->assertStringStartsWith('data:image/png;base64,', $data['cover']);
        $this->assertSame(array_keys(SlaCatalog::DIVISI), array_column($data['slas'], 'divisi'));
        $this->assertSame('AGUSTUS 2026', $data['slas'][0]['period']['label']);
    }
}
