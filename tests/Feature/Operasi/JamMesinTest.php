<?php

namespace Tests\Feature\Operasi;

use App\Enums\JamMesinJenis;
use App\Enums\RoleName;
use App\Enums\StatusCodeCategory;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\OperasiJamMesin;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Jam Operasi / Jam Pemeliharaan / Jam Gangguan (typed in) and Jam Siap
 * Operasi (24 jam minus the three).
 */
class JamMesinTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Wua-Wua']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'type' => 'SM 453 AK', 'serial_number' => '26881', 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #2', 'serial_number' => '26882', 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    private function save(string $jenis, array $readings): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route("operasi.pengusahaan.jam-{$jenis}.store"), $this->period(['readings' => $readings]))
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_each_sheet_is_saved_on_its_own(): void
    {
        $this->save('operasi', [$this->mak1->id => ['1' => 16.53, '2' => 15.77, '31' => 5], $this->mak2->id => ['1' => 17.22]]);
        $this->save('pemeliharaan', [$this->mak1->id => ['2' => 1.5]]);

        $operasi = OperasiJamMesin::query()->where('jenis', JamMesinJenis::Operasi)->sole();
        $this->assertSame('49.52', $operasi->grand_total);
        $this->assertEquals(32.3, $operasi->totals_by_machine[$this->mak1->id]);
        $this->assertArrayNotHasKey(31, $operasi->readings[$this->mak1->id], 'September has 30 days.');
        $this->assertSame('1.50', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Pemeliharaan)->sole()->grand_total);

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-operasi.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/jam-operasi/index')
                ->where('jenis', 'operasi')
                ->has('machines', 2)
                ->where('machines.0.type', 'SM 453 AK')
                ->where("readings.{$this->mak1->id}.1", 16.53)
                ->where('days_in_month', 30)
                ->where('can_write', true));

        // More than 24 jam a day is refused.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-gangguan.store'), $this->period(['readings' => [$this->mak1->id => ['1' => 25]]]))
            ->assertSessionHasErrors('readings.*.*');
    }

    public function test_jam_siap_operasi_is_24_minus_the_three_sheets(): void
    {
        $this->save('operasi', [$this->mak1->id => ['1' => 16.53, '2' => 15.77, '3' => 24], $this->mak2->id => ['25' => 24]]);
        $this->save('pemeliharaan', [$this->mak1->id => ['2' => 1.5]]);
        $this->save('gangguan', [$this->mak2->id => ['25' => 8]]);

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-siap-ops.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/jam-siap-ops/index')
                ->where("readings.{$this->mak1->id}.1", 7.47)
                ->where("readings.{$this->mak1->id}.2", 6.73)
                ->where("readings.{$this->mak1->id}.3", 0)
                ->where("readings.{$this->mak1->id}.4", 24)
                ->where("readings.{$this->mak2->id}.25", -8)
                ->where('over', [['machine_id' => $this->mak2->id, 'day' => 25]])
                ->where('filled', ['operasi' => true, 'pemeliharaan' => true, 'gangguan' => true])
                ->where("totals_by_machine.{$this->mak1->id}", round(30 * 24 - 16.53 - 15.77 - 24 - 1.5, 2)));
    }

    public function test_star_stop_hours_are_offered_per_category(): void
    {
        $operasiCode = UnitStatusCode::factory()->create(['category' => StatusCodeCategory::Operasi]);
        $gangguanCode = UnitStatusCode::factory()->create(['category' => StatusCodeCategory::Gangguan]);
        EngineStatusLog::factory()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak1->id, 'report_date' => '2026-09-05', 'status_code_id' => $operasiCode->id, 'duration_minutes' => 600]);
        EngineStatusLog::factory()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak1->id, 'report_date' => '2026-09-05', 'status_code_id' => $operasiCode->id, 'duration_minutes' => 90]);
        EngineStatusLog::factory()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak2->id, 'report_date' => '2026-09-06', 'status_code_id' => $gangguanCode->id, 'duration_minutes' => 15]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-operasi.index', $this->period()))
            ->assertInertia(fn ($page) => $page->where("star_stop.{$this->mak1->id}.5", 11.5)->missing("star_stop.{$this->mak2->id}"));
        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-gangguan.index', $this->period()))
            ->assertInertia(fn ($page) => $page->where("star_stop.{$this->mak2->id}.6", 0.25));
    }

    public function test_readers_cannot_save_and_every_pdf_carries_the_logo(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);
        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.jam-operasi.index', $this->period()))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.jam-operasi.store'), $this->period(['readings' => []]))->assertForbidden();

        foreach (['jam-operasi', 'jam-pemeliharaan', 'jam-gangguan', 'jam-siap-ops', 'pemakaian-pelumas', 'pemakaian-bbm', 'stand-meter'] as $menu) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.pdf", $this->period()))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        $html = view('operasi.partials.kop-pengusahaan', ['unit' => $this->unit])->render();
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('UNIT LAYANAN PUSAT LISTRIK PLTD WUA-WUA', $html);

        foreach (['operasi.jam-mesin.pdf', 'operasi.pemakaian-pelumas.pdf', 'operasi.pemakaian-bbm.pdf'] as $view) {
            $this->assertStringContainsString("@include('operasi.partials.kop-pengusahaan'", (string) file_get_contents(resource_path('views/'.str_replace('.', '/', $view).'.blade.php')), $view);
        }
    }
}
