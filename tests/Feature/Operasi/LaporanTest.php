<?php

namespace Tests\Feature\Operasi;

use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Models\DailyEngineReport;
use App\Models\Machine;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const REPORT = 'laporan-operasi-bulanan';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_laporan_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();

        // Operator has no operasi.laporan.view.
        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operasi.laporan.index'))
            ->assertForbidden();
    }

    public function test_tl_operasi_sees_the_registered_reports(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/laporan/index')
                ->where('reports.0.code', self::REPORT),
            );
    }

    public function test_the_monthly_report_builds_its_payload(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-07-31')->create([
            'kwh_produksi_stand_akhir' => 0,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-01')->create([
            'kwh_produksi_stand_akhir' => 100,
        ]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.show', [
                'report' => self::REPORT,
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/laporan/monthly-engine')
                ->where('data.engine.name', $engine->name)
                ->has('data.rows', 31)
                ->has('data.summary.total')
                ->has('data.hours')
                ->has('data.sfc'),
            );
    }

    public function test_the_report_spreadsheet_returns_an_editable_grid(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.spreadsheet', [
                'report' => self::REPORT,
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/laporan/spreadsheet')
                ->where('report.code', self::REPORT)
                ->has('grid.rows')
                ->has('print_url'),
            );
    }

    public function test_the_report_document_editor_opens_with_content_and_grid(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.document.edit', [
                'report' => self::REPORT,
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/laporan/document')
                ->where('report.code', self::REPORT)
                ->where('format', 'html')
                ->where('has_saved', false)
                ->has('content')
                ->has('grid.rows')
                ->has('letterhead')
                ->has('pdf_url'),
            );
    }

    public function test_saving_the_report_document_persists_and_reopens_saved(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.laporan.document.store', ['report' => self::REPORT]), [
                'unit_id' => $unit->id,
                'engine_id' => $engine->id,
                'month' => 8,
                'year' => 2026,
                'format' => 'grid',
                'content_grid' => ['rows' => [], 'cols' => 3],
            ])
            ->assertRedirect();

        $record = OperasiReportDocument::query()
            ->where('unit_id', $unit->id)->where('report_code', self::REPORT)
            ->where('engine_id', $engine->id)->where('month', 8)->where('year', 2026)
            ->first();
        $this->assertNotNull($record);
        $this->assertSame('grid', $record->format);

        $this->actingAs($user)
            ->get(route('operasi.laporan.document.edit', [
                'report' => self::REPORT, 'unit_id' => $unit->id, 'engine_id' => $engine->id,
                'month' => 8, 'year' => 2026,
            ]))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('format', 'grid'));
    }

    public function test_the_report_document_pdf_streams(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.document.pdf', [
                'report' => self::REPORT, 'unit_id' => $unit->id, 'engine_id' => $engine->id,
                'month' => 8, 'year' => 2026,
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_report_document_respects_unit_scope(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit($foreignUnit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->get(route('operasi.laporan.document.edit', [
                'report' => self::REPORT, 'unit_id' => $foreignUnit->id, 'engine_id' => $foreignEngine->id,
                'month' => 8, 'year' => 2026,
            ]))
            ->assertForbidden();
    }

    public function test_an_unknown_report_code_is_not_found(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.show', [
                'report' => 'tidak-ada',
                'unit_id' => $unit->id,
            ]))
            ->assertNotFound();
    }

    public function test_a_report_cannot_be_built_for_a_unit_outside_scope(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit($foreignUnit)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->get(route('operasi.laporan.show', [
                'report' => self::REPORT,
                'unit_id' => $foreignUnit->id,
                'engine_id' => $foreignEngine->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();
    }
}
