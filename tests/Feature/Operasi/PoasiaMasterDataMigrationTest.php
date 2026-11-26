<?php

namespace Tests\Feature\Operasi;

use App\Enums\KwhMeterKategori;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\KwhMeter;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The master data of PLTD Poasia & PLTD Poasia Containerized given by the user
 * (migration 2026_11_26_144800): known rows renamed in place, missing ones
 * created, every other row of those masters deactivated, never deleted.
 */
class PoasiaMasterDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        (require database_path('migrations/2026_11_26_144800_seed_poasia_fuel_lubricant_kwh_master.php'))->up();
    }

    public function test_it_fills_the_poasia_masters_and_keeps_the_ids_of_known_rows(): void
    {
        $poasia = Unit::factory()->create(['name' => 'PLTD Poasia']);
        $other = Unit::factory()->create(['name' => 'PLTD Lain']);
        $mirrlees = collect(['MIRRLEES #1', 'MIRRLEES #2', 'MIRRLEES #4', 'MIRRLEES #5'])
            ->map(fn (string $name) => Machine::factory()->create(['unit_id' => $poasia->id, 'name' => $name, 'is_active' => true]));
        $hsd1500 = FuelTank::factory()->forUnit($poasia)->create(['name' => 'Tangki HSD 1500 Ton']);
        $mfoC = FuelTank::factory()->forUnit($poasia)->create(['name' => 'Tangki MFO 1500 Ton (C)']);
        $aurelia = LubricantType::factory()->create(['unit_id' => $poasia->id, 'name' => 'Total Aurelia TI3030']);
        $argina = LubricantType::factory()->create(['unit_id' => $poasia->id, 'name' => 'Shell Argina S3']);
        $produksi = KwhMeter::factory()->forUnit($poasia)->create(['name' => 'kWh Produksi MIRRLEES #1', 'kategori' => KwhMeterKategori::Produksi, 'machine_id' => $mirrlees[0]->id]);
        $trafo = KwhMeter::factory()->forUnit($poasia)->create(['name' => 'kWh Trafo PS Utama', 'kategori' => KwhMeterKategori::SusutTrafo]);
        $otherTank = FuelTank::factory()->forUnit($other)->create(['name' => 'Tangki unit lain']);

        $this->migrate();
        $this->migrate(); // idempotent

        $tanks = FuelTank::query()->where('unit_id', $poasia->id)->where('is_active', true)->orderBy('sort_order')->get();
        $this->assertSame([
            'Tangki 1500 KL (HSD B50)', 'Tangki 90 KL (HSD B50)', 'Tangki 33 KL (HSD B50)',
            'Tangki 1500 KL (MFO) #1', 'Tangki 1500 KL (MFO) #2',
            'Tangki Harian 3.5 KL (HSD B50) #1', 'Tangki Harian 3.5 KL (HSD B50) #2',
        ], $tanks->pluck('name')->all());
        $this->assertSame($hsd1500->id, $tanks[0]->id, 'renamed in place');
        $this->assertEquals(1_500_000, $tanks[0]->capacity_liter);
        $this->assertSame('B50', BbmType::query()->find($tanks[0]->bbm_type_id)->code);
        $this->assertSame('MFO', BbmType::query()->find($tanks[3]->bbm_type_id)->code);
        $this->assertTrue($tanks[5]->is_daily_tank);
        $this->assertFalse($mfoC->fresh()->is_active, 'not in the list: deactivated, not deleted');
        $this->assertTrue($otherTank->fresh()->is_active, 'other units untouched');

        $this->assertSame(['Total Aurelia (Mirrlees)'], LubricantType::query()->where('unit_id', $poasia->id)->where('is_active', true)->pluck('name')->all());
        $this->assertSame($aurelia->id, LubricantType::query()->where('name', 'Total Aurelia (Mirrlees)')->value('id'));
        $this->assertFalse($argina->fresh()->is_active);

        $meters = KwhMeter::query()->where('unit_id', $poasia->id)->where('is_active', true)->orderBy('sort_order')->get();
        $this->assertCount(12, $meters);
        $this->assertSame('kWh Bruto MIRRLEES #1', $meters[0]->name);
        $this->assertSame($produksi->id, $meters[0]->id);
        $this->assertSame(['produksi', 'netto', 'ps'], $meters->pluck('kategori')->map->value->unique()->values()->all());
        $this->assertSame($mirrlees[3]->id, $meters->firstWhere('name', 'kWh Netto MIRRLEES #5')->machine_id);
        $this->assertFalse($trafo->fresh()->is_active);
        $this->assertSame(1, BbmType::query()->where('code', 'B50')->count());
    }

    public function test_the_containerized_unit_gets_its_daily_tanks_lubricants_and_incoming_meters(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia Containerized']);
        $cummins = collect(['CUMMINS #1', 'CUMMINS #2', 'CUMMINS #3'])
            ->map(fn (string $name) => Machine::factory()->create(['unit_id' => $unit->id, 'name' => $name, 'is_active' => true]));
        $timbun = FuelTank::factory()->forUnit($unit)->create(['name' => 'Tangki Timbun #1 (HSD)']);

        $this->migrate();

        $this->assertSame(
            ['Tangki Harian 2 KL (HSD B50) #1', 'Tangki Harian 2 KL (HSD B50) #2', 'Tangki Harian 2 KL (HSD B50) #3'],
            FuelTank::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertFalse($timbun->fresh()->is_active);
        $this->assertSame(['Meditran SX Plus CH-4', 'Trafolube A'], LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->pluck('name')->all());

        $meters = KwhMeter::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();
        $this->assertSame(['kWh Incoming Cummins #6', 'kWh Incoming Cummins #7', 'kWh Incoming Cummins #8'], $meters->pluck('name')->all());
        $this->assertSame($cummins->pluck('id')->all(), $meters->pluck('machine_id')->all());
    }

    public function test_a_database_without_the_poasia_units_is_left_alone(): void
    {
        $this->migrate();

        $this->assertSame(0, BbmType::query()->where('code', 'B50')->count());
    }
}
