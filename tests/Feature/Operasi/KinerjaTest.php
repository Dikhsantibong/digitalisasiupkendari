<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperasiRekap;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Kinerja Unit Mesin is filled from Master Mesin, Beban Tertinggi and the Jam
 * sheets; only corrections are stored.
 */
class KinerjaTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    private Machine $dhs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Wua-Wua']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'WUA-WUA 1', 'merk' => 'MaK', 'capacity_kw' => 2544, 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'WUA-WUA 2', 'merk' => 'MaK', 'capacity_kw' => 2544, 'is_active' => true]);
        $this->dhs = Machine::factory()->forUnit($this->unit)->create(['name' => 'WUA-WUA 6', 'merk' => 'DAIHATSU', 'capacity_kw' => 3000, 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_the_values_come_from_the_other_sheets_and_can_be_corrected(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.beban-tinggi.store'), $this->period(['daya_mampu' => [$this->mak1->id => 1700, $this->mak2->id => 1600, $this->dhs->id => 0]]));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-operasi.store'), $this->period(['readings' => [$this->mak1->id => ['1' => 24, '2' => 20], $this->mak2->id => ['1' => 10]]]));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-pemeliharaan.store'), $this->period(['readings' => [$this->mak1->id => ['3' => 1.5]]]));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kinerja.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/kinerja/index')
                ->where('rows.0.values.daya_terpasang', 2544)
                ->where('rows.0.values.daya_mampu', 1700)
                ->where('rows.0.values.kondisi', 'Beroperasi')
                ->where('rows.0.values.jam_operasi', 44)
                ->where('rows.0.values.jam_har', 1.5)
                ->where('rows.0.ratio', 66.824)
                ->where('rows.1.ratio', 62.893)
                ->where('rows.2.values.kondisi', 'Tidak Operasi')
                // The unit ratio averages the machines "Beroperasi" only.
                ->where('totals.ratio', 64.86)
                ->where('totals.daya_terpasang', 8088));

        // WUA-WUA 6 is corrected to "Beroperasi" (Excel: on duty with 0 jam); unchanged values are not stored.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.kinerja.store'), $this->period([
            'values' => [
                $this->mak1->id => ['daya_terpasang' => '2544', 'daya_mampu' => '1700', 'kondisi' => 'Beroperasi', 'jam_operasi' => '44'],
                $this->dhs->id => ['kondisi' => 'Beroperasi', 'kode_kondisi' => 'S'],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([$this->dhs->id => ['kondisi' => 'Beroperasi', 'kode_kondisi' => 'S']], OperasiRekap::query()->sole()->overrides);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.kinerja.index', $this->period()))
            ->assertInertia(fn ($page) => $page
                ->where('rows.2.values.kondisi', 'Beroperasi')
                ->where('rows.2.edited', ['kondisi', 'kode_kondisi'])
                ->where('rows.2.auto.kondisi', 'Tidak Operasi')
                ->where('totals.ratio', round((66.824 + 62.893 + 0) / 3, 2)));
    }

    public function test_readers_view_and_print_but_cannot_correct(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.kinerja.index', $this->period()))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.kinerja.pdf', $this->period()))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.kinerja.store'), $this->period(['values' => []]))->assertForbidden();
    }
}
