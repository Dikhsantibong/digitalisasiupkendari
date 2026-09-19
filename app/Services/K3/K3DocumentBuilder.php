<?php

namespace App\Services\K3;

use App\Enums\ReportModule;
use App\Models\Unit;
use App\Services\Reports\ReportWorkflowService;
use Illuminate\Support\Facades\View;

/**
 * Assembles the editable K3 monthly-report document: letterhead metadata (kop +
 * ISO document number), the computed report payload, and the initial rich-text
 * body. ISO numbers default from config/k3.php and stay editable inside the
 * document. Mirrors {@see HarDocumentBuilder}.
 */
class K3DocumentBuilder
{
    public function __construct(
        private readonly K3ReportBuilder $reports,
        private readonly K3InputTables $inputTables,
        private readonly ReportWorkflowService $workflows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $report = $this->reports->monthly($unit, $month, $year);
        $numbers = (array) config('k3.document.numbers', []);

        return [
            'document' => [
                'number' => (string) ($numbers['report'] ?? ''),
                'title' => (string) config('k3.document.title', 'LAPORAN KINERJA K3 & KEAMANAN'),
                'revision' => (string) config('k3.document.revision', '00'),
                'numbers' => $numbers,
                'signature_blocks' => $this->workflows->signatureBlocks(ReportModule::K3, $unit, $month, $year),
            ],
            'report' => $report,
            // The K3 input tables (same as their PDF/Excel exports) behind the report points.
            'inputs' => $this->inputTables->forReport($unit, $month, $year),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('k3.laporan.document-body', ['data' => $data])->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('k3.laporan.letterhead', ['data' => $data])->render();
    }

    public function contentStyles(): string
    {
        return View::make('k3.laporan.styles')->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function pengusahaanBodyHtml(array $data): string
    {
        return View::make('k3.laporan.pengusahaan-body', ['data' => $data])->render();
    }

    public function pengusahaanStyles(): string
    {
        return View::make('k3.laporan.pengusahaan-styles')->render();
    }
}
