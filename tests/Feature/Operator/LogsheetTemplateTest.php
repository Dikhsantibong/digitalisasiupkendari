<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\LogsheetParameter;
use App\Models\LogsheetTemplate;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use App\Services\Operasi\MesinHarianSheet;
use Database\Seeders\LogsheetParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Logsheet per jenis mesin (user, 2026-11-21): PLTD Poasia reads the Mirrlees
 * form (Logsheet Lokal02), PLTD Poasia Containerized keeps the Cummins one;
 * templates (parameters, slots, patrol check items, machines) are managed by
 * Super Admin.
 */
class LogsheetTemplateTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private LogsheetTemplate $mirrlees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->seed(LogsheetParameterSeeder::class);
        $this->mirrlees = LogsheetTemplate::query()->where('code', 'mirrlees-pltd-poasia')->sole();
    }

    private function param(LogsheetTemplate $template, string $code): LogsheetParameter
    {
        return LogsheetParameter::query()->ofTemplate($template)->where('code', $code)->sole();
    }

    public function test_a_mirrlees_machine_shows_its_own_form_and_a_cummins_machine_keeps_the_original(): void
    {
        $unit = Unit::factory()->create();
        $mirrlees = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'logsheet_template_id' => $this->mirrlees->id]);
        $cummins = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $operator = $this->userWithRole(RoleName::Operator, $unit);

        $this->actingAs($operator)->get(route('operator.logsheet.index', ['unit_id' => $unit->id, 'engine_id' => $mirrlees->id, 'log_date' => '2026-11-20']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('template.name', 'Mirrlees (PLTD Poasia)')
                ->where('template.is_default', false)
                // Beban, 2 × 9 stud main bearing, ruangan, crankshaft, oil in/out, level oli run/stop, back wash in/out, expansion tank, trafo, level oil, temp oil.
                ->has('parameters', 31)
                ->where('parameters.1.group_label', 'Temperature Stud Main Bearing')
                ->where('parameters.1.name', 'Bank A')
                ->where('parameters.1.normal_value', '60')
                ->where('parameters.0.trip_value', '2860')
                ->has('rows', 24)
                ->where('slots.23', '24:00'));

        $this->actingAs($operator)->get(route('operator.logsheet.index', ['unit_id' => $unit->id, 'engine_id' => $cummins->id, 'log_date' => '2026-11-20']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('template.is_default', true)
                ->has('parameters', 22)
                ->has('rows', 29));
    }

    public function test_the_mirrlees_form_saves_numbers_choices_and_texts(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'logsheet_template_id' => $this->mirrlees->id]);
        $operator = $this->userWithRole(RoleName::Operator, $unit);
        $period = ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-11-20'];
        $load = $this->param($this->mirrlees, 'LOAD');
        $level = $this->param($this->mirrlees, 'LEVEL_EXPANSION_TANK');
        $oli = $this->param($this->mirrlees, 'LEVEL_OLI_RUN');

        // A choice outside L/N/H is refused; 17:30 is not a Mirrlees slot.
        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['sheet' => ['01:00' => ['p_'.$level->id => 'X']]])
            ->assertSessionHasErrors('sheet.01:00.p_'.$level->id);
        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['sheet' => ['17:30' => ['p_'.$load->id => '1500']]])
            ->assertStatus(422);

        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['sheet' => [
            '01:00' => ['p_'.$load->id => '1520', 'p_'.$level->id => 'N', 'p_'.$oli->id => 'MAX -2'],
            '02:00' => ['p_'.$load->id => '1610'],
        ]])->assertSessionHasNoErrors();

        $sheet = OperatorLogsheet::query()->sole();
        $this->assertSame('N', $sheet->readings()->where('parameter_id', $level->id)->value('value_text'));
        $this->assertSame('MAX -2', $sheet->readings()->where('parameter_id', $oli->id)->value('value_text'));

        $this->actingAs($operator)->get(route('operator.logsheet.index', $period))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.values.p_'.$load->id, '1520')
                ->where('rows.0.values.p_'.$level->id, 'N')
                ->where('rows.0.values.p_'.$oli->id, 'MAX -2'));

        // The grouped PDF prints, and Beban Tertinggi (Operasi) reads the Mirrlees LOAD.
        $this->actingAs($operator)->get(route('operator.logsheet.pdf', $period))->assertOk()->assertHeader('content-type', 'application/pdf');
        $beban = app(MesinHarianSheet::class)->bebanFromLogsheet($unit, 11, 2026, [['id' => $engine->id]]);
        $this->assertEqualsWithDelta(1610.0, $beban[$engine->id][20], 0.001);
    }

    public function test_super_admin_manages_a_template_and_its_machines(): void
    {
        $unit = Unit::factory()->create();
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)->post(route('operator.logsheet-template.store'), ['name' => 'SWD Kolaka', 'copy_from' => $this->mirrlees->id])->assertRedirect();
        $template = LogsheetTemplate::query()->where('name', 'SWD Kolaka')->sole();
        $this->assertSame(31, $template->parameters()->count());

        $first = $template->parameters()->orderBy('sort_order')->first();
        $this->actingAs($admin)->put(route('operator.logsheet-template.update', $template), [
            'name' => 'SWD Kolaka',
            'time_slots' => ['02:00', '01:00'],
            'patrol_items' => [['system' => 'COOLING SYSTEM', 'peralatan' => 'Radiator']],
            'parameters' => [
                ['id' => $first->id, 'code' => 'LOAD', 'name' => 'Beban', 'unit_of_measure' => 'kW', 'input_type' => 'number', 'is_active' => true],
                ['name' => 'Tekanan Udara', 'sub_channel' => 'A', 'unit_of_measure' => 'bar', 'input_type' => 'number', 'is_active' => true],
                ['name' => 'Level', 'input_type' => 'choice', 'options' => 'L, N, H', 'normal_value' => 'N', 'is_active' => true],
            ],
            'machine_ids' => [$machine->id],
        ])->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame(['01:00', '02:00'], $template->time_slots);
        $this->assertSame([['system' => 'COOLING SYSTEM', 'peralatan' => 'Radiator']], $template->patrol_items);
        $this->assertSame(['LOAD', 'TEKANAN_UDARA_A', 'LEVEL'], $template->parameters()->orderBy('sort_order')->pluck('code')->all());
        $this->assertSame(['L', 'N', 'H'], $this->param($template, 'LEVEL')->options);
        $this->assertSame($template->id, $machine->fresh()->logsheet_template_id);

        // A new Patrol Check sheet of that machine starts from the template's items.
        $this->actingAs($admin)->get(route('operasi.input.patrol-check-mesin.index', ['unit_id' => $unit->id, 'machine_id' => $machine->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items', 1)->where('items.0.peralatan', 'Radiator'));

        // A parameter with readings is only deactivated when removed.
        $sheet = OperatorLogsheet::query()->create(['unit_id' => $unit->id, 'engine_id' => $machine->id, 'log_date' => '2026-11-20', 'status' => 'draft']);
        $sheet->readings()->create(['time_slot' => '01:00', 'parameter_id' => $first->id, 'value' => 1500]);
        $this->actingAs($admin)->put(route('operator.logsheet-template.update', $template), [
            'name' => 'SWD Kolaka', 'time_slots' => ['01:00'], 'machine_ids' => [],
            'parameters' => [['name' => 'Level', 'input_type' => 'text', 'is_active' => true]],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($first->fresh()->is_active);
        $this->assertNull($machine->fresh()->logsheet_template_id);

        // Used template cannot be deleted; the default one never.
        $this->actingAs($admin)->delete(route('operator.logsheet-template.destroy', $template));
        $this->assertNotNull($template->fresh());
        $this->actingAs($admin)->delete(route('operator.logsheet-template.destroy', LogsheetTemplate::default()))->assertStatus(422);
    }

    public function test_only_super_admin_changes_templates(): void
    {
        $unit = Unit::factory()->create();
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $this->actingAs($koordinator)->get(route('operator.logsheet-template.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('operator/logsheet-template/index')->where('can_manage', false));
        $this->actingAs($koordinator)->post(route('operator.logsheet-template.store'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($koordinator)->put(route('operator.logsheet-template.update', $this->mirrlees), ['name' => 'X'])->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))->get(route('operator.logsheet-template.index'))->assertForbidden();
    }
}
