<?php

namespace Tests\Feature\Operasi;

use App\Enums\JamMesinJenis;
use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperasiJamMesin;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Operasi\JamMesinSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Jam Pembangkit: Jam Operasi, Pemeliharaan and Gangguan of every mesin in one
 * sheet, saved into the same records as the separate sheets.
 */
class JamPembangkitTest extends TestCase
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
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #2', 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_the_three_hours_are_saved_at_once_into_the_existing_sheets(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-pembangkit.store'), $this->period([
            'readings' => [
                'operasi' => [$this->mak1->id => ['1' => 16.5, '2' => 20], $this->mak2->id => ['1' => 18]],
                'pemeliharaan' => [$this->mak1->id => ['2' => 4]],
                'gangguan' => [$this->mak1->id => ['1' => 2.5]],
                'standby' => [$this->mak1->id => ['1' => 5], $this->mak2->id => ['1' => 6]],
            ],
            'catatan' => 'MAK #1 trip tanggal 1',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('54.50', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Operasi)->sole()->grand_total);
        $this->assertSame('4.00', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Pemeliharaan)->sole()->grand_total);
        $this->assertSame('2.50', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Gangguan)->sole()->grand_total);
        $this->assertSame('11.00', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Standby)->sole()->grand_total);

        // Jam Siap Operasi (24 − OPS − PO − MO − GGN, the standby) and the separate sheets read the same records.
        $siap = app(JamMesinSheet::class)->siapOperasi($this->unit, 9, 2026, app(JamMesinSheet::class)->machines($this->unit));
        $this->assertEquals(5.0, $siap['readings'][$this->mak1->id][1]);
        $this->assertEquals(0.0, $siap['readings'][$this->mak1->id][2]);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-pembangkit.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/jam-pembangkit/index')
                ->has('machines', 2)
                ->where('days_in_month', 30)
                ->where("readings.operasi.{$this->mak1->id}.1", 16.5)
                ->where("readings.gangguan.{$this->mak1->id}.1", 2.5)
                ->where('catatan', 'MAK #1 trip tanggal 1')
                ->where('can_write', true));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-operasi.index', $this->period()))
            ->assertInertia(fn ($page) => $page->where("readings.{$this->mak1->id}.2", 20));

        // Clearing a cell and saving again removes it.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-pembangkit.store'), $this->period([
            'readings' => ['operasi' => [$this->mak2->id => ['1' => 18]], 'standby' => [$this->mak2->id => ['1' => 6]]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame('18.00', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Operasi)->sole()->grand_total);
        $this->assertSame('0.00', OperasiJamMesin::query()->where('jenis', JamMesinJenis::Gangguan)->sole()->grand_total);
    }

    public function test_a_filled_day_must_make_exactly_24_hours_with_mo_and_standby(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $store = fn (array $readings) => $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-pembangkit.store'), $this->period(['readings' => $readings]));

        // More than 24, less than 24, and one cell over 24 are refused.
        $store(['operasi' => [$this->mak1->id => ['1' => 20]], 'gangguan' => [$this->mak1->id => ['1' => 5]]])->assertSessionHasErrors("readings.operasi.{$this->mak1->id}.1");
        $store(['operasi' => [$this->mak1->id => ['1' => 20]]])->assertSessionHasErrors("readings.operasi.{$this->mak1->id}.1");
        $store(['operasi' => [$this->mak1->id => ['1' => 25]]])->assertSessionHasErrors();
        $this->assertSame(0, OperasiJamMesin::query()->count());

        // OPS + PO + MO + GGN + Standby = 24; an empty day is not checked.
        $store([
            'operasi' => [$this->mak1->id => ['1' => 16]],
            'mo' => [$this->mak1->id => ['1' => 2]],
            'standby' => [$this->mak1->id => ['1' => 6]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('2.00', OperasiJamMesin::query()->where('jenis', JamMesinJenis::MaintenanceOutage)->sole()->grand_total);

        $siap = app(JamMesinSheet::class)->siapOperasi($this->unit, 9, 2026, app(JamMesinSheet::class)->machines($this->unit));
        $this->assertEquals(6.0, $siap['readings'][$this->mak1->id][1]);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.jam-pembangkit.index', $this->period()))
            ->assertInertia(fn ($page) => $page
                ->where('jenis', fn ($list): bool => collect($list)->pluck('value')->all() === ['operasi', 'pemeliharaan', 'mo', 'gangguan', 'standby'])
                ->where("readings.mo.{$this->mak1->id}.1", 2)
                ->where("readings.standby.{$this->mak1->id}.1", 6));
    }

    public function test_the_project_leader_fills_it_while_operators_and_view_only_roles_cannot_save(): void
    {
        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $this->unit))
            ->post(route('operasi.pengusahaan.jam-pembangkit.store'), $this->period(['readings' => ['operasi' => [$this->mak1->id => ['1' => 8]], 'standby' => [$this->mak1->id => ['1' => 16]]]]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, OperasiJamMesin::query()->where('jenis', JamMesinJenis::Operasi)->count());

        // Operators have no Pengusahaan input for now; the Koordinator only reads.
        foreach ([RoleName::Operator, RoleName::KoordinatorOperasi] as $role) {
            $this->actingAs($this->userWithRole($role, $this->unit))
                ->post(route('operasi.pengusahaan.jam-pembangkit.store'), $this->period(['readings' => []]))
                ->assertForbidden();
        }
    }
}
