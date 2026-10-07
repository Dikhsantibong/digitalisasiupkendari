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
use App\Services\Operasi\BaFisikPelumasSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Berita Acara Pemeriksaan Fisik Pelumas: the stock
 * figures come from Perincian Minyak Pelumas; the BA number, the inspection
 * day and time, the signers and the counted stock (drum, cm, liter) are typed
 * in ({@see BaFisikPelumasSheet}).
 */
class PengusahaanBaFisikPelumasController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly BaFisikPelumasSheet $sheet,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $data = $this->sheet->build($unit, $month, $year);

        return Inertia::render('pengusahaan/operasi/ba-fisik-pelumas/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'lubricants' => $data['lubricants'],
            'rows' => (object) $data['rows'],
            'header' => $data['header'],
            'items' => (object) $data['items'],
            'catatan' => $data['record']?->catatan ?? '',
            'saved_at' => $data['record']?->updated_at?->toIso8601String(),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'header.nomor' => ['nullable', 'string', 'max:100'],
            'header.tanggal' => ['required', 'date'],
            'header.pukul' => ['nullable', 'string', 'max:10'],
            'header.mengetahui' => ['nullable', 'string', 'max:120'],
            'header.mengetahui_jabatan' => ['nullable', 'string', 'max:120'],
            'header.dibuat' => ['nullable', 'string', 'max:120'],
            'header.dibuat_jabatan' => ['nullable', 'string', 'max:120'],
            'items' => ['nullable', 'array'],
            'items.*.drum' => ['nullable', 'numeric', 'min:0'],
            'items.*.cm' => ['nullable', 'numeric', 'min:0'],
            'items.*.liter' => ['nullable', 'numeric', 'min:0'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'items.*.*.min' => 'Stock fisik tidak boleh negatif.',
            'header.tanggal.required' => 'Tanggal pemeriksaan wajib diisi.',
        ]);

        $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $keys = array_keys($this->sheet->build($unit, $month, $year)['rows']);

        $record = OperasiRekap::query()->updateOrCreate(
            ['unit_id' => $unit->id, 'jenis' => BaFisikPelumasSheet::JENIS, 'month' => $month, 'year' => $year],
            [
                'overrides' => [
                    'header' => array_map(fn (mixed $value): string => trim((string) $value), $validated['header']),
                    'items' => $this->sheet->items($validated['items'] ?? [], $keys),
                ],
                'catatan' => $validated['catatan'] ?? null,
                'input_by' => $user->id,
            ],
        );

        $this->activityLogger->log(
            $record->wasRecentlyCreated ? ActivityEvent::Created : ActivityEvent::Updated,
            "BA Pemeriksaan Fisik Pelumas {$month}/{$year} disimpan untuk {$unit->name}",
            $record,
            unit: $unit->id,
        );

        return back()->with('success', 'Berita acara pemeriksaan fisik pelumas berhasil disimpan.');
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $data = $this->sheet->build($unit, $month, $year);

        $pdf = Pdf::loadView('operasi.ba-fisik-pelumas.pdf', [
            ...$data,
            'unit' => $unit,
            'opening' => $this->sheet->opening($unit, $data['header']['tanggal'], $data['header']['pukul']),
            'month_start' => Indonesian::longDate(Carbon::create($year, $month, 1)),
            'tanggal_label' => Indonesian::longDate(Carbon::parse($data['header']['tanggal'])),
            'catatan' => $data['record']?->catatan,
        ])->setPaper('a4', 'landscape');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("BA_Pemeriksaan_Fisik_Pelumas_{$unit->name}_{$month}_{$year}.pdf");
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
