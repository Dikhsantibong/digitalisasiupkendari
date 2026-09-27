<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\K3PengusahaanTimeFrame;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PengusahaanTimeFrameController extends Controller
{
    /** @var list<array{no_urut: int, uraian_pelaporan: string, pic: string}> */
    public const DEFAULT_ITEMS = [
        ['no_urut' => 1, 'uraian_pelaporan' => 'Inspeksi P3K', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 2, 'uraian_pelaporan' => 'Inspeksi Potensi Bahaya Kebakaran', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 3, 'uraian_pelaporan' => 'Laporan Identifikasi Potensi Bahaya Kebakaran', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 4, 'uraian_pelaporan' => 'Inspeksi Tempat Kerja dan Fasilitas', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 5, 'uraian_pelaporan' => 'Inspeksi APAR & APAT', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 6, 'uraian_pelaporan' => 'Pengetesan Hydrant', 'pic' => 'K3 & Keamanan, Operator'],
        ['no_urut' => 7, 'uraian_pelaporan' => 'Inspeksi Rambu - Rambu K3', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 8, 'uraian_pelaporan' => 'Penyemprotan Disinfectan', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 9, 'uraian_pelaporan' => 'Inspeksi APD', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 10, 'uraian_pelaporan' => 'Membuat Laporan P2K3', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 11, 'uraian_pelaporan' => 'Rapat Evaluasi SLA Security', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 12, 'uraian_pelaporan' => 'Inspeksi Hydrant', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 13, 'uraian_pelaporan' => 'Inspeksi Fire Alarm', 'pic' => 'K3 & Keamanan'],
        ['no_urut' => 14, 'uraian_pelaporan' => 'Membuat Laporan Bulanan Kinerja K3 dan Lingkungan', 'pic' => 'K3 & Keamanan'],
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        if ($request->filled('unit_id')) {
            $unit = Unit::query()->findOrFail($request->integer('unit_id'));
            abort_unless($user->canAccessUnit($unit), 403);
        } else {
            $unit = $units->first();
        }

        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $days = $this->buildDays($year, $month);

        $stored = K3PengusahaanTimeFrame::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($stored->isEmpty()) {
            $rows = collect(self::DEFAULT_ITEMS)->map(function (array $item, int $index): array {
                return [
                    'id' => null,
                    'no_urut' => $item['no_urut'],
                    'uraian_pelaporan' => $item['uraian_pelaporan'],
                    'pic' => $item['pic'],
                    'rencana' => [],
                    'realisasi' => [],
                    'keterangan' => '',
                    'sort_order' => $index + 1,
                ];
            })->all();
        } else {
            $rows = $stored->map(function (K3PengusahaanTimeFrame $r, int $index): array {
                return [
                    'id' => $r->id,
                    'no_urut' => $r->no_urut ?? ($index + 1),
                    'uraian_pelaporan' => $r->uraian_pelaporan,
                    'pic' => $r->pic ?? '',
                    'rencana' => is_array($r->rencana) ? array_values(array_map('intval', $r->rencana)) : [],
                    'realisasi' => is_array($r->realisasi) ? array_values(array_map('intval', $r->realisasi)) : [],
                    'keterangan' => $r->keterangan ?? '',
                    'sort_order' => $r->sort_order,
                ];
            })->all();
        }

        return Inertia::render('k3/pengusahaan/time-frame/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'days' => $days,
            'rows' => $rows,
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::K3PengusahaanWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3PengusahaanWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.no_urut' => ['nullable', 'integer'],
            'rows.*.uraian_pelaporan' => ['required', 'string', 'max:255'],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.rencana' => ['nullable', 'array'],
            'rows.*.rencana.*' => ['integer', 'between:1,31'],
            'rows.*.realisasi' => ['nullable', 'array'],
            'rows.*.realisasi.*' => ['integer', 'between:1,31'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:1000'],
            'rows.*.sort_order' => ['nullable', 'integer'],
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        DB::transaction(function () use ($validated, $unit, $month, $year, $user): void {
            K3PengusahaanTimeFrame::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] ?? [] as $index => $row) {
                $rencana = collect($row['rencana'] ?? [])
                    ->map(fn ($d): int => (int) $d)
                    ->filter(fn (int $d): bool => $d >= 1 && $d <= 31)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                $realisasi = collect($row['realisasi'] ?? [])
                    ->map(fn ($d): int => (int) $d)
                    ->filter(fn (int $d): bool => $d >= 1 && $d <= 31)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                K3PengusahaanTimeFrame::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $row['no_urut'] ?? ($index + 1),
                    'uraian_pelaporan' => trim((string) $row['uraian_pelaporan']),
                    'pic' => isset($row['pic']) ? trim((string) $row['pic']) : null,
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'keterangan' => isset($row['keterangan']) ? trim((string) $row['keterangan']) : null,
                    'sort_order' => $row['sort_order'] ?? ($index + 1),
                    'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Time Frame Pengusahaan K3 & Keamanan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Time Frame Pengusahaan berhasil disimpan.']);

        return back();
    }

    /**
     * @return list<array{day: int, dow: string, is_red: bool, holiday: string|null}>
     */
    private function buildDays(int $year, int $month): array
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get(['date', 'description']);

        return collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $holiday = $holidays->first(fn ($h): bool => Carbon::parse($h->date)->day === $day);

            return [
                'day' => $day,
                'dow' => $date->locale('id')->isoFormat('dd'),
                'is_red' => $date->isSunday() || $holiday !== null,
                'holiday' => $holiday?->description,
            ];
        })->all();
    }
}
