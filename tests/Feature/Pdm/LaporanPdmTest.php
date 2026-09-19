<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\PdmDocumentRecord;
use App\Models\PdmSampleMonitoring;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanPdmTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_laporan_page_shows_the_filters(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.laporan.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/laporan/index')
                ->where('filters', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026])
                ->has('options.units', 1),
            );
    }

    public function test_the_document_embeds_every_jadwal_and_input_table_without_red_lines(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create(['name' => 'ZHEJIANG #1']);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/laporan/document')
                ->where('has_saved', false)
                ->where('format', 'html')
                ->where('content', function (string $content): bool {
                    foreach (['jadwal-harian', 'jadwal-patrol-check', 'jadwal-5s5r', 'jadwal-meeting', 'kesiapan-apd', 'sample-monitoring', 'permit-to-work', 'checklist-5s5r', 'air-pendingin', 'kontrol-material', 'realisasi-prediktif'] as $key) {
                        if (! str_contains($content, 'id="part-'.$key.'"')) {
                            return false;
                        }
                    }

                    return str_contains($content, 'LEMBAR PENGESAHAN')
                        && str_contains($content, 'ZHEJIANG #1')
                        && ! str_contains($content, 'k3-no-data')
                        && ! str_contains($content, 'data:image/png;base64');
                })
                ->where('content_styles', fn (string $styles): bool => str_contains($styles, '.pdm-v-pdm-input-sample-monitoring-pdf')
                    && ! str_contains($styles, '@page { size: A4 landscape'))
                ->has('grid.rows'),
            );
    }

    public function test_saved_sample_monitoring_data_reaches_the_document(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        PdmSampleMonitoring::factory()->create(['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'lokasi' => 'Gudang Sampel Poasia']);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->where('content', fn (string $content): bool => str_contains($content, 'Gudang Sampel Poasia')));
    }

    public function test_the_edited_document_is_saved_and_can_be_regenerated(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('pdm.laporan.document.store'), $period + ['format' => 'html', 'content_html' => '<div class="pdm-section"><p>Catatan edit manual</p></div>'])
            ->assertRedirect();

        $record = PdmDocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertStringContainsString('Catatan edit manual', (string) $record->content_html);

        $this->actingAs($user)
            ->get(route('pdm.laporan.document.edit', $period))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('content', '<div class="pdm-section"><p>Catatan edit manual</p></div>'));

        $this->actingAs($user)->post(route('pdm.laporan.document.regenerate'), $period)->assertRedirect();

        $this->assertStringContainsString('id="part-sample-monitoring"', (string) $record->fresh()->content_html);
    }

    public function test_the_pdf_merges_portrait_front_pages_with_landscape_tables(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'download' => 1]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertGreaterThan(10, $pages);
        $this->assertSame('P', $reader->getTemplateSize($reader->importPage(1))['orientation']);

        $orientations = collect(range(1, $pages))->map(fn (int $page): string => $reader->getTemplateSize($reader->importPage($page))['orientation'])->unique();
        $this->assertContains('L', $orientations->all());
    }

    public function test_a_role_without_pdm_write_cannot_save_the_document(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('pdm.laporan.document.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'format' => 'html', 'content_html' => '<p>x</p>'])
            ->assertForbidden();
    }

    public function test_a_role_without_pdm_laporan_is_forbidden(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('pdm.laporan.index', ['unit_id' => $unit->id]))
            ->assertForbidden();
    }
}
