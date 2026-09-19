<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3AirLimbah;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Logbook Pemantauan Pemanfaatan Air Limbah (modul K3).
 */
class AirLimbahController extends Controller
{
    /**
     * Default standard areas from official reference.
     *
     * @var list<string>
     */
    public const DEFAULT_AREAS = [
        'Taman Area Kantor',
        'Taman Depan TPS',
        'Taman Depan Pos Security',
        'Taman Depan Musholla',
        'Taman Samping Ruang PI',
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $savedRecords = K3AirLimbah::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($savedRecords->isEmpty()) {
            $rows = collect(self::DEFAULT_AREAS)->map(fn (string $area, int $idx): array => [
                'id' => null,
                'no_urut' => $idx + 1,
                'area_penyiraman' => $area,
                'tanggal' => null,
                'waktu_penyiraman' => '16.00 WITA',
                'metode_pemanfaatan' => 'Penyiraman Tanaman',
                'debit_awal' => 0,
                'debit_akhir' => 1,
                'debit_jumlah' => 1,
                'frekuensi' => '1',
                'pic' => 'K3L',
                'keterangan' => '',
                'sort_order' => $idx,
            ])->all();
        } else {
            $rows = $savedRecords->map(fn (K3AirLimbah $r, int $idx): array => [
                'id' => $r->id,
                'no_urut' => $r->no_urut ?: ($idx + 1),
                'area_penyiraman' => $r->area_penyiraman,
                'tanggal' => $r->tanggal?->format('Y-m-d'),
                'waktu_penyiraman' => $r->waktu_penyiraman ?? '16.00 WITA',
                'metode_pemanfaatan' => $r->metode_pemanfaatan ?? 'Penyiraman Tanaman',
                'debit_awal' => (float) $r->debit_awal,
                'debit_akhir' => (float) $r->debit_akhir,
                'debit_jumlah' => (float) $r->debit_jumlah,
                'frekuensi' => $r->frekuensi ?? '1',
                'pic' => $r->pic ?? 'K3L',
                'keterangan' => $r->keterangan ?? '',
                'sort_order' => $r->sort_order,
            ])->all();
        }

        return Inertia::render('k3/input/air-limbah', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $rows,
            'defaultAreas' => self::DEFAULT_AREAS,
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.area_penyiraman' => ['required', 'string', 'max:255'],
            'rows.*.tanggal' => ['nullable', 'date'],
            'rows.*.waktu_penyiraman' => ['nullable', 'string', 'max:100'],
            'rows.*.metode_pemanfaatan' => ['nullable', 'string', 'max:255'],
            'rows.*.debit_awal' => ['nullable', 'numeric'],
            'rows.*.debit_akhir' => ['nullable', 'numeric'],
            'rows.*.debit_jumlah' => ['nullable', 'numeric'],
            'rows.*.frekuensi' => ['nullable', 'string', 'max:100'],
            'rows.*.pic' => ['nullable', 'string', 'max:100'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            $existingIds = [];
            foreach ($validated['rows'] ?? [] as $index => $row) {
                $debitAwal = isset($row['debit_awal']) && is_numeric($row['debit_awal']) ? (float) $row['debit_awal'] : 0.0;
                $debitAkhir = isset($row['debit_akhir']) && is_numeric($row['debit_akhir']) ? (float) $row['debit_akhir'] : 0.0;
                $debitJumlah = isset($row['debit_jumlah']) && is_numeric($row['debit_jumlah'])
                    ? (float) $row['debit_jumlah']
                    : round(max(0, $debitAkhir - $debitAwal), 2);

                $attributes = [
                    'no_urut' => $index + 1,
                    'area_penyiraman' => $row['area_penyiraman'],
                    'tanggal' => ! empty($row['tanggal']) ? Carbon::parse($row['tanggal'])->format('Y-m-d') : null,
                    'waktu_penyiraman' => $row['waktu_penyiraman'] ?? '16.00 WITA',
                    'metode_pemanfaatan' => $row['metode_pemanfaatan'] ?? 'Penyiraman Tanaman',
                    'debit_awal' => $debitAwal,
                    'debit_akhir' => $debitAkhir,
                    'debit_jumlah' => $debitJumlah,
                    'frekuensi' => $row['frekuensi'] ?? '1',
                    'pic' => $row['pic'] ?? 'K3L',
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $index,
                    'input_by' => $user->id,
                ];

                if (! empty($row['id'])) {
                    $record = K3AirLimbah::query()->where('id', $row['id'])->where('unit_id', $unit->id)->first();
                    if ($record) {
                        $record->update($attributes);
                        $existingIds[] = $record->id;

                        continue;
                    }
                }

                $newRecord = K3AirLimbah::create([
                    ...$attributes,
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                ]);
                $existingIds[] = $newRecord->id;
            }

            K3AirLimbah::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
                ->delete();
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Logbook Pemantauan Air Limbah {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Logbook pemantauan air limbah berhasil disimpan.',
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );

        $unit = Unit::query()->with('serviceUnit')->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $records = K3AirLimbah::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];
        $monthName = $monthNames[$month] ?? '';

        $rows = $records->map(fn (K3AirLimbah $r, int $idx): array => [
            'no_urut' => $r->no_urut ?: ($idx + 1),
            'area_penyiraman' => $r->area_penyiraman,
            'tanggal_formatted' => $r->tanggal ? Carbon::parse($r->tanggal)->locale('id')->isoFormat('D MMMM Y') : '-',
            'waktu_penyiraman' => $r->waktu_penyiraman ?: '-',
            'metode_pemanfaatan' => $r->metode_pemanfaatan ?: '-',
            'debit_awal' => $r->debit_awal,
            'debit_akhir' => $r->debit_akhir,
            'debit_jumlah' => $r->debit_jumlah,
            'frekuensi' => $r->frekuensi ?: '-',
            'pic' => $r->pic ?: '-',
        ])->all();

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        $pdf = Pdf::loadView('k3.input.air-limbah-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'rows' => $rows,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Logbook_Pemantauan_Air_Limbah_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
