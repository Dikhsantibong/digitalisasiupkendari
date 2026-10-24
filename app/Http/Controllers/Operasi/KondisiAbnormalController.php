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
 * Laporan Kondisi Abnormal & Gangguan Pembangkit (Operasi input). One record
 * per kejadian: the operator adds it with its tanggal (several a day are
 * fine), jenis (abnormal / gangguan) & durasi, the indikasi, the related
 * parameter, the short-term follow-up (SR, FLM, lainnya), the long-term
 * follow-up and the impact on the system and on the plant. The page shows
 * the month's rekap; the same records feed the PDF and the Laporan Operasi.
 *
 * Writers: operasi.input.write (Koordinator — every record of the unit) and
 * the operators' field permission (their own records).
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

    /** Jenis kejadian => label. */
    public const JENIS = ['abnormal' => 'Abnormal', 'gangguan' => 'Gangguan'];

    /** Free-text follow-up & impact columns. */
    private const TEXT_FIELDS = ['parameter_terkait', 'tl_pendek_keterangan', 'tl_panjang', 'dampak_sistem', 'dampak_pembangkit'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit, $month, $year] = $this->target($request);
        $records = $this->records($unit, $month, $year);

        return Inertia::render('operasi/input/kondisi-abnormal/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'entries' => $records->values()->map(fn (KondisiAbnormal $record, int $index): array => [
                ...$this->present($record, $index + 1),
                'input_by' => $record->inputUser?->name,
                'can_edit' => $this->canEdit($user, $record),
            ])->all(),
            'summary' => $this->summary($records),
            'rekap_harian' => $this->daily($records),
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'tindak_pendek' => self::TINDAK_PENDEK,
                'jenis' => self::JENIS,
            ],
            'can_write' => $this->canWrite($user),
            'today' => Carbon::now('Asia/Makassar')->toDateString(),
        ]);
    }

    public function store(Request $request, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $values = $this->values($request);
        $date = Carbon::parse($values['tanggal']);

        $record = KondisiAbnormal::query()->create([
            'unit_id' => $unit->id,
            'year' => $date->year,
            'month' => $date->month,
            'no_urut' => $this->nextNumber($unit->id, $date->year, $date->month),
            ...$values,
            'input_by' => $user->id,
        ]);

        $events->kondisiAbnormal($unit, [$record], $user);
        $this->activityLogger->log(ActivityEvent::Created, "Melaporkan kondisi {$this->jenisOf($record)} {$unit->name} tanggal {$date->format('d/m/Y')}", $record, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan '.$this->jenisOf($record).' tersimpan.']);

        return redirect()->route('operasi.input.kondisi-abnormal.index', ['unit_id' => $unit->id, 'month' => $date->month, 'year' => $date->year]);
    }

    public function update(Request $request, KondisiAbnormal $kondisiAbnormal, EventNotifier $events): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $kondisiAbnormal), 403);

        $values = $this->values($request);
        $date = Carbon::parse($values['tanggal']);
        $moved = $date->year !== $kondisiAbnormal->year || $date->month !== $kondisiAbnormal->month;

        $kondisiAbnormal->fill([...$values, 'year' => $date->year, 'month' => $date->month]);
        if ($moved) {
            $kondisiAbnormal->no_urut = $this->nextNumber($kondisiAbnormal->unit_id, $date->year, $date->month);
        }
        $kondisiAbnormal->save();

        if ($kondisiAbnormal->wasChanged(['is_abnormal', 'is_gangguan'])) {
            $events->kondisiAbnormal($kondisiAbnormal->unit, [$kondisiAbnormal], $user);
        }
        $this->activityLogger->log(ActivityEvent::Updated, "Mengubah laporan kondisi {$this->jenisOf($kondisiAbnormal)} #{$kondisiAbnormal->id}", $kondisiAbnormal, unit: $kondisiAbnormal->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan diperbarui.']);

        return redirect()->route('operasi.input.kondisi-abnormal.index', ['unit_id' => $kondisiAbnormal->unit_id, 'month' => $date->month, 'year' => $date->year]);
    }

    public function destroy(Request $request, KondisiAbnormal $kondisiAbnormal): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canEdit($user, $kondisiAbnormal), 403);

        $period = ['unit_id' => $kondisiAbnormal->unit_id, 'month' => $kondisiAbnormal->month, 'year' => $kondisiAbnormal->year];
        $kondisiAbnormal->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus laporan kondisi abnormal/gangguan #{$kondisiAbnormal->id}", unit: $period['unit_id']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan dihapus.']);

        return redirect()->route('operasi.input.kondisi-abnormal.index', $period);
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
     * the Laporan Operasi Pembangkit document: the month's records in date order.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $records = $this->records($unit, $month, $year);
        $summary = $this->summary($records);

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
            'rows' => $records->values()->map(function (KondisiAbnormal $record, int $index): array {
                $row = $this->present($record, $index + 1);

                return [
                    ...$row,
                    'no_urut' => $row['no'],
                    'tanggal' => $row['tanggal'] !== '' ? Carbon::parse($row['tanggal'])->format('d/m/Y') : '',
                    'is_abnormal' => $row['is_abnormal'] === 1 ? '1' : '',
                    'durasi_abnormal' => $row['durasi_abnormal'] > 0 ? $row['durasi_abnormal'] : '',
                    'is_gangguan' => $row['is_gangguan'] === 1 ? '1' : '',
                    'durasi_gangguan' => $row['durasi_gangguan'] > 0 ? $row['durasi_gangguan'] : '',
                ];
            })->all(),
            'tindakPendek' => self::TINDAK_PENDEK,
            'totalAbnormalCount' => $summary['total_abnormal_count'],
            'totalAbnormalDurasi' => $summary['total_abnormal_durasi'],
            'totalGangguanCount' => $summary['total_gangguan_count'],
            'totalGangguanDurasi' => $summary['total_gangguan_durasi'],
            'totalTindakPendek' => $summary['tindak_pendek'],
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ]];
    }

    /**
     * The validated values of one record: jenis + durasi go to the matching
     * abnormal / gangguan columns.
     *
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'jenis' => ['required', 'in:abnormal,gangguan'],
            'durasi' => ['nullable', 'numeric', 'min:0', 'max:744'],
            'uraian_kondisi' => ['required', 'string', 'max:2000'],
            'parameter_terkait' => ['nullable', 'string', 'max:2000'],
            'tl_pendek_sr' => ['nullable', 'boolean'],
            'tl_pendek_flm' => ['nullable', 'boolean'],
            'tl_pendek_lainnya' => ['nullable', 'boolean'],
            'tl_pendek_keterangan' => ['nullable', 'string', 'max:2000'],
            'tl_panjang' => ['nullable', 'string', 'max:2000'],
            'dampak_sistem' => ['nullable', 'string', 'max:2000'],
            'dampak_pembangkit' => ['nullable', 'string', 'max:2000'],
        ]);

        $abnormal = $validated['jenis'] === 'abnormal';
        $durasi = (float) ($validated['durasi'] ?? 0);

        return [
            'tanggal' => $validated['tanggal'],
            'uraian_kondisi' => trim($validated['uraian_kondisi']),
            'is_abnormal' => $abnormal ? 1 : 0,
            'durasi_abnormal' => $abnormal ? $durasi : 0,
            'is_gangguan' => $abnormal ? 0 : 1,
            'durasi_gangguan' => $abnormal ? 0 : $durasi,
            ...collect(self::TINDAK_PENDEK)->keys()->mapWithKeys(fn (string $key): array => [$key => (bool) ($validated[$key] ?? false)])->all(),
            ...collect(self::TEXT_FIELDS)->mapWithKeys(fn (string $key): array => [$key => trim((string) ($validated[$key] ?? '')) ?: null])->all(),
        ];
    }

    /**
     * @return Collection<int, KondisiAbnormal>
     */
    private function records(Unit $unit, int $month, int $year): Collection
    {
        return KondisiAbnormal::query()
            ->with('inputUser:id,name')
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(KondisiAbnormal $record, int $number): array
    {
        $row = [
            'id' => $record->id,
            'no' => $number,
            'tanggal' => $record->tanggal?->format('Y-m-d') ?? '',
            'jenis' => (int) $record->is_gangguan === 1 && (int) $record->is_abnormal !== 1 ? 'gangguan' : 'abnormal',
            'uraian_kondisi' => (string) ($record->uraian_kondisi ?? ''),
            'is_abnormal' => (int) $record->is_abnormal,
            'durasi_abnormal' => (float) $record->durasi_abnormal,
            'is_gangguan' => (int) $record->is_gangguan,
            'durasi_gangguan' => (float) $record->durasi_gangguan,
        ];
        $row['durasi'] = $row['durasi_abnormal'] + $row['durasi_gangguan'];
        foreach (array_keys(self::TINDAK_PENDEK) as $key) {
            $row[$key] = $record->{$key} ? 1 : 0;
        }
        foreach (self::TEXT_FIELDS as $key) {
            $row[$key] = (string) ($record->{$key} ?? '');
        }

        return $row;
    }

    /**
     * @param  Collection<int, KondisiAbnormal>  $records
     * @return array{total_abnormal_count: int, total_abnormal_durasi: float, total_gangguan_count: int, total_gangguan_durasi: float, tindak_pendek: array<string, int>}
     */
    private function summary(Collection $records): array
    {
        return [
            'total_abnormal_count' => $records->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_abnormal === 1)->count(),
            'total_abnormal_durasi' => (float) $records->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_abnormal),
            'total_gangguan_count' => $records->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_gangguan === 1)->count(),
            'total_gangguan_durasi' => (float) $records->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_gangguan),
            'tindak_pendek' => collect(self::TINDAK_PENDEK)->keys()
                ->mapWithKeys(fn (string $key): array => [$key => $records->filter(fn (KondisiAbnormal $r): bool => (bool) $r->{$key})->count()])->all(),
        ];
    }

    /**
     * The rekap per tanggal: how many abnormal / gangguan and their durasi.
     *
     * @param  Collection<int, KondisiAbnormal>  $records
     * @return list<array{tanggal: string, abnormal: int, durasi_abnormal: float, gangguan: int, durasi_gangguan: float}>
     */
    private function daily(Collection $records): array
    {
        return $records->filter(fn (KondisiAbnormal $r): bool => $r->tanggal !== null)
            ->groupBy(fn (KondisiAbnormal $r): string => $r->tanggal->format('Y-m-d'))
            ->map(fn (Collection $day, string $date): array => [
                'tanggal' => $date,
                'abnormal' => $day->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_abnormal === 1)->count(),
                'durasi_abnormal' => (float) $day->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_abnormal),
                'gangguan' => $day->filter(fn (KondisiAbnormal $r): bool => (int) $r->is_gangguan === 1)->count(),
                'durasi_gangguan' => (float) $day->sum(fn (KondisiAbnormal $r): float => (float) $r->durasi_gangguan),
            ])->values()->all();
    }

    private function nextNumber(int $unitId, int $year, int $month): int
    {
        return (int) KondisiAbnormal::query()->where('unit_id', $unitId)->where('year', $year)->where('month', $month)->max('no_urut') + 1;
    }

    private function jenisOf(KondisiAbnormal $record): string
    {
        return (int) $record->is_gangguan === 1 ? 'gangguan' : 'abnormal';
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganKondisiAbnormal)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganKondisiAbnormal) && ! $user->isReadOnly();
    }

    /** The Koordinator edits every record of the unit; an operator only their own. */
    private function canEdit(User $user, KondisiAbnormal $record): bool
    {
        return $this->canWrite($user)
            && $user->canAccessUnit($record->unit_id)
            && ($user->hasPermissionTo(PermissionName::OperasiInputWrite) || $record->input_by === $user->id);
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
