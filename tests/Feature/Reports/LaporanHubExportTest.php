<?php

namespace Tests\Feature\Reports;

use App\Enums\RoleName;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The PDF & Excel buttons beside "Buka Dokumen …" on every Laporan page
 * (components/laporan/laporan-export-buttons.tsx): Excel asks the document
 * page for just its deferred document (an Inertia partial reload) and builds
 * the book from that — the same document (and styles) the PDF prints.
 */
class LaporanHubExportTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string, 3: string}>
     */
    public static function documents(): array
    {
        return [
            'operasi project' => ['operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan'], 'operasi/laporan/document', 'operasi.laporan.document.pdf'],
            'operasi' => ['operasi.laporan.pengusahaan.edit', [], 'operasi/laporan/pengusahaan', 'operasi.laporan.pengusahaan.pdf'],
            'pemeliharaan project' => ['har.laporan.document.edit', [], 'har/laporan/document', 'har.laporan.document.pdf'],
            'pemeliharaan' => ['har.laporan.pengusahaan.edit', [], 'har/laporan/pengusahaan', 'har.laporan.pengusahaan.pdf'],
            'k3 project' => ['k3.laporan.document.edit', [], 'k3/laporan/document', 'k3.laporan.document.pdf'],
            'k3' => ['k3.laporan.pengusahaan.edit', [], 'k3/laporan/pengusahaan', 'k3.laporan.pengusahaan.pdf'],
            'logistik' => ['logistik.laporan.document.edit', [], 'logistik/laporan/document', 'logistik.laporan.document.pdf'],
            'pdm' => ['pdm.laporan.document.edit', [], 'pdm/laporan/document', 'pdm.laporan.document.pdf'],
        ];
    }

    /**
     * @param  array<string, string>  $params
     */
    #[DataProvider('documents')]
    public function test_the_laporan_page_gets_the_document_for_its_excel(string $route, array $params, string $component, string $pdfRoute): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin));
        $query = $params + ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $response = $this->get(route($route, $query), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
            'X-Inertia-Partial-Component' => $component,
            'X-Inertia-Partial-Data' => 'content,content_styles,grid,format,has_saved',
        ])->assertOk();

        $props = $response->json('props');
        $this->assertIsString($props['content']);
        // The document's own styles: the Excel lays the document out with them, as the PDF prints it.
        $this->assertIsString($props['content_styles']);
        // The lembar the Excel book splits on (report-book.ts): *-section / section-box blocks or break-before pages.
        $this->assertMatchesRegularExpression('/class="[^"]*(-section|section-box|break-before)\b/', $props['content']);
        $this->assertArrayNotHasKey('filters', $props, 'only the document is sent, not the whole page');
        $this->assertSame('html', $props['format']);
        $this->assertFalse($props['has_saved']);

        $this->get(route($pdfRoute, $query))->assertOk();
    }
}
