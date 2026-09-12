<?php

namespace Tests\Feature;

use App\Http\Controllers\Concerns\EmbedsReportLogo;
use Tests\TestCase;

class ReportLogoEmbedTest extends TestCase
{
    private function embedder(): object
    {
        return new class
        {
            use EmbedsReportLogo;

            public function run(string $html): string
            {
                return $this->embedAssets($html);
            }
        };
    }

    public function test_it_inlines_a_relative_logo_src(): void
    {
        $out = $this->embedder()->run('<img src="/logo/sidebar-logo.png" alt="Logo">');

        $this->assertStringContainsString('data:image/png;base64,', $out);
        $this->assertStringNotContainsString('/logo/sidebar-logo.png', $out);
    }

    public function test_it_inlines_an_absolute_logo_src_rewritten_by_the_editor(): void
    {
        // TinyMCE rewrites the src to an absolute URL after a save; the embed must
        // still catch it, otherwise dompdf shows the alt text instead of the logo.
        $out = $this->embedder()->run('<img src="http://localhost/logo/sidebar-logo.png">');

        $this->assertStringContainsString('data:image/png;base64,', $out);
        $this->assertStringNotContainsString('logo/sidebar-logo.png"', $out);
    }
}
