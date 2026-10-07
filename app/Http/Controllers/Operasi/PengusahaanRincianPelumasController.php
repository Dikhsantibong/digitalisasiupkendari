<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\OperasiRekap;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\PerincianPelumasSheet;
use App\Services\Operasi\RekapPelumasSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Perincian Minyak Pelumas and Rekap Pelumas
 * (Pemakaian Pelumas & Grease): monthly views of Persediaan / Pemakaian
 * Pelumas, with the sheet passed as the route's `jenis` default. Only what no
 * other sheet holds is typed in here.
 */
class PengusahaanRincianPelumasController extends Controller
{
    use AuthorizesFieldInput;

    /** @var array<string, string> jenis => menu title */
    private const TITLES = [
        PerincianPelumasSheet::JENIS => 'Perincian Minyak Pelumas',
        RekapPelumasSheet::JENIS => 'Rekap Pelumas',
    ];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly PerincianPelumasSheet $perincian,
        private readonly RekapPelumasSheet $rekap,
    ) {}

    public function index(Request $request, string $jenis): Response
    {
        $title = self::TITLES[$jenis] ?? abort(404);
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $data = $this->data($jenis, $unit, $month, $year);

        return Inertia::render("pengusahaan/operasi/{$jenis}/index", [
            'title' => $title,
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'lubricants' => $data['lubricants'],
            'machines' => $data['machines'],
            'auto' => [
                ...$data['auto'],
                'pemakaian' => (object) $data['auto']['pemakaian'],
                'ba_fisik' => (object) $data['auto']['ba_fisik'],
            ],
            'manual' => $data['manual'],
            'lines' => $jenis === PerincianPelumasSheet::JENIS ? PerincianPelumasSheet::PENGIRIMAN : RekapPelumasSheet::ALAT_BANTU,
            'catatan' => $data['record']?->catatan ?? '',
            'saved_at' => $data['record']?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        $title = self::TITLES[$jenis] ?? abort(404);
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'asal' => ['nullable', 'array'],
            'asal.*' => ['nullable', 'string', 'max:100'],
            'pengembalian' => ['nullable', 'array'],
            'pengembalian.tanggal' => ['nullable', 'date'],
            'pengembalian.amounts.*' => ['nullable', 'numeric', 'min:0'],
            'non_mesin.*' => ['nullable', 'numeric', 'min:0'],
            'pengiriman' => ['nullable', 'array'],
            'pengiriman.*.tanggal' => ['nullable', 'date'],
            'pengiriman.*.amounts.*' => ['nullable', 'numeric', 'min:0'],
            'alat_bantu' => ['nullable', 'array'],
            'alat_bantu.*.*' => ['nullable', 'numeric', 'min:0'],
            'fisik' => ['nullable', 'array'],
            'fisik.*' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            '*.min' => 'Jumlah tidak boleh negatif.',
            '*.*.min' => 'Jumlah tidak boleh negatif.',
            '*.*.*.min' => 'Jumlah tidak boleh negatif.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $data = $this->data($jenis, $unit, $month, $year);
        $keys = array_column($data['lubricants'], 'key');
        $data['manual'] = $jenis === PerincianPelumasSheet::JENIS
            ? $this->perincian->sanitize($validated, $keys, Carbon::create($year, $month, 1)->daysInMonth)
            : $this->rekap->sanitize($validated, $keys);

        // The physical stock is kept only where it differs from the computed one.
        $fisik = $data['manual']['fisik'];
        $data['manual']['fisik'] = [];
        $computed = $this->totals($jenis, $data);
        $sisaField = $jenis === PerincianPelumasSheet::JENIS ? 'sisa' : 'kartu';
        $data['manual']['fisik'] = array_filter($fisik, fn (float $value, string $key): bool => round($value, 2) !== round($computed[$key][$sisaField] ?? 0, 2), ARRAY_FILTER_USE_BOTH);

        $record = OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => $jenis, 'month' => $month, 'year' => $year],
            ['overrides' => $data['manual'], 'catatan' => $validated['catatan'] ?? null, 'input_by' => $user->id],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "{$title} {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', "{$title} berhasil disimpan.");
    }

    public function pdf(Request $request, string $jenis): HttpResponse
    {
        $title = self::TITLES[$jenis] ?? abort(404);
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $data = $this->data($jenis, $unit, $month, $year);

        $pdf = Pdf::loadView("operasi.rincian-pelumas.{$jenis}-pdf", [
            ...$data,
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'month' => $month,
            'year' => $year,
            'totals' => $this->totals($jenis, $data),
            'lines' => $jenis === PerincianPelumasSheet::JENIS ? PerincianPelumasSheet::PENGIRIMAN : RekapPelumasSheet::ALAT_BANTU,
            'catatan' => $data['record']?->catatan,
        ])->setPaper('a4', count($data['lubricants']) > 5 ? 'landscape' : 'portrait');

        $file = str_replace(' ', '_', $title);
        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$month}_{$year}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function data(string $jenis, Unit $unit, int $month, int $year): array
    {
        return $jenis === PerincianPelumasSheet::JENIS
            ? $this->perincian->build($unit, $month, $year)
            : $this->rekap->build($unit, $month, $year);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, float>>
     */
    private function totals(string $jenis, array $data): array
    {
        return $jenis === PerincianPelumasSheet::JENIS ? $this->perincian->totals($data) : $this->rekap->totals($data);
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    private function canWrite(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanWrite);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }
}
