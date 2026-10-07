<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiTug;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class TugPelumasTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    private LubricantType $sallyx;

    private LubricantType $turbolube;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Wua-Wua']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'serial_number' => '26881', 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #2', 'serial_number' => '26882', 'is_active' => true]);
        $this->sallyx = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'SALLYX 420', 'code' => '8050021', 'sort_order' => 1, 'is_active' => true]);
        $this->turbolube = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'TURBOLUBE XT68', 'code' => '8050337', 'sort_order' => 2, 'is_active' => true]);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    private function fillPemakaianPelumas(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period([
            'readings' => [
                "{$this->sallyx->id}_{$this->mak1->id}" => ['2' => 209, '5' => 209, '28' => 3762],
                "{$this->turbolube->id}_{$this->mak1->id}" => ['2' => 1, '10' => 1],
                "{$this->sallyx->id}_{$this->mak2->id}" => ['24' => 209],
            ],
        ]))->assertRedirect();
    }

    public function test_the_tug_of_a_machine_is_read_from_pemakaian_pelumas(): void
    {
        $this->fillPemakaianPelumas();
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-pelumas.index', $this->period(['machine_id' => $this->mak1->id])))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/tug-pelumas/index')
                ->has('machines', 2)
                ->where('machines.0.total', 4182)
                ->where('machines.1.total', 209)
                ->where('tug.machine.name', 'MAK #1')
                ->where('tug.columns.0.code', '8050021')
                ->has('tug.days', 30)
                ->where('tug.days.1.values.'.$this->sallyx->id, 209)
                ->where('tug.days.1.total', 210)
                ->where('tug.totals.'.$this->sallyx->id, 4180)
                ->where('tug.grand_total', 4182)
                ->where('tug.has_sheet', true)
                ->where('tug.header.pekerjaan', 'RUTIN')
                ->where('tug.header.tanggal_dokumen', '2026-10-01')
                ->where('can_write', true));

        // The machine filter switches to MAK #2.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-pelumas.index', $this->period(['machine_id' => $this->mak2->id])))
            ->assertInertia(fn ($page) => $page->where('tug.machine.name', 'MAK #2')->where('tug.grand_total', 209));
    }

    public function test_the_header_is_saved_per_machine_and_carried_to_the_next_month(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.tug-pelumas.store'), $this->period([
            'machine_id' => $this->mak1->id,
            'nomor' => '00081/LOG.00.02/550231/2026',
            'pekerjaan' => 'RUTIN',
            'cost_center' => '2503133101',
            'kode_perkiraan' => '1023',
            'tanggal_dokumen' => '2026-10-01',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('operasi_tugs', ['machine_id' => $this->mak1->id, 'jenis' => 'pelumas', 'month' => 9, 'nomor' => '00081/LOG.00.02/550231/2026', 'cost_center' => '2503133101']);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-pelumas.index', ['unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026, 'machine_id' => $this->mak1->id]))
            ->assertInertia(fn ($page) => $page
                ->where('tug.header_saved', false)
                ->where('tug.header.nomor', null)
                ->where('tug.header.cost_center', '2503133101')
                ->where('tug.header.kode_perkiraan', '1023')
                ->where('tug.header.tanggal_dokumen', '2026-11-01'));

        // A machine of another unit is rejected.
        $foreign = Machine::factory()->forUnit(Unit::factory()->create())->create();
        $this->actingAs($tl)->post(route('operasi.pengusahaan.tug-pelumas.store'), $this->period(['machine_id' => $foreign->id, 'pekerjaan' => 'RUTIN']))
            ->assertSessionHasErrors('machine_id');
    }

    public function test_the_pdf_prints_one_machine_or_all_of_them(): void
    {
        $this->fillPemakaianPelumas();
        OperasiTug::factory()->forMachine($this->mak1, 9, 2026)->create();
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-pelumas.pdf', $this->period(['machine_id' => $this->mak1->id])))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($tl)->get(route('operasi.pengusahaan.tug-pelumas.pdf', $this->period(['all' => 1, 'download' => 1])))
            ->assertOk()->assertDownload();
    }

    public function test_readers_can_view_but_not_save(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.tug-pelumas.index', $this->period()))
            ->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.tug-pelumas.store'), $this->period(['machine_id' => $this->mak1->id, 'pekerjaan' => 'RUTIN']))
            ->assertForbidden();

        $other = $this->userWithRole(RoleName::TeamLeaderOperasi, Unit::factory()->create());
        $this->actingAs($other)->get(route('operasi.pengusahaan.tug-pelumas.pdf', $this->period(['all' => 1])))->assertForbidden();
    }
}
