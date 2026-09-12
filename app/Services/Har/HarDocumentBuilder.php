<?php

namespace App\Services\Har;

use App\Models\Unit;
use Illuminate\Support\Facades\View;

/**
 * Assembles the editable HAR monthly-report document: the letterhead metadata
 * (kop + ISO document number), the computed report payload, and the initial
 * rich-text body. The ISO numbers default from config/har.php and stay editable
 * inside the document itself (they are saved with the edited content).
 */
class HarDocumentBuilder
{
    public function __construct(private readonly HarReportBuilder $reports) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $report = $this->reports->monthly($unit, $month, $year);
        $numbers = (array) config('har.document.numbers', []);

        return [
            'document' => [
                'number' => (string) ($numbers['report'] ?? ''),
                'title' => (string) config('har.document.title', 'LAPORAN PEMELIHARAAN (HAR)'),
                'revision' => (string) config('har.document.revision', '00'),
                'numbers' => $numbers,
            ],
            'report' => $report,
        ];
    }

    /**
     * The initial rich-text body (letterhead + all report sections) shown in the
     * text editor and used for the text-mode PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('har.laporan.document-body', ['data' => $data])->render();
    }

    /**
     * The letterhead banner shown above the spreadsheet editor and prepended to
     * the grid-mode PDF.
     *
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('har.laporan.letterhead', ['data' => $data])->render();
    }

    public function contentStyles(): string
    {
        return View::make('har.laporan.styles')->render();
    }
}
