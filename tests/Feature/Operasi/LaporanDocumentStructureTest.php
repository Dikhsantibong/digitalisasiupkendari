<?php

namespace Tests\Feature\Operasi;

use App\Enums\FuelType;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarUnsafeCondition;
use App\Models\KondisiAbnormal;
use App\Models\Machine;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiBlackstartJadwal;
use App\Models\OperasiCommissioningTest;
use App\Models\OperasiCommPeralatan;
use App\Models\OperasiDataTeknis;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiFlmMonitoring;
use App\Models\OperasiInventarisJadwal;
use App\Models\OperasiMeetingShiftJadwal;
use App\Models\OperasiPembuatanIk;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Laporan Operasi Pembangkit document follows the official Daftar Isi:
 * I. Sampul, II. Daftar Isi, III. Lembar Pengesahan, IV. Resume Statistik
 * (with 3D charts), V. 19 Laporan Operasi points (landscape; the jadwal &
 * input points embed their own PDF view), VI. Lampiran.
 */
class LaporanDocumentStructureTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const REPORT = 'laporan-operasi-bulanan';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_document_follows_the_daftar_isi_order(): void
    {
        [$unit, $engine] = $this->unitWithEngine();
        $html = $this->content($unit, $engine);

        $ids = ['sec-1', 'sec-2', 'sec-pengesahan', 'sec-3'];
        for ($point = 1; $point <= 19; $point++) {
            $ids[] = 'sec-4-'.$point;
        }
        $ids[] = 'sec-5';

        $positions = array_map(fn (string $id): int|false => strpos($html, 'id="'.$id.'"'), $ids);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_every_laporan_operasi_point_is_landscape_and_the_jadwal_tables_are_always_printed(): void
    {
        [$unit, $engine] = $this->unitWithEngine();
        $html = $this->content($unit, $engine);

        for ($point = 1; $point <= 19; $point++) {
            $this->assertStringContainsString('<div class="op-section op-landscape" id="sec-4-'.$point.'">', $html, 'sec-4-'.$point);
        }

        // Jadwal & input points embed their own PDF view — never a red line.
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 11, 15, 17, 18, 19] as $point) {
            $section = $this->section($html, 'sec-4-'.$point);
            $this->assertStringNotContainsString('op-red-line', $section, 'sec-4-'.$point);
            $this->assertStringContainsString('class="op-v-', $section, 'sec-4-'.$point);
        }

        // Pembuatan patrol check has no input yet.
        $this->assertStringContainsString('op-red-line', $this->section($html, 'sec-4-12'));
        $this->assertStringContainsString('<div class="op-section" id="sec-5">', $html);
    }

    public function test_the_pengesahan_takes_its_signatories_from_the_employees(): void
    {
        [$unit, $engine] = $this->unitWithEngine();
        $unit->update(['service_unit_id' => ServiceUnit::factory()->create()->id]);
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Adi Yusuf Sanjani N', 'position' => 'Team Leader Pemeliharaan']);
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Eric Agus Hasti Pradana', 'position' => 'Koordinator Pemeliharaan']);
        Employee::factory()->create(['unit_id' => null, 'service_unit_id' => $unit->service_unit_id, 'is_active' => true, 'name' => 'Zulkiflin', 'position' => 'Manager UL']);
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Fajar Project Leader', 'position' => 'Project Leader']);
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Gita Office Operasi', 'position' => 'Office Operasi']);
        // A free-text look-alike jabatan is never picked as signer.
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Bukan Penanda Tangan', 'position' => 'TL Operasi']);

        $section = $this->section($this->content($unit, $engine), 'sec-pengesahan');

        $this->assertStringContainsString('LEMBAR PENGESAHAN', $section);
        $this->assertStringContainsString('ADI YUSUF SANJANI N', $section);
        $this->assertStringContainsString('ERIC AGUS HASTI PRADANA', $section);
        $this->assertStringContainsString('ZULKIFLIN', $section);
        $this->assertStringNotContainsString('BUKAN PENANDA TANGAN', $section);
        $this->assertStringContainsString('Kendari, 1 September 2026', $section);

        // Tanda tangan laporan on the Resume Statistik: Project Leader + Office Operasi.
        $resume = $this->section($this->content($unit, $engine), 'sec-3');
        $this->assertStringContainsString('FAJAR PROJECT LEADER', $resume);
        $this->assertStringContainsString('GITA OFFICE OPERASI', $resume);
    }

    public function test_a_point_with_data_shows_its_table_and_feeds_the_resume(): void
    {
        [$unit, $engine] = $this->unitWithEngine();
        Operasi5s5rJadwal::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'no_urut' => 1,
            'pelaksana' => 'Regu A', 'rencana' => [1, 8], 'realisasi' => [1], 'target' => 2,
        ]);
        KondisiAbnormal::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'no_urut' => 1,
            'uraian_kondisi' => 'Trip mesin #1', 'tanggal' => '2026-08-05',
            'is_gangguan' => 1, 'durasi_gangguan' => 2.5,
        ]);

        $html = $this->content($unit, $engine);

        foreach (['sec-4-3', 'sec-4-14'] as $id) {
            $section = $this->section($html, $id);
            $this->assertStringNotContainsString('op-red-line', $section, $id);
            $this->assertStringContainsString('Regu A', $section, $id);
        }

        $kondisi = $this->section($html, 'sec-4-15');
        $this->assertStringNotContainsString('op-red-line', $kondisi);
        $this->assertStringContainsString('Trip mesin #1', $kondisi);

        $resume = $this->section($html, 'sec-3');
        $this->assertStringContainsString('Jadwal 5S5R', $resume);
        $this->assertStringContainsString('50%', $resume);
        $this->assertSame(2, substr_count($resume, 'data:image/png;base64'), 'the 3D bar & pie charts');
    }

    public function test_every_jadwal_and_input_source_renders_its_table(): void
    {
        [$unit, $engine] = $this->unitWithEngine();
        $period = ['unit_id' => $unit->id, 'year' => 2026, 'month' => 8];

        OperasiFlmJadwal::query()->create($period + ['section' => 'rutin', 'shift' => 'A', 'label' => 'REALISASI FLM', 'row_type' => 'mark', 'days' => ['1' => 'v', '2' => 'v'], 'target' => 4]);
        OperasiMeetingShiftJadwal::query()->create($period + ['label' => 'Meeting Regu B', 'row_type' => 'mark', 'days' => ['3' => 'v'], 'target' => 2]);
        OperasiInventarisJadwal::query()->create($period + ['uraian' => 'Inventaris Tools', 'shift' => 'C', 'rencana' => [5], 'realisasi' => [5], 'target' => 1]);
        OperasiPembuatanIk::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'nama' => 'IK Start Mesin', 'pic' => 'Andi', 'months' => ['8' => '1']]);
        OperasiDataTeknis::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'nama' => 'Data Teknis Genset', 'pic' => 'Budi', 'months' => ['8' => '1']]);
        OperasiBlackstartJadwal::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'uraian' => 'Cek Baterai Blackstart', 'rencana' => ['8-1'], 'realisasi' => ['8-1']]);
        OperasiCommPeralatan::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'nama_peralatan' => 'COMPRESOR 1', 'beban_50' => ['8-2']]);
        OperasiCommissioningTest::query()->create($period + ['section' => 'PERSIAPAN', 'no_urut' => 1, 'kegiatan' => 'Periksa PMT Feeder', 'status' => 'ok']);
        HarUnsafeCondition::query()->create($period + ['kategori' => 'Unsafe Condition', 'temuan' => 'Kabel terkelupas', 'lokasi' => 'Ruang Panel']);
        OperasiFlmMonitoring::factory()->create($period + ['mesin' => 'Sensor Coolant Temp']);

        $html = $this->content($unit, $engine);

        $expected = [
            'sec-4-2' => 'REALISASI FLM',
            'sec-4-2-monitoring' => 'Sensor Coolant Temp',
            'sec-4-4' => 'Meeting Regu B',
            'sec-4-5' => 'Inventaris Tools',
            'sec-4-6' => 'IK Start Mesin',
            'sec-4-7' => 'Data Teknis Genset',
            'sec-4-8' => 'Cek Baterai Blackstart',
            'sec-4-9' => 'COMPRESOR 1',
            'sec-4-10' => 'Data Teknis Genset',
            'sec-4-11' => 'Periksa PMT Feeder',
            'sec-4-13' => 'Kabel terkelupas',
        ];

        foreach ($expected as $id => $text) {
            $section = $this->section($html, $id);
            $this->assertStringNotContainsString('op-red-line', $section, $id);
            $this->assertStringContainsString($text, $section, $id);
        }

        $resume = $this->section($html, 'sec-3');
        $this->assertStringContainsString('Jadwal First Line Maintenance (Rutin)', $resume);
        $this->assertStringContainsString('Jadwal Pemeriksaan Instalasi Blackstart', $resume);
    }

    public function test_the_pdf_merges_portrait_and_landscape_pages(): void
    {
        [$unit, $engine] = $this->unitWithEngine();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.document.pdf', [
                'report' => self::REPORT, 'unit_id' => $unit->id, 'engine_id' => $engine->id,
                'month' => 8, 'year' => 2026,
            ]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        $reader = new Fpdi;
        $count = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $orientations = '';
        for ($page = 1; $page <= $count; $page++) {
            $orientations .= $reader->getTemplateSize($reader->importPage($page))['orientation'];
        }

        // Sampul, Daftar Isi, Pengesahan, Resume portrait; the 19 points landscape; Lampiran portrait.
        $this->assertMatchesRegularExpression('/^P{4,}L{19,}P$/', $orientations);
    }

    /**
     * @return array{0: Unit, 1: Machine}
     */
    private function unitWithEngine(): array
    {
        $unit = Unit::factory()->create();

        return [$unit, Machine::factory()->forUnit($unit)->create(['fuel_type' => FuelType::HsdOnly])];
    }

    private function content(Unit $unit, Machine $engine): string
    {
        $content = '';

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.laporan.document.edit', [
                'report' => self::REPORT, 'unit_id' => $unit->id, 'engine_id' => $engine->id,
                'month' => 8, 'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$content): void {
                $content = (string) $page->toArray()['props']['content'];
            });

        return $content;
    }

    /**
     * The markup of one report section, up to the next one.
     */
    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start, $id);
        $end = strpos($html, 'class="op-section', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}
