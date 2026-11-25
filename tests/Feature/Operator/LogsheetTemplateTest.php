<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\LogsheetParameter;
use App\Models\LogsheetTemplate;
use App\Models\Machine;
use App\Models\OperatorLogsheet;
use App\Models\Unit;
use App\Services\Operasi\MesinHarianSheet;
use App\Services\Operator\LogsheetLayout;
use Database\Seeders\LogsheetParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        // Hours lock 60 min after their time (WITA): the tests start at 00:30 on the day they fill.
        $this->travelTo(Carbon::parse('2026-11-20 00:30', 'Asia/Makassar'));
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

        $this->actingAs($operator)->get(route('operator.logsheet.index', ['unit_id' => $unit->id, 'engine_id' => $mirrlees->id, 'log_date' => '2026-11-20', 'lembar' => 'lokal-02']))
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
        $period = ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-11-20', 'lembar' => 'lokal-02'];
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
        // Lokal 01 (36) + Lokal 02 (31) + Lokal 03 (39) + Lokal 04 Cylinder Head (17), with their lembar.
        $this->assertSame(123, $template->parameters()->count());
        $this->assertCount(4, $template->sheetList());

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

    public function test_pltd_poasia_reads_four_lembar_each_with_its_own_interval(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'logsheet_template_id' => $this->mirrlees->id]);
        $operator = $this->userWithRole(RoleName::Operator, $unit);
        $period = ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-11-22'];

        $this->actingAs($operator)->get(route('operator.logsheet.index', $period))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('sheets', 4)
                ->where('sheets.0.name', 'Logsheet Lokal 01')
                ->where('sheets.1.name', 'Logsheet Lokal 02')
                ->where('sheets.3.name', 'Logsheet Lokal 04 (Cylinder Head)')
                ->where('active_sheet.key', 'lokal-01')
                ->where('active_sheet.summary', true)
                // Beban, jam operasi, BBM, speed, governor, exhaust A1–B8 + T/C (2), jacket water … inter cooler.
                ->has('parameters', 36)
                ->where('parameters.5.group_label', 'Exhaust Temperature')
                ->where('parameters.5.sub_channel', 'A1')
                ->has('rows', 24));

        $this->actingAs($operator)->get(route('operator.logsheet.index', $period + ['lembar' => 'lokal-02']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('active_sheet.summary', true)->where('parameters.0.name', 'Beban')->where('parameters.1.group_label', 'Temperature Stud Main Bearing')->has('rows', 24));

        $this->actingAs($operator)->get(route('operator.logsheet.index', $period + ['lembar' => 'lokal-03']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('active_sheet.interval', 'Setiap 2 Jam')
                ->where('active_sheet.summary', true)
                // Beban, cam & crank shaft door bank A/B (32), Autonic, 3 pressures, 2 asap.
                ->has('parameters', 39)
                ->where('parameters.1.group_label', 'Temperature Cam Shaft Door')
                ->where('parameters.1.sub_channel', 'A1')
                ->has('rows', 12)
                ->where('rows.0.time_slot', '01:00')
                ->where('rows.11.time_slot', '23:00'));

        $this->actingAs($operator)->get(route('operator.logsheet.index', $period + ['lembar' => 'cylinder-head']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('parameters', 17)->has('rows', 12));

        // Lokal 03 and Lokal 02 share 01:00: saving one never clears the other; 02:00 is not a Lokal 03 slot.
        $l03 = LogsheetParameter::query()->ofSheet($this->mirrlees, 'lokal-03')->where('code', 'L03_BEBAN')->sole();
        $l02 = $this->param($this->mirrlees, 'LOAD');
        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['lembar' => 'lokal-03', 'sheet' => ['01:00' => ['p_'.$l03->id => '2200'], '03:00' => ['p_'.$l03->id => '2400']]])
            ->assertSessionHasNoErrors();
        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['lembar' => 'lokal-02', 'sheet' => ['01:00' => ['p_'.$l02->id => '1500']]])->assertSessionHasNoErrors();
        $this->actingAs($operator)->post(route('operator.logsheet.store'), $period + ['lembar' => 'lokal-03', 'time_slot' => '02:00', 'values' => []])->assertSessionHasErrors('time_slot');

        $sheet = OperatorLogsheet::query()->sole();
        $this->assertSame(3, $sheet->readings()->count());
        $this->actingAs($operator)->get(route('operator.logsheet.index', $period + ['lembar' => 'lokal-03']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('rows.0.values.p_'.$l03->id, '2200')->where('stats.0.value', '2 / 12')->where('lembar_dipilih', true));

        // The phone's lembar choice: filled hours per lembar; no lembar picked yet.
        $this->actingAs($operator)->get(route('operator.logsheet.index', $period))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('lembar_dipilih', false)
                ->where('sheets.0.filled', 0)->where('sheets.0.total', 24)
                ->where('sheets.1.filled', 1)->where('sheets.1.total', 24)
                ->where('sheets.2.filled', 2)->where('sheets.2.total', 12)
                ->where('sheets.3.filled', 0));

        // MIN / MAX / RATA2 of Lokal 03, printed on its PDF.
        [$min, $max, $avg] = LogsheetLayout::summaryRows(collect([$l03]), [['time_slot' => '01:00', 'values' => ['p_'.$l03->id => '2200']], ['time_slot' => '03:00', 'values' => ['p_'.$l03->id => '2400']]]);
        $this->assertSame(['2200', '2400', '2300'], [$min['values']['p_'.$l03->id], $max['values']['p_'.$l03->id], $avg['values']['p_'.$l03->id]]);
        $this->actingAs($operator)->get(route('operator.logsheet.pdf', $period + ['lembar' => 'lokal-03']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_lokal_01_reference_notes_merge_over_the_exhaust_columns_like_the_form(): void
    {
        $parameters = LogsheetParameter::query()->ofSheet($this->mirrlees, 'lokal-01')->orderBy('sort_order')->get();
        $cells = LogsheetLayout::referenceCells($parameters, 'normal_value');

        // Beban … Governor one cell each, then one note over Exhaust A1–B8, then T/C.
        $this->assertSame(['1500', 'JAM', 'MFO', '1000', '75-90'], array_column(array_slice($cells, 0, 5), 'value'));
        $this->assertSame(16, $cells[5]['colspan']);
        $this->assertStringStartsWith('Pada beban 1900 - 2300 kw', $cells[5]['value']);
        $this->assertSame(1, $cells[6]['colspan']);
        $this->assertSame($parameters->count(), array_sum(array_column($cells, 'colspan')));

        // Short equal values (Lokal 02 stud bearing "60") stay one cell per column; "Pembacaan langsung" merges IN / OUT.
        $lokal02 = LogsheetParameter::query()->ofSheet($this->mirrlees, 'lokal-02')->orderBy('sort_order')->get();
        $cells02 = LogsheetLayout::referenceCells($lokal02, 'normal_value');
        $this->assertSame(1, $cells02[1]['colspan']);
        $this->assertContains(['value' => 'Pembacaan langsung', 'colspan' => 2], $cells02);

        $unit = Unit::factory()->create();
        $engine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'logsheet_template_id' => $this->mirrlees->id]);
        $operator = $this->userWithRole(RoleName::Operator, $unit);
        $period = ['unit_id' => $unit->id, 'engine_id' => $engine->id, 'log_date' => '2026-11-22'];
        $bbm = $parameters->firstWhere('code', 'L01_BBM');
        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['lembar' => 'lokal-01', 'sheet' => ['01:00' => ['p_'.$bbm->id => 'XYZ']]])->assertSessionHasErrors();
        $this->actingAs($operator)->post(route('operator.logsheet.sheet'), $period + ['lembar' => 'lokal-01', 'sheet' => ['01:00' => ['p_'.$bbm->id => 'MFO']]])->assertSessionHasNoErrors();
        $this->actingAs($operator)->get(route('operator.logsheet.pdf', $period + ['lembar' => 'lokal-01']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_super_admin_edits_the_lembar_of_a_template(): void
    {
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $this->actingAs($admin)->post(route('operator.logsheet-template.store'), ['name' => 'Deutz'])->assertRedirect();
        $template = LogsheetTemplate::query()->where('name', 'Deutz')->sole();

        $this->actingAs($admin)->put(route('operator.logsheet-template.update', $template), [
            'name' => 'Deutz',
            'sheets' => [
                ['key' => 'utama', 'name' => 'Lokal 01', 'interval' => 'Setiap Jam', 'summary' => false, 'slots' => ['02:00', '01:00']],
                ['key' => 'lembar-2', 'name' => 'Lokal 02', 'interval' => 'Setiap 2 Jam', 'summary' => true, 'slots' => ['01:00', '03:00']],
            ],
            'parameters' => [
                ['sheet_key' => 'utama', 'code' => 'LOAD', 'name' => 'Beban', 'input_type' => 'number', 'is_active' => true],
                ['sheet_key' => 'lembar-2', 'name' => 'Suhu', 'input_type' => 'number', 'is_active' => true],
            ],
        ])->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame(['01:00', '02:00'], $template->sheet('utama')['slots']);
        $this->assertTrue($template->sheet('lembar-2')['summary']);
        $this->assertSame(['utama', 'lembar-2'], $template->parameters()->orderBy('sort_order')->pluck('sheet_key')->all());

        // Back to one lembar: a plain template again.
        $this->actingAs($admin)->put(route('operator.logsheet-template.update', $template), [
            'name' => 'Deutz',
            'sheets' => [['key' => 'utama', 'name' => 'Lokal 01', 'slots' => ['01:00']]],
            'parameters' => [['name' => 'Beban', 'code' => 'LOAD', 'input_type' => 'number', 'is_active' => true]],
        ])->assertSessionHasNoErrors();
        $template->refresh();
        $this->assertNull($template->sheets);
        $this->assertSame(['01:00'], $template->time_slots);
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
