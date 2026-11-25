<?php

namespace Tests\Unit\Operasi;

use App\Services\Operasi\DocumentGridBuilder;
use Tests\TestCase;

class DocumentGridBuilderTest extends TestCase
{
    public function test_grid_to_html_honours_merges_bold_and_alignment(): void
    {
        $grid = [
            'cols' => 2,
            'merges' => [[0, 0, 0, 1]],
            'rows' => [
                [['t' => 'JUDUL', 'b' => true, 'a' => 'c']],
                [['t' => 'kiri'], ['t' => '123', 'a' => 'r']],
            ],
        ];

        $html = app(DocumentGridBuilder::class)->gridToHtml($grid);

        $this->assertStringContainsString('colspan="2"', $html);
        $this->assertStringContainsString('font-weight:bold', $html);
        $this->assertStringContainsString('text-align:center', $html);
        $this->assertStringContainsString('text-align:right', $html);
        $this->assertStringContainsString('JUDUL', $html);
        // The merged origin is the only cell in the first row.
        $this->assertSame(1, substr_count(explode('</tr>', $html)[0], '<td'));
    }

    public function test_grid_to_html_escapes_cell_text(): void
    {
        $grid = [
            'cols' => 1,
            'merges' => [],
            'rows' => [[['t' => '<script>x</script>']]],
        ];

        $html = app(DocumentGridBuilder::class)->gridToHtml($grid);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_a_book_prints_every_sheet_on_its_own_page(): void
    {
        // Laporan modul Excel mode (2026-11-25): Sampul + one sheet per lembar.
        $sheet = fn (string $text, bool $noborder = false): array => ['name' => $text, 'cols' => 1, 'merges' => [], 'rows' => [[['t' => $text] + ($noborder ? ['noborder' => true] : [])]]];
        $grid = [...$sheet('Sampul', true), 'sheets' => [$sheet('Sampul', true), $sheet("Jadwal FLM\nBaris 2"), $sheet('PTW')]];

        $html = app(DocumentGridBuilder::class)->gridToHtml($grid);

        $this->assertSame(3, substr_count($html, '<table'));
        $this->assertSame(2, substr_count($html, 'page-break-after: always'));
        $this->assertStringContainsString("Jadwal FLM<br>\nBaris 2", $html);
        $this->assertStringContainsString('border:none', $html);
        $this->assertLessThan(strpos($html, 'PTW'), strpos($html, 'Jadwal FLM'));
    }
}
