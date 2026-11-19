<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Models\SlaDocument;
use App\Models\Unit;
use App\Services\Reports\SlaDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The SLA as an editable document (user, 2026-11-19): the SLA per divisi and
 * the Laporan SLA gabungan open in the document editor (Teks / Excel /
 * Pratinjau PDF); a saved document is what the PDF prints, "Muat Ulang dari
 * Data" rebuilds it from the inputs, and without one the PDF stays as before.
 */
class SlaDocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 10, 'year' => 2026];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Carbon::setTestNow('2026-11-20 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_sla_per_divisi_opens_as_an_editable_document(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Raha', 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)->get(route('sla.show', ['divisi' => 'operasi', 'unit_id' => $unit->id, ...self::PERIOD]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('laporan/sla')
                ->where('document.title', 'SLA 1.1.a Pelaksanaan Pengoperasian Pembangkit')
                ->where('document.has_saved', false)
                ->where('document.can_write', true)
                ->where('document.save_extra.scope', 'operasi')
                ->where('document.save_extra.unit_id', $unit->id)
                ->has('sla.rows')
                ->loadDeferredProps('document', fn (AssertableInertia $page) => $page
                    ->where('content', fn (string $html): bool => str_contains($html, 'id="sla-unit-'.$unit->id.'"')
                        && str_contains($html, 'Pelaksanaan Pengoperasian Pembangkit')
                        && ! str_contains($html, 'class="foot"'))
                    ->where('content_styles', fn (string $css): bool => str_contains($css, '.sla-doc'))
                    ->where('grid.rows', fn ($rows): bool => count($rows) > 5)));
    }

    public function test_a_saved_edit_is_what_the_pdf_prints_and_muat_ulang_restores_the_data(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Raha', 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $target = ['scope' => 'operasi', 'unit_id' => $unit->id, ...self::PERIOD];
        $pdf = fn (): string => (string) $this->actingAs($admin)->get(route('sla.pdf', ['divisi' => 'operasi', 'unit_id' => $unit->id, ...self::PERIOD]))->assertOk()->getContent();

        // Template PDF (nothing saved) — the same as before.
        $templatePages = $this->pages($pdf());

        // An edited value is saved and the PDF prints the saved document (one short page).
        $edited = '<div class="sla-doc-unit" id="sla-unit-'.$unit->id.'" data-unit-id="'.$unit->id.'"><div class="sla-doc"><table class="sla"><tr><td>Pencapaian diedit 87%</td></tr></table></div></div>';
        $this->actingAs($admin)->post(route('sla.document.store'), $target + ['format' => 'html', 'content_html' => $edited])->assertSessionHasNoErrors();

        $document = SlaDocument::query()->sole();
        $this->assertSame(['operasi', $unit->id, 'html', SlaDocumentBuilder::BODY_VERSION], [$document->scope, $document->unit_id, $document->format, $document->content_version]);
        $this->assertSame(1, $this->pages($pdf()));
        $this->actingAs($admin)->get(route('sla.show', ['divisi' => 'operasi', 'unit_id' => $unit->id, ...self::PERIOD]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('document.has_saved', true)
                ->loadDeferredProps('document', fn (AssertableInertia $page) => $page->where('content', $edited)));

        // Muat ulang dari data: back to the computed SLA.
        $this->actingAs($admin)->post(route('sla.document.regenerate'), $target)->assertSessionHasNoErrors();
        $this->assertStringContainsString('Pelaksanaan Pengoperasian Pembangkit', (string) $document->fresh()->content_html);
        $this->assertSame($templatePages, $this->pages($pdf()));

        // A grid (Excel mode) save prints too.
        $this->actingAs($admin)->post(route('sla.document.store'), $target + ['format' => 'grid', 'content_grid' => [
            'name' => 'SLA', 'cols' => 2, 'col_widths' => [200, 70], 'merges' => [], 'rows' => [[['t' => 'Kriteria', 'b' => true], ['t' => '95%']]],
        ]])->assertSessionHasNoErrors();
        $this->assertGreaterThanOrEqual(1, $this->pages($pdf()));
    }

    public function test_the_laporan_sla_gabungan_keeps_its_cover_per_unit_with_the_edited_body(): void
    {
        $poasia = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        $kolaka = Unit::factory()->create(['name' => 'PLTD Kolaka', 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)->get(route('sla.combined.index', ['unit_id' => 'all', ...self::PERIOD]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('laporan/sla-gabungan')
                ->where('document.title', 'Laporan SLA Gabungan')
                ->where('document.unit_label', 'Semua Unit')
                ->where('document.save_extra.unit_id', 'all')
                ->has('recap', 2)
                ->loadDeferredProps('document', fn (AssertableInertia $page) => $page
                    ->where('content', fn (string $html): bool => str_contains($html, 'id="sla-unit-'.$poasia->id.'"') && str_contains($html, 'id="sla-unit-'.$kolaka->id.'"') && str_contains($html, 'REKAPITULASI'))));

        $blocks = collect([$kolaka, $poasia])->map(fn (Unit $unit): string => '<div class="sla-doc-unit" id="sla-unit-'.$unit->id.'" data-unit-id="'.$unit->id.'"><div class="sla-doc"><p>Edit '.$unit->name.'</p></div></div>')->join("\n");
        $this->actingAs($admin)->post(route('sla.document.store'), ['scope' => 'gabungan', 'unit_id' => 'all', ...self::PERIOD, 'format' => 'html', 'content_html' => $blocks])->assertSessionHasNoErrors();
        $this->assertNull(SlaDocument::query()->sole()->unit_id);

        // Per unit: the cover (portrait) then its edited block (landscape).
        $content = (string) $this->actingAs($admin)->get(route('sla.combined.pdf', ['unit_id' => 'all', ...self::PERIOD]))->assertOk()->getContent();
        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString($content));
        $orientations = collect(range(1, $pages))->map(fn (int $p): string => $reader->getTemplateSize($reader->importPage($p))['orientation'])->all();
        $this->assertSame(['P', 'L', 'P', 'L'], $orientations);

        // One unit of the gabungan has its own (unsaved) document.
        $this->actingAs($admin)->get(route('sla.combined.index', ['unit_id' => $poasia->id, ...self::PERIOD]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('document.has_saved', false)->where('document.save_extra.unit_id', $poasia->id));
    }

    public function test_only_those_who_may_open_the_sla_save_it(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);
        $payload = ['format' => 'html', 'content_html' => '<p>x</p>', ...self::PERIOD];

        // The SLA per divisi stays Super Admin only; the Project Leader edits the gabungan of its own unit.
        $projectLeader = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);
        $this->actingAs($projectLeader)->post(route('sla.document.store'), ['scope' => 'operasi', 'unit_id' => $unit->id] + $payload)->assertForbidden();
        $this->actingAs($projectLeader)->post(route('sla.document.store'), ['scope' => 'gabungan', 'unit_id' => $other->id] + $payload)->assertForbidden();
        $this->actingAs($projectLeader)->post(route('sla.document.store'), ['scope' => 'gabungan', 'unit_id' => $unit->id] + $payload)->assertSessionHasNoErrors();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))->post(route('sla.document.store'), ['scope' => 'gabungan', 'unit_id' => $unit->id] + $payload)->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))->post(route('sla.document.store'), ['scope' => 'lainnya', 'unit_id' => $unit->id] + $payload)->assertSessionHasErrors('scope');

        $this->assertSame(1, SlaDocument::query()->count());
    }

    private function pages(string $pdf): int
    {
        return (new Fpdi)->setSourceFile(StreamReader::createByString($pdf));
    }
}
