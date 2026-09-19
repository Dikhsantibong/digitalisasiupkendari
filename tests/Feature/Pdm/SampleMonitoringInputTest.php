<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Models\PdmSampleMonitoring;
use App\Models\Unit;
use App\Support\PdmSampleMonitoringForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class SampleMonitoringInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_shows_the_blank_form_rows(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.sample-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/input/sample-monitoring/index')
                ->has('sections', 3)
                ->has('document.rows.pengiriman', 12)
                ->has('document.rows.hasil', 8)
                ->has('document.rows.temuan', 6)
                ->has('document.rekap', 3)
                ->where('document.rekap.2.jenis', 'TOTAL')
                ->where('has_saved', false),
            );
    }

    public function test_saving_keeps_filled_rows_and_computes_the_rekap(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        $this->actingAs($user)->post(route('pdm.input.sample-monitoring.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'lokasi' => 'PLTD Kolaka', 'pic_monitoring' => 'Laode', 'catatan' => 'Sample dikirim via ekspedisi',
            'targets' => [['jenis' => 'Sample Oli', 'target' => 3, 'keterangan' => 'Bulanan']],
            'rows' => [
                'pengiriman' => [
                    ['tanggal_pengiriman' => '2026-08-03', 'jenis_sample' => 'Sample Oli', 'unit_peralatan' => 'Mesin #1', 'status_pengiriman' => 'Dikirim'],
                    ['jenis_sample' => 'Sample Oli', 'unit_peralatan' => 'Mesin #2', 'status_pengiriman' => 'Belum Dikirim'],
                    ['jenis_sample' => null, 'unit_peralatan' => null],
                ],
                'hasil' => [
                    ['jenis_sample' => 'Sample Oli', 'tanggal_hasil' => '2026-08-20', 'status' => 'Hasil Diterima', 'tindak_lanjut' => 'Ganti oli', 'status_tindak_lanjut' => 'Open'],
                ],
                'temuan' => [
                    ['temuan' => 'Viskositas turun', 'pic' => 'Laode', 'status' => 'Open'],
                ],
            ],
        ])->assertRedirect();

        $document = PdmSampleMonitoring::query()->with('items')->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('PLTD Kolaka', $document->lokasi);
        $this->assertCount(2, $document->items->where('section', 'pengiriman'));
        $this->assertSame('Viskositas turun', $document->items->firstWhere('section', 'temuan')->data['temuan']);

        $this->actingAs($user)
            ->get(route('pdm.input.sample-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('document.rows.pengiriman.0.unit_peralatan', 'Mesin #1')
                ->has('document.rows.pengiriman', 12)
                ->where('document.rekap.1.jenis', 'Sample Oli')
                ->where('document.rekap.1.target', 3)
                ->where('document.rekap.1.terkirim', 1)
                ->where('document.rekap.1.belum_terkirim', 2)
                ->where('document.rekap.1.hasil_diterima', 1)
                ->where('document.rekap.1.menunggu', 0)
                ->where('document.rekap.1.perlu_tindak_lanjut', 1)
                ->where('document.rekap.1.status', 'Belum Sesuai Target'),
            );
    }

    public function test_the_rekap_counts_only_matching_samples(): void
    {
        $rekap = PdmSampleMonitoringForm::rekap(
            [['jenis_sample' => 'sample oli trafo', 'tanggal_pengiriman' => '2026-08-01'], ['jenis_sample' => 'Sample Oli', 'status_pengiriman' => 'Diterima Lab']],
            [],
            ['Sample Oli Trafo' => ['target' => 1]],
        );

        $this->assertSame(1, $rekap[0]['terkirim']);
        $this->assertSame('Sesuai Target', $rekap[0]['status']);
        $this->assertSame(1, $rekap[1]['terkirim']);
        $this->assertSame(2, $rekap[2]['terkirim']);
    }

    public function test_the_pdf_has_two_landscape_pages(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.sample-monitoring.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $count = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_a_role_without_pdm_input_cannot_open_the_form(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('pdm.input.sample-monitoring.index', ['unit_id' => $unit->id]))
            ->assertForbidden();
    }
}
