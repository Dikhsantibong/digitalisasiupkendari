<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\HarDocumentRecord;
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
    use AuthorizesFieldInput;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganLaporanGangguan) ||
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

        $machines = Machine::query()
            ->where('unit_id', $unit->id)
            ->orderBy('name')
            ->get(['id', 'name', 'merk', 'type', 'serial_number', 'capacity_kw']);

        $machineOptions = $machines->map(function (Machine $m) use ($unit): array {
            $capNum = $m->capacity_kw !== null && (float) $m->capacity_kw > 0 ? (float) $m->capacity_kw : null;
            $cap = $capNum !== null ? number_format($capNum, 2, '.', '') : '';
            $mampu = $capNum !== null ? number_format(round($capNum * 0.9, 2), 2, '.', '') : '';

            return [
                'id' => $m->id,
                'name' => $m->name,
                'merek' => $m->name,
                'type' => (string) ($m->type ?? ''),
                'no_seri' => (string) ($m->serial_number ?? ''),
                'fungsi_pembangkit' => $unit->name,
                'daya_terpasang' => $cap,
                'daya_mampu' => $mampu,
            ];
        })->keyBy('id');

        $reports = HarLaporanGangguan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->orderBy('sort_order')
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('id')
            ->get()
            ->map(function (HarLaporanGangguan $report) use ($machineOptions, $unit): HarLaporanGangguan {
                if ($report->machine_id !== null && $machineOptions->has($report->machine_id)) {
                    $master = $machineOptions->get($report->machine_id);
                    $report->merek = $master['merek'];
                    $report->type = $master['type'] !== '' ? $master['type'] : $report->type;
                    $report->no_seri = $master['no_seri'] !== '' ? $master['no_seri'] : $report->no_seri;
                    $report->daya_terpasang = $master['daya_terpasang'] !== '' ? $master['daya_terpasang'] : $report->daya_terpasang;
                    $report->daya_mampu = $master['daya_mampu'] !== '' ? $master['daya_mampu'] : $report->daya_mampu;
                    $report->fungsi_pembangkit = ($report->fungsi_pembangkit !== null && trim((string) $report->fungsi_pembangkit) !== '')
                        ? $report->fungsi_pembangkit
                        : $unit->name;
                }

                return $report;
            });

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
                'machines' => $machineOptions->values()->all(),
            ],
            'can_write' => $this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganLaporanGangguan),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganLaporanGangguan), 403);

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

        $machine = ! empty($validated['machine_id'])
            ? Machine::query()->where('unit_id', $unit->id)->find((int) $validated['machine_id'])
            : null;

        $capNum = $machine?->capacity_kw !== null && (float) $machine->capacity_kw > 0
            ? (float) $machine->capacity_kw
            : null;
        $cap = $capNum !== null ? number_format($capNum, 2, '.', '') : null;
        $mampu = $capNum !== null ? number_format(round($capNum * 0.9, 2), 2, '.', '') : null;

        $data = [
            'nomor' => $validated['nomor'] ?? null,
            'tanggal_laporan' => ! empty($validated['tanggal_laporan']) ? Carbon::parse($validated['tanggal_laporan'])->toDateString() : null,
            'hal' => $validated['hal'] ?? null,
            'form_code' => ($validated['form_code'] ?? null) ?: 'LH - 05',
            'unit_kesatuan' => ($validated['unit_kesatuan'] ?? null) ?: $unit->name,
            'machine_id' => $machine?->id ?? ($validated['machine_id'] ?? null),
            'merek' => $machine !== null ? $machine->name : ($validated['merek'] ?? null),
            'type' => $machine !== null ? ($machine->type ?: ($validated['type'] ?? null)) : ($validated['type'] ?? null),
            'no_seri' => $machine !== null ? ($machine->serial_number ?: ($validated['no_seri'] ?? null)) : ($validated['no_seri'] ?? null),
            'rh' => $validated['rh'] ?? null,
            'jsb' => $validated['jsb'] ?? null,
            'jsmo' => $validated['jsmo'] ?? null,
            'jsi_terakhir' => $validated['jsi_terakhir'] ?? null,
            'fungsi_pembangkit' => ($validated['fungsi_pembangkit'] ?? null) ?: ($machine !== null ? $unit->name : null),
            'daya_terpasang' => $machine !== null ? ($cap ?? ($validated['daya_terpasang'] ?? null)) : ($validated['daya_terpasang'] ?? null),
            'daya_mampu' => $machine !== null ? ($mampu ?? ($validated['daya_mampu'] ?? null)) : ($validated['daya_mampu'] ?? null),
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

        HarDocumentRecord::query()
            ->where('unit_id', $unit->id)
            ->where('year', (int) $validated['year'])
            ->delete();

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
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, PermissionName::HarLapanganLaporanGangguan), 403);
        abort_unless($user->canAccessUnit($laporanGangguan->unit_id), 403);

        $unitId = $laporanGangguan->unit_id;
        $year = $laporanGangguan->year;
        $laporanGangguan->delete();

        HarDocumentRecord::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus laporan gangguan #{$laporanGangguan->id}",
            unit: $unitId,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan gangguan berhasil dihapus.']);

        return back();
    }

    public function pdf(Request $request, HarLaporanGangguan $laporanGangguan): HttpResponse
    {
        $user = $request->user();
        abort_unless(
            $this->allowsFieldInput($user, PermissionName::HarInputView, PermissionName::HarLapanganLaporanGangguan) ||
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
        if ($laporanGangguan->machine_id !== null) {
            $machine = $laporanGangguan->relationLoaded('machine')
                ? $laporanGangguan->machine
                : Machine::query()->find($laporanGangguan->machine_id);
            if ($machine !== null) {
                $laporanGangguan->merek = $machine->name;
                $laporanGangguan->type = $machine->type ?: $laporanGangguan->type;
                $laporanGangguan->no_seri = $machine->serial_number ?: $laporanGangguan->no_seri;
                $laporanGangguan->daya_terpasang = ($machine->capacity_kw !== null && (float) $machine->capacity_kw > 0)
                    ? (string) $machine->capacity_kw
                    : $laporanGangguan->daya_terpasang;
            }
        }

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
        return HarLaporanGangguan::query()->with('machine')->where('unit_id', $unit->id)->where('year', $year)
            ->whereMonth('tanggal_laporan', $month)->orderBy('tanggal_laporan')->orderBy('id')->get();
    }
}
