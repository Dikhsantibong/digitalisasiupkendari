<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\Har\HarDocumentBuilder;
use Database\Seeders\HarMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * One Work Order input grouped into the Laporan Pengusahaan sheets: WO PM/PdM/CM,
 * WO Rekomendasi Enjiniring (A15), WO Waiting Shutdown (A16) and WO Waiting
 * Material dan Jasa (A17).
 */
class WorkOrderGroupingTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(HarMasterSeeder::class);
    }

    public function test_waiting_details_and_material_lines_are_saved_and_shown(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->create(['name' => 'MAK #4']);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.work-order.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [[
                'wonum' => 'WO4663',
                'description' => 'PENGGANTIAN MATERIAL MAK 4',
                'type_code' => 'CM',
                'engine_name' => 'MAK #4',
                'assetnum' => 'WUAWTD004MJA10AV001',
                'work_group_code' => 'MECHD',
                'owner_group' => 'MECHD',
                'status_code' => 'WMATL',
                'waiting_reason' => 'material',
                'priority_text' => 'Urgent',
                'materials' => [
                    ['description' => 'Piston Ring 1', 'stockcode' => '186117', 'amount' => '1'],
                    ['description' => '', 'stockcode' => '', 'amount' => ''],
                ],
            ]],
        ])->assertSessionHasNoErrors();

        $wo = WorkOrder::query()->sole();
        $this->assertSame('WUAWTD004MJA10AV001', $wo->assetnum);
        $this->assertSame('Urgent', $wo->priority_text);
        $this->assertSame([['description' => 'Piston Ring 1', 'stockcode' => '186117', 'amount' => '1']], $wo->materials);
        $this->assertSame('WMATL', $wo->status->code);

        $this->actingAs($user)->get(route('har.input.work-order.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.assetnum', 'WUAWTD004MJA10AV001')
                ->where('rows.0.owner_group', 'MECHD')
                ->where('rows.0.materials.0.stockcode', '186117'));
    }

    public function test_the_pengusahaan_sheets_group_the_same_work_orders(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->create(['name' => 'MAK #2']);
        Machine::factory()->forUnit($unit)->create(['name' => 'MAK #3']);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.work-order.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [
                ['wonum' => 'WOENJI1', 'description' => 'Rekomendasi modifikasi cooling', 'type_code' => 'ENJI', 'status_code' => 'INPRG', 'work_group_code' => 'MECHD'],
                ['wonum' => 'WOSHUT1', 'description' => 'Overhaul menunggu shutdown', 'type_code' => 'PM', 'engine_name' => 'MAK #3', 'assetnum' => 'WUAWTD003MJA', 'waiting_reason' => 'shutdown', 'priority_text' => 'High'],
                ['wonum' => 'WOMAT1', 'description' => 'Pressure gauge rusak', 'type_code' => 'CM', 'work_group_code' => 'MECHD', 'waiting_reason' => 'material',
                    'materials' => [['description' => '0-10 Bar', 'stockcode' => '102120'], ['description' => '0-16 Bar', 'stockcode' => '104733']]],
                ['wonum' => 'WOLIS1', 'description' => 'KVAR abnormal', 'type_code' => 'CM', 'work_group_code' => 'ELECD', 'waiting_reason' => 'jasa',
                    'materials' => [['description' => 'AVR,RBS,TBS', 'amount' => '1 Set']]],
            ],
        ])->assertSessionHasNoErrors();

        $builder = app(HarDocumentBuilder::class);
        $html = $builder->pengusahaanBodyHtml($builder->build($unit, 8, 2026));

        // A15 Rekomendasi Enjiniring.
        $enji = $this->section($html, 'sec-16');
        $this->assertStringContainsString('WO REKOMENDASI ENJINIRING YANG TERBIT BULAN INI', $enji);
        $this->assertStringContainsString('FMKD-314-10.3.3-A15', $enji);
        $this->assertStringContainsString('WOENJI1', $enji);

        // A16 Waiting Shutdown: one block per machine, the WO in its machine's block.
        $shutdown = $this->section($html, 'sec-17');
        $this->assertStringContainsString('FMKD-314-10.3.3-A16', $shutdown);
        $this->assertSame(2, substr_count($shutdown, 'MESIN: '));
        $this->assertStringContainsString('WOPRIOR TEXT', $shutdown);
        $this->assertInOrder($shutdown, ['MESIN: MAK #3', 'WOSHUT1', 'WUAWTD003MJA', 'High']);

        // A17 Waiting Material dan Jasa: one line per item, per bidang.
        $material = $this->section($html, 'sec-18');
        $this->assertStringContainsString('FMKD-314-10.3.3-A17', $material);
        foreach (['Mekanik', 'Listrik', 'Kontrol dan Instrumen', 'Sipil'] as $bidang) {
            $this->assertStringContainsString("disechedulekan Bidang {$bidang}", $material);
        }
        $this->assertSame(2, substr_count($material, 'WOMAT1'));
        $this->assertInOrder($material, ['Bidang Mekanik', '102120', '104733', 'Bidang Listrik', 'WOLIS1', '1 Set', 'Bidang Kontrol']);
        $this->assertStringNotContainsString('WOSHUT1', $material);

        // The CM waiting material is still a CM WO (A14) — grouped, not moved.
        $this->assertStringContainsString('WOMAT1', $this->section($html, 'sec-15'));
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertInOrder(string $haystack, array $needles): void
    {
        $offset = 0;
        foreach ($needles as $needle) {
            $position = strpos($haystack, $needle, $offset);
            $this->assertNotFalse($position, "'{$needle}' missing or out of order");
            $offset = $position + strlen($needle);
        }
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start, $id);
        $next = strpos($html, 'class="break-before', $start + 10);

        return substr($html, $start, $next === false ? null : $next - $start);
    }
}
