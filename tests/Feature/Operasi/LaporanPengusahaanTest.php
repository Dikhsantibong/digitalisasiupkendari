<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Models\BbmType;
use App\Models\DailyEngineReport;
use App\Models\DailyFeederReading;
use App\Models\Feeder;
use App\Models\FuelReceipt;
use App\Models\FuelTank;
use App\Models\Machine;
use App\Models\OperasiReportDocument;
use App\Models\OperasiResourcePembangkit;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operasi\OperasiPengusahaanBook;
use App\Services\Operasi\OperasiPengusahaanDocument;
use App\Services\Operasi\OperasiPengusahaanReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Laporan Pengusahaan Pembangkit of Operasi (Akses 2) is built from the
 * Pengusahaan Operasi inputs and opened from the Laporan Operasi page.
 */
class LaporanPengusahaanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private ServiceUnit $serviceUnit;

    private Unit $unit;

    private User $tl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($this->serviceUnit)->create(['is_active' => true, 'name' => 'PLTD Uji']);
        $this->tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
    }

    public function test_the_report_is_built_from_the_pengusahaan_inputs(): void
    {
        $this->seedInputs();

        $data = app(OperasiPengusahaanDocument::class)->build($this->unit, 8, 2026, $this->tl);
        $sections = collect($data['pengusahaan'])->keyBy('key');

        $this->assertSame(1, $sections['rekap']['no']);
        $this->assertTrue($sections['kinerja']['filled']);
        $kinerja = collect($sections['kinerja']['rows'])->mapWithKeys(fn (array $row): array => count($row) === 4 ? [$row[1]['t'] => $row[3]['t']] : []);
        $this->assertSame('1.500', $kinerja['Produksi kWh bruto'], 'Stand 1.000 at 31 Jul → 2.500 at 2 Aug.');
        $this->assertSame('100', $kinerja['Pemakaian sendiri (PS)']);
        $this->assertSame('1.400', $kinerja['Produksi kWh netto']);
        $this->assertSame('300', $kinerja['Pemakaian BBM HSD'], 'Flowmeter 5.000 → 5.300.');
        $this->assertSame('12.000', $kinerja['Penerimaan BBM HSD']);
        $this->assertSame('8.000', $kinerja['Stok BBM akhir bulan (Resource Pembangkit)']);
        $this->assertSame('700', $kinerja['Energi tersalur ke feeder'], 'Feeder stand 10.000 (Jul) → 10.700.');
        $this->assertSame('0,2000', $kinerja['SFC bruto']);

        $this->assertTrue($sections['harian']['filled']);
        $this->assertTrue($sections['penerimaan-bbm']['filled']);
        $this->assertTrue($sections['resource-pembangkit']['filled']);
        $this->assertTrue($sections['feeder']['filled']);
        $this->assertFalse($sections['star-stop']['filled']);

        // Portrait sections are numbered before the landscape ones.
        $orientations = collect($data['pengusahaan'])->skip(1)->pluck('orientation')->values()->all();
        $this->assertSame($orientations, collect($orientations)->sortBy(fn (string $o): int => $o === 'portrait' ? 0 : 1)->values()->all());
    }

    public function test_the_tl_opens_edits_and_saves_the_document(): void
    {
        $this->seedInputs();

        $this->actingAs($this->tl)
            ->get(route('operasi.laporan.pengusahaan.edit', ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/laporan/pengusahaan')
                ->where('can_write', true)
                ->where('has_saved', false)
                ->where('document_number', OperasiPengusahaanReport::documentNumber($this->unit, 8, 2026))
                ->where('content', fn (string $html): bool => str_contains($html, 'DAFTAR ISI') && str_contains($html, 'IKHTISAR SENTRAL') && str_contains($html, 'LAPORAN PENGUSAHAAN PEMBANGKIT'))
                ->has('grid.rows'));

        $this->actingAs($this->tl)
            ->post(route('operasi.laporan.pengusahaan.store'), ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026, 'format' => 'html', 'content_html' => '<p>Isi diedit TL</p>'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('operasi_report_documents', ['unit_id' => $this->unit->id, 'report_code' => 'pengusahaan', 'month' => 8, 'year' => 2026, 'engine_id' => null]);
        $this->actingAs($this->tl)
            ->get(route('operasi.laporan.pengusahaan.edit', ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('content', '<p>Isi diedit TL</p>'));

        $this->actingAs($this->tl)
            ->post(route('operasi.laporan.pengusahaan.regenerate'), ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026])
            ->assertSessionHasNoErrors();
        $this->assertStringContainsString('TUG 9 BBM &amp; PELUMAS', OperasiReportDocument::query()->where('report_code', 'pengusahaan')->value('content_html'));
    }

    public function test_the_chapters_follow_the_filing_order_and_the_jenis_bbm_of_the_master(): void
    {
        $hsd = BbmType::factory()->create(['code' => 'HSD', 'name' => 'Bio Solar', 'sort_order' => 0]);
        $mfo = BbmType::factory()->create(['code' => 'MFO', 'name' => 'MFO', 'sort_order' => 1]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $hsd->id, 'fuel_type' => TankFuelType::Hsd, 'is_active' => true]);
        FuelTank::factory()->create(['unit_id' => $this->unit->id, 'bbm_type_id' => $mfo->id, 'fuel_type' => TankFuelType::Mfo, 'is_active' => true]);
        Machine::factory()->forUnit($this->unit)->create(['name' => 'MAK #1', 'is_active' => true]);

        $chapters = app(OperasiPengusahaanBook::class)->chapters($this->unit, 9, 2026, $this->tl);
        $titles = array_column($chapters, 'title');

        $this->assertSame('IKHTISAR SENTRAL', $titles[0]);
        $this->assertSame('TUG 9 BBM & PELUMAS', end($titles));
        $this->assertCount(29, $chapters);
        // One Rincian BBM and one BBM page per jenis BBM of the unit, in the master order.
        $this->assertSame(['RINCIAN BBM HSD PLTD UJI', 'RINCIAN BBM MFO PLTD UJI'], array_values(array_filter($titles, fn (string $t): bool => str_starts_with($t, 'RINCIAN BBM'))));
        $this->assertSame(['BBM (HSD)', 'BBM (MFO)'], array_values(array_filter($titles, fn (string $t): bool => str_starts_with($t, 'BBM ('))));

        $byKey = collect($chapters)->keyBy('key');
        // Each chapter is the menu's own page, in its own orientation.
        $this->assertSame('landscape', $byKey['ikhtisar']['parts'][0]['orientation']);
        $this->assertStringContainsString('IKHTISAR SENTRAL', $byKey['ikhtisar']['parts'][0]['body']);
        $this->assertStringContainsString('PERINCIAAN BAHAN BAKAR', $byKey['rincian-bbm-mfo']['parts'][0]['body']);
        $this->assertStringContainsString('(MFO)', $byKey['rincian-bbm-mfo']['parts'][0]['body']);
        $this->assertCount(2, $byKey['neraca-daya']['parts']);
        $this->assertCount(2, $byKey['tug-9']['parts']);
        // A chapter without a menu yet prints a placeholder page.
        $this->assertStringContainsString('belum memiliki menu', $byKey['indikator']['parts'][0]['body']);
    }

    public function test_the_pdf_is_rendered(): void
    {
        $this->seedInputs();

        $response = $this->actingAs($this->tl)
            ->get(route('operasi.laporan.pengusahaan.pdf', ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_access_follows_the_pengusahaan_permissions(): void
    {
        $query = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];
        $staf = $this->userWithRole(RoleName::StafOperasi, $this->unit);
        $manager = $this->userWithRole(RoleName::ManagerUl, $this->serviceUnit);
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $tlElsewhere = $this->userWithRole(RoleName::TeamLeaderOperasi, Unit::factory()->create(['is_active' => true]));

        $this->actingAs($staf)->get(route('operasi.laporan.pengusahaan.edit', $query))->assertOk()->assertInertia(fn ($page) => $page->where('can_write', true));
        $this->actingAs($manager)->get(route('operasi.laporan.pengusahaan.edit', $query))->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)->post(route('operasi.laporan.pengusahaan.store'), $query + ['format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($koordinator)->get(route('operasi.laporan.pengusahaan.edit', $query))->assertForbidden();
        $this->actingAs($tlElsewhere)->get(route('operasi.laporan.pengusahaan.edit', $query))->assertForbidden();
    }

    public function test_the_laporan_operasi_document_routes_still_resolve_report_codes(): void
    {
        // "pengusahaan" is its own route and never reaches laporan/{report}.
        $this->assertSame(url('/operasi/laporan/pengusahaan'), route('operasi.laporan.pengusahaan.edit'));
        $this->assertSame(url('/operasi/laporan/laporan-operasi-bulanan/dokumen'), route('operasi.laporan.document.edit', 'laporan-operasi-bulanan'));
    }

    private function seedInputs(): void
    {
        $engine = Machine::factory()->create(['unit_id' => $this->unit->id, 'name' => 'MESIN A']);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-07-31')->create([
            'kwh_produksi_stand_akhir' => 1000, 'kwh_pakai_sendiri_stand_akhir' => 50, 'flowmeter_hsd_stand_akhir' => 5000, 'flowmeter_hsd_tambah_liter' => 0,
        ]);
        DailyEngineReport::factory()->forEngineOnDate($engine, '2026-08-02')->create([
            'kwh_produksi_stand_akhir' => 2500, 'kwh_pakai_sendiri_stand_akhir' => 150, 'flowmeter_hsd_stand_akhir' => 5300, 'flowmeter_hsd_tambah_liter' => 0,
            'beban_puncak_pagi_kw' => 400, 'beban_puncak_malam_kw' => 450,
        ]);
        FuelReceipt::factory()->create(['unit_id' => $this->unit->id, 'report_date' => '2026-08-05', 'fuel_type' => TankFuelType::Hsd, 'volume_liter' => 12000]);
        OperasiResourcePembangkit::query()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8, 'tanggal' => 1, 'stok_awal' => 10000, 'pemakaian' => 2000, 'pengiriman' => 0, 'stok_akhir' => 8000]);
        $feeder = Feeder::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Penyulang 1']);
        DailyFeederReading::factory()->create(['unit_id' => $this->unit->id, 'feeder_id' => $feeder->id, 'report_date' => '2026-07-31', 'stand_akhir' => 10000]);
        DailyFeederReading::factory()->create(['unit_id' => $this->unit->id, 'feeder_id' => $feeder->id, 'report_date' => '2026-08-02', 'stand_akhir' => 10700]);
    }
}
