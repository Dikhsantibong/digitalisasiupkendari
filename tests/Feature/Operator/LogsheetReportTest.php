<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperatorLogsheetDocument;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Database\Seeders\LogsheetParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LogsheetReportTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(LogsheetParameterSeeder::class);
    }

    private function engineForUnit(Unit $unit): Machine
    {
        return Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
    }

    public function test_a_role_without_logsheet_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->get(route('operator.laporan.index'))
            ->assertForbidden();
    }

    public function test_the_operator_sees_the_report_index(): void
    {
        $unit = Unit::factory()->create();
        $this->engineForUnit($unit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.laporan.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('operator/laporan/index')->has('options.machines', 1));
    }

    public function test_the_document_editor_opens_with_content_and_grid(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.laporan.logsheet.edit', [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/laporan/document')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid.rows')
                ->has('letterhead')
                ->has('pdf_url'));
    }

    public function test_saving_persists_and_reopens_saved(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);
        $user = $this->userWithRole(RoleName::Operator, $unit);

        $this->actingAs($user)
            ->post(route('operator.laporan.logsheet.store'), [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
                'format' => 'grid', 'content_grid' => ['rows' => [], 'cols' => 3],
            ])
            ->assertRedirect();

        $record = OperatorLogsheetDocument::query()
            ->where('unit_id', $unit->id)->where('engine_id', $engine->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('grid', $record->format);
        $this->assertSame('2026-09-13', $record->log_date->toDateString());

        $this->actingAs($user)
            ->get(route('operator.laporan.logsheet.edit', [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
            ]))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('format', 'grid'));
    }

    public function test_regenerate_rebuilds_from_template(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);
        $user = $this->userWithRole(RoleName::Operator, $unit);

        OperatorLogsheetDocument::query()->create([
            'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
            'format' => 'html', 'content_html' => '<p>Isi lama</p>',
        ]);

        $this->actingAs($user)
            ->post(route('operator.laporan.logsheet.regenerate'), [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
            ])
            ->assertRedirect();

        $record = OperatorLogsheetDocument::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertStringNotContainsString('Isi lama', (string) $record->content_html);
        $this->assertStringContainsString('op-cover', (string) $record->content_html);
    }

    public function test_the_pdf_streams(): void
    {
        $unit = Unit::factory()->create();
        $engine = $this->engineForUnit($unit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.laporan.logsheet.pdf', [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_can_view_but_not_write(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);
        $engine = $this->engineForUnit($unit);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('operator.laporan.logsheet.edit', [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
            ]))
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($manager)
            ->post(route('operator.laporan.logsheet.store'), [
                'unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-09-13',
                'format' => 'html', 'content_html' => '<p>x</p>',
            ])
            ->assertForbidden();
    }

    public function test_a_foreign_unit_is_forbidden(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $foreignEngine = $this->engineForUnit($foreignUnit);

        $this->actingAs($this->userWithRole(RoleName::Operator, $ownUnit))
            ->get(route('operator.laporan.logsheet.edit', [
                'unit_id' => $foreignUnit->id, 'engine_id' => $foreignEngine->id, 'log_date' => '2026-09-13',
            ]))
            ->assertForbidden();
    }
}
