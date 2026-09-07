<?php

namespace Tests\Feature\Operasi;

use App\Enums\BeritaAcaraType;
use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Enums\StockItemType;
use App\Enums\TankFuelType;
use App\Models\DailyEngineReport;
use App\Models\DocumentRecord;
use App\Models\DocumentTemplate;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\LubricantReceipt;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\PhysicalStockTake;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Services\Operasi\BeritaAcaraBuilder;
use App\Services\Operasi\DocumentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class BeritaAcaraTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_fuel_document_computes_administrative_stock_and_variance(): void
    {
        $unit = Unit::factory()->create();
        $tank = FuelTank::factory()->forUnit($unit)->create(['fuel_type' => TankFuelType::Hsd]);
        $engine = Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly]);

        $prev = ReportPeriod::factory()->forUnit($unit)->create(['month' => 7, 'year' => 2026]);
        $current = ReportPeriod::factory()->forUnit($unit)->create(['month' => 8, 'year' => 2026]);

        // Opening physical stock (previous period opname) = 1000 L.
        PhysicalStockTake::factory()->create([
            'unit_id' => $unit->id, 'report_period_id' => $prev->id,
            'item_type' => StockItemType::Fuel, 'tank_id' => $tank->id, 'physical_qty_liter' => 1000,
        ]);
        // Closing physical stock (current period opname) = 1500 L.
        PhysicalStockTake::factory()->create([
            'unit_id' => $unit->id, 'report_period_id' => $current->id,
            'item_type' => StockItemType::Fuel, 'tank_id' => $tank->id, 'physical_qty_liter' => 1500,
        ]);

        // Deliveries during the month = 500 L.
        FuelReceipt::factory()->create([
            'unit_id' => $unit->id, 'fuel_type' => TankFuelType::Hsd,
            'report_date' => '2026-08-05', 'volume_liter' => 500,
        ]);

        // Usage = 300 L (flow meter 0 -> 300 on day 1, factor defaults to 1).
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-07-31')->create([
            'flowmeter_hsd_stand_akhir' => 0,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-01')->create([
            'flowmeter_hsd_stand_akhir' => 300, 'flowmeter_hsd_tambah_liter' => 0,
        ]);

        $data = app(BeritaAcaraBuilder::class)->build($unit, BeritaAcaraType::Hsd, 8, 2026);

        $this->assertSame(1000.0, $data['persediaan_awal']);
        $this->assertSame(500.0, $data['penerimaan_total']);
        $this->assertSame(1500.0, $data['jumlah_stock']);   // A = awal + terima
        $this->assertSame(300.0, $data['pemakaian_total']);  // B
        $this->assertSame(1200.0, $data['administrasi']);    // D = A - B - C
        $this->assertSame(1500.0, $data['fisik_total']);     // E
        $this->assertSame(300.0, $data['selisih']);          // F = E - D
    }

    public function test_the_lubricant_document_lists_each_type_with_carryover(): void
    {
        $unit = Unit::factory()->create();
        $lubricant = LubricantType::factory()->forUnit($unit)->create();

        $prev = ReportPeriod::factory()->forUnit($unit)->create(['month' => 7, 'year' => 2026]);
        $current = ReportPeriod::factory()->forUnit($unit)->create(['month' => 8, 'year' => 2026]);

        PhysicalStockTake::factory()->create([
            'unit_id' => $unit->id, 'report_period_id' => $prev->id,
            'item_type' => StockItemType::Lubricant, 'tank_id' => null,
            'lubricant_type_id' => $lubricant->id, 'physical_qty_liter' => 50,
        ]);
        PhysicalStockTake::factory()->create([
            'unit_id' => $unit->id, 'report_period_id' => $current->id,
            'item_type' => StockItemType::Lubricant, 'tank_id' => null,
            'lubricant_type_id' => $lubricant->id, 'physical_qty_liter' => 60,
        ]);
        LubricantReceipt::factory()->create([
            'unit_id' => $unit->id, 'lubricant_type_id' => $lubricant->id,
            'report_date' => '2026-08-10', 'volume' => 20,
        ]);

        $data = app(BeritaAcaraBuilder::class)->build($unit, BeritaAcaraType::Pelumas, 8, 2026);

        $this->assertCount(1, $data['rows']);
        $row = $data['rows'][0];
        $this->assertSame(50.0, $row['awal']);
        $this->assertSame(20.0, $row['penerimaan']);
        $this->assertSame(70.0, $row['stock']);
        $this->assertSame(70.0, $row['administrasi']);
        $this->assertSame(60.0, $row['fisik_liter']);
        $this->assertSame(-10.0, $row['selisih']);
    }

    public function test_the_template_service_prefers_a_unit_override(): void
    {
        $unit = Unit::factory()->create();
        DocumentTemplate::query()->create([
            'type' => BeritaAcaraType::Hsd->value, 'unit_id' => $unit->id,
            'title' => 'Custom', 'document_number' => '999/KHUSUS',
        ]);

        $template = app(DocumentTemplateService::class)->resolve(BeritaAcaraType::Hsd, $unit);

        $this->assertSame('999/KHUSUS', $template->document_number);
    }

    public function test_a_role_without_permission_cannot_open_berita_acara(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operasi.berita-acara.index'))
            ->assertForbidden();
    }

    public function test_the_pdf_endpoint_returns_a_pdf(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.berita-acara.pdf', [
                'type' => BeritaAcaraType::Hsd->value,
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_saving_a_berita_acara_stores_the_edited_html(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.berita-acara.store'), [
                'unit_id' => $unit->id,
                'type' => BeritaAcaraType::Hsd->value,
                'month' => 8,
                'year' => 2026,
                'format' => 'html',
                'content_html' => '<p>Konten hasil edit</p>',
            ])
            ->assertRedirect();

        $record = DocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame(BeritaAcaraType::Hsd, $record->type);
        $this->assertSame('021/OPS/BA-HSD', $record->document_number);
        $this->assertStringContainsString('Konten hasil edit', (string) $record->content_html);
        $this->assertSame($user->id, $record->created_by);
        $this->assertIsArray($record->snapshot);
    }

    public function test_saving_twice_updates_the_same_record(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);
        $payload = [
            'unit_id' => $unit->id,
            'type' => BeritaAcaraType::Hsd->value,
            'month' => 8,
            'year' => 2026,
            'format' => 'html',
        ];

        $this->actingAs($user)->post(route('operasi.berita-acara.store'), [...$payload, 'content_html' => '<p>Versi 1</p>'])->assertRedirect();
        $this->actingAs($user)->post(route('operasi.berita-acara.store'), [...$payload, 'content_html' => '<p>Versi 2</p>'])->assertRedirect();

        $this->assertDatabaseCount('document_records', 1);
        $record = DocumentRecord::query()->firstOrFail();
        $this->assertStringContainsString('Versi 2', (string) $record->content_html);
    }

    public function test_saving_in_excel_mode_stores_the_grid(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operasi.berita-acara.store'), [
                'unit_id' => $unit->id,
                'type' => BeritaAcaraType::Hsd->value,
                'month' => 8,
                'year' => 2026,
                'format' => 'grid',
                'content_grid' => [
                    'cols' => 2,
                    'merges' => [],
                    'rows' => [[['t' => 'A'], ['t' => 'B']]],
                ],
            ])
            ->assertRedirect();

        $record = DocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('grid', $record->format);
        $this->assertIsArray($record->content_grid);
    }

    public function test_excel_mode_requires_a_grid(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->post(route('operasi.berita-acara.store'), [
                'unit_id' => $unit->id,
                'type' => BeritaAcaraType::Hsd->value,
                'month' => 8,
                'year' => 2026,
                'format' => 'grid',
            ])
            ->assertSessionHasErrors('content_grid');
    }

    public function test_pdf_renders_from_the_saved_grid_in_excel_mode(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)->post(route('operasi.berita-acara.store'), [
            'unit_id' => $unit->id,
            'type' => BeritaAcaraType::Hsd->value,
            'month' => 8,
            'year' => 2026,
            'format' => 'grid',
            'content_grid' => [
                'cols' => 2,
                'merges' => [[0, 0, 0, 1]],
                'rows' => [[['t' => 'JUDUL', 'b' => true, 'a' => 'c']], [['t' => 'x'], ['t' => '1', 'a' => 'r']]],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('operasi.berita-acara.pdf', [
            'type' => BeritaAcaraType::Hsd->value,
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_reopening_a_saved_document_loads_the_edited_html(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);

        $this->actingAs($user)->post(route('operasi.berita-acara.store'), [
            'unit_id' => $unit->id,
            'type' => BeritaAcaraType::Hsd->value,
            'month' => 8,
            'year' => 2026,
            'format' => 'html',
            'content_html' => '<p>Catatan khusus editan</p>',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('operasi.berita-acara.show', [
                'type' => BeritaAcaraType::Hsd->value,
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/berita-acara/editor')
                ->where('has_saved', true)
                ->has('content_styles')
                ->where('content', fn ($html) => str_contains((string) $html, 'Catatan khusus editan')),
            );
    }

    public function test_a_berita_acara_cannot_be_saved_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->post(route('operasi.berita-acara.store'), [
                'unit_id' => $foreignUnit->id,
                'type' => BeritaAcaraType::Hsd->value,
                'month' => 8,
                'year' => 2026,
                'format' => 'html',
                'content_html' => '<p>x</p>',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('document_records', 0);
    }
}
