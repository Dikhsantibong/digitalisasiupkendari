<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\HarUnsafeCondition;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UnsafeConditionController extends Controller
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
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $records = HarUnsafeCondition::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = $records->map(function (HarUnsafeCondition $r): array {
            return [
                'id' => $r->id,
                'periode' => $r->periode,
                'kategori' => $r->kategori,
                'temuan' => $r->temuan,
                'kondisi' => $r->kondisi ?? '',
                'tindak_lanjut' => $r->tindak_lanjut ?? '',
                'rekomendasi' => $r->rekomendasi ?? '',
                'lokasi' => $r->lokasi ?? '',
                'keterangan' => strtolower($r->keterangan),
                'foto_sebelum' => $r->foto_sebelum,
                'foto_sebelum_url' => $r->foto_sebelum ? Storage::disk('public')->url($r->foto_sebelum) : null,
                'foto_sesudah' => $r->foto_sesudah,
                'foto_sesudah_url' => $r->foto_sesudah ? Storage::disk('public')->url($r->foto_sesudah) : null,
            ];
        })->all();

        $unsafeActionCount = $records->where('kategori', 'UNSAFE ACTION')->count();
        $unsafeConditionCount = $records->where('kategori', 'UNSAFE CONDITION')->count();
        $openCount = $records->filter(fn (HarUnsafeCondition $r) => strtolower($r->keterangan) === 'open')->count();
        $closeCount = $records->filter(fn (HarUnsafeCondition $r) => strtolower($r->keterangan) === 'close')->count();

        return Inertia::render('har/input/unsafe-condition/index', [
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ],
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'unsafe_action_count' => $unsafeActionCount,
                'unsafe_condition_count' => $unsafeConditionCount,
                'open_count' => $openCount,
                'close_count' => $closeCount,
            ],
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
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
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'periode' => ['required', 'string', 'max:50'],
            'kategori' => ['required', 'string', Rule::in(['UNSAFE ACTION', 'UNSAFE CONDITION'])],
            'temuan' => ['required', 'string'],
            'kondisi' => ['nullable', 'string', 'max:255'],
            'tindak_lanjut' => ['nullable', 'string'],
            'rekomendasi' => ['nullable', 'string'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['required', 'string', Rule::in(['open', 'close'])],
            'foto_sebelum' => ['nullable', 'image', 'max:10240'],
            'foto_sesudah' => ['nullable', 'image', 'max:10240'],
        ]);

        $sebelumPath = null;
        if ($request->hasFile('foto_sebelum')) {
            $sebelumPath = $request->file('foto_sebelum')->store('har-unsafe', 'public');
        }

        $sesudahPath = null;
        if ($request->hasFile('foto_sesudah')) {
            $sesudahPath = $request->file('foto_sesudah')->store('har-unsafe', 'public');
        }

        $maxSort = (int) HarUnsafeCondition::query()
            ->where('unit_id', $unit->id)
            ->where('year', (int) $validated['year'])
            ->where('month', (int) $validated['month'])
            ->max('sort_order');

        HarUnsafeCondition::query()->create([
            'unit_id' => $unit->id,
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
            'periode' => $validated['periode'],
            'kategori' => $validated['kategori'],
            'temuan' => $validated['temuan'],
            'kondisi' => $validated['kondisi'] ?? null,
            'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
            'rekomendasi' => $validated['rekomendasi'] ?? null,
            'lokasi' => $validated['lokasi'] ?? null,
            'keterangan' => strtolower($validated['keterangan']),
            'foto_sebelum' => $sebelumPath,
            'foto_sesudah' => $sesudahPath,
            'sort_order' => $maxSort + 1,
            'input_by' => $user->id,
        ]);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah laporan unsafe action/condition {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan temuan berhasil disimpan.']);

        return back();
    }

    public function update(Request $request, HarUnsafeCondition $unsafeCondition): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($unsafeCondition->unit_id), 403);

        $validated = $request->validate([
            'periode' => ['required', 'string', 'max:50'],
            'kategori' => ['required', 'string', Rule::in(['UNSAFE ACTION', 'UNSAFE CONDITION'])],
            'temuan' => ['required', 'string'],
            'kondisi' => ['nullable', 'string', 'max:255'],
            'tindak_lanjut' => ['nullable', 'string'],
            'rekomendasi' => ['nullable', 'string'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['required', 'string', Rule::in(['open', 'close'])],
            'foto_sebelum' => ['nullable', 'image', 'max:10240'],
            'foto_sesudah' => ['nullable', 'image', 'max:10240'],
        ]);

        $data = [
            'periode' => $validated['periode'],
            'kategori' => $validated['kategori'],
            'temuan' => $validated['temuan'],
            'kondisi' => $validated['kondisi'] ?? null,
            'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
            'rekomendasi' => $validated['rekomendasi'] ?? null,
            'lokasi' => $validated['lokasi'] ?? null,
            'keterangan' => strtolower($validated['keterangan']),
        ];

        if ($request->hasFile('foto_sebelum')) {
            if ($unsafeCondition->foto_sebelum) {
                Storage::disk('public')->delete($unsafeCondition->foto_sebelum);
            }
            $data['foto_sebelum'] = $request->file('foto_sebelum')->store('har-unsafe', 'public');
        }

        if ($request->hasFile('foto_sesudah')) {
            if ($unsafeCondition->foto_sesudah) {
                Storage::disk('public')->delete($unsafeCondition->foto_sesudah);
            }
            $data['foto_sesudah'] = $request->file('foto_sesudah')->store('har-unsafe', 'public');
        }

        $unsafeCondition->update($data);

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Memperbarui laporan unsafe action/condition #{$unsafeCondition->id}",
            unit: $unsafeCondition->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan temuan berhasil diperbarui.']);

        return back();
    }

    public function destroy(Request $request, HarUnsafeCondition $unsafeCondition): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($unsafeCondition->unit_id), 403);

        if ($unsafeCondition->foto_sebelum) {
            Storage::disk('public')->delete($unsafeCondition->foto_sebelum);
        }
        if ($unsafeCondition->foto_sesudah) {
            Storage::disk('public')->delete($unsafeCondition->foto_sesudah);
        }

        $unsafeCondition->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus laporan unsafe action/condition #{$unsafeCondition->id}",
            unit: $unsafeCondition->unit_id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Laporan temuan berhasil dihapus.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
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
        $month = (int) ($request->integer('month') ?: $now->month);
        $month = max(1, min(12, $month));
        $year = (int) ($request->integer('year') ?: $now->year);

        $records = HarUnsafeCondition::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $rows = $records->map(function (HarUnsafeCondition $r): array {
            $sebelumBase64 = null;
            if ($r->foto_sebelum && Storage::disk('public')->exists($r->foto_sebelum)) {
                $path = Storage::disk('public')->path($r->foto_sebelum);
                $mime = mime_content_type($path) ?: 'image/jpeg';
                $sebelumBase64 = "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
            }

            $sesudahBase64 = null;
            if ($r->foto_sesudah && Storage::disk('public')->exists($r->foto_sesudah)) {
                $path = Storage::disk('public')->path($r->foto_sesudah);
                $mime = mime_content_type($path) ?: 'image/jpeg';
                $sesudahBase64 = "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
            }

            return [
                'id' => $r->id,
                'periode' => $r->periode,
                'kategori' => $r->kategori,
                'temuan' => $r->temuan,
                'kondisi' => $r->kondisi ?? '',
                'tindak_lanjut' => $r->tindak_lanjut ?? '',
                'rekomendasi' => $r->rekomendasi ?? '',
                'lokasi' => $r->lokasi ?? '',
                'keterangan' => strtoupper($r->keterangan),
                'foto_sebelum' => $sebelumBase64,
                'foto_sesudah' => $sesudahBase64,
            ];
        })->all();

        $unsafeActionCount = $records->where('kategori', 'UNSAFE ACTION')->count();
        $unsafeConditionCount = $records->where('kategori', 'UNSAFE CONDITION')->count();

        $monthNames = [
            1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
            5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DESEMBER',
        ];
        $monthName = $monthNames[$month] ?? '';

        $logoLeftPath = public_path('logo/sidebar-logo.png');
        $logoRightPath = public_path('logo/mkp.jpg');
        $logoLeft = file_exists($logoLeftPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoLeftPath)) : null;
        $logoRight = file_exists($logoRightPath) ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($logoRightPath)) : null;

        $pdf = Pdf::loadView('har.input.unsafe-condition-pdf', [
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthName,
            'rows' => $rows,
            'unsafeActionCount' => $unsafeActionCount,
            'unsafeConditionCount' => $unsafeConditionCount,
            'logoLeft' => $logoLeft,
            'logoRight' => $logoRight,
        ])->setPaper('a4', 'landscape');

        $safeUnitName = str_replace(' ', '_', $unit->name);

        return $pdf->download("Laporan_Unsafe_Action_Condition_{$safeUnitName}_{$month}_{$year}.pdf");
    }
}
