<?php

namespace Tests\Feature\Reports;

use App\Services\Reports\OrientationPdfMerger;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class OrientationPdfMergerTest extends TestCase
{
    public function test_each_section_prints_on_its_own_page_in_its_orientation(): void
    {
        $body = '<div class="sec" id="a"><a href="#c">…</a></div>'
            .'<div class="sec wide" id="b">B</div>'
            .'<div class="sec wide" id="c">C</div>'
            .'<div class="sec" id="d">D</div>';

        $pdf = app(OrientationPdfMerger::class)->renderSections('', $body, 'sec', 'wide', 'Footer', unnumberedPages: 1);

        $this->assertSame('PLLP', $this->orientations($pdf));
    }

    public function test_a_nested_section_is_not_printed_twice(): void
    {
        $body = '<div class="sec" id="outer">Outer<div class="sec wide" id="inner">Inner</div></div>';

        $pdf = app(OrientationPdfMerger::class)->renderSections('', $body, 'sec', 'wide', 'Footer');

        $this->assertSame('P', $this->orientations($pdf));
    }

    public function test_a_body_without_sections_prints_whole_in_portrait(): void
    {
        $pdf = app(OrientationPdfMerger::class)->renderSections('', '<p>Isi bebas</p>', 'sec', 'wide', 'Footer');

        $this->assertSame('P', $this->orientations($pdf));
    }

    public function test_section_classes_match_whole_tokens_only(): void
    {
        $body = '<div class="sec" id="a">A</div><div class="section-like wide">not a section</div>';

        $pdf = app(OrientationPdfMerger::class)->renderSections('', $body, 'sec', 'wide', 'Footer');

        $this->assertSame('P', $this->orientations($pdf));
    }

    /**
     * One letter per page: P (portrait) or L (landscape).
     */
    private function orientations(string $pdf): string
    {
        $reader = new Fpdi;
        $count = $reader->setSourceFile(StreamReader::createByString($pdf));

        $orientations = '';
        for ($page = 1; $page <= $count; $page++) {
            $orientations .= $reader->getTemplateSize($reader->importPage($page))['orientation'];
        }

        return $orientations;
    }
}
