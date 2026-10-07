<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperasiIkhtisarSentralMesin;
use App\Models\OperasiRekap;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Ikhtisar Sentral takes its figures from Stand kWh, Jam Operasi, Beban
 * Tertinggi, Pemakaian BBM / Pelumas and Penerimaan BBM; only corrections are
 * remembered.
 */
class IkhtisarSentralOtomatisTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_the_figures_follow_the_other_sheets_and_corrections_are_kept(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create();
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'Bio Solar']);
        FuelTank::factory()->create(['unit_id' => $unit->id, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        $mak = Machine::factory()->forUnit($unit)->create(['name' => 'MAK #1', 'is_active' => true]);
        $oli = LubricantType::factory()->forUnit($unit)->create(['name' => 'Sallyx 420', 'is_active' => true]);
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026];

        $this->actingAs($tl)->post(route('operasi.pengusahaan.stand-kwh.store'), $period + [
            'opening' => [$mak->id => ['produksi' => 1000, 'ps' => 100]],
            'stands' => [$mak->id => ['1' => ['produksi' => 21000, 'ps' => 1100]]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($tl)->post(route('operasi.pengusahaan.jam-operasi.store'), $period + ['readings' => [$mak->id => ['1' => 20]]]);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.beban-tinggi.store'), $period + ['readings' => [$mak->id => ['1' => 1500]]]);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $period + ['readings' => ["HSD_{$mak->id}" => ['1' => 5000]]]);
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-pelumas.store'), $period + ['readings' => ["{$oli->id}_{$mak->id}" => ['1' => 20]]]);
        FuelReceipt::factory()->create(['unit_id' => $unit->id, 'report_date' => '2026-09-10', 'fuel_type' => TankFuelType::Hsd, 'volume_liter' => 8000]);

        $this->actingAs($tl)->get(route('operasi.pengusahaan.daily-report.index', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/operasi/daily-report/index')
                ->where("ikhtisar.mesins.{$mak->id}.kwh_dibangkit", 20000)
                ->where("ikhtisar.mesins.{$mak->id}.jam_jalan", 20)
                ->where("ikhtisar.mesins.{$mak->id}.bbm.HSD", 5000)
                ->where('fuels.0.code', 'HSD')
                ->where("ikhtisar.mesins.{$mak->id}.pemakaian_pelumas.{$oli->id}", 20)
                ->where("ikhtisar.mesins.{$mak->id}.t_kalor", round(5000 * 10289 * 0.949 / 20000, 2))
                ->where('ikhtisar.summary.kwh_pemakaian_sendiri', 1000)
                ->where('ikhtisar.summary.beban_puncak_malam_kw', 1500)
                ->where('ikhtisar.inventory.penerimaan.bbm.HSD', 8000)
                ->where('edited', []));

        // Saving with one corrected figure keeps only that correction.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.daily-report.store'), $period + [
            'summary' => ['kwh_pemakaian_sendiri' => 1000, 'beban_puncak_pagi_kw' => 1200, 'beban_puncak_malam_kw' => 1500, 'jam_jalan_perhari' => 24],
            'mesins' => [['engine_id' => $mak->id, 'kwh_dibangkit' => 20000, 'jam_jalan' => 19.5, 'bbm' => ['HSD' => 5000], 't_kalor' => 0, 'pemakaian_pelumas' => [$oli->id => 20]]],
            'inventory' => ['penerimaan' => ['bbm' => ['HSD' => 8000], 'lubricants' => [$oli->id => 0]]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $overrides = OperasiRekap::query()->where('jenis', 'ikhtisar-sentral')->sole()->overrides;
        $this->assertSame(19.5, $overrides["mesins.{$mak->id}.jam_jalan"]);
        $this->assertArrayNotHasKey("mesins.{$mak->id}.kwh_dibangkit", $overrides);
        // The per-jenis BBM is stored, and the legacy HSD column still follows it.
        $this->assertSame(5000.0, (float) OperasiIkhtisarSentralMesin::query()->sole()->pemakaian_hsd);

        // A later change in Pemakaian BBM still flows in; the corrected jam jalan stays.
        $this->actingAs($tl)->post(route('operasi.pengusahaan.pemakaian-bbm.store'), $period + ['readings' => ["HSD_{$mak->id}" => ['1' => 6000]]]);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.daily-report.index', $period))
            ->assertInertia(fn ($page) => $page
                ->where("ikhtisar.mesins.{$mak->id}.bbm.HSD", 6000)
                ->where("ikhtisar.mesins.{$mak->id}.jam_jalan", 19.5)
                ->where('ikhtisar.summary.beban_puncak_pagi_kw', 1200));
    }
}
