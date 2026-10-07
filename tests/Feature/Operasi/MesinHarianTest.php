<?php

namespace Tests\Feature\Operasi;

use App\Enums\MesinHarianJenis;
use App\Enums\RoleName;
use App\Enums\StatusCodeCategory;
use App\Models\EngineStatusLog;
use App\Models\Machine;
use App\Models\OperasiMesinHarian;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\UnitStatusCode;
use App\Services\Operasi\MesinHarianSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Beban Tertinggi (max, with daya mampu) and Jumlah Kali Gangguan (sum, from
 * Star-Stop) per mesin per tanggal.
 */
class MesinHarianTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

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

        $this->actingAs($tl)->post(route('operasi.pengusahaan.beban-tinggi.store'), $this->period([
            'readings' => [$this->mak1->id => ['1' => 1500, '2' => 1450], $this->mak3->id => ['1' => 1800, '2' => 1700]],
            'daya_mampu' => [$this->mak1->id => 1500, $this->mak3->id => 1800],
        ]))->assertRedirect()->assertSessionHasNoErrors();

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

    public function test_jumlah_kali_gangguan_counts_whole_numbers_and_reads_star_stop(): void
    {
        $code = UnitStatusCode::factory()->create(['category' => StatusCodeCategory::Gangguan, 'label' => 'Trip tanpa indikasi']);
        EngineStatusLog::factory()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak1->id, 'report_date' => '2026-09-11', 'status_code_id' => $code->id, 'start_datetime' => '2026-09-11 13:34:00', 'stop_datetime' => '2026-09-11 14:00:00', 'duration_minutes' => 26, 'keterangan' => null]);
        EngineStatusLog::factory()->create(['unit_id' => $this->unit->id, 'engine_id' => $this->mak1->id, 'report_date' => '2026-09-11', 'status_code_id' => $code->id, 'start_datetime' => '2026-09-11 15:07:00', 'stop_datetime' => '2026-09-11 16:55:00', 'duration_minutes' => 108]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kali-gangguan.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/kali-gangguan/index')
                ->where("star_stop.{$this->mak1->id}.11", 2)
                ->has('star_stop_notes', 2)
                ->where('star_stop_notes.0', 'Tgl 11/09 MAK #1 jam 13:34 Trip tanpa indikasi, jam 14:00 start kembali')
                ->where('daya_mampu', null));

        $this->actingAs($tl)->post(route('operasi.pengusahaan.kali-gangguan.store'), $this->period(['readings' => [$this->mak1->id => ['11' => 1.5]]]))
            ->assertSessionHasErrors('readings.*.*');
        $this->actingAs($tl)->post(route('operasi.pengusahaan.kali-gangguan.store'), $this->period(['readings' => [$this->mak1->id => ['11' => 2]], 'catatan' => '1. Tgl 11 MAK #1 trip']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2.0, (float) OperasiMesinHarian::query()->where('jenis', MesinHarianJenis::KaliGangguan)->sole()->readings[$this->mak1->id][11]);
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
