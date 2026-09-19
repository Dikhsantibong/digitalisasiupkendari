<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\OperasiPembuatanIk;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal Pembuatan IK (OPERASI). Matriks tahunan 12 bulan per Instruksi Kerja;
 * klik sel bulan → isi 1, JUMLAH = jumlah bulan terisi.
 */
class PembuatanIkController extends Controller
{
    /**
     * @var list<array{nama: string, pic: string}>
     */
    private const DEFAULTS = [
        ['nama' => 'IK Pengoperasian Mesin', 'pic' => 'Koord Operasi'],
        ['nama' => 'IK Penggantian filter bbm', 'pic' => 'Koord Operasi'],
    ];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);

        $saved = OperasiPembuatanIk::query()->where('unit_id', $unit->id)->where('year', $year)->orderBy('sort_order')->orderBy('id')->get();

        $rows = $saved->isEmpty()
            ? collect(self::DEFAULTS)->map(fn (array $d): array => ['id' => null, 'nama' => $d['nama'], 'pic' => $d['pic'], 'months' => []])->all()
            : $saved->map(fn (OperasiPembuatanIk $r): array => ['id' => $r->id, 'nama' => $r->nama ?? '', 'pic' => $r->pic ?? '', 'months' => $r->months ?? []])->all();

        return Inertia::render('operasi/jadwal/pembuatan-ik/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'year' => $year],
            'options' => ['units' => $units->all(), 'years' => range($now->year - 3, $now->year + 1)],
            'rows' => $rows,
            'can_write' => $user->hasPermissionTo(PermissionName::OperasiInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputWrite), 403);
        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.nama' => ['nullable', 'string', 'max:255'],
            'rows.*.pic' => ['nullable', 'string', 'max:255'],
            'rows.*.months' => ['nullable', 'array'],
        ]);

        $year = (int) $validated['year'];
        $clean = fn ($m) => collect($m ?? [])->filter(fn ($v): bool => $v !== null && trim((string) $v) !== '')->mapWithKeys(fn ($v, $k): array => [(string) $k => '1'])->all();

        DB::transaction(function () use ($validated, $unit, $year, $user, $clean): void {
            OperasiPembuatanIk::query()->where('unit_id', $unit->id)->where('year', $year)->delete();
            foreach ($validated['rows'] ?? [] as $i => $row) {
                if (trim((string) ($row['nama'] ?? '')) === '') {
                    continue;
                }
                OperasiPembuatanIk::query()->create([
                    'unit_id' => $unit->id, 'year' => $year, 'no_urut' => $i + 1,
                    'nama' => $row['nama'], 'pic' => $row['pic'] ?? null, 'months' => $clean($row['months'] ?? []),
                    'sort_order' => $i, 'input_by' => $user->id,
                ]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan Jadwal Pembuatan IK {$unit->name} {$year}", $unit, unit: $unit->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal Pembuatan IK berhasil disimpan.']);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView), 403);

        $unit = Unit::query()->with('serviceUnit')->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $year = (int) ($request->integer('year') ?: Carbon::now()->year);

        [$view, $data] = $this->pdfView($unit, 1, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        return $pdf->download('Jadwal_Pembuatan_IK_'.str_replace(' ', '_', $unit->name)."_{$year}.pdf");
    }

    /**
     * The PDF view and its data for one unit & period — shared by the PDF and
     * the Laporan Operasi Pembangkit document.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $rows = OperasiPembuatanIk::query()->where('unit_id', $unit->id)->where('year', $year)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (OperasiPembuatanIk $r): array => [
                'nama' => $r->nama ?? '', 'pic' => $r->pic ?? '', 'months' => $r->months ?? [],
                'jumlah' => count(array_filter($r->months ?? [], fn ($v): bool => trim((string) $v) !== '')),
            ])->all();

        return ['operasi.jadwal.pembuatan-pdf', [
            'unit' => $unit, 'year' => $year, 'rows' => $rows,
            'barTitle' => 'JADWAL PEMBUATAN IK', 'namaHeader' => 'INSTRUKSI KERJA', 'sectionTitle' => 'A. PEMBUATAN INSTRUKSI KERJA',
            ...JadwalPdf::logos(),
        ]];
    }
}
