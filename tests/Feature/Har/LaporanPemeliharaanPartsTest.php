<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarLaporanGangguan;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\Har\HarDocumentBuilder;
use App\Services\Har\HarDocumentGridBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Laporan Pemeliharaan Pembangkit carries the HAR formulir & input data
 * (Logbook Mutasi, LH-05, Daily Meeting, Rekap & Abnormal Gangguan, Patrol
 * Check) and prints portrait + landscape pages merged into one PDF.
 */
class LaporanPemeliharaanPartsTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_saved_formulir_and_input_data_reach_the_report(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'Mesin Uji #3']);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->post(route('har.formulir.logbook-mutasi.store'), [
            'unit_id' => $unit->id, 'tanggal' => '2026-08-12',
            'absensi' => [['nama' => 'Mekanik Logbook', 'jabatan' => 'Mekanik']],
            'apd' => [['item' => 'HELM', 'keterangan' => 'Lengkap']],
            'rutin' => [['uraian' => 'PATROL CEK', 'keterangan' => 'Normal']],
            'non_rutin' => [['uraian' => 'Overhaul pompa transfer logbook', 'keterangan' => 'Selesai']],
            'kondisi_k3' => [],
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('har.formulir.daily-meeting.store'), [
            'unit_id' => $unit->id, 'tanggal' => '2026-08-14', 'acara' => 'Meeting HAR Uji', 'peserta' => [['nama' => 'Peserta Meeting Uji']],
        ])->assertSessionHasNoErrors();

        HarLaporanGangguan::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'tanggal_laporan' => '2026-08-20', 'gejala' => 'Gejala LH05 getaran tinggi',
        ]);
        HarLaporanGangguan::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'tanggal_laporan' => '2026-07-20', 'gejala' => 'Gejala bulan lain',
        ]);

        $this->actingAs($user)->post(route('har.input.laporan-gangguan.store'), $period + ['rows' => [
            ['tanggal_kejadian' => '2026-08-08', 'no_unit' => 'CUM #7', 'tindakan' => 'Ganti O ring rekap', 'sistem' => 'Sistem Pelumas', 'status' => 'Close'],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('har.input.abnormal-gangguan.store'), $period + ['rows' => [
            ['uraian' => 'Bocor BBM abnormal uji', 'jenis' => 'MESIN', 'tanggal' => '2026-08-20', 'abnormal' => 1, 'durasi_abnormal' => 2],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('har.input.lembar.store', ['lembar' => 'patrol-check-pemeliharaan']), $period + [
            'machine_id' => $machine->id,
            'rows' => [['section' => 'fuel', 'fields' => ['peralatan' => 'PT Pump Patrol Uji'], 'cells' => ['main' => ['d3' => 'N']]]],
        ])->assertSessionHasNoErrors();

        $builder = app(HarDocumentBuilder::class);
        $data = $builder->build($unit, 8, 2026);
        $parts = collect($data['parts'])->keyBy('key');

        foreach (['daily-meeting', 'logbook-mutasi', 'tabel-laporan-gangguan', 'tabel-abnormal-gangguan', "lembar-patrol-check-pemeliharaan-{$machine->id}"] as $key) {
            $this->assertTrue($parts[$key]['saved'], $key);
        }
        $this->assertSame('portrait', $parts['logbook-mutasi']['orientation']);
        $this->assertSame('landscape', $parts['tabel-laporan-gangguan']['orientation']);
        // Only the LH-05 of the selected month is embedded.
        $this->assertCount(1, $parts->filter(fn (array $part): bool => str_starts_with($part['key'], 'laporan-gangguan-lh05')));

        $body = $builder->bodyHtml($data);
        foreach (['Overhaul pompa transfer logbook', 'Peserta Meeting Uji', 'Gejala LH05 getaran tinggi', 'Ganti O ring rekap', 'Bocor BBM abnormal uji', 'PT Pump Patrol Uji', 'Mesin Uji #3'] as $text) {
            $this->assertStringContainsString($text, $body);
        }
        $this->assertStringNotContainsString('Gejala bulan lain', $body);
        // No rowspan body cells in the 5S5R table (dompdf breaks them across pages) and
        // tabel columns sized in % so they fit the report's margins.
        $this->assertStringNotContainsString('<td rowspan', $parts['program-5s5r']['body']);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*width: \d+px/', $parts['tabel-laporan-gangguan']['body']);
        $this->assertStringContainsString('href="#part-logbook-mutasi"', $body);
        $this->assertStringContainsString('class="har-section har-landscape" id="sec-5"', $body);

        // The editor gets each fragment's scoped styles; the grid carries the tables.
        $this->assertStringContainsString('.'.$parts['logbook-mutasi']['scope'], $builder->contentStyles($data));
        $this->assertStringContainsString('Ganti O ring rekap', json_encode(app(HarDocumentGridBuilder::class)->build($data)));
    }

    public function test_the_pdf_merges_portrait_and_landscape_pages(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $orientations = collect(range(1, $pages))->map(fn (int $page): string => $reader->getTemplateSize($reader->importPage($page))['orientation']);

        $this->assertSame('P', $orientations->first(), 'cover is portrait');
        $this->assertEqualsCanonicalizing(['P', 'L'], $orientations->unique()->values()->all());
    }
}
