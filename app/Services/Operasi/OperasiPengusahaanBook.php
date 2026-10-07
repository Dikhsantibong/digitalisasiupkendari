<?php

namespace App\Services\Operasi;

use App\Enums\BeritaAcaraType;
use App\Enums\JamMesinJenis;
use App\Enums\MesinHarianJenis;
use App\Http\Controllers\Operasi\PengusahaanBaFisikPelumasController;
use App\Http\Controllers\Operasi\PengusahaanBeritaAcaraController;
use App\Http\Controllers\Operasi\PengusahaanDailyReportController;
use App\Http\Controllers\Operasi\PengusahaanInventarisController;
use App\Http\Controllers\Operasi\PengusahaanJamMesinController;
use App\Http\Controllers\Operasi\PengusahaanKinerjaController;
use App\Http\Controllers\Operasi\PengusahaanKinerjaTermalController;
use App\Http\Controllers\Operasi\PengusahaanKwhController;
use App\Http\Controllers\Operasi\PengusahaanMesinHarianController;
use App\Http\Controllers\Operasi\PengusahaanPemakaianBbmController;
use App\Http\Controllers\Operasi\PengusahaanPemakaianPelumasController;
use App\Http\Controllers\Operasi\PengusahaanRincianBbmController;
use App\Http\Controllers\Operasi\PengusahaanRincianPelumasController;
use App\Http\Controllers\Operasi\PengusahaanTugBbmController;
use App\Http\Controllers\Operasi\PengusahaanTugPelumasController;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\PdfViewRecorder;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\Indonesian;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The chapters of the Laporan Pengusahaan Pembangkit (Operasi), in the order
 * the unit files them (Ikhtisar Sentral … TUG 9). Each chapter is the PDF page
 * of its Pengusahaan menu itself — the controller's pdf action is run with a
 * {@see PdfViewRecorder} so the report prints exactly what the menu prints —
 * embedded as a scoped fragment in its own orientation; the PDF merges the
 * portrait and landscape pages.
 *
 * Chapters per jenis BBM (Rincian BBM, BBM, BA fisik BBM) follow the unit's
 * jenis BBM in the master ({@see UnitFuelTypes}); nothing is fixed to HSD/MFO.
 * A chapter without a menu yet prints a placeholder page.
 */
class OperasiPengusahaanBook
{
    public function __construct(
        private readonly ScopedHtmlFragment $fragments,
        private readonly UnitFuelTypes $fuels,
        private readonly RincianBbmSheet $rincianBbm,
    ) {}

    /**
     * @return list<array{no: int, key: string, title: string, parts: list<array{id: string, orientation: string, scope: string, body: string, css: string}>}>
     */
    public function chapters(Unit $unit, int $month, int $year, User $user): array
    {
        $fuels = $this->fuels->forUnit($unit);
        $call = fn (string $controller, string $method, array $query = [], array $args = []): Closure => fn () => app($controller)->{$method}($this->request($unit, $month, $year, $user, $query), ...$args);

        $specs = [
            ['ikhtisar', 'IKHTISAR SENTRAL', [$call(PengusahaanDailyReportController::class, 'pdf')]],
            ['ba-feeder', 'BERITA ACARA KWH FEEDER', [$call(PengusahaanBeritaAcaraController::class, 'pdf', [], [BeritaAcaraType::Feeder->value])]],
            ['ba-fisik', 'BERITA ACARA PEMERIKSAAN FISIK BBM DAN PELUMAS', [
                // The BBM berita acara exist per jenis the BA module knows; only the unit's own jenis are printed.
                ...array_values(array_filter(array_map(
                    fn (array $fuel): ?Closure => ($type = BeritaAcaraType::tryFrom(strtolower($fuel['code']))) !== null && $type->isFuel()
                        ? $call(PengusahaanBeritaAcaraController::class, 'pdf', [], [$type->value])
                        : null,
                    $fuels,
                ))),
                $call(PengusahaanBaFisikPelumasController::class, 'pdf'),
            ]],
            ['rekap-bbm-tahunan', 'REKAP BBM DARI JANUARI SAMPAI '.strtoupper(Indonesian::monthName($month)), [fn (): array => $this->rekapTahunan($unit, $month, $year, $fuels)]],
            ['rekap-pelumas', 'REKAP PELUMAS', [$call(PengusahaanRincianPelumasController::class, 'pdf', [], ['rekap-pelumas'])]],
            ['pelumas', 'PELUMAS', [$call(PengusahaanPemakaianPelumasController::class, 'pdf')]],
            ...array_map(fn (array $fuel): array => [
                'rincian-bbm-'.Str::slug($fuel['code']),
                'RINCIAN BBM '.strtoupper($fuel['code']).' '.strtoupper($unit->name),
                [$call(PengusahaanRincianBbmController::class, 'pdf', ['fuel' => $fuel['code']], ['perincian-bbm'])],
            ], $fuels),
            ['rincian-pelumas', 'RINCIAN PELUMAS', [$call(PengusahaanRincianPelumasController::class, 'pdf', [], ['perincian-pelumas'])]],
            ['neraca-daya', 'NERACA DAYA & INVENTARISASI', [$call(PengusahaanKinerjaController::class, 'pdf'), $call(PengusahaanInventarisController::class, 'pdf')]],
            ['beban-tinggi', 'BEBAN TINGGI', []],
            ['beban-harian', 'BEBAN HARIAN TERTINGGI', [$call(PengusahaanMesinHarianController::class, 'pdf', [], [MesinHarianJenis::BebanTinggi])]],
            ['indikator', 'INDIKATOR', []],
            ['data-kinerja', 'DATA KINERJA', [$call(PengusahaanKinerjaTermalController::class, 'pdf')]],
            ['jam-operasi', 'JAM OPERASI', [$call(PengusahaanJamMesinController::class, 'pdf', [], [JamMesinJenis::Operasi])]],
            ['jam-har', 'JAM HAR', [$call(PengusahaanJamMesinController::class, 'pdf', [], [JamMesinJenis::Pemeliharaan])]],
            ['jam-gangguan', 'JAM GANGGUAN', [$call(PengusahaanJamMesinController::class, 'pdf', [], [JamMesinJenis::Gangguan])]],
            ['kali-gangguan', 'KALI GANGGUAN', [$call(PengusahaanMesinHarianController::class, 'pdf', [], [MesinHarianJenis::KaliGangguan])]],
            ['fjk-mesin', 'FJK MESIN', []],
            ['kwh-kit', 'KWH KIT', [$call(PengusahaanKwhController::class, 'energiPdf', [], ['dibangkit'])]],
            ['kwh-ps', 'KWH PS', [$call(PengusahaanKwhController::class, 'energiPdf', [], ['pemakaian-sendiri'])]],
            ['stand-kwh', 'STAND KWH METER', [$call(PengusahaanKwhController::class, 'transferPricingPdf')]],
            ...array_map(fn (array $fuel): array => [
                'bbm-'.Str::slug($fuel['code']),
                'BBM ('.strtoupper($fuel['code']).')',
                [$call(PengusahaanPemakaianBbmController::class, 'pdf', ['fuel' => $fuel['code']])],
            ], $fuels),
            ['rekap-bbm', 'REKAP BBM '.strtoupper($unit->name), [$call(PengusahaanRincianBbmController::class, 'pdf', [], ['rekap-bbm'])]],
            ['tara-kalor', 'TARA KALOR', [$call(PengusahaanMesinHarianController::class, 'pdf', [], [MesinHarianJenis::TaraKalor])]],
            ['sfc', 'SFC', [$call(PengusahaanKwhController::class, 'sfcPdf', [], ['sfc'])]],
            ['gangguan-feeder', 'GANGGUAN FEEDER', []],
            ['tug-9', 'TUG 9 BBM & PELUMAS', [$call(PengusahaanTugBbmController::class, 'pdf', ['all' => 1]), $call(PengusahaanTugPelumasController::class, 'pdf', ['all' => 1])]],
        ];

        $chapters = [];
        foreach ($specs as $index => [$key, $title, $sources]) {
            $parts = [];
            foreach ($sources as $part => $source) {
                $page = $this->page($source);
                if ($page !== null) {
                    $parts[] = $this->part("{$key}-{$part}", $page);
                }
            }

            if ($parts === []) {
                $parts[] = $this->part("{$key}-0", [
                    'html' => View::make('operasi.laporan.pengusahaan-placeholder', ['unit' => $unit, 'title' => $title, 'period_label' => Indonesian::monthName($month).' '.$year])->render(),
                    'orientation' => 'portrait',
                ]);
            }

            $chapters[] = ['no' => $index + 1, 'key' => $key, 'title' => $title, 'parts' => $parts];
        }

        return $chapters;
    }

    /**
     * The HTML and orientation a source prints: a menu's pdf action (recorded)
     * or a page built here. Null when it prints nothing or the user may not
     * see it.
     *
     * @param  Closure(): mixed  $source
     * @return array{html: string, orientation: string}|null
     */
    private function page(Closure $source): ?array
    {
        try {
            $direct = null;
            $recorded = PdfViewRecorder::record(function () use ($source, &$direct): void {
                $result = $source();
                if (is_array($result)) {
                    $direct = $result;
                }
            });
        } catch (HttpExceptionInterface) {
            return null;
        }

        if ($direct !== null) {
            return $direct;
        }

        return $recorded === null ? null : [
            'html' => View::make($recorded['view'], $recorded['data'])->render(),
            'orientation' => $recorded['orientation'],
        ];
    }

    /**
     * @param  array{html: string, orientation: string}  $page
     * @return array{id: string, orientation: string, scope: string, body: string, css: string}
     */
    private function part(string $id, array $page): array
    {
        $scope = 'op-p-'.$id;
        $fragment = $this->fragments->extract($page['html'], $scope);

        return ['id' => "bab-{$id}", 'orientation' => $page['orientation'], 'scope' => $scope, 'body' => $fragment['body'], 'css' => $fragment['css']];
    }

    /**
     * Rekap BBM dari Januari sampai bulan ini: per bulan and jenis BBM the
     * persediaan awal, penerimaan, pemakaian, pengiriman and sisa (the same
     * figures as Perincian Bahan Bakar).
     *
     * @param  list<array{code: string, name: string}>  $fuels
     * @return array{html: string, orientation: string}
     */
    private function rekapTahunan(Unit $unit, int $month, int $year, array $fuels): array
    {
        $months = [];
        for ($m = 1; $m <= $month; $m++) {
            $months[$m] = $this->rincianBbm->totals($this->rincianBbm->build($unit, $m, $year));
        }

        return [
            'html' => View::make('operasi.laporan.rekap-bbm-tahunan', [
                'unit' => $unit,
                'year' => $year,
                'period_label' => 'JANUARI - '.strtoupper(Indonesian::monthName($month)).' '.$year,
                'fuels' => $fuels,
                'months' => $months,
            ])->render(),
            'orientation' => 'landscape',
        ];
    }

    /**
     * A GET request for a menu's pdf action, as the user printing the report.
     *
     * @param  array<string, mixed>  $query
     */
    private function request(Unit $unit, int $month, int $year, User $user, array $query): Request
    {
        $request = Request::create('/', 'GET', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year, ...$query]);
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
