<?php

namespace Tests\Feature\Operasi;

use App\Enums\PersediaanJenis;
use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiPersediaan;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Persediaan Bahan Bakar (4 periods) and Persediaan Pelumas (the 3 periods of
 * Pemakaian Pelumas): typed penerimaan / pengiriman, pemakaian from the
 * Pemakaian sheets and an opening stock carried from last month.
 */
class PersediaanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create();
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'Bio Solar', 'sort_order' => 0]);
        $mfo = BbmType::factory()->create(['code' => 'MFO', 'name' => 'MFO', 'sort_order' => 1]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $mfo->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => true]);
        $this->mak1 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'is_active' => true]);
        $this->mak2 = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #2', 'is_active' => true]);
    }

    private function period(int $month = 9, array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => $month, 'year' => 2026] + $extra;
    }

    public function test_persediaan_bbm_runs_the_stock_per_day_with_four_periods(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period(9, ['readings' => [
            "HSD_{$this->mak1->id}" => ['1' => 20000, '15' => 10000],
            "HSD_{$this->mak2->id}" => ['1' => 9669],
        ]]))->assertSessionHasNoErrors();

        $this->actingAs($tl)->post(route('operasi.pengusahaan.persediaan-bbm.store'), $this->period(9, [
            'opening' => ['HSD' => 583058],
            'entries' => [
                'HSD' => ['3' => ['penerimaan' => 30000], '15' => ['kirim_1' => 15000]],
                // Not a BBM of this unit.
                'B30' => ['1' => ['penerimaan' => 5]],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $record = OperasiPersediaan::query()->sole();
        $this->assertSame(PersediaanJenis::Bbm, $record->jenis);
        $this->assertSame(['HSD'], array_keys($record->entries));
        $this->assertEquals(583058, $record->opening['HSD']);
        // 583058 − 29669 + 30000 − 10000 − 15000
        $this->assertEquals(558389, $record->closing['HSD']);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.persediaan-bbm.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/persediaan-bbm/index')
                ->has('items', 2)
                ->where('items.0.key', 'HSD')
                ->where('items.1.key', 'MFO')
                ->has('periods', 4)
                ->where('periods.2.from', 15)
                ->where('periods.2.to', 22)
                ->where('periods.3.to', 30)
                ->where('kirim_columns', ['kirim_1' => 'TUG 8 / Kembali Sewa SMP', 'kirim_2' => 'Pinjam THAS'])
                ->where('pemakaian.HSD.1', 29669)
                ->where('pemakaian.HSD.15', 10000)
                ->where('carried_opening.HSD', null)
                ->where('can_write', true));

        // October carries September's saldo akhir; an unchanged opening is not stored as a correction.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.persediaan-bbm.index', $this->period(10)))
            ->assertInertia(fn ($page) => $page->where('carried_opening.HSD', 558389)->where('carried_opening.MFO', 0));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.persediaan-bbm.store'), $this->period(10, ['opening' => ['HSD' => 558389, 'MFO' => 100]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['MFO' => 100], array_map('intval', OperasiPersediaan::query()->where('month', 10)->sole()->opening));

        $this->actingAs($tl)->post(route('operasi.pengusahaan.persediaan-bbm.store'), $this->period(9, ['entries' => ['HSD' => ['1' => ['penerimaan' => -1]]]]))
            ->assertSessionHasErrors('entries.*.*.*');
    }

    public function test_persediaan_pelumas_uses_the_periods_of_pemakaian_pelumas(): void
    {
        $oli = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'Sallyx 420', 'is_active' => true]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period(9, ['readings' => ["{$oli->id}_{$this->mak1->id}" => ['2' => 418]]]));

        $this->actingAs($tl)->post(route('operasi.pengusahaan.persediaan-pelumas.store'), $this->period(9, [
            'opening' => [(string) $oli->id => 2717],
            'entries' => [(string) $oli->id => ['23' => ['penerimaan' => 9405, 'kirim_3' => 5]]],
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(2717 - 418 + 9405 - 5, OperasiPersediaan::query()->where('jenis', 'pelumas')->sole()->closing[$oli->id]);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.persediaan-pelumas.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/persediaan-pelumas/index')
                ->where('items.0.name', 'Sallyx 420')
                ->has('periods', 3)
                ->where('periods.0.to', 10)
                ->where('periods.2.from', 21)
                ->where('kirim_columns', ['kirim_1' => 'TUG 8', 'kirim_2' => 'TUG 10', 'kirim_3' => 'Over Flow'])
                ->where("pemakaian.{$oli->id}.2", 418));
    }

    public function test_readers_view_and_print_but_cannot_save(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        foreach (['bbm', 'pelumas'] as $jenis) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.persediaan-{$jenis}.index", $this->period()))->assertOk()
                ->assertInertia(fn ($page) => $page->where('can_write', false));
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.persediaan-{$jenis}.pdf", $this->period()))->assertOk()
                ->assertHeader('content-type', 'application/pdf');
            $this->actingAs($managerUl)->post(route("operasi.pengusahaan.persediaan-{$jenis}.store"), $this->period())->assertForbidden();
        }
    }
}
