<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiPemakaianPelumas;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Operasi\OperasiPengusahaanReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PemakaianPelumasTest extends TestCase
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

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['name' => 'PLTD Uji']);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK 1', 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK 2', 'is_active' => true]);
        $this->sallyx = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'SALLYX 420', 'sort_order' => 1, 'is_active' => true]);
        $this->turbolube = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'TURBOLUBE 46', 'sort_order' => 2, 'is_active' => true]);

        // Turbolube is only used by MAK 2 (Master Mesin → pelumas).
        $this->turbolube->machines()->attach($this->mak2);
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026] + $extra;
    }

    public function test_the_columns_come_from_the_master_of_the_unit(): void
    {
        $otherUnit = Unit::factory()->create();
        LubricantType::factory()->forUnit($otherUnit)->create(['name' => 'PELUMAS UNIT LAIN', 'is_active' => true]);
        LubricantType::factory()->forUnit($this->unit)->create(['name' => 'NONAKTIF', 'is_active' => false]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.pemakaian-pelumas.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/pemakaian-pelumas/index')
                ->has('lubricants', 2)
                ->where('lubricants.0.name', 'SALLYX 420')
                ->has('lubricants.0.machines', 2)
                ->where('lubricants.1.name', 'TURBOLUBE 46')
                ->has('lubricants.1.machines', 1)
                ->where('lubricants.1.machines.0.name', 'MAK 2')
                ->where('days_in_month', 31)
                ->has('periods', 3)
                ->where('can_write', true));
    }

    public function test_saving_keeps_only_valid_cells_and_computes_the_totals_on_the_server(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $sallyxMak1 = "{$this->sallyx->id}_{$this->mak1->id}";
        $sallyxMak2 = "{$this->sallyx->id}_{$this->mak2->id}";

        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period([
            'readings' => [
                $sallyxMak1 => ['2' => 209, '18' => 209, '28' => 3762],
                $sallyxMak2 => ['5' => 209.5, '32' => 50],
                "{$this->turbolube->id}_{$this->mak2->id}" => ['24' => 1.25],
                // Turbolube is not used by MAK 1, and the last key is not of this unit.
                "{$this->turbolube->id}_{$this->mak1->id}" => ['1' => 99],
                '999_999' => ['1' => 10],
            ],
            'catatan' => 'Penambahan oli MAK 1 tgl 28.',
        ]))->assertRedirect();

        $record = OperasiPemakaianPelumas::query()->with('items')->sole();
        $this->assertSame([$sallyxMak1, $sallyxMak2, "{$this->turbolube->id}_{$this->mak2->id}"], array_keys($record->raw_readings));
        $this->assertSame('4390.75', $record->grand_total_liter);
        $this->assertEquals(4389.5, $record->totals_by_lubricant[$this->sallyx->id]);
        $this->assertSame('Penambahan oli MAK 1 tgl 28.', $record->catatan);

        $mak1 = $record->items->firstWhere('machine_id', $this->mak1->id);
        $this->assertSame('209.00', $mak1->subtotal_p1);
        $this->assertSame('209.00', $mak1->subtotal_p2);
        $this->assertSame('3762.00', $mak1->subtotal_p3);
        $this->assertSame('4180.00', $mak1->total_liter);
        $this->assertCount(3, $record->items);

        // Saving again replaces the items instead of adding to them.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period(['readings' => [$sallyxMak1 => ['1' => 5]]]))->assertRedirect();
        $this->assertCount(1, $record->fresh()->items);
        $this->assertSame('5.00', $record->fresh()->grand_total_liter);
    }

    public function test_pelumas_tambah_and_ganti_are_kept_apart_and_the_total_is_both(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $key = "{$this->sallyx->id}_{$this->mak1->id}";

        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period([
            'readings' => [$key => ['2' => 10, '5' => 1]],
            'readings_ganti' => [$key => ['2' => 200]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        // Every other sheet keeps reading the total.
        $record = OperasiPemakaianPelumas::query()->sole();
        $this->assertEquals(['2' => 210, '5' => 1], $record->raw_readings[$key]);
        $this->assertEquals(211, $record->grand_total_liter);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.pemakaian-pelumas.index', $this->period()))
            ->assertInertia(fn ($page) => $page
                ->where("readings.{$key}.2", 10)
                ->where("readings_ganti.{$key}.2", 200));

        $this->actingAs($tl)->get(route('operasi.pengusahaan.pelumas-tambah-ganti.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/pelumas-tambah-ganti/index')
                ->where("tambah.{$key}.2", 10)
                ->where("tambah.{$key}.5", 1)
                ->where("ganti.{$key}.2", 200)
                ->has('groups', 1));

        foreach (['tambah', 'ganti'] as $jenis) {
            $this->actingAs($tl)->get(route('operasi.pengusahaan.pelumas-tambah-ganti.pdf', $this->period(['jenis' => $jenis, 'lubricant' => $this->sallyx->id])))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period([
            'readings' => ["{$this->sallyx->id}_{$this->mak1->id}" => ['1' => -5]],
        ]))->assertSessionHasErrors('readings.*.*');

        $this->assertDatabaseCount('operasi_pemakaian_pelumas', 0);
    }

    public function test_readers_can_view_and_print_but_not_save(): void
    {
        OperasiPemakaianPelumas::query()->create($this->period(['raw_readings' => ["{$this->sallyx->id}_{$this->mak1->id}" => ['3' => 12]]]));
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.pemakaian-pelumas.index', $this->period()))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false)->where('grand_total_liter', 12));
        $this->actingAs($managerUl)->get(route('operasi.pengusahaan.pemakaian-pelumas.pdf', $this->period()))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($managerUl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period(['readings' => []]))->assertForbidden();

        $other = $this->userWithRole(RoleName::TeamLeaderOperasi, Unit::factory()->create());
        $this->actingAs($other)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period(['readings' => []]))->assertForbidden();
        $this->actingAs($other)->get(route('operasi.pengusahaan.pemakaian-pelumas.pdf', $this->period()))->assertForbidden();
    }

    public function test_the_pengusahaan_report_includes_the_pemakaian_pelumas_section(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period([
            'readings' => ["{$this->sallyx->id}_{$this->mak1->id}" => ['2' => 209, '15' => 100]],
        ]));

        $section = collect(app(OperasiPengusahaanReport::class)->sections($this->unit, 8, 2026))->firstWhere('key', 'pemakaian-pelumas');

        $this->assertTrue($section['filled']);
        $this->assertSame('MAK 1', $section['rows'][0][2]['t']);
        $this->assertSame('309,00', $section['rows'][0][6]['t']);
        $this->assertSame('Jumlah SALLYX 420', $section['rows'][1][0]['t']);
    }
}
