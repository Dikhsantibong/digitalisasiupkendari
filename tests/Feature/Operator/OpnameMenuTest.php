<?php

namespace Tests\Feature\Operator;

use App\Enums\KwhMeterKategori;
use App\Enums\OpnameJenis;
use App\Enums\RoleName;
use App\Enums\TankJenis;
use App\Models\BbmType;
use App\Models\FuelTank;
use App\Models\KwhMeter;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\OperatorOpname;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Opname BBM, Opname Pelumas and Opname kWh (modul Operator): one daily sheet
 * each, rows from the unit's own master, stand awal carried from the previous
 * opname and the kWh netto & SFC formulas.
 */
class OpnameMenuTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Poasia']);
    }

    public function test_the_operator_opens_the_three_opname_menus(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        foreach (['bbm' => ['fisik', 'administrasi'], 'pelumas' => ['pelumas'], 'kwh' => ['produksi', 'ps', 'susut_trafo']] as $jenis => $sections) {
            $this->actingAs($operator)->get(route("operator.opname-{$jenis}.index"))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component("operator/opname-{$jenis}/index")
                    ->where('unit.name', 'PLTD Poasia')
                    ->where('filters.tanggal', now('Asia/Makassar')->toDateString())
                    ->where('sections', fn ($list): bool => collect($list)->pluck('key')->all() === $sections)
                    ->where('can_write', true));
        }
    }

    public function test_opname_bbm_groups_the_tanks_by_jenis_and_carries_the_flowmeter_stand(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $tank = fn (string $name, ?TankJenis $jenis, int $order): FuelTank => FuelTank::factory()->forUnit($this->unit)->create(['name' => $name, 'jenis' => $jenis, 'sort_order' => $order, 'is_active' => true]);
        $setlink = $tank('Setlink HSD', TankJenis::Setlink, 1);
        $storage = $tank('Storage 1500 Ton', TankJenis::Storage, 2);
        $noJenis = $tank('Tangki Lama', null, 3);
        $service = $tank('Service Tank', TankJenis::Service, 4);
        $tank('Transfer Pump Tank', TankJenis::Transfer, 5);
        $mesin = Machine::factory()->create(['unit_id' => $this->unit->id, 'name' => 'MIRRLEES #1', 'is_active' => true]);

        $this->actingAs($operator)->get(route('operator.opname-bbm.index', ['tanggal' => '2026-11-04']))
            ->assertInertia(fn ($page) => $page
                // Storage first (a tank without jenis counts as storage), then setlink; transfer is not opnamed.
                ->where('sections.0.items', fn ($items): bool => collect($items)->map(fn ($i): string => "{$i['name']}:{$i['group']}")->all() === ['Storage 1500 Ton:storage', 'Tangki Lama:storage', 'Setlink HSD:setlink'])
                ->where('sections.1.items', fn ($items): bool => collect($items)->pluck('key')->all() === ["t{$service->id}", "m{$mesin->id}"]));

        $this->actingAs($operator)->post(route('operator.opname-bbm.store'), [
            'unit_id' => $this->unit->id,
            'tanggal' => '2026-11-04',
            'values' => [
                'fisik' => ["t{$storage->id}" => ['cm' => 210, 'liter' => 1200000], "t{$setlink->id}" => ['cm' => 95.5, 'liter' => '']],
                'administrasi' => ["t{$service->id}" => ['cm' => 80, 'liter' => 3500], "m{$mesin->id}" => ['awal' => 1000, 'akhir' => 1750, 'subtotal' => 999]],
                // Rows the sheet does not have are ignored.
                'lain' => ['x' => ['cm' => 1]],
            ],
        ])->assertRedirect(route('operator.opname-bbm.index', ['unit_id' => $this->unit->id, 'tanggal' => '2026-11-04']))->assertSessionHasNoErrors();

        $record = OperatorOpname::query()->sole();
        $this->assertSame(OpnameJenis::Bbm, $record->jenis);
        $this->assertSame('2026-11-04', $record->tanggal->toDateString());
        $this->assertEquals([
            'fisik' => ["t{$storage->id}" => ['cm' => 210, 'liter' => 1200000], "t{$setlink->id}" => ['cm' => 95.5]],
            'administrasi' => ["t{$service->id}" => ['cm' => 80, 'liter' => 3500], "m{$mesin->id}" => ['awal' => 1000, 'akhir' => 1750]],
        ], $record->data);
        $this->assertSame($operator->id, $record->input_by);
        $this->assertNotNull($noJenis);

        // The next day starts from the stand akhir.
        $this->actingAs($operator)->get(route('operator.opname-bbm.index', ['tanggal' => '2026-11-05']))
            ->assertInertia(fn ($page) => $page
                ->where("values.administrasi.m{$mesin->id}.awal", 1750)
                ->missing("values.fisik.t{$storage->id}")
                ->where('saved', null));
    }

    public function test_opname_kwh_computes_the_netto_psst_and_sfc(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $mesin = Machine::factory()->create(['unit_id' => $this->unit->id, 'name' => 'MAK #1', 'is_active' => true]);
        $produksi = KwhMeter::factory()->forUnit($this->unit)->create(['name' => 'kWh Generator 1', 'kategori' => KwhMeterKategori::Produksi]);
        $trafo = KwhMeter::factory()->forUnit($this->unit)->create(['name' => 'Trafo Step Up', 'kategori' => KwhMeterKategori::SusutTrafo]);
        KwhMeter::factory()->forUnit(Unit::factory()->create())->create(['name' => 'Unit lain', 'kategori' => KwhMeterKategori::Produksi]);
        $date = '2026-11-04';

        OperatorOpname::factory()->create(['unit_id' => $this->unit->id, 'tanggal' => $date, 'jenis' => OpnameJenis::Bbm, 'data' => ['administrasi' => ["m{$mesin->id}" => ['awal' => 0, 'akhir' => 1000]]]]);

        $this->actingAs($operator)->post(route('operator.opname-kwh.store'), [
            'unit_id' => $this->unit->id,
            'tanggal' => $date,
            'values' => [
                'produksi' => ["k{$produksi->id}" => ['awal' => 1000, 'akhir' => 5000]],
                // No kWh PS meter in the master: the mesin stands in.
                'ps' => ["m{$mesin->id}" => ['awal' => 0, 'akhir' => 200]],
                'susut_trafo' => ["k{$trafo->id}" => ['awal' => 0, 'akhir' => 100]],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($operator)->get(route('operator.opname-kwh.index', ['tanggal' => $date]))
            ->assertInertia(fn ($page) => $page
                ->where('sections.0.items', fn ($items): bool => collect($items)->pluck('name')->all() === ['kWh Generator 1'])
                ->where('sections.1.items.0.key', "m{$mesin->id}")
                ->where('summary', [
                    'produksi' => 4000, 'ps' => 200, 'susut_trafo' => 100,
                    // PSST = PS + susut trafo; netto = produksi − PSST.
                    'psst' => 300, 'netto' => 3700,
                    'bbm' => 1000,
                    // SFC bruto = kWh produksi ÷ BBM; netto = kWh netto ÷ BBM.
                    'sfc_bruto' => 4, 'sfc_netto' => 3.7,
                ])
                ->where('bbm_pemakaian', 1000));
    }

    public function test_opname_pelumas_lists_the_units_lubricants_validates_and_guards(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $argina = LubricantType::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Shell Argina S3', 'is_active' => true]);
        LubricantType::factory()->create(['unit_id' => Unit::factory()->create()->id, 'name' => 'Pelumas unit lain', 'is_active' => true]);
        $payload = fn (array $values): array => ['unit_id' => $this->unit->id, 'tanggal' => '2026-11-04', 'values' => ['pelumas' => ["l{$argina->id}" => $values]]];

        $this->actingAs($operator)->get(route('operator.opname-pelumas.index'))
            ->assertInertia(fn ($page) => $page->where('sections.0.items', fn ($items): bool => collect($items)->pluck('name')->all() === ['Shell Argina S3']));

        $this->actingAs($operator)->post(route('operator.opname-pelumas.store'), $payload(['awal' => -1]))->assertSessionHasErrors("values.pelumas.l{$argina->id}.awal");
        $this->actingAs($operator)->post(route('operator.opname-pelumas.store'), $payload(['akhir' => 'banyak']))->assertSessionHasErrors("values.pelumas.l{$argina->id}.akhir");
        $this->assertSame(0, OperatorOpname::query()->count());

        // TL Operasi reads the opname, an operator of another unit cannot save here.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))->get(route('operator.opname-pelumas.index'))
            ->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))->post(route('operator.opname-pelumas.store'), $payload(['awal' => 1]))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))->post(route('operator.opname-pelumas.store'), $payload(['awal' => 1]))->assertForbidden();

        // Saving, then clearing every value removes the day's opname.
        $this->actingAs($operator)->post(route('operator.opname-pelumas.store'), $payload(['awal' => 12, 'akhir' => 10]))->assertSessionHasNoErrors();
        $this->assertEquals(['pelumas' => ["l{$argina->id}" => ['awal' => 12, 'akhir' => 10]]], OperatorOpname::query()->sole()->data);
        $this->actingAs($operator)->post(route('operator.opname-pelumas.store'), $payload(['awal' => '', 'akhir' => '']))->assertSessionHasNoErrors();
        $this->assertSame(0, OperatorOpname::query()->count());
    }

    public function test_the_master_sets_the_jenis_tangki_and_the_kwh_meters(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $tank = FuelTank::factory()->forUnit($this->unit)->create(['name' => 'Tangki 1', 'jenis' => null]);

        $this->actingAs($koordinator)->put(route('operasi.master.update', ['fuel-tanks', $tank->id]), [
            'unit_id' => $this->unit->id, 'name' => 'Tangki 1', 'jenis' => 'setlink', 'bbm_type_id' => $tank->bbm_type_id ?? BbmType::factory()->create()->id, 'fuel_type' => 'hsd', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(TankJenis::Setlink, $tank->fresh()->jenis);

        $this->actingAs($koordinator)->post(route('operasi.master.store', 'kwh-meters'), [
            'unit_id' => $this->unit->id, 'name' => 'kWh Outgoing', 'kategori' => 'produksi', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(KwhMeterKategori::Produksi, KwhMeter::query()->sole()->kategori);
    }

    public function test_a_user_without_the_logsheet_permission_is_refused(): void
    {
        $user = User::factory()->create();

        foreach (['bbm', 'pelumas', 'kwh'] as $jenis) {
            $this->actingAs($user)->get(route("operator.opname-{$jenis}.index"))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['bbm', 'pelumas', 'kwh'] as $jenis) {
            $this->get(route("operator.opname-{$jenis}.index"))->assertRedirect(route('login'));
        }
    }
}
