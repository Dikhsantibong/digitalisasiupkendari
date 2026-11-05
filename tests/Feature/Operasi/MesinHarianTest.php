<?php

namespace Tests\Feature\Operasi;

use App\Enums\MesinHarianJenis;
use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperasiMesinHarian;
use App\Models\OperatorStartStop;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Operasi\MesinHarianSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\Concerns\RecordsLogsheetLoad;
use Tests\TestCase;

/**
 * Beban Tertinggi (max, with daya mampu) and Jumlah Kali Gangguan (sum, from
 * Star-Stop) per mesin per tanggal.
 */
class MesinHarianTest extends TestCase
{
    use InteractsWithAccessControl, RecordsLogsheetLoad, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Wua-Wua']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'merk' => 'MAK', 'capacity_kw' => 2544, 'is_active' => true]);
        $this->mak3 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #3', 'merk' => 'MAK', 'capacity_kw' => 2544, 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_beban_tertinggi_keeps_daya_mampu_and_takes_the_highest_values(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        // Before anything is saved, daya mampu falls back to the machine capacity.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.beban-tinggi.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('pengusahaan/operasi/beban-tinggi/index')->where("daya_mampu.{$this->mak1->id}", 2544));

        // The values come from the operators' logsheet: the highest LOAD of each day.
        $this->logsheetLoad($this->mak1, '2026-09-01', [1200, 1500, 900]);
        $this->logsheetLoad($this->mak1, '2026-09-02', [1450]);
        $this->logsheetLoad($this->mak3, '2026-09-01', [1800]);
        $this->logsheetLoad($this->mak3, '2026-09-02', [1600, 1700]);

        // Typed readings are ignored; daya mampu & catatan are still saved.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.beban-tinggi.store'), $this->period([
            'readings' => [$this->mak1->id => ['1' => 99]],
            'daya_mampu' => [$this->mak1->id => 1500, $this->mak3->id => 1800],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($tl)->get(route('operasi.pengusahaan.beban-tinggi.index', $this->period()))
            ->assertInertia(fn ($page) => $page
                ->where('from_logsheet', true)
                ->where("readings.{$this->mak1->id}.1", 1500)
                ->where("readings.{$this->mak3->id}.2", 1700));

        $record = OperasiMesinHarian::query()->where('jenis', MesinHarianJenis::BebanTinggi)->sole();
        $this->assertEquals([$this->mak1->id => 1500, $this->mak3->id => 1800], $record->params['daya_mampu']);

        // The next month starts from this month's daya mampu.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.beban-tinggi.index', ['unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->where("daya_mampu.{$this->mak3->id}", 1800));

        $summary = app(MesinHarianSheet::class)->summarize(MesinHarianJenis::BebanTinggi, $record->readings, [['id' => $this->mak1->id], ['id' => $this->mak3->id]], 30);
        $this->assertSame(1500.0, $summary['by_machine'][$this->mak1->id]);
        $this->assertSame(3300.0, $summary['by_day'][1]);
        $this->assertSame(3300.0, $summary['total']);
    }

    public function test_jumlah_kali_gangguan_comes_from_the_start_stop_engine_gangguan_stops(): void
    {
        OperatorStartStop::factory()->gangguan()->count(2)->create(['engine_id' => $this->mak1->id, 'unit_id' => $this->unit->id, 'tanggal' => '2026-09-11']);
        OperatorStartStop::factory()->create(['engine_id' => $this->mak1->id, 'unit_id' => $this->unit->id, 'tanggal' => '2026-09-12']);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kali-gangguan.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/kali-gangguan/index')
                ->where('from_start_stop', true)
                ->where("readings.{$this->mak1->id}.11", 2)
                ->missing("readings.{$this->mak1->id}.12")
                ->where('daya_mampu', null));

        // Typed counts are ignored; the catatan is saved.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.kali-gangguan.store'), $this->period(['readings' => [$this->mak1->id => ['11' => 7]], 'catatan' => '1. Tgl 11 MAK #1 trip']))
            ->assertSessionHasNoErrors();
        $record = OperasiMesinHarian::query()->where('jenis', MesinHarianJenis::KaliGangguan)->sole();
        $this->assertSame(2.0, (float) $record->readings[$this->mak1->id][11]);
        $this->assertSame('1. Tgl 11 MAK #1 trip', $record->catatan);
    }

    public function test_tara_kalor_is_typed_in_and_averaged_over_the_filled_cells(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.tara-kalor.store'), $this->period([
            'readings' => [$this->mak1->id => ['1' => 2618, '2' => 2623], $this->mak3->id => ['1' => 2494, '3' => 2489]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $record = OperasiMesinHarian::query()->where('jenis', MesinHarianJenis::TaraKalor)->sole();
        $summary = app(MesinHarianSheet::class)->summarize(MesinHarianJenis::TaraKalor, $record->readings, [['id' => $this->mak1->id], ['id' => $this->mak3->id]], 30);

        $this->assertEquals(2620.5, $summary['by_machine'][$this->mak1->id]);
        $this->assertEquals(2556, $summary['by_day'][1]);
        // A day with one machine is that machine's value; an empty day is 0.
        $this->assertEquals(2489, $summary['by_day'][3]);
        $this->assertEquals(0, $summary['by_day'][4]);
        $this->assertEquals(round((2618 + 2623 + 2494 + 2489) / 4, 2), $summary['total']);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.tara-kalor.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/tara-kalor/index')
                ->where("readings.{$this->mak1->id}.2", 2623)
                ->where('daya_mampu', null));
        $this->actingAs($tl)->get(route('operasi.pengusahaan.tara-kalor.pdf', $this->period()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_readers_cannot_save_and_the_pdfs_print(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.beban-tinggi.store'), $this->period(['readings' => []]))->assertForbidden();
        foreach (['beban-tinggi', 'kali-gangguan'] as $menu) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.index", $this->period()))->assertOk()
                ->assertInertia(fn ($page) => $page->where('can_write', false));
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.pdf", $this->period()))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }
}
