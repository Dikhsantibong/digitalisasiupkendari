<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Akses 2 — Pengusahaan: Rencana & Realisasi pemeliharaan rutin, pengukuran
 * air and monitoring pelumas (six sheets = 3 scopes × rencana / realisasi).
 */
class ScheduleInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_moved_from_akses_1_to_the_pengusahaan(): void
    {
        $unit = Unit::factory()->create();

        $this->assertFalse(app('router')->has('har.input.schedule.index'));
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.pengusahaan.rencana-realisasi.index'))
            ->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('har.pengusahaan.rencana-realisasi.index'))
            ->assertForbidden();

        foreach ([RoleName::TeamLeaderPemeliharaan, RoleName::StafPemeliharaan] as $role) {
            $this->actingAs($this->userWithRole($role, $unit))
                ->get(route('har.pengusahaan.rencana-realisasi.index', ['unit_id' => $unit->id]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('pengusahaan/har/rencana-realisasi/index')->where('can_write', true));
        }
    }

    public function test_every_scope_lists_each_machine_with_its_rencana_and_realisasi_sheet(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->count(2)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.pengusahaan.rencana-realisasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('days', 31)
                // 1 Aug 2026 is a Saturday, 3 Aug a Monday.
                ->where('days.0.is_red', true)
                ->where('days.2.is_red', false)
                ->has('scopes.har', 2)
                ->has('scopes.air', 2)
                ->has('scopes.pelumas', 2)
                ->has('scopes.har.0.rencana.days')
                ->has('scopes.har.0.realisasi.durasi')
                ->where('document.number', 'FMKD-314-10.3.1-A1'),
            );
    }

    public function test_saving_stores_all_six_sheets_with_their_row_fields(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create();
        $staf = $this->userWithRole(RoleName::StafPemeliharaan, $unit);
        $row = fn (array $extra = []): array => [['engine_id' => $engine->id, 'days' => [], 'durasi' => [], 'jam_operasi' => '', 'keterangan' => '', 'status_note' => '', ...$extra]];

        $this->actingAs($staf)->post(route('har.pengusahaan.rencana-realisasi.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'sheets' => [
                ['scope' => 'har', 'plan_type' => 'rencana', 'rows' => $row(['days' => ['4' => 'P1', '5' => '', '12' => 'P4'], 'keterangan' => 'Jadwal HAR mengacu prognosa'])],
                ['scope' => 'har', 'plan_type' => 'realisasi', 'rows' => $row(['days' => ['5' => 'P1'], 'durasi' => ['5' => '6'], 'jam_operasi' => '1200'])],
                ['scope' => 'air', 'plan_type' => 'rencana', 'rows' => $row(['days' => ['4' => 'v']])],
                ['scope' => 'air', 'plan_type' => 'realisasi', 'rows' => $row(['days' => ['5' => 'v']])],
                ['scope' => 'pelumas', 'plan_type' => 'rencana', 'rows' => $row(['status_note' => 'Gangguan vibrasi tinggi (usulan ATTB)'])],
                ['scope' => 'pelumas', 'plan_type' => 'realisasi', 'rows' => $row()],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(6, MaintenanceSchedule::query()->where('unit_id', $unit->id)->count());

        $sheet = fn (string $scope, string $plan): MaintenanceSchedule => MaintenanceSchedule::query()
            ->where('engine_id', $engine->id)->where('scope', $scope)->where('plan_type', $plan)->sole();

        $this->assertSame(['4' => 'P1', '12' => 'P4'], $sheet('har', 'rencana')->schedule_data);
        $this->assertSame('Jadwal HAR mengacu prognosa', $sheet('har', 'rencana')->keterangan);
        $this->assertSame(['5' => '6'], $sheet('har', 'realisasi')->durasi_data);
        $this->assertSame('1200', $sheet('har', 'realisasi')->jam_operasi);
        $this->assertSame(['5' => 'v'], $sheet('air', 'realisasi')->schedule_data);
        $this->assertSame('Gangguan vibrasi tinggi (usulan ATTB)', $sheet('pelumas', 'rencana')->status_note);
        $this->assertNull($sheet('pelumas', 'realisasi')->status_note);

        // The saved sheets come back on the page.
        $this->actingAs($staf)->get(route('har.pengusahaan.rencana-realisasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('scopes.har.0.rencana.days.12', 'P4')
                ->where('scopes.har.0.realisasi.durasi.5', '6')
                ->where('scopes.pelumas.0.rencana.status_note', 'Gangguan vibrasi tinggi (usulan ATTB)'));
    }

    public function test_an_engine_from_another_unit_is_rejected(): void
    {
        $unit = Unit::factory()->create();
        $foreignEngine = Machine::factory()->forUnit(Unit::factory()->create())->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->post(route('har.pengusahaan.rencana-realisasi.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'sheets' => [['scope' => 'har', 'plan_type' => 'rencana', 'rows' => [['engine_id' => $foreignEngine->id, 'days' => ['1' => 'P1']]]]],
            ])
            ->assertSessionHasErrors('sheets.0.rows.0.engine_id');
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.pengusahaan.rencana-realisasi.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'sheets' => [],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_schedules', 0);
    }
}
