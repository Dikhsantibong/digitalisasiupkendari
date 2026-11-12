<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Models\WoStatus;
use App\Services\Reports\ModuleResume;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Resume Bulanan per modul: KPI with their direction, Ringkasan Arah Data,
 * charts (every model, with running totals) and tables — preview page, PDF
 * and Excel (landscape), for whoever may view the module's laporan.
 */
class ModuleResumeTest extends TestCase
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

    public function test_every_module_builds_kpis_insights_charts_and_tables(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Uji', 'is_active' => true]);
        $user = $this->userWithRole(RoleName::SuperAdmin);

        foreach (array_keys(ModuleResume::MODULES) as $module) {
            $this->actingAs($user)->get(route('laporan.resume.show', ['module' => $module, 'unit_id' => $unit->id, 'month' => 11, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('laporan/resume')
                    ->where('resume.module', $module)
                    ->where('resume.unit.name', 'PLTD Uji')
                    ->has('resume.kpis', 5)
                    ->has('resume.insights')
                    ->where('resume.charts', fn ($charts): bool => count($charts) >= 5
                        && collect($charts)->every(fn (array $chart): bool => str_starts_with($chart['svg'], '<svg'))
                        // Several chart models, and at least one running total (akumulasi) — except K3, whose S-curve is the cumulative one.
                        && collect($charts)->pluck('type')->unique()->count() >= 3
                        && ($module === 'k3' ? collect($charts)->contains('type', 'scurve') : collect($charts)->contains(fn (array $c): bool => str_starts_with($c['title'], 'Akumulasi'))))
                    ->where('resume.sections', fn ($sections): bool => collect($sections)->pluck('title')->contains('Kelengkapan Input') && collect($sections)->pluck('title')->contains('Status Laporan')),
                );
        }
    }

    public function test_figures_come_from_the_inputs_with_their_direction_and_running_totals(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $open = WoStatus::factory()->create(['name' => 'In Progress']);
        foreach ([[10, 1], [11, 3]] as [$month, $count]) {
            $period = ReportPeriod::factory()->forUnit($unit)->create(['month' => $month, 'year' => 2026]);
            WorkOrder::factory()->count($count)->forUnit($unit)->create(['report_period_id' => $period->id, 'wo_status_id' => $open->id, 'service_cost' => 100_000]);
        }

        $resume = app(ModuleResume::class)->build('pemeliharaan', $unit, 11, 2026);

        $wo = collect($resume['kpis'])->firstWhere('label', 'Work Order');
        $this->assertSame('3', $wo['value']);
        $this->assertSame('naik', $wo['trend']);
        $this->assertSame(200.0, $wo['delta']);
        $this->assertSame('membaik', $wo['assessment']);
        $this->assertTrue(collect($resume['insights'])->contains(fn (array $line): bool => str_contains($line['text'], 'Work Order naik 200,00% dibanding bulan lalu')));

        $table = collect($resume['sections'])->first(fn (array $s): bool => str_starts_with($s['title'], 'Work Order, Service Request'));
        $this->assertContains('Kumulatif WO', $table['columns']);
        $this->assertSame(['Okt 26', '1', '1'], array_slice($table['rows'][4], 0, 3));
        $this->assertSame(['Nov 26', '3', '4'], array_slice($table['rows'][5], 0, 3));

        $cumulative = collect($resume['charts'])->firstWhere('title', 'Akumulasi Work Order & Service Request');
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 1.0, 4.0], $cumulative['series'][0]['values']);
    }

    public function test_the_pdf_is_landscape_and_access_follows_the_module_and_unit(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);
        $tlK3 = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $response = $this->actingAs($tlK3)->get(route('laporan.resume.pdf', ['module' => 'k3', 'unit_id' => $unit->id, 'month' => 11, 'year' => 2026]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);

        // Another module's laporan or another unit stays closed.
        $this->actingAs($tlK3)->get(route('laporan.resume.show', ['module' => 'pemeliharaan', 'unit_id' => $unit->id]))->assertForbidden();
        $this->actingAs($tlK3)->get(route('laporan.resume.show', ['module' => 'k3', 'unit_id' => $other->id]))->assertForbidden();
        $this->actingAs($tlK3)->get('/laporan/resume/tidak-ada')->assertNotFound();
    }
}
