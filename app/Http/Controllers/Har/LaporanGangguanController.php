<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarLaporanGangguan;
use App\Models\Machine;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LaporanGangguanController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $year = (int) ($request->integer('year') ?: $now->year);

        $reports = HarLaporanGangguan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('id')
            ->get();

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('har/formulir/laporan-gangguan/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => [
                'unit_id' => $unit->id,
                'year' => $year,
            ],
            'reports' => $reports,
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'machines' => $machines->all(),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'id' => ['nullable', 'integer'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'nomor' => ['nullable', 'string', 'max:255'],
            'tanggal_laporan' => ['nullable', 'date'],
            'hal' => ['nullable', 'string', 'max:255'],
            'form_code' => ['nullable', 'string', 'max:50'],
            'unit_kesatuan' => ['nullable', 'string', 'max:255'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'merek' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'no_seri' => ['nullable', 'string', 'max:255'],
            'rh' => ['nullable', 'string', 'max:255'],
            'jsb' => ['nullable', 'string', 'max:255'],
            'jsmo' => ['nullable', 'string', 'max:255'],
            'jsi_terakhir' => ['nullable', 'string', 'max:255'],
            'fungsi_pembangkit' => ['nullable', 'string', 'max:255'],
            'daya_terpasang' => ['nullable', 'string', 'max:255'],
            'daya_mampu' => ['nullable', 'string', 'max:255'],
            'tanggal_jam_kerusakan' => ['nullable', 'string', 'max:255'],
            'peralatan_rusak' => ['nullable', 'string'],
            'gejala' => ['nullable', 'string'],
            'urutan_kejadian' => ['nullable', 'string'],
            'parameter_terkait' => ['nullable', 'string'],
            'analisa_penyebab' => ['nullable', 'string'],
            'akibat' => ['nullable', 'string'],
            'tindak_lanjut_pendek' => ['nullable', 'string'],
            'tindak_lanjut_panjang' => ['nullable', 'string'],
            'eviden' => ['nullable', 'string'],
        ]);

        $data = [
            'nomor' => $validated['nomor'] ?? null,
            'tanggal_laporan' => ! empty($validated['tanggal_laporan']) ? Carbon::parse($validated['tanggal_laporan'])->toDateString() : null,
            'hal' => $validated['hal'] ?? null,
            'form_code' => $validated['form_code'] ?: 'LH - 05',
            'unit_kesatuan' => $validated['unit_kesatuan'] ?? null,
            'machine_id' => $validated['machine_id'] ?? null,
            'merek' => $validated['merek'] ?? null,
            'type' => $validated['type'] ?? null,
            'no_seri' => $validated['no_seri'] ?? null,
            'rh' => $validated['rh'] ?? null,
            'jsb' => $validated['jsb'] ?? null,
            'jsmo' => $validated['jsmo'] ?? null,
            'jsi_terakhir' => $validated['jsi_terakhir'] ?? null,
            'fungsi_pembangkit' => $validated['fungsi_pembangkit'] ?? null,
            'daya_terpasang' => $validated['daya_terpasang'] ?? null,
            'daya_mampu' => $validated['daya_mampu'] ?? null,
            'tanggal_jam_kerusakan' => $validated['tanggal_jam_kerusakan'] ?? null,
            'peralatan_rusak' => $validated['peralatan_rusak'] ?? null,
            'gejala' => $validated['gejala'] ?? null,
            'urutan_kejadian' => $validated['urutan_kejadian'] ?? null,
            'parameter_terkait' => $validated['parameter_terkait'] ?? null,
            'analisa_penyebab' => $validated['analisa_penyebab'] ?? null,
            'akibat' => $validated['akibat'] ?? null,
            'tindak_lanjut_pendek' => $validated['tindak_lanjut_pendek'] ?? null,
            'tindak_lanjut_panjang' => $validated['tindak_lanjut_panjang'] ?? null,
            'eviden' => $validated['eviden'] ?? null,
            'input_by' => $user->id,
        ];

        if (! empty($validated['id'])) {
            $report = HarLaporanGangguan::query()
                ->where('id', (int) $validated['id'])
                ->where('unit_id', $unit->id)
                ->firstOrFail();
            $report->update($data);
        } else {
            HarLaporanGangguan::query()->create([
                ...$data,
                'unit_id' => $unit->id,
                'year' => (int) $validated['year'],
            ]);
        }

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menyimpan laporan gangguan pembangkit {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan gangguan berhasil disimpan.']);

        return back();
    }

    public function destroy(Request $request, HarLaporanGangguan $laporanGangguan): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($laporanGangguan->unit_id), 403);

        $laporanGangguan->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus laporan gangguan #{$laporanGangguan->id}",
            unit: $laporanGangguan->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan gangguan berhasil dihapus.']);

        return back();
    }

    public function pdf(Request $request, HarLaporanGangguan $laporanGangguan): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::HarInputView) ||
            $user->hasPermissionTo(PermissionName::HarLaporanView),
            403
        );
        abort_unless($user->canAccessUnit($laporanGangguan->unit_id), 403);

        $unit = $laporanGangguan->unit()->with('serviceUnit')->firstOrFail();
        [$view, $data] = $this->pdfView($unit, $laporanGangguan);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Gangguan_{$safeUnitName}_{$laporanGangguan->id}.pdf");
    }

    /**
     * PDF view & data of one LH-05 report (a blank form when unsaved) — reused by the Laporan Pemeliharaan.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, HarLaporanGangguan $laporanGangguan): array
    {
        return ['har.formulir.laporan-gangguan-pdf', [
            'unit' => $unit,
            'report' => $laporanGangguan,
            'tanggalLaporan' => $laporanGangguan->tanggal_laporan
                ? $laporanGangguan->tanggal_laporan->locale('id')->isoFormat('D MMMM Y')
                : '',
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * The unit's LH-05 reports dated in the given month.
     *
     * @return Collection<int, HarLaporanGangguan>
     */
    public function reports(Unit $unit, int $month, int $year): Collection
    {
        return HarLaporanGangguan::query()->where('unit_id', $unit->id)->where('year', $year)
            ->whereMonth('tanggal_laporan', $month)->orderBy('tanggal_laporan')->orderBy('id')->get();
    }
}
