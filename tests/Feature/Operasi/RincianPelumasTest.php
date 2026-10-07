<?php

namespace Tests\Feature\Operasi;

use App\Enums\LubricantUnit;
use App\Enums\RoleName;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiRekap;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Operasi\PerincianPelumasSheet;
use App\Services\Operasi\RekapPelumasSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Perincian Minyak Pelumas and Rekap Pelumas (Pemakaian Pelumas & Grease):
 * persediaan / penerimaan / TUG from Persediaan Pelumas, pemakaian per mesin
 * from Pemakaian Pelumas, the rest typed in.
 */
class RincianPelumasTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak;

    private LubricantType $oli;

    private LubricantType $grease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create();
        $this->mak = Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'merk' => 'MAK', 'type' => '8M 453 AK', 'serial_number' => '26881', 'is_active' => true]);
        $this->oli = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'Sallyx', 'code' => '420', 'unit_of_measure' => LubricantUnit::Liter, 'sort_order' => 0, 'is_active' => true]);
        $this->grease = LubricantType::factory()->forUnit($this->unit)->create(['name' => 'Grease EP2', 'unit_of_measure' => LubricantUnit::Kg, 'sort_order' => 1, 'is_active' => true]);

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $this->period(['readings' => [
            "{$this->oli->id}_{$this->mak->id}" => ['2' => 418],
            "{$this->grease->id}_{$this->mak->id}" => ['3' => 2],
        ]]))->assertSessionHasNoErrors();
        $this->actingAs($tl)->post(route('operasi.pengusahaan.persediaan-pelumas.store'), $this->period([
            'opening' => [$this->oli->id => 2717, $this->grease->id => 50],
            'entries' => [$this->oli->id => ['23' => ['penerimaan' => 9405], '25' => ['kirim_1' => 5]]],
        ]))->assertSessionHasNoErrors();
    }

    private function period(array $extra = []): array
    {
        return ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026] + $extra;
    }

    public function test_perincian_reads_the_other_sheets_and_keeps_only_what_is_typed(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $oli = (string) $this->oli->id;

        $this->actingAs($tl)->get(route('operasi.pengusahaan.perincian-pelumas.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/perincian-pelumas/index')
                ->has('lubricants', 2)
                ->where("auto.awal.{$oli}", 2717)
                ->where('auto.penerimaan.0.day', 23)
                ->where("auto.penerimaan.0.amounts.{$oli}", 9405)
                ->where("auto.kirim_persediaan.{$oli}", 5)
                ->where("auto.pemakaian.{$this->mak->id}.{$oli}", 418)
                ->where('lines', PerincianPelumasSheet::PENGIRIMAN));

        // Computed sisa: 2717 + 9405 + 10 − 418 − 20 − (5 + 100) = 11589; grease 50 − 2 = 48.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.perincian-pelumas.store'), $this->period([
            'asal' => ['23' => 'UPDK Kendari'],
            'pengembalian' => ['tanggal' => '2026-09-10', 'amounts' => [$oli => 10]],
            'non_mesin' => [$oli => 20],
            'pengiriman' => ['pltd' => ['tanggal' => '2026-09-18', 'amounts' => [$oli => 100]]],
            'fisik' => [$oli => 11589, $this->grease->id => 47],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $manual = OperasiRekap::query()->where('jenis', PerincianPelumasSheet::JENIS)->sole()->overrides;
        $this->assertSame('UPDK Kendari', $manual['asal'][23]);
        // A physical stock equal to the computed one is not stored.
        $this->assertSame([(string) $this->grease->id => 47.0], array_map('floatval', $manual['fisik']));

        $sheet = app(PerincianPelumasSheet::class);
        $totals = $sheet->totals($sheet->build($this->unit, 9, 2026));
        $this->assertEquals(11589, $totals[$oli]['sisa']);
        $this->assertEquals(0, $totals[$oli]['selisih']);
        $this->assertEquals(-1, $totals[(string) $this->grease->id]['selisih']);
        $this->assertEquals(12122 + 10 + 50, $totals['total']['total_persediaan']);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.perincian-pelumas.pdf', $this->period()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_rekap_pelumas_splits_pelumas_and_grease_and_reads_the_tug_from_persediaan(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $oli = (string) $this->oli->id;

        $this->actingAs($tl)->post(route('operasi.pengusahaan.rekap-pelumas.store'), $this->period([
            // The pengiriman line comes from Persediaan Pelumas and cannot be typed.
            'alat_bantu' => ['turbocharger' => [$oli => 4], 'pengiriman' => [$oli => 999]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['turbocharger'], array_keys(OperasiRekap::query()->where('jenis', RekapPelumasSheet::JENIS)->sole()->overrides['alat_bantu']));

        $sheet = app(RekapPelumasSheet::class);
        $totals = $sheet->totals($sheet->build($this->unit, 9, 2026));
        // 2717 + 9405 − 418 − (5 + 4)
        $this->assertEquals(11695, $totals[$oli]['kartu']);
        $this->assertEquals(9, $totals[$oli]['alat_bantu']);
        $this->assertEquals(11695, $totals['pelumas']['kartu']);
        $this->assertEquals(48, $totals['grease']['kartu']);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.rekap-pelumas.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/rekap-pelumas/index')
                ->where('lubricants.1.unit_of_measure', 'kg')
                ->where('machines.0.serial_number', '26881')
                ->where("manual.alat_bantu.turbocharger.{$oli}", 4)
                ->where('lines', RekapPelumasSheet::ALAT_BANTU));
        $this->actingAs($tl)->get(route('operasi.pengusahaan.rekap-pelumas.pdf', $this->period()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_ba_pemeriksaan_fisik_reads_perincian_and_its_count_feeds_the_other_sheets(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $oli = (string) $this->oli->id;

        // Administrasi = 2717 + 9405 − 418 − 5.
        $this->actingAs($tl)->get(route('operasi.pengusahaan.ba-fisik-pelumas.index', $this->period()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/ba-fisik-pelumas/index')
                ->where("rows.{$oli}.stock", 12122)
                ->where("rows.{$oli}.pemakaian", 418)
                ->where("rows.{$oli}.administrasi", 11699)
                ->where('header.tanggal', '2026-10-01')
                ->where('header.pukul', '10.00'));

        $this->actingAs($tl)->post(route('operasi.pengusahaan.ba-fisik-pelumas.store'), $this->period([
            'header' => ['nomor' => '027.BA/KIT.05.01/560204/2026', 'tanggal' => '2026-10-01', 'pukul' => '10.00', 'mengetahui' => 'Manajer Uji', 'dibuat' => 'TL Uji'],
            'items' => [$oli => ['drum' => 23, 'cm' => 0, 'liter' => 11690]],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        // The counted liters are the sisa fisik of Perincian and Rekap Pelumas.
        $perincian = app(PerincianPelumasSheet::class);
        $this->assertEquals(-9, $perincian->totals($perincian->build($this->unit, 9, 2026))[$oli]['selisih']);
        $rekap = app(RekapPelumasSheet::class);
        $this->assertEquals(11690, $rekap->totals($rekap->build($this->unit, 9, 2026))[$oli]['fisik']);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.ba-fisik-pelumas.pdf', $this->period()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_readers_view_and_print_but_cannot_save(): void
    {
        $managerUl = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        foreach (['perincian-pelumas', 'rekap-pelumas'] as $menu) {
            $this->actingAs($managerUl)->get(route("operasi.pengusahaan.{$menu}.index", $this->period()))
                ->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
            $this->actingAs($managerUl)->post(route("operasi.pengusahaan.{$menu}.store"), $this->period())->assertForbidden();
        }
    }
}
