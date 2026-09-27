<?php

namespace Tests\Feature\Har;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarBatteryVoltage;
use App\Models\HarPrelubeTest;
use App\Models\HarVibration;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The 13 technical HAR formulir are Akses 2 — Pengusahaan pages
 * (pages/pengusahaan/har): TL & Staf Pemeliharaan fill the data only; the kop
 * is static, the signatories come from the unit's jabatan holders and the PDF
 * is rendered from the saved data.
 */
class HarFormulirPengusahaanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const SLUGS = [
        'prelube-test',
        'hydrotest',
        'timing-injection-pump',
        'crankshaft-deflection',
        'counter-weight',
        'axial-conrod',
        'clearance-valve',
        'combustion-pressure',
        'injector-pressure',
        'motor-current',
        'vibration',
        'lube-quality',
        'battery-voltage',
    ];

    private Unit $unit;

    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->machine = Machine::factory()->create(['unit_id' => $this->unit->id, 'name' => 'MIRRLEES #3', 'is_active' => true]);
    }

    public function test_every_formulir_opens_saves_and_prints_for_the_team_leader(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $query = ['unit_id' => $this->unit->id, 'machine_id' => $this->machine->id, 'test_date' => '2026-09-10'];

        foreach (self::SLUGS as $slug) {
            $form = null;

            $this->actingAs($tl)->get(route("har.pengusahaan.{$slug}.index", $query))
                ->assertOk()
                ->assertInertia(function (AssertableInertia $page) use ($slug, &$form): void {
                    $page->component("pengusahaan/har/{$slug}/index")
                        ->where('can_write', true)
                        ->where('has_saved', false)
                        ->has('document.number')
                        ->has('signatories', 3);
                    $form = $page->toArray()['props']['form'];
                });

            // The page's own form posted back as-is saves the record.
            $this->actingAs($tl)->post(route("har.pengusahaan.{$slug}.store"), [...$query, ...$form])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route("har.pengusahaan.{$slug}.index", $query));

            $this->actingAs($tl)->get(route("har.pengusahaan.{$slug}.index", $query))
                ->assertInertia(fn (AssertableInertia $page) => $page->where('has_saved', true)->has('history', 1));

            $this->actingAs($tl)->get(route("har.pengusahaan.{$slug}.pdf", $query))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_saving_fills_the_static_kop_and_the_units_signatories(): void
    {
        $tlEmployee = Employee::factory()->forUnit($this->unit)->create(['position' => EmployeePosition::TeamLeaderPemeliharaan->value, 'name' => 'Budi TL']);
        $staf = $this->userWithRole(RoleName::StafPemeliharaan, $this->unit);
        $stafEmployee = Employee::factory()->forUnit($this->unit)->create(['position' => 'Staf Pemeliharaan', 'user_id' => $staf->id, 'name' => 'Sari Staf']);

        $this->actingAs($staf)->post(route('har.pengusahaan.prelube-test.store'), [
            'unit_id' => $this->unit->id,
            'machine_id' => $this->machine->id,
            'test_date' => '2026-09-10',
            'cylinders_count' => 2,
            'checklist_items' => [
                ['cylinder' => 1, 'camshaft' => 'v', 'conrod' => 'v', 'piston' => 'X', 'rocker_arm' => 'v', 'notes' => 'Piston aus'],
                ['cylinder' => 2, 'camshaft' => 'v', 'conrod' => 'v', 'piston' => 'v', 'rocker_arm' => 'v', 'notes' => ''],
            ],
            'notes' => 'Catatan uji',
            // Kop & signatory fields are not editable: they are ignored.
            'document_number' => 'DIUBAH',
            'tl_har_name' => 'Nama Palsu',
        ])->assertSessionHasNoErrors();

        $record = HarPrelubeTest::query()->sole();
        $this->assertSame('FMKD-314-10.3.3.a-B12', $record->document_number);
        $this->assertSame($tlEmployee->id, $record->tl_har_id);
        $this->assertNull($record->tl_har_name);
        $this->assertSame($stafEmployee->id, $record->staff_har_id);
        $this->assertSame('form', $record->format);
        $this->assertSame('X', $record->checklist_items[0]['piston']);

        $this->actingAs($staf)->get(route('har.pengusahaan.prelube-test.index', ['unit_id' => $this->unit->id, 'machine_id' => $this->machine->id, 'test_date' => '2026-09-10']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('signatories.1.name', 'Budi TL')
                ->where('signatories.2.name', 'Sari Staf')
                ->where('form.cylinders_count', 2));
    }

    public function test_computed_values_are_recalculated_on_save(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $base = ['unit_id' => $this->unit->id, 'machine_id' => $this->machine->id, 'test_date' => '2026-09-10'];

        $this->actingAs($tl)->post(route('har.pengusahaan.battery-voltage.store'), [
            ...$base,
            'cells_24v' => [['cell' => 1, 'voltage' => '2,10'], ['cell' => 2, 'voltage' => '2,30']],
            'cells_110v' => [['cell' => 1, 'voltage' => '1.9']],
            'charging_conditions' => [['mode' => 'FLOATING', 'item' => 'Rectifier', 'cond_24v' => 'OK', 'cond_110v' => 'OK', 'notes' => '']],
            'summary_24v' => ['max' => '99', 'min' => '99', 'total' => '99'],
        ])->assertSessionHasNoErrors();

        $battery = HarBatteryVoltage::query()->sole();
        $this->assertNotSame('99', $battery->summary_24v['max']);
        $this->assertNotSame('', $battery->summary_24v['total']);

        $this->actingAs($tl)->post(route('har.pengusahaan.vibration.store'), [
            ...$base,
            'measurements' => [['point' => 'A1', 'v_max' => '2', 'v_min' => '1', 'v_avg' => '9', 'h_max' => '', 'h_min' => '', 'h_avg' => '9', 'notes' => '']],
        ])->assertSessionHasNoErrors();

        $row = HarVibration::query()->sole()->measurements[0];
        $this->assertNotSame('9', $row['v_avg']);
        $this->assertSame('', $row['h_avg']);
    }

    public function test_the_formulir_moved_out_of_akses_1(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $query = ['unit_id' => $this->unit->id, 'machine_id' => $this->machine->id];

        $this->actingAs($koordinator)->get(route('har.pengusahaan.prelube-test.index', $query))->assertForbidden();
        $this->actingAs($koordinator)->post(route('har.pengusahaan.prelube-test.store'), $query)->assertForbidden();
        $this->assertFalse(app('router')->has('har.formulir.prelube-test.index'));

        // Daily Meeting, Logbook Mutasi & LH-05 stay with the Koordinator.
        $this->actingAs($koordinator)->get(route('har.formulir.index'))->assertOk();
        $this->actingAs($koordinator)->get(route('har.formulir.daily-meeting.index', ['unit_id' => $this->unit->id]))->assertOk();
        $this->actingAs($koordinator)->get(route('har.formulir.logbook-mutasi.index', ['unit_id' => $this->unit->id]))->assertOk();
        $this->actingAs($koordinator)->get(route('har.formulir.laporan-gangguan.index', ['unit_id' => $this->unit->id]))->assertOk();
    }

    public function test_work_order_and_service_request_are_shared_by_both_accesses(): void
    {
        $query = ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026];

        foreach ([RoleName::KoordinatorPemeliharaan, RoleName::TeamLeaderPemeliharaan, RoleName::StafPemeliharaan] as $role) {
            $user = $this->userWithRole($role, $this->unit);

            foreach (['har.input.work-order.index', 'har.input.service-request.index'] as $name) {
                $this->actingAs($user)->get(route($name, $query))
                    ->assertOk()
                    ->assertInertia(fn (AssertableInertia $page) => $page->where('can_write', true));
            }
        }

        $operasi = $this->userWithRole(RoleName::StafOperasi, $this->unit);
        $this->actingAs($operasi)->get(route('har.input.work-order.index', $query))->assertForbidden();
    }
}
