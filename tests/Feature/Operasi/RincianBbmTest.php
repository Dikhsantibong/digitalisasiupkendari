<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\OperasiRekap;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Operasi\RincianBbmSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Perincian Bahan Bakar and Rekap Bahan Bakar: one shared typed-in record per
 * month, every jenis BBM of the unit from the master.
 */
class RincianBbmTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create();
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'Bio Solar', 'sort_order' => 0]);
        $mfo = BbmType::factory()->create(['code' => 'MFO', 'name' => 'MFO', 'sort_order' => 1]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Tangki tegak 1', 'capacity_liter' => 100000, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Tangki MFO', 'bbm_type_id' => $mfo->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => true]);
        $this->mak = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'is_active' => true]);

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $this->period(['readings' => ["HSD_{$this->mak->id}" => ['1' => 29669]]]));
        $this->actingAs($tl)->post(route('operasi.pengusahaan.persediaan-bbm.store'), $this->period([
            'opening' => ['HSD' => 583058],
            'entries' => ['HSD' => ['3' => ['penerimaan' => 30000], '10' => ['penerimaan' => 120000], '15' => ['kirim_1' => 15000]]],
        ]))->assertSessionHasNoErrors();
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_both_sheets_share_one_record_and_agree_on_the_sisa(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.perincian-bbm.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/perincian-bbm/index')
                ->has('fuels', 2)
                ->where('tanks.HSD.0.name', 'Tangki tegak 1')
                ->where('auto.awal.HSD', 583058)
                ->where('auto.penerimaan.HSD.0.amount', 30000)
                ->where('auto.penerimaan.HSD.1.amount', 120000)
                ->where('auto.pengiriman.HSD', 15000)
                ->where("auto.pemakaian.{$this->mak->id}.HSD", 29669));

        // Perincian saves pengembalian, koreksi and fisik; the lain it sends is ignored.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.perincian-bbm.store'), $this->period(['fuels' => [
            'HSD' => ['pengembalian' => ['tanggal' => '2026-09-20', 'amount' => 1000], 'koreksi' => ['keterangan' => 'Pinjam THAS', 'amount' => 500], 'fisik' => 668000, 'lain' => [$this->mak->id => ['cuci' => 99]]],
        ]]))->assertRedirect()->assertSessionHasNoErrors();
        // Rekap saves the lain only, keeping what Perincian saved.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.rekap-bbm.store'), $this->period(['fuels' => [
            'HSD' => ['lain' => [$this->mak->id => ['test' => 200, 'cuci' => 100]], 'fisik' => 1],
        ]]))->assertRedirect()->assertSessionHasNoErrors();

        $stored = OperasiRekap::query()->where('jenis', RincianBbmSheet::JENIS)->sole()->overrides['HSD'];
        $this->assertEquals(1000, $stored['pengembalian']['amount']);
        $this->assertEquals(668000, $stored['fisik']);
        $this->assertEquals(['test' => 200, 'cuci' => 100], $stored['lain'][$this->mak->id]);

        $sheet = app(RincianBbmSheet::class);
        $totals = $sheet->totals($sheet->build($this->unit, 9, 2026))['HSD'];
        // 583058 + 1000 + 150000 − (29669 + 300) − 15000 − 500
        $this->assertEquals(688589, $totals['sisa']);
        $this->assertEquals(668000 - 688589, $totals['selisih']);
        $this->assertEquals(0, $sheet->totals($sheet->build($this->unit, 9, 2026))['MFO']['sisa']);

        foreach (['perincian-bbm', 'rekap-bbm'] as $menu) {
            $this->actingAs($tl)->get(route("operasi.pengusahaan.{$menu}.pdf", $this->period()))
                ->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_readers_view_but_cannot_save(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        foreach (['perincian-bbm', 'rekap-bbm'] as $menu) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.index", $this->period()))
                ->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
            $this->actingAs($managerUl)->post(route("operasi.pengusahaan.{$menu}.store"), $this->period())->assertForbidden();
        }
    }
}
