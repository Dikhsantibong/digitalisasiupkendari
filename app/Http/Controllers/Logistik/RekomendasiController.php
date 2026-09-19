<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesLogistikInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\LogistikRekomendasi;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Logistik & Gudang "Rekomendasi Logistik & Gudang": numbered
 * recommendations per unit & month (uraian, kondisi existing, tindak lanjut,
 * keterangan). Before anything is saved the form starts from the standard
 * uraian topics; it always shows at least {@see self::MIN_ROWS} numbered rows,
 * like the paper form. PDF A4 portrait + Excel (from the page).
 */
class RekomendasiController extends Controller
{
    use HandlesLogistikInput;
    use RendersReportPdf;

    public const MIN_ROWS = 11;

    /** @var list<string> */
    public const DEFAULT_URAIAN = [
        'Ketersediaan stok material',
        'Beberapa spare part kritis masih terbatas',
        'Material Consumable',
        'Inventaris Tools',
        'Ketersediaan Laptop',
    ];

    private const FIELDS = ['uraian', 'kondisi_existing', 'tindak_lanjut', 'keterangan'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$units, $unit, $month, $year] = $this->logistikReadTarget($request);

        return Inertia::render('logistik/input/rekomendasi/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->logistikFilterOptions($units),
            'rows' => $this->rows($unit, $month, $year),
            'has_saved' => $this->saved($unit, $month, $year)->isNotEmpty(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::LogistikInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$unit, $month, $year] = $this->logistikWriteTarget($request);

        $validated = $request->validate([
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.uraian' => ['nullable', 'string', 'max:255'],
            'rows.*.kondisi_existing' => ['nullable', 'string', 'max:2000'],
            'rows.*.tindak_lanjut' => ['nullable', 'string', 'max:2000'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $unit, $month, $year, $request): void {
            LogistikRekomendasi::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)->delete();

            $number = 0;
            foreach ($validated['rows'] as $row) {
                $values = collect(self::FIELDS)->mapWithKeys(fn (string $field): array => [$field => trim((string) ($row[$field] ?? '')) ?: null]);
                if ($values->filter()->isEmpty()) {
                    continue;
                }

                LogistikRekomendasi::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => ++$number,
                    ...$values->all(),
                    'input_by' => $request->user()->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Rekomendasi Logistik & Gudang {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rekomendasi Logistik & Gudang berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        [, $unit, $month, $year] = $this->logistikReadTarget($request);
        [$view, $data] = $this->pdfView($unit, $month, $year);

        $filename = sprintf('Rekomendasi_Logistik_Gudang_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year);

        return $this->streamReportPdf($request, $view, $data, $filename, 'portrait');
    }

    /**
     * The PDF view and its data (A4 portrait) — reusable by the Laporan Logistik.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        return ['logistik.input.rekomendasi-pdf', [
            'unit' => $unit,
            'periodLabel' => Indonesian::monthName($month).' Tahun '.$year,
            'rows' => $this->rows($unit, $month, $year),
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Saved rows — or the standard uraian topics before anything is saved —
     * followed by blank numbered rows up to {@see self::MIN_ROWS}.
     *
     * @return list<array{id: int|null, no_urut: int, uraian: string, kondisi_existing: string, tindak_lanjut: string, keterangan: string}>
     */
    private function rows(Unit $unit, int $month, int $year): array
    {
        $saved = $this->saved($unit, $month, $year);

        $rows = $saved->isEmpty()
            ? array_map(fn (string $uraian): array => ['id' => null, 'uraian' => $uraian, 'kondisi_existing' => '', 'tindak_lanjut' => '', 'keterangan' => ''], self::DEFAULT_URAIAN)
            : $saved->map(fn (LogistikRekomendasi $r): array => [
                'id' => $r->id,
                'uraian' => (string) ($r->uraian ?? ''),
                'kondisi_existing' => (string) ($r->kondisi_existing ?? ''),
                'tindak_lanjut' => (string) ($r->tindak_lanjut ?? ''),
                'keterangan' => (string) ($r->keterangan ?? ''),
            ])->all();

        for ($n = count($rows); $n < self::MIN_ROWS; $n++) {
            $rows[] = ['id' => null, 'uraian' => '', 'kondisi_existing' => '', 'tindak_lanjut' => '', 'keterangan' => ''];
        }

        return array_map(fn (array $row, int $index): array => ['id' => $row['id'], 'no_urut' => $index + 1] + $row, $rows, array_keys($rows));
    }

    /**
     * @return Collection<int, LogistikRekomendasi>
     */
    private function saved(Unit $unit, int $month, int $year): Collection
    {
        return LogistikRekomendasi::query()->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('no_urut')->get();
    }
}
