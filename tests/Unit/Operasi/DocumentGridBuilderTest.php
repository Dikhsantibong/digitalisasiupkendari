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
}
