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
use App\Services\Operasi\RincianBbmSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Perincian Bahan Bakar and Rekap Bahan Bakar, with the
 * sheet passed as the route's `jenis` default. Both share one stored record
 * ({@see RincianBbmSheet}); each page saves only its own parts: Perincian the
 * pengembalian, koreksi and physical stock, Rekap the non-mesin pemakaian.
 */
class PengusahaanRincianBbmController extends Controller
{
    use AuthorizesFieldInput;

    /** @var array<string, array{title: string, fields: list<string>}> */
    private const SHEETS = [
        'perincian-bbm' => ['title' => 'Perincian Bahan Bakar', 'fields' => ['pengembalian', 'koreksi', 'fisik']],
        'rekap-bbm' => ['title' => 'Rekap Bahan Bakar', 'fields' => ['lain']],
    ];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly RincianBbmSheet $sheet,
    ) {}

    public function index(Request $request, string $jenis): Response
    {
        $config = self::SHEETS[$jenis] ?? abort(404);
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $data = $this->sheet->build($unit, $month, $year);

        return Inertia::render("pengusahaan/operasi/{$jenis}/index", [
            'title' => $config['title'],
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'fuels' => $data['fuels'],
            'machines' => $data['machines'],
            'tanks' => $data['tanks'],
            'auto' => [...$data['auto'], 'pemakaian' => (object) $data['auto']['pemakaian']],
            'manual' => array_map(fn (array $fuel): array => [...$fuel, 'lain' => (object) $fuel['lain']], $data['manual']),
            'lain_labels' => RincianBbmSheet::LAIN,
            'manager' => $data['manager'],
            'catatan' => $data['record']?->catatan ?? '',
            'saved_at' => $data['record']?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        $config = self::SHEETS[$jenis] ?? abort(404);
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'fuels' => ['nullable', 'array'],
            'fuels.*.pengembalian.tanggal' => ['nullable', 'date'],
            'fuels.*.pengembalian.amount' => ['nullable', 'numeric', 'min:0'],
            'fuels.*.koreksi.keterangan' => ['nullable', 'string', 'max:150'],
            'fuels.*.koreksi.amount' => ['nullable', 'numeric', 'min:0'],
            'fuels.*.fisik' => ['nullable', 'numeric', 'min:0'],
            'fuels.*.lain' => ['nullable', 'array'],
            'fuels.*.lain.*.*' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'fuels.*.*.min' => 'Jumlah tidak boleh negatif.',
            'fuels.*.*.*.min' => 'Jumlah tidak boleh negatif.',
            'fuels.*.lain.*.*.min' => 'Jumlah tidak boleh negatif.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $data = $this->sheet->build($unit, $month, $year);
        $codes = array_column($data['fuels'], 'key');
        $posted = $this->sheet->sanitize($validated['fuels'] ?? [], $codes, array_column($data['machines'], 'id'));

        // This page's parts replace the stored ones; the other page's parts are kept.
        foreach ($codes as $code) {
            foreach ($config['fields'] as $field) {
                $data['manual'][$code][$field] = $posted[$code][$field];
            }
        }

        // The physical stock is kept only where it differs from the computed one.
        $withoutFisik = $data;
        foreach ($codes as $code) {
            $withoutFisik['manual'][$code]['fisik'] = null;
        }
        $computed = $this->sheet->totals($withoutFisik);
        foreach ($codes as $code) {
            $fisik = $data['manual'][$code]['fisik'];
            if ($fisik !== null && round($fisik, 2) === round($computed[$code]['sisa'], 2)) {
                $data['manual'][$code]['fisik'] = null;
            }
        }

        $record = OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => RincianBbmSheet::JENIS, 'month' => $month, 'year' => $year],
            ['overrides' => $data['manual'], 'catatan' => $validated['catatan'] ?? $data['record']?->catatan, 'input_by' => $user->id],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "{$config['title']} {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', "{$config['title']} berhasil disimpan.");
    }

    public function pdf(Request $request, string $jenis): HttpResponse
    {
        $config = self::SHEETS[$jenis] ?? abort(404);
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $data = $this->sheet->build($unit, $month, $year);
        $codes = array_column($data['fuels'], 'key');
        // Perincian prints one jenis BBM (?fuel=HSD), or every jenis one after another.
        $shown = in_array($request->query('fuel'), $codes, true) ? [$request->query('fuel')] : $codes;

        $pdf = Pdf::loadView("operasi.rincian-bbm.{$jenis}-pdf", [
            ...$data,
            'unit' => $unit,
            'period_label' => Indonesian::monthName($month).' '.$year,
            'signed_at' => Indonesian::longDate(Carbon::create($year, $month, 1)->addMonth()),
            'month' => $month,
            'year' => $year,
            'shown' => $shown,
            'totals' => $this->sheet->totals($data),
            'lain_labels' => RincianBbmSheet::LAIN,
            'catatan' => $data['record']?->catatan,
        ])->setPaper('a4', $jenis === 'rekap-bbm' ? 'landscape' : 'portrait');

        $file = str_replace(' ', '_', $config['title']);
        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("{$file}_{$unit->name}_{$month}_{$year}.pdf");
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
