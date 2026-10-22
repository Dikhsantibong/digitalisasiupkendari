<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\KondisiAbnormal;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifications\EventNotifier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan Kondisi Abnormal & Gangguan Pembangkit (Operasi input): per finding
 * the indikasi (uraian kondisi), tanggal, abnormal / gangguan with their
 * durasi, the related parameter, the short-term follow-up (SR, FLM, lainnya),
 * the long-term follow-up and the impact on the system and on the plant. The
 * same rows feed the PDF rekap and the Laporan Operasi Pembangkit.
 */
class KondisiAbnormalController extends Controller
{
    use AuthorizesFieldInput;

    /** Short-term follow-up columns => label. */
    public const TINDAK_PENDEK = [
        'tl_pendek_sr' => 'SR',
        'tl_pendek_flm' => 'FLM',
        'tl_pendek_lainnya' => 'Lainnya',
    ];

    /** Free-text follow-up & impact columns. */
    private const TEXT_FIELDS = ['parameter_terkait', 'tl_pendek_keterangan', 'tl_panjang', 'dampak_sistem', 'dampak_pembangkit'];

    private const MIN_ROWS = 30;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit, $month, $year] = $this->target($request);
        $sheet = $this->sheet($unit, $month, $year);

        return Inertia::render('operasi/input/kondisi-abnormal/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $sheet['rows'],
            'summary' => $sheet['summary'],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'tindak_pendek' => self::TINDAK_PENDEK,
            ],
            'can_write' => $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganKondisiAbnormal),
        ]);
    }

    public function store(Request $request, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganKondisiAbnormal), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['required', 'array'],
            'rows.*.no_urut' => ['required', 'integer', 'min:1'],
            'rows.*.uraian_kondisi' => ['nullable', 'string'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.is_abnormal' => ['nullable', 'integer', 'in:0,1'],
            'rows.*.durasi_abnormal' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_gangguan' => ['nullable', 'integer', 'in:0,1'],
            'rows.*.durasi_gangguan' => ['nullable', 'numeric', 'min:0'],
            'rows.*.parameter_terkait' => ['nullable', 'string', 'max:2000'],
            'rows.*.tl_pendek_sr' => ['nullable', 'boolean'],
            'rows.*.tl_pendek_flm' => ['nullable', 'boolean'],
            'rows.*.tl_pendek_lainnya' => ['nullable', 'boolean'],
            'rows.*.tl_pendek_keterangan' => ['nullable', 'string', 'max:2000'],
            'rows.*.tl_panjang' => ['nullable', 'string', 'max:2000'],
            'rows.*.dampak_sistem' => ['nullable', 'string', 'max:2000'],
            'rows.*.dampak_pembangkit' => ['nullable', 'string', 'max:2000'],
        ]);

        $flagged = [];

        foreach ($validated['rows'] as $row) {
            $text = fn (string $key): ?string => trim((string) ($row[$key] ?? '')) ?: null;
            $tindak = collect(self::TINDAK_PENDEK)->keys()->mapWithKeys(fn (string $key): array => [$key => (bool) ($row[$key] ?? false)])->all();
            $texts = collect(self::TEXT_FIELDS)->mapWithKeys(fn (string $key): array => [$key => $text($key)])->all();

            $hasData = $text('uraian_kondisi') !== null ||
                ! empty($row['tanggal']) ||
                ! empty($row['is_abnormal']) ||
                ! empty($row['durasi_abnormal']) ||
                ! empty($row['is_gangguan']) ||
                ! empty($row['durasi_gangguan']) ||
                in_array(true, $tindak, true) ||
                array_filter($texts) !== [];

            if (! $hasData) {
                KondisiAbnormal::query()
                    ->where('unit_id', $unit->id)
                    ->where('year', (int) $validated['year'])
                    ->where('month', (int) $validated['month'])
                    ->where('no_urut', (int) $row['no_urut'])
                    ->delete();

                continue;
            }

            $record = KondisiAbnormal::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'no_urut' => (int) $row['no_urut'],
                ],
                [
                    'uraian_kondisi' => $row['uraian_kondisi'] ?? null,
                    'tanggal' => ! empty($row['tanggal']) ? Carbon::parse($row['tanggal'])->toDateString() : null,
                    'is_abnormal' => (int) ($row['is_abnormal'] ?? 0),
                    'durasi_abnormal' => (float) ($row['durasi_abnormal'] ?? 0),
                    'is_gangguan' => (int) ($row['is_gangguan'] ?? 0),
                    'durasi_gangguan' => (float) ($row['durasi_gangguan'] ?? 0),
                    ...$tindak,
                    ...$texts,
                    'input_by' => $user->id,
                ]
            );

            if ($record->wasRecentlyCreated || $record->wasChanged(['is_abnormal', 'is_gangguan'])) {
                $flagged[] = $record;
            }
        }

        $events->kondisiAbnormal($unit, $flagged, $user);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan kondisi abnormal dan gangguan pembangkit {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan kondisi abnormal & gangguan berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        abort_unless($this->canView($request->user()), 403);

        [, $unit, $month, $year] = $this->target($request);

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Kondisi_Abnormal_{$safeUnitName}_{$month}_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $sheet = $this->sheet($unit, $month, $year);

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        return ['operasi.input.kondisi-abnormal-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthNames[$month] ?? '',
            'rows' => array_map(fn (array $row): array => [
                ...$row,
                'tanggal' => $row['tanggal'] !== '' ? Carbon::parse($row['tanggal'])->format('d/m/Y') : '',
                'is_abnormal' => $row['is_abnormal'] === 1 ? '1' : '',
                'durasi_abnormal' => $row['durasi_abnormal'] > 0 ? $row['durasi_abnormal'] : '',
                'is_gangguan' => $row['is_gangguan'] === 1 ? '1' : '',
                'durasi_gangguan' => $row['durasi_gangguan'] > 0 ? $row['durasi_gangguan'] : '',
            ], $sheet['rows']),
            'tindakPendek' => self::TINDAK_PENDEK,
            'totalAbnormalCount' => $sheet['summary']['total_abnormal_count'],
            'totalAbnormalDurasi' => $sheet['summary']['total_abnormal_durasi'],
            'totalGangguanCount' => $sheet['summary']['total_gangguan_count'],
            'totalGangguanDurasi' => $sheet['summary']['total_gangguan_durasi'],
            'totalTindakPendek' => $sheet['summary']['tindak_pendek'],
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }

    /**
     * The numbered rows (at least {@see self::MIN_ROWS}) and the totals.
     *
     * @return array{rows: list<array<string, mixed>>, summary: array{total_abnormal_count: int, total_abnormal_durasi: float, total_gangguan_count: int, total_gangguan_durasi: float, tindak_pendek: array<string, int>}}
     */
    private function sheet(Unit $unit, int $month, int $year): array
    {
        $records = KondisiAbnormal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('no_urut')
            ->get()
            ->keyBy('no_urut');

        $summary = [
            'total_abnormal_count' => 0,
            'total_abnormal_durasi' => 0.0,
            'total_gangguan_count' => 0,
            'total_gangguan_durasi' => 0.0,
            'tindak_pendek' => array_fill_keys(array_keys(self::TINDAK_PENDEK), 0),
        ];
        $rows = [];

        for ($i = 1, $count = max(self::MIN_ROWS, $records->keys()->max() ?? self::MIN_ROWS); $i <= $count; $i++) {
            $rec = $records->get($i);
            $row = [
                'id' => $rec?->id,
                'no_urut' => $i,
                'uraian_kondisi' => $rec?->uraian_kondisi ?? '',
                'tanggal' => $rec?->tanggal?->format('Y-m-d') ?? '',
                'is_abnormal' => $rec ? (int) $rec->is_abnormal : 0,
                'durasi_abnormal' => $rec ? (float) $rec->durasi_abnormal : 0,
                'is_gangguan' => $rec ? (int) $rec->is_gangguan : 0,
                'durasi_gangguan' => $rec ? (float) $rec->durasi_gangguan : 0,
            ];
            foreach (array_keys(self::TINDAK_PENDEK) as $key) {
                $row[$key] = $rec !== null && $rec->{$key} ? 1 : 0;
                $summary['tindak_pendek'][$key] += $row[$key];
            }
            foreach (self::TEXT_FIELDS as $key) {
                $row[$key] = (string) ($rec?->{$key} ?? '');
            }

            $summary['total_abnormal_count'] += $row['is_abnormal'] === 1 ? 1 : 0;
            $summary['total_abnormal_durasi'] += $row['durasi_abnormal'];
            $summary['total_gangguan_count'] += $row['is_gangguan'] === 1 ? 1 : 0;
            $summary['total_gangguan_durasi'] += $row['durasi_gangguan'];
            $rows[] = $row;
        }

        return ['rows' => $rows, 'summary' => $summary];
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganKondisiAbnormal)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        return [$units, $unit, $month, $year];
    }
}
