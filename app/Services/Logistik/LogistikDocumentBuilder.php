<?php

namespace App\Services\Logistik;

use App\Enums\ReportModule;
use App\Http\Controllers\Logistik\FormController;
use App\Http\Controllers\Logistik\JadwalSheetController;
use App\Http\Controllers\Logistik\RekomendasiController;
use App\Models\LogistikFormRow;
use App\Models\LogistikJadwalRow;
use App\Models\LogistikRekomendasi;
use App\Models\Unit;
use App\Services\Pdm\PdmDocumentBuilder;
use App\Services\Reports\ReportWorkflowService;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\Indonesian;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikJadwal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Assembles the editable Laporan Logistik & Gudang: I. Sampul, II. Lembar
 * Pengesahan, III. Daftar Isi, IV. every jadwal and V. every input table.
 * Each table is its own PDF view (resources/views/logistik/…) rendered with
 * exactly the data its page shows — saved rows, or the page's default rows —
 * and embedded as a scoped fragment in its own portrait or landscape section.
 * Mirrors {@see PdmDocumentBuilder}.
 */
class LogistikDocumentBuilder
{
    public const TITLE = 'LAPORAN LOGISTIK & GUDANG PEMBANGKIT';

    public function __construct(
        private readonly ScopedHtmlFragment $fragments,
        private readonly ReportWorkflowService $workflows,
    ) {}

    /**
     * @return array{document: array{number: string, title: string, grid_name: string}, report: array<string, mixed>, parts: list<array{group: string, key: string, title: string, orientation: string, saved: bool, scope: string, body: string}>, styles: string}
     */
    public function build(Unit $unit, int $month, int $year): array
    {
        $parts = [];
        $styles = [];

        foreach ($this->sources($unit, $month, $year) as $source) {
            [$view, $data] = ($source['view'])();
            $scope = 'lg-v-'.Str::slug(str_replace('.', '-', $view));
            $fragment = $this->fragments->extract(View::make($view, $data)->render(), $scope);
            // Views shared by several sheets/forms (e.g. pelaksana, form) carry the same scoped CSS.
            $styles[$scope] = $fragment['css'];

            $parts[] = [
                'group' => $source['group'],
                'key' => $source['key'],
                'title' => $source['title'],
                'orientation' => $source['orientation'],
                'saved' => $source['saved'],
                'scope' => $scope,
                'body' => $fragment['body'],
            ];
        }

        return [
            'document' => [
                'number' => sprintf('LAP-LOG/%s/%02d/%d', Str::upper(Str::slug($unit->name)), $month, $year),
                'title' => self::TITLE,
                'grid_name' => 'Laporan Logistik',
            ],
            'report' => [
                'unit' => ['id' => $unit->id, 'name' => $unit->name],
                'period' => ['month' => $month, 'year' => $year, 'month_name' => Indonesian::monthName($month), 'label' => Indonesian::monthName($month).' '.$year],
                'pengesahan' => $this->pengesahan($unit, $month, $year),
            ],
            'parts' => $parts,
            'styles' => implode("\n", $styles),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('logistik.laporan.document-body', ['data' => $data])->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('logistik.laporan.letterhead', ['data' => $data])->render();
    }

    /**
     * The report styles plus every embedded table's scoped styles.
     *
     * @param  array<string, mixed>  $data
     */
    public function contentStyles(array $data): string
    {
        return View::make('logistik.laporan.styles')->render()."\n".$data['styles'];
    }

    /**
     * Lembar Pengesahan (Mengetahui Manager UL · Menyetujui TL Pemeliharaan ·
     * Memeriksa Koordinator Pemeliharaan) and the in-report block
     * (Project Leader · Office Logistik) of the unit, from the report workflow.
     *
     * @return array{tempat_tanggal: string, blocks: array{pengesahan: string, laporan: string}}
     */
    public function pengesahan(Unit $unit, int $month, int $year): array
    {
        return [
            'tempat_tanggal' => 'Kendari, '.Indonesian::longDate(Carbon::create($year, $month, 1)->endOfMonth()),
            'blocks' => $this->workflows->signatureBlocks(ReportModule::Logistik, $unit, $month, $year),
        ];
    }

    /**
     * Every table of the report in Daftar Isi order: the jadwal sheets, then
     * Rekomendasi, the grid inputs and the table forms.
     *
     * @return list<array{group: string, key: string, title: string, orientation: string, saved: bool, view: \Closure(): array{0: string, 1: array<string, mixed>}}>
     */
    private function sources(Unit $unit, int $month, int $year): array
    {
        $sheets = app(JadwalSheetController::class);
        $forms = app(FormController::class);
        $sheetSaved = fn (string $key): bool => LogistikJadwalRow::query()->where('unit_id', $unit->id)->where('jadwal', $key)
            ->where('year', $year)->where('month', LogistikJadwal::yearly($key) ? 0 : $month)->exists();
        $sheet = fn (string $key, string $group): array => [
            'group' => $group,
            'key' => $key,
            'title' => LogistikJadwal::sheet($key)['title'],
            'orientation' => LogistikJadwal::orientation($key),
            'saved' => $sheetSaved($key),
            'view' => fn (): array => $sheets->pdfView($key, $unit, $month, $year),
        ];

        return [
            ...array_map(fn (string $key): array => $sheet($key, 'jadwal'), LogistikJadwal::keysFor('jadwal')),
            [
                'group' => 'input',
                'key' => 'rekomendasi',
                'title' => 'Rekomendasi Logistik & Gudang',
                'orientation' => 'portrait',
                'saved' => LogistikRekomendasi::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->exists(),
                'view' => fn (): array => app(RekomendasiController::class)->pdfView($unit, $month, $year),
            ],
            ...array_map(fn (string $key): array => $sheet($key, 'input'), LogistikJadwal::keysFor('input')),
            ...array_map(fn ($form): array => [
                'group' => 'input',
                'key' => 'form-'.$form->key(),
                'title' => $form->title(),
                'orientation' => $form->orientation(),
                'saved' => LogistikFormRow::query()->where('unit_id', $unit->id)->where('form', $form->key())->where('year', $year)->where('month', $month)->exists(),
                'view' => fn (): array => $forms->pdfView($form, $unit, $month, $year),
            ], LogistikForms::all()),
        ];
    }
}
