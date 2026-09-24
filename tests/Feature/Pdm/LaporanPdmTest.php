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

    public function test_the_laporan_page_lists_the_report_contents_with_their_saved_state(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);
        $this->actingAs($user)->post(route('pdm.input.forms.store', ['form' => 'checklist-patrol-check']), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => ['turbin' => [['item' => 'Bearing Turbin', 'status' => 'OK']]],
        ]);

        $this->actingAs($user)
            ->get(route('pdm.laporan.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('contents', function ($contents): bool {
                    $keys = collect($contents)->pluck('key')->all();
                    $saved = collect($contents)->pluck('saved', 'key');
                    $realisasi = array_search('realisasi-prediktif', $keys, true);

                    // Same order as the Input PdM menu: both patrol check forms follow Realisasi Prediktif.
                    return array_slice($keys, $realisasi, 3) === ['realisasi-prediktif', 'patrol-check-pdm', 'checklist-patrol-check']
                        && $saved['checklist-patrol-check'] === true
                        && $saved['patrol-check-pdm'] === false
                        && collect($contents)->every(fn (array $item): bool => ! array_key_exists('view', $item));
                }),
            );
    }

    public function test_the_patrol_check_forms_reach_the_document(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];
        $this->actingAs($user)->post(route('pdm.input.forms.store', ['form' => 'patrol-check-pdm']), $period + [
            'rows' => ['checklist' => [['area' => 'Generator', 'item' => 'Getaran bearing uji', 'tidak' => '1', 'temuan' => 'Getaran DE tinggi']]],
        ]);
        $this->actingAs($user)->post(route('pdm.input.forms.store', ['form' => 'checklist-patrol-check']), $period + [
            'rows' => ['pendingin' => [['item' => 'Oil Cooler uji', 'status' => 'NOK', 'tindakan' => 'Ganti gasket cooler']]],
        ]);

        $props = [];
        $this->actingAs($user)
            ->get(route('pdm.laporan.document.edit', $period))
            ->assertInertia(function ($page) use (&$props): void {
                $props = $page->toArray()['props'];
            });
        $content = $props['content'];

        $positions = array_map(fn (string $key): int|false => strpos($content, 'id="part-'.$key.'"'), ['realisasi-prediktif', 'patrol-check-pdm', 'checklist-patrol-check']);
        $this->assertNotContains(false, $positions);
        $this->assertSame(array_values(collect($positions)->sort()->all()), $positions, 'Patrol check forms follow Realisasi Prediktif');

        $this->assertStringContainsString('PATROL CHECK PREDICTIVE MAINTENANCE (PdM)', $content);
        $this->assertStringContainsString('Getaran DE tinggi', $content);
        $this->assertStringContainsString('Rekap Hasil Patrol', $content);
        $this->assertStringContainsString('Ganti gasket cooler', $content);
        $this->assertStringContainsString('RINGKASAN HASIL PATROLI', $content);
        // Daftar Isi lists both forms; the checklist section prints its own kop.
        $this->assertStringContainsString('- Patrol Check Predictive Maintenance (PdM)', $content);
        $this->assertStringContainsString('- Laporan Checklist Patrol Check PdM', $content);
        $this->assertStringContainsString('DOKUMEN/LAPORAN CHECKLIST PATROL CHECK PdM', $content);
        $this->assertStringContainsString('Ganti gasket cooler', (string) json_encode($props['grid']['rows']));
    }

    public function test_every_report_section_carries_the_pln_logo_left_and_the_mkp_logo_right(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $content = '';
        $response->assertInertia(function ($page) use (&$content): void {
            $content = $page->toArray()['props']['content'];
        });

        $sections = preg_split('/(?=<div class="pdm-section)/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sections = array_values(array_filter($sections, fn (string $section): bool => str_starts_with($section, '<div class="pdm-section')));
        $this->assertGreaterThan(15, count($sections));

        foreach ($sections as $section) {
            preg_match('/id="([^"]+)"/', $section, $id);
            $pln = strpos($section, '/logo/sidebar-logo.png');
            $mkp = strpos($section, '/logo/mkp.jpg');

            $this->assertNotFalse($pln, "PLN logo missing in {$id[1]}");
            $this->assertNotFalse($mkp, "MKP logo missing in {$id[1]}");
            $this->assertLessThan($mkp, $pln, "PLN logo must be left of MKP in {$id[1]}");
        }
    }

    public function test_a_role_without_pdm_laporan_is_forbidden(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('pdm.laporan.index', ['unit_id' => $unit->id]))
            ->assertForbidden();
    }
}
